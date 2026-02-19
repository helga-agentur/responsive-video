<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Queue\QueueFactory;
use Drupal\responsive_video\ConversionRepository;
use Drupal\responsive_video\Entity\ResponsiveVideoStyle;
use Drupal\responsive_video\Entity\VideoCodec;
use Drupal\responsive_video\Entity\VideoStyle;

/**
 * Hook implementations for VideoStyle, VideoCodec and ResponsiveVideoStyle config entity events.
 *
 * When a style or codec is added, changed or removed, all completed conversions
 * are reset to pending and re-enqueued so the new configuration is applied.
 */
final class HookVideoStyle
{
  public function __construct(
    private readonly ConversionRepository $repository,
    private readonly QueueFactory $queueFactory,
  ) {}

  #[Hook("video_style_insert")]
  public function onInsert(VideoStyle $style): void
  {
    $this->requeueAllCompleted();
  }

  #[Hook("video_style_update")]
  public function onUpdate(VideoStyle $style): void
  {
    $this->requeueAllCompleted();
  }

  #[Hook("video_style_delete")]
  public function onDelete(VideoStyle $style): void
  {
    $this->requeueAllCompleted();
  }

  #[Hook("video_codec_insert")]
  public function onCodecInsert(VideoCodec $codec): void
  {
    $this->requeueAllCompleted();
  }

  #[Hook("video_codec_update")]
  public function onCodecUpdate(VideoCodec $codec): void
  {
    $this->requeueAllCompleted();
  }

  #[Hook("video_codec_delete")]
  public function onCodecDelete(VideoCodec $codec): void
  {
    $this->requeueAllCompleted();
  }

  #[Hook("responsive_video_style_insert")]
  public function onResponsiveVideoStyleInsert(ResponsiveVideoStyle $style): void
  {
    $this->requeueAllCompleted();
  }

  #[Hook("responsive_video_style_update")]
  public function onResponsiveVideoStyleUpdate(ResponsiveVideoStyle $style): void
  {
    $this->requeueAllCompleted();
  }

  #[Hook("responsive_video_style_delete")]
  public function onResponsiveVideoStyleDelete(ResponsiveVideoStyle $style): void
  {
    $this->requeueAllCompleted();
  }

  private function requeueAllCompleted(): void
  {
    $queue = $this->queueFactory->get("responsive_video_converterqueue");
    foreach ($this->repository->loadCompletedMids() as $mid) {
      $oldFids = $this->repository->deleteFiles($mid);
      if ($oldFids) {
        $this->queueFactory->get("responsive_video_cleanupqueue")->createItem([
          "mid" => $mid,
          "fids" => $oldFids,
        ]);
      }
      $this->repository->resetToPending($mid);
      $queue->createItem($mid);
    }
  }
}
