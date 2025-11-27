<?php

declare(strict_types=1);

namespace Drupal\responsive_video\EventSubscriber;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\responsive_video\Event\ResponsiveVideoEvent;
use Drupal\responsive_video\FilesystemManager;
use Drupal\responsive_video\VideoConverterService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * Listens to Entity change events
 */
final readonly class ResponsiveVideoSubscriber implements EventSubscriberInterface {

  public function __construct(
    public FilesystemManager $filesystemManager,
    public VideoConverterService $videoConverterService,
  ) {}

  /**
   * @throws InvalidPluginDefinitionException
   * @throws PluginException
   * @throws PluginNotFoundException
   * @throws \Exception
   */
  public function onVideoCreate(ResponsiveVideoEvent $event): void {
    $this->videoConverterService->convertMediaToAllStyles($event->getMedium());
  }

  /**
   * @param ResponsiveVideoEvent $event
   * @return void
   * @throws InvalidPluginDefinitionException
   * @throws PluginException
   * @throws PluginNotFoundException
   */
  public function onVideoUpdate(ResponsiveVideoEvent $event): void {
    // todo cleanup too much code for an event subscriber...
    // delete assets if file of medium changed
    $medium = $event->getMedium();
    // did video file change?
    $original = $medium->getOriginal();
    $currentMediumTargetId = $this->filesystemManager->getMediumFileTargetId($medium);
    $originalMediumTargetId = $this->filesystemManager->getMediumFileTargetId($original);
    if (!$currentMediumTargetId && !$originalMediumTargetId) {
      return;
    }

    if ($currentMediumTargetId !== $originalMediumTargetId) {
      $this->filesystemManager->deleteAssetsOfMedium($original);

      // create new assets
      $this->onVideoCreate($event);
    }

  }

  public function onVideoDelete(ResponsiveVideoEvent $event): void {
    //todo get all assets and delete them
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
