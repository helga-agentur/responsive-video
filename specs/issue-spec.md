# Responsive Video Module — Implementation Spec

## Context

This is a Drupal module for responsive video delivery. Editors upload a source video via a `responsive_video` Media entity; the system uploads it to a converter service (default: Cloudinary), which performs the conversions; the converted files are downloaded, stored as managed Drupal files, and served locally. The frontend renders a `<video>` element with `<source>` tags per breakpoint.

---

## Goals

- Editor uploads one source video; system auto-generates all configured codecs and sizes in the background
- Converted files are downloaded from the converter and served from Drupal
- Frontend renders the optimal video per device (breakpoint + codec)
- If conversions are not yet ready, a fallback is used
- Converter backend is swappable via a plugin; Cloudinary is the default implementation

---

## Architecture Overview

```
[Media form] → saves responsive_video Media entity
      ↓
hook_media_insert / hook_media_update (source file changed?)
      ↓
[ResponsiveVideoSubscriber] → writes status row, enqueues conversion job
      ↓
[Queue Worker / cron] → VideoConverterService → ConverterPlugin
      → uploads source to Cloudinary (stores remote ID + width/height)
      → requests eager transformations for all codec/style combos
      → streams each converted file + poster to temp file
      → saves as managed Drupal files (public://responsive_videos/Y-m/)
      → deletes remote files from Cloudinary
      ↓
[responsive_video_conversion + responsive_video_conversion_file tables] updated
      ↓
[Field Formatter] → render array → Twig template → <video> with <source> per breakpoint
```

---

## Data Model

### `responsive_video` Media bundle

Shipped as CMI: `config/install/media.type.responsive_video.yml` and accompanying field config.

**Fields:**
- `field_media_video_file` — source file (managed Drupal file, via media source plugin)

No conversion state on the Media entity.

### Database tables (created in `hook_install`, dropped in `hook_uninstall`)

**`responsive_video_conversion`** — one row per Media entity:

| Column | Type | Notes |
|---|---|---|
| `mid` | int unsigned | Media entity ID (primary key) |
| `status` | varchar(16) | `pending`, `processing`, `completed`, `failed` |
| `remote_id` | varchar(255) | Converter public ID; set after upload, cleared after remote cleanup |
| `poster_fid` | int unsigned | Managed file ID; NULL until generated |
| `width` | int unsigned | Source video width; from upload response |
| `height` | int unsigned | Source video height; from upload response |
| `changed` | int unsigned | Unix timestamp of last status change; used for staleness detection |

**`responsive_video_conversion_file`** — one row per converted file:

| Column | Type | Notes |
|---|---|---|
| `id` | int unsigned | Auto-increment primary key |
| `mid` | int unsigned | FK to `responsive_video_conversion.mid`; indexed |
| `codec_id` | varchar(64) | `VideoCodec` config entity ID |
| `style_id` | varchar(64) | `VideoStyle` config entity ID |
| `fid` | int unsigned | Managed file ID |

All reads/writes go through a dedicated `ConversionRepository` service (injected, never called statically). No direct DB calls outside it.

### Config entities (keep existing, rename one)

- `VideoStyle` — width/height variant
- `VideoCodec` (renamed from `VideoFormat`) — file ending, MIME type, weight, status
- `ResponsiveVideoStyle` — `min-width` breakpoint (integer, pixels) + set of `VideoStyle` IDs; ordered by breakpoint value **descending** for `<source>` output (largest first, so browsers match correctly with `min-width`)

`VideoCodec` is global — all active codecs are generated for every breakpoint. `ResponsiveVideoStyle` controls sizes per breakpoint only.

### Default config (shipped in `config/install/`)

**VideoCodec:**
- `h264` — `mp4`, `video/mp4`, weight 1
- `av1` — `mp4`, `video/mp4; codecs=av01.0.05M.08`, weight 2

**VideoStyle:**
- `320p` — width 320
- `480p` — width 480
- `720p` — width 720
- `1080p` — width 1080

**ResponsiveVideoStyle:** none shipped by default — site builders configure breakpoints per project.

---

## Functional Requirements

### 1. Media source plugin

- Implement `MediaSourceInterface` for `responsive_video`
- Defines source field (`field_media_video_file`)
- `getMetadata()` returns data available on the entity itself (e.g. file name, MIME type) plus `thumbnail_uri` (poster file URI from `ConversionRepository` if available, NULL otherwise — required for Media library display); width and height are read directly by the formatter via `ConversionRepository`, not through `getMetadata()`
- Upload validation: allowed MIME types (`video/mp4`, `video/webm`, `video/ogg`, `video/quicktime`, `video/x-msvideo`, `video/x-ms-wmv`) and extensions (`mp4`, `webm`, `ogv`, `mov`, `avi`, `wmv`)

### 2. Field formatter

- Registered on `field_media_video_file` field type
- Reads `ConversionRepository` once per Media entity per request (one query per table); loads poster file entity separately by `poster_fid` if present
- Returns a **render array** using a theme hook (`responsive_video`); no HTML generated in PHP
- Render array carries: list of sources (url, type, media query), poster URL, fallback URL
- Twig template (`responsive-video.html.twig`) renders `<video>` + `<source>` elements
- Source order: for each `ResponsiveVideoStyle` (ordered by breakpoint descending — largest first), for each active `VideoCodec` (ordered by weight)
- `poster` attribute from `poster_fid` if present; omitted otherwise
- If no converted files exist, fallback is the original source file URL
- Render array has cache tag `media:{mid}`; invalidated by queue worker on completion

### 3. Module settings form (`/admin/config/media/responsive-video`)

- Active converter plugin selection
- Plugin-specific configuration (e.g. Cloudinary API key/secret/cloud name)
- Output directory (default: `responsive_videos`, relative to public files)
- Staleness threshold for stuck `processing` jobs (default: 1 hour)
- Readiness poll: max attempts (default: 10) and backoff base interval (default: 5s)

### 4. Converter plugin system

- Plugin type: `ResponsiveVideoConverterApiPlugin` (keep existing base)
- All services injected via constructor; no static calls
- Interface:
  - `uploadVideo(FileInterface $file): UploadResult` — `UploadResult` value object: `remoteId`, `width`, `height`
  - `isReady(string $remoteId, VideoCodec $codec, VideoStyle $style): bool` — checks whether an eager transformation is ready for download
  - `downloadConvertedFile(string $remoteId, VideoCodec $codec, VideoStyle $style, string $destination): void` — streams to `$destination`; no in-memory buffering; `$destination` is a path within `FileSystemInterface::getTempDirectory()`
  - `downloadPoster(string $remoteId, string $destination): void` — streams to `$destination`; temp path via `FileSystemInterface::getTempDirectory()`
  - `deleteRemote(string $remoteId): void` — deletes source + all derivatives from remote
  - configuration form: build/validate/submit
- Cloudinary implementation requests **eager transformations** on upload so all variants are pre-generated before download begins
- `VideoConverterService` orchestrates: calls plugin, moves temp files to final destination via `FilesystemManager`, registers file usage, writes to `ConversionRepository`

### 5. Event system

Hooks dispatch events **after** the entity is fully persisted:
- `hook_media_insert` → dispatches `ResponsiveVideoEvent::CREATE`
- `hook_media_update` → dispatches `ResponsiveVideoEvent::UPDATE` **only if source file ID changed** (compare `$entity->original` vs current `field_media_video_file`); if `$entity->original` is NULL, treat as unchanged and do not dispatch
- `hook_media_delete` → dispatches `ResponsiveVideoEvent::DELETE`

**`ResponsiveVideoSubscriber`** (all dependencies injected):
- On CREATE: inserts row with `status: pending`, enqueues conversion job
- On UPDATE: reads old file IDs from `ConversionRepository`, enqueues cleanup job with those IDs, resets row to `status: pending`, enqueues conversion job
- On DELETE: enqueues cleanup job for all converted files + poster; deletes DB rows immediately

File deletion (decrementing file usage, removing zero-usage files) is always handled asynchronously via a dedicated **cleanup queue** (`responsive_video_cleanupqueue`) to avoid blocking web requests.

### 6. Queue workers

**`responsive_video_converterqueue`** — conversion:
- Payload: Media entity ID
- On process:
  1. Calls `ConversionRepository::claimJob($mid)` — atomic `UPDATE ... WHERE status = 'pending'` setting `status = 'processing'` and `changed = now()`; aborts if claim fails (affected rows = 0), preventing concurrent duplicate processing
  2. Calls `VideoConverterService`: if `remote_id` already set (stale job resumed after partial upload), skip upload and reuse existing `remote_id`; otherwise upload → `UploadResult` → store `remote_id`, width, height; then request eager transformations → poll plugin for readiness with exponential backoff (max attempts + timeout configurable in settings) → stream-download all files + poster to temp → delete remote
  3. Moves temp files to final destination, saves as managed files, registers file usage (`responsive_video`, `media`, `$mid`)
  4. Sets `status: completed`, writes file IDs + width/height, clears `remote_id`
  5. Invalidates `media:{mid}` cache tag via injected `CacheTagsInvalidatorInterface`
- On exception: sets `status: failed`, logs via injected logger, does not requeue; if `remote_id` is set, attempts `deleteRemote()` in a separate try/catch (failure logged, does not override original exception) and clears `remote_id` regardless of whether deletion succeeds; temp files cleaned in `finally`
- Media entity is **never resaved**

**`responsive_video_cleanupqueue`** — file deletion:
- Payload: array of file IDs to release + Media entity ID
- For each FID: decrements file usage (`responsive_video`, `media`, `$mid`), then explicitly calls `$file->delete()` — does not rely on Drupal's auto-cleanup of zero-usage files, which is configuration-dependent and unreliable

**`hook_cron`** — staleness recovery:
- Queries `responsive_video_conversion` for rows with `status: processing` and `changed` older than the staleness threshold
- Resets each stale row to `status: pending`; preserves existing `remote_id` so the next worker reuses the upload if it already completed
- Re-enqueues a conversion job for each stale row
- Ensures stuck jobs (e.g. from interrupted cron runs or PHP timeouts) are automatically recovered

---

## Non-Functional Requirements

- All services injected via constructor; no `\Drupal::` static calls in service or plugin classes
- No hardcoded codec or style identifiers in PHP
- All DB access for conversion state goes through `ConversionRepository`
- File usage registered as (`responsive_video`, `media`, `$mid`); deletion always goes through it
- No in-memory buffering of video file content; always stream to temp file
- All complex HTML via Twig templates; formatters return render arrays only
- Converted files stored under `public://` — access control out of scope
- Tests: PHPUnit unit and kernel tests for services, queue workers, event subscriber, `ConversionRepository`, formatter; runnable via `composer test`

---

## Test Strategy

### Manual scenarios

1. Upload valid video → row inserted with `status: pending`
2. Run cron → status progresses to `processing`, then `completed`; converted files on disk, file rows in DB
3. Visit page → `<video>` rendered with local URLs, correct `<source>` + `media` + `type` attributes per breakpoint and codec
4. Upload invalid file type → validation error, entity not saved, no DB row created
5. Converter plugin fails → `status: failed`, error logged, no partial files left, temp files cleaned up
6. Delete Media entity → DB rows deleted immediately; file cleanup queued and processed on next cron
7. Replace source video → old file cleanup queued, row reset, re-conversion queued
8. Conversion pending → formatter renders fallback (original source URL), no errors

### Edge cases

- `hook_media_update` fires on every save — must not re-enqueue unless source file ID actually changed; `$entity->original` NULL → no dispatch
- `status: processing` beyond staleness threshold → `hook_cron` resets to `pending` and re-enqueues; `claimJob()` will then succeed on the next cron run
- Multiple `VideoStyle` entries per `ResponsiveVideoStyle` (regression: issue #19)
- AV1 `<source type>` must be `video/mp4; codecs=av01.0.05M.08` (regression: issue #20)
- Two queue workers processing same `mid` concurrently → prevented by atomic `UPDATE ... WHERE status = 'pending'` in `ConversionRepository::claimJob(int $mid): bool` — exactly one worker succeeds (affected rows = 1); all others abort
- `poster_fid` absent → `<video>` renders without `poster` attribute, no error
- Cloudinary `deleteRemote()` fails → logged, does not block `status: completed`; remote orphan acceptable

---

## Out of Scope

- Video player UI / controls styling
- Subtitle/caption support
- CDN configuration
- Access control on converted files
