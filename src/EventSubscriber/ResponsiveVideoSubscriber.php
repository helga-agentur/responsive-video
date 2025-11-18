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
    // @todo Place your code here.
  }

  /**
   * Kernel response event handler.
   */
  public function onVideoUpdate(ResponseEvent $event): void {
    // @todo Place your code here.
  }

  public function onVideoDelete(RequestEvent $event): void {
    // @todo do something
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
