<?php

namespace Drupal\responsive_video\Hook;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\media\MediaInterface;
use Drupal\responsive_video\Event\ResponsiveVideoEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

class HookMedia {

  public function __construct(readonly EventDispatcherInterface $eventDispatcher) {
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('event_dispatcher'),
    );
  }

  #[Hook('media_presave')]
  public function hookPresave(MediaInterface $media) {

    match (true) {
      $media->isNew() => $this->eventDispatcher->dispatch(new ResponsiveVideoEvent($media), ResponsiveVideoEvent::CREATE),
      !$media->isNew() => $this->eventDispatcher->dispatch(new ResponsiveVideoEvent($media), ResponsiveVideoEvent::UPDATE),
    };

  }

  #[Hook('media_predelete')]
  public function hookDelete(MediaInterface $media) {
    $this->eventDispatcher->dispatch(new ResponsiveVideoEvent($media), ResponsiveVideoEvent::DELETE);
  }

}
