<?php

declare(strict_types=1);

namespace Drupal\responsive_video\EventSubscriber;

use Drupal\Core\Queue\QueueFactory;
use Drupal\responsive_video\ConversionRepository;
use Drupal\responsive_video\Event\ResponsiveVideoEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts to responsive_video Media entity lifecycle events.
 *
 * All dependencies injected; no static calls; no entity saves.
 */
final class ResponsiveVideoSubscriber implements EventSubscriberInterface
{
  public function __construct(
    private readonly ConversionRepository $repository,
    private readonly QueueFactory $queueFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array
  {
    return [
      ResponsiveVideoEvent::CREATE => "onCreate",
      ResponsiveVideoEvent::UPDATE => "onUpdate",
      ResponsiveVideoEvent::DELETE => "onDelete",
    ];
  }

  public function onCreate(ResponsiveVideoEvent $event): void
  {
    $mid = (int) $event->getMedium()->id();
    $this->repository->insert($mid);
    $this->enqueueConversion($mid);
  }

  public function onUpdate(ResponsiveVideoEvent $event): void
  {
    $mid = (int) $event->getMedium()->id();

    // Collect existing converted file IDs for async cleanup before resetting.
    $oldFids = $this->repository->deleteFiles($mid);
    if ($oldFids) {
      $this->enqueueCleanup($mid, $oldFids);
    }

    $this->repository->resetToPending($mid);
    $this->enqueueConversion($mid);
  }

  public function onDelete(ResponsiveVideoEvent $event): void
  {
    $mid = (int) $event->getMedium()->id();

    $fids = $this->repository->deleteFiles($mid);

    $row = $this->repository->load($mid);
    if ($row && !empty($row["poster_fid"])) {
      $fids[] = (int) $row["poster_fid"];
    }

    if ($fids) {
      $this->enqueueCleanup($mid, $fids);
    }

    $this->repository->delete($mid);
  }

  // ---------------------------------------------------------------------------
  // Private helpers
  // ---------------------------------------------------------------------------

  private function enqueueConversion(int $mid): void
  {
    $this->queueFactory
      ->get("responsive_video_converterqueue")
      ->createItem($mid);
  }

  private function enqueueCleanup(int $mid, array $fids): void
  {
    $this->queueFactory->get("responsive_video_cleanupqueue")->createItem([
      "mid" => $mid,
      "fids" => $fids,
    ]);
  }
}
