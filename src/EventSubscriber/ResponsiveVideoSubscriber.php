<?php

declare(strict_types=1);

namespace Drupal\responsive_video\EventSubscriber;

use Drupal\Core\Entity\EntityTypeEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listens to Entity change events
 */
final class ResponsiveVideoSubscriber implements EventSubscriberInterface {

  /**
   * Kernel request event handler.
   */
  public function onVideoCreate(RequestEvent $event): void {
    /*
     * 0. Check if Filesystem (/files/responsive_videos/YYYY-MM) is created
     * 0.1 Create directory if not present
     * 1. Save Video in /files/responsive_videos/YYYY-MM
     * 2. get all possible Video Formats
     * 3. get all responsive-video-styles
     * 4. get all video-styles activated in responsive-video-styles
     * 5. send all permutations of combinations to converter
     * 6. check if filesystem is ready. Every video is saved in /files/responsive_videos/styles/{sylename}/YYYY-MM
     */
  }

  /**
   * Kernel response event handler.
   */
  public function onVideoUpdate(ResponseEvent $event): void {
    // -> onVideoCreate
  }

  public function onVideoDelete(RequestEvent $event): void {
    // delete all assets and the original video
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EntityTypeEvents::CREATE => ['onVideoCreate'],
      EntityTypeEvents::UPDATE => ['onVideoUpdate'],
      EntityTypeEvents::DELETE => ['onVideoDelete'],
    ];
  }

}
