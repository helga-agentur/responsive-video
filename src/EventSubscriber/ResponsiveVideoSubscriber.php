<?php

declare(strict_types=1);

namespace Drupal\responsive_video\EventSubscriber;

use Drupal\Core\Entity\EntityTypeEvents;
use Drupal\responsive_video\Event\ResponsiveVideoEvent;
use Drupal\responsive_video\FilesystemManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listens to Entity change events
 */
final readonly class ResponsiveVideoSubscriber implements EventSubscriberInterface {

  public function __construct(
    public FilesystemManager $filesystemManager,
  ) {}

  public function onVideoCreate(ResponsiveVideoEvent $event): void {
    /*
     *  Check if Filesystem (/files/responsive_videos/YYYY-MM) is created
     *  Create directory if not present
     *  get all possible Video Formats
     *  get all responsive-video-styles
     *  get all video-styles activated in responsive-video-styles
     *  send all permutations of combinations to converter
     *  check if filesystem is ready. Every video is saved in /files/responsive_videos/styles/{sylename}/YYYY-MM
     */

    $date = date('Y-m');
    $this->filesystemManager->prepareDateDirectory($date);
  }

  /**
   * Kernel response event handler.
   */
  public function onVideoUpdate(ResponsiveVideoEvent $event): void {
    // delete assets and create new ones if the media file changed
    $medium = $event->getMedium();
    // did video file change?
    $original = $medium->getOriginal();
    $currentMediumTargetId = $this->filesystemManager->getMediumFileTargetId($medium);
    $originalMediumTargetId = $this->filesystemManager->getMediumFileTargetId($original);
    if (!$currentMediumTargetId && !$originalMediumTargetId) {
      return;
    }

    if ($currentMediumTargetId !== $originalMediumTargetId) {

    }

  }

  public function onVideoDelete(ResponsiveVideoEvent $event): void {
    // get all assets and delete them
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ResponsiveVideoEvent::CREATE => ['onVideoCreate'],
      ResponsiveVideoEvent::UPDATE => ['onVideoUpdate'],
      ResponsiveVideoEvent::DELETE => ['onVideoDelete'],
    ];
  }


}
