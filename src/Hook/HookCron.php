<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Queue\QueueFactory;
use Drupal\responsive_video\ConversionRepository;

/**
 * Cron hook: recovers stale processing jobs.
 */
final class HookCron
{
  public function __construct(
    private readonly ConversionRepository $repository,
    private readonly QueueFactory $queueFactory,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TimeInterface $time,
  ) {}

  #[Hook("cron")]
  public function onCron(): void
  {
    $settings = $this->configFactory->get("responsive_video.settings");
    $stalenessSeconds = (int) ($settings->get("staleness_threshold") ?? 3600);
    $maxRetries = (int) ($settings->get("max_retries") ?? 10);

    $queue = $this->queueFactory->get("responsive_video_converterqueue");

    // Recover stale processing jobs.
    $threshold = $this->time->getRequestTime() - $stalenessSeconds;
    foreach ($this->repository->loadStaleMids($threshold) as $mid) {
      $this->repository->resetToPending($mid);
      $queue->createItem($mid);
    }

    // Retry failed jobs that have not yet exhausted their retry budget.
    foreach ($this->repository->loadFailedMids($maxRetries) as $mid) {
      $this->repository->incrementRetryCount($mid);
      $this->repository->resetToPending($mid);
      $queue->createItem($mid);
    }
  }
}
