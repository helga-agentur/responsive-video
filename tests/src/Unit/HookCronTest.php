<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_video\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\responsive_video\ConversionRepository;
use Drupal\responsive_video\Hook\HookCron;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\responsive_video\Hook\HookCron
 * @group responsive_video
 */
class HookCronTest extends UnitTestCase
{
  private ConversionRepository $repository;
  private QueueInterface $queue;
  private HookCron $hook;

  protected function setUp(): void
  {
    parent::setUp();

    $this->repository = $this->createMock(ConversionRepository::class);

    $this->queue = $this->createMock(QueueInterface::class);
    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory
      ->method("get")
      ->with("responsive_video_converterqueue")
      ->willReturn($this->queue);

    $config = $this->createMock(ImmutableConfig::class);
    $config
      ->method("get")
      ->willReturnMap([["staleness_threshold", 3600], ["max_retries", 10]]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory
      ->method("get")
      ->with("responsive_video.settings")
      ->willReturn($config);

    $time = $this->createMock(TimeInterface::class);
    $time->method("getRequestTime")->willReturn(10000);

    $this->hook = new HookCron(
      $this->repository,
      $queueFactory,
      $configFactory,
      $time,
    );
  }

  /**
   * @covers ::onCron
   */
  public function testRequeuesStaleJobs(): void
  {
    $this->repository
      ->method("loadStaleMids")
      ->with(10000 - 3600)
      ->willReturn([1, 2]);
    $this->repository->method("loadFailedMids")->willReturn([]);

    $this->repository->expects($this->exactly(2))->method("resetToPending");
    $this->queue->expects($this->exactly(2))->method("createItem");

    $this->hook->onCron();
  }

  /**
   * @covers ::onCron
   */
  public function testRequeuesFailedJobsBelowMaxRetries(): void
  {
    $this->repository->method("loadStaleMids")->willReturn([]);
    $this->repository
      ->method("loadFailedMids")
      ->with(10)
      ->willReturn([3, 4]);

    $this->repository
      ->expects($this->exactly(2))
      ->method("incrementRetryCount");
    $this->repository->expects($this->exactly(2))->method("resetToPending");
    $this->queue->expects($this->exactly(2))->method("createItem");

    $this->hook->onCron();
  }

  /**
   * @covers ::onCron
   */
  public function testNoJobsNoQueueInteraction(): void
  {
    $this->repository->method("loadStaleMids")->willReturn([]);
    $this->repository->method("loadFailedMids")->willReturn([]);

    $this->queue->expects($this->never())->method("createItem");

    $this->hook->onCron();
  }

  /**
   * @covers ::onCron
   */
  public function testBothStaleAndFailedAreProcessed(): void
  {
    $this->repository->method("loadStaleMids")->willReturn([1]);
    $this->repository->method("loadFailedMids")->willReturn([2]);

    $this->queue->expects($this->exactly(2))->method("createItem");

    $this->hook->onCron();
  }
}
