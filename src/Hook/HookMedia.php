<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\media\MediaInterface;
use Drupal\responsive_video\Event\ResponsiveVideoEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Hook implementations for Media entity events.
 *
 * Dispatches after the entity is fully persisted so subscribers can safely
 * read the DB state and enqueue queue items.
 */
final class HookMedia
{
  public function __construct(
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  #[Hook("media_insert")]
  public function onInsert(MediaInterface $media): void
  {
    if ($media->bundle() !== "responsive_video") {
      return;
    }
    $this->eventDispatcher->dispatch(
      new ResponsiveVideoEvent($media),
      ResponsiveVideoEvent::CREATE,
    );
  }

  #[Hook("media_update")]
  public function onUpdate(MediaInterface $media): void
  {
    if ($media->bundle() !== "responsive_video") {
      return;
    }

    // Only dispatch if the source file actually changed.
    // Guard against $media->original being NULL (e.g. programmatic saves).
    $original = $media->original;
    if ($original === null) {
      return;
    }

    $currentFid = $media->get("field_media_video_file")->target_id;
    $originalFid = $original->get("field_media_video_file")->target_id;

    if ((string) $currentFid === (string) $originalFid) {
      return;
    }

    $this->eventDispatcher->dispatch(
      new ResponsiveVideoEvent($media),
      ResponsiveVideoEvent::UPDATE,
    );
  }

  #[Hook("media_delete")]
  public function onDelete(MediaInterface $media): void
  {
    if ($media->bundle() !== "responsive_video") {
      return;
    }
    $this->eventDispatcher->dispatch(
      new ResponsiveVideoEvent($media),
      ResponsiveVideoEvent::DELETE,
    );
  }
}
