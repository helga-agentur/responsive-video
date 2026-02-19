# Responsive Video

Drupal module that provides a **Responsive Video** media type with automatic, queue-based video conversion through pluggable converter backends (default: [Cloudinary](https://cloudinary.com)).

Upload a video once — the module converts it into every configured codec × style combination, generates a poster image, and renders a fully responsive `<video>` element with `<source>` tags ordered by breakpoint.

## Requirements

- Drupal 10 or 11
- Core modules: `media`, `file`, `media_library`

## Installation and configuration

1. Add the repository and require the package:

   ```
   composer require helga-agentur/responsive_video
   ```

   > The package is not on packagist. Add the repository to your `composer.json`
   > [as described in the Composer docs](https://getcomposer.org/doc/articles/handling-private-packages.md).

2. Enable the module:

   ```
   drush en responsive_video
   ```

3. Configure the converter backend:
   **Administration → Configuration → Media → Responsive Video Settings**
   (`/admin/config/media/responsive-video`)

   Select a converter plugin (e.g. Cloudinary) and fill in its credentials.

   > **Security:** never commit real API keys. Store them in `settings.ddev.php`
   > (or an equivalent environment-specific file):
   >
   > ```php
   > $config['responsive_video.settings']['converter_plugin_configuration']['apiKey']    = 'LOOKITUP';
   > $config['responsive_video.settings']['converter_plugin_configuration']['apiSecret'] = 'LOOKITUP';
   > $config['responsive_video.settings']['converter_plugin_configuration']['cloudName'] = 'LOOKITUP';
   > ```

4. Review the shipped defaults and adjust to taste (all accessible via
   **Administration → Structure**):

   | Entity                 | Admin path                                | Ships with              |
   |------------------------|-------------------------------------------|-------------------------|
   | Video Codecs           | `/admin/structure/video-codec`            | H.264 (`video/mp4`), AV1 (`video/mp4; codecs=av01…`) |
   | Video Styles           | `/admin/structure/video-style`            | 320 p, 480 p, 720 p, 1080 p |
   | Responsive Video Styles| `/admin/structure/responsive-video-style` | _(none — create your own)_ |

5. Create at least one **Responsive Video Style** and assign breakpoints + video styles to it.

6. Add the *Responsive Video* media type to a media reference field on your content entities.

## Architecture overview

```
Media insert / update / delete
        │
        ▼
   HookMedia  ──dispatches──▶  ResponsiveVideoEvent
                                       │
                                       ▼
                            ResponsiveVideoSubscriber
                              │                │
                    enqueue conversion    enqueue cleanup
                              │                │
                              ▼                ▼
                     Converterqueue      Cleanupqueue
                              │
                              ▼
                    VideoConverterService
                     ┌────────┼────────┐
                     ▼        ▼        ▼
              ConverterPlugin  Filesystem  ConversionRepository
              (e.g. Cloudinary)  Manager        (DB state)
```

### Config entities

| Entity                  | Purpose                                                             | Key properties                       |
|-------------------------|---------------------------------------------------------------------|--------------------------------------|
| **VideoCodec**          | Defines an output codec (file ending, MIME type, weight/priority).  | `fileEnding`, `mimeType`, `weight`   |
| **VideoStyle**          | Defines a size variant (width × height).                            | `width`, `height`                    |
| **ResponsiveVideoStyle**| Maps a `min-width` breakpoint to a set of video styles.             | `breakpoint`, `videoStyles`          |

### Services

| Service                               | Responsibility                                                                                   |
|---------------------------------------|--------------------------------------------------------------------------------------------------|
| `VideoConverterService`               | Orchestrates conversion for a single media entity (upload → poll → download → persist).          |
| `ConversionRepository`                | All database access for conversion state (`responsive_video_conversion` / `…_file` tables).      |
| `ConverterPluginManager`              | Resolves and instantiates the active converter plugin from config.                               |
| `FilesystemManager`                   | Manages public file paths (`public://<output_dir>/Y-m/<mid>/…`).                                |
| `ResponsiveVideoSubscriber`           | Reacts to media lifecycle events: inserts conversion rows and enqueues jobs.                     |

### Queue workers

| Queue                                | Worker           | Purpose                                                     |
|--------------------------------------|------------------|-------------------------------------------------------------|
| `responsive_video_converterqueue`    | `Converterqueue` | Uploads, polls, downloads and persists all converted files.  |
| `responsive_video_cleanupqueue`      | `Cleanupqueue`   | Deletes managed files that are no longer needed.             |

### Converter plugin system

Converter backends are Drupal plugins that implement `ResponsiveVideoConverterApiPluginInterface`. The module ships with a **Cloudinary** plugin.

To add your own backend, create a class that extends `ResponsiveVideoConverterApiPluginPluginBase` and annotate it with the `#[ResponsiveVideoConverterApiPlugin]` attribute. It will be discovered automatically and can be selected in the settings form.

### Hooks

| Class              | Hook(s)                                          | Purpose                                              |
|--------------------|--------------------------------------------------|------------------------------------------------------|
| `HookMedia`        | `media_insert`, `media_update`, `media_delete`   | Dispatches `ResponsiveVideoEvent` for the bundle.    |
| `HookCron`         | `cron`                                           | Recovers stale processing jobs; retries failed jobs. |
| `HookTheme`        | `theme`                                          | Registers the `responsive_video` theme hook.         |
| `HookVideoStyle`   | video-style related hooks                        | Re-queues conversions when a video style changes.    |

### Database tables

Installed via `responsive_video.install`:

- **`responsive_video_conversion`** — one row per media entity, tracks `status` (`pending` / `processing` / `completed` / `failed`), `remote_id`, `poster_fid`, source `width`/`height`, `retry_count`, `changed`.
- **`responsive_video_conversion_file`** — one row per converted file, links `mid` + `codec_id` + `style_id` to a managed `fid`.

### Rendering

The **`ResponsiveVideoFormatter`** field formatter builds a render array with the `responsive_video` theme hook. The template (`responsive-video.html.twig`) outputs a `<video>` element containing:

- One `<source>` per codec × style combination, ordered largest breakpoint first (for correct `min-width` matching).
- An optional `poster` attribute from the auto-generated poster image.
- A fallback `<source>` pointing to the original upload.

### Permissions

| Permission                            | Purpose                                    |
|---------------------------------------|--------------------------------------------|
| `administer responsive video`         | Access the global settings form.           |
| `administer responsive_video_style`   | Manage responsive video style entities.    |
| `administer video_style`              | Manage video style entities.               |
| `administer video_codec`              | Manage video codec entities.               |

## Cron and queue processing

Conversions run through Drupal's queue system. For fast turnaround in production, add a cron job that processes the converter queue every minute:

```
*/1 * * * * cd /path/to/drupal && ./vendor/bin/drush queue:run responsive_video_converterqueue
```

For local development you can trigger it manually:

```
drush queue:run responsive_video_converterqueue
```

The cleanup queue can be processed the same way:

```
drush queue:run responsive_video_cleanupqueue
```

**Automatic recovery (via `hook_cron`):**

- Jobs stuck in `processing` longer than the configured *staleness threshold* (default: 1 hour) are reset to `pending` and re-queued.
- `failed` jobs are retried up to the configured *max retries* (default: 10).

## Settings reference

All settings live in `responsive_video.settings` and are editable at `/admin/config/media/responsive-video`.

| Setting                  | Default              | Description                                                        |
|--------------------------|----------------------|--------------------------------------------------------------------|
| `converter_plugin`       | _(empty)_            | Machine name of the active converter plugin.                       |
| `converter_plugin_configuration` | `{}`         | Plugin-specific credentials / options.                             |
| `output_directory`       | `responsive_videos`  | Sub-directory inside `public://` for converted files.              |
| `staleness_threshold`    | `3600`               | Seconds before a `processing` job is considered stale.             |
| `poll_max_attempts`      | `10`                 | Max readiness-poll attempts per transformation.                    |
| `poll_interval_seconds`  | `5`                  | Base interval for exponential-backoff polling (5 s, 10 s, 20 s …).|

---

## User manual (for editors)

### Uploading a video

1. Navigate to the content or entity form that contains a **media reference field** configured to accept *Responsive Video* media.
2. Click **Add media** (or the equivalent media library button).
3. Select the **Responsive Video** tab and upload your video file.
   Accepted formats: **mp4, webm, ogv, mov, avi, wmv**.
4. Give the media item a name and save.

That's it — the conversion process starts automatically in the background.

### What happens after upload

1. **Queued** — as soon as you save, the video is placed in a conversion queue with the status *pending*.
2. **Processing** — the next time the queue runs (usually within a minute on production), the original file is uploaded to the converter service (e.g. Cloudinary).
3. **Converted** — the service creates one version for every *video style × video codec* combination. Each version is downloaded back into the site and stored as a managed file. A poster image (thumbnail) is generated automatically.
4. **Ready** — the video now renders as a fully responsive `<video>` element with all sources and a poster image. The browser picks the best source based on screen size and codec support.

### Triggering conversion manually

If you don't want to wait for the next scheduled queue run, ask a developer or administrator to execute:

```
drush queue:run responsive_video_converterqueue
```

This processes all pending conversions immediately. The cleanup queue can be triggered the same way:

```
drush queue:run responsive_video_cleanupqueue
```

### Checking conversion status

- If you see the video player with multiple quality options → conversion is **complete**.
- If only the original file plays (no responsive sources) → conversion is still **pending or processing**. Wait for the next queue run and reload the page.
- If conversion fails repeatedly, an administrator can check the Drupal logs (`/admin/reports/dblog`) for error messages.

### Replacing a video

Edit the media item, remove the existing file, and upload a new one. The module detects the file change, cleans up old converted files, and starts a fresh conversion automatically.

### Deleting a video

When you delete a responsive video media item, all associated converted files and the poster image are cleaned up automatically via the cleanup queue.

### Tips for editors

- **File size** — there is no strict upload limit enforced by the module, but very large files take longer to convert. Ask your administrator about any server-side upload limits.
- **Aspect ratio** — the module preserves the original aspect ratio. Video styles only set a target width (height scales proportionally).
- **Patience** — conversion is asynchronous. On a freshly uploaded video, the responsive sources appear after the queue has finished processing (typically within a few minutes).
