<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_video\Unit;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\responsive_video\ConversionRepository;
use Drupal\responsive_video\Entity\VideoStyle;
use Drupal\responsive_video\Hook\HookVideoStyle;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\responsive_video\Hook\HookVideoStyle
 * @group responsive_video
 */
class HookVideoStyleTest extends UnitTestCase
{
  private ConversionRepository $repository;
  private QueueInterface $converterQueue;
  private QueueInterface $cleanupQueue;
  private HookVideoStyle $hook;
  private VideoStyle $style;

  protected function setUp(): void
  {
    parent::setUp();

    $this->repository = $this->createMock(ConversionRepository::class);

    $this->converterQueue = $this->createMock(QueueInterface::class);
    $this->cleanupQueue = $this->createMock(QueueInterface::class);

    $queueFactory = $this->createMock(QueueFactory::class);
    $queueFactory
      ->method("get")
      ->willReturnCallback(fn($name) => match ($name) {
        "responsive_video_converterqueue" => $this->converterQueue,
        "responsive_video_cleanupqueue" => $this->cleanupQueue,
      });

    $this->hook = new HookVideoStyle($this->repository, $queueFactory);
    $this->style = $this->createMock(VideoStyle::class);
  }

  /**
   * @covers ::onInsert
   */
  public function testInsertRequeuesCompletedMids(): void
  {
    $this->repository->method("loadCompletedMids")->willReturn([1, 2]);
    $this->repository->method("deleteFiles")->willReturn([]);

    $this->repository->expects($this->exactly(2))->method("resetToPending");
    $this->converterQueue->expects($this->exactly(2))->method("createItem");

    $this->hook->onInsert($this->style);
  }

  /**
   * @covers ::onUpdate
   */
  public function testUpdateRequeuesCompletedMids(): void
  {
    $this->repository->method("loadCompletedMids")->willReturn([3]);
    $this->repository->method("deleteFiles")->willReturn([]);

    $this->repository->expects($this->once())->method("resetToPending")->with(3);
    $this->converterQueue->expects($this->once())->method("createItem")->with(3);

    $this->hook->onUpdate($this->style);
  }

  /**
   * @covers ::onDelete
   */
  public function testDeleteRequeuesCompletedMids(): void
  {
    $this->repository->method("loadCompletedMids")->willReturn([5]);
    $this->repository->method("deleteFiles")->willReturn([]);

    $this->repository->expects($this->once())->method("resetToPending")->with(5);
    $this->converterQueue->expects($this->once())->method("createItem")->with(5);

    $this->hook->onDelete($this->style);
  }

  /**
   * @covers ::onInsert
   */
  public function testOldFilesAreQueuedForCleanup(): void
  {
    $this->repository->method("loadCompletedMids")->willReturn([7]);
    $this->repository
      ->method("deleteFiles")
      ->with(7)
      ->willReturn([10, 11]);

    $this->cleanupQueue
      ->expects($this->once())
      ->method("createItem")
      ->with(["mid" => 7, "fids" => [10, 11]]);

    $this->hook->onInsert($this->style);
  }

  /**
   * @covers ::onInsert
   */
  public function testNoCompletedMidsNoQueueInteraction(): void
  {
    $this->repository->method("loadCompletedMids")->willReturn([]);

    $this->converterQueue->expects($this->never())->method("createItem");
    $this->cleanupQueue->expects($this->never())->method("createItem");

    $this->hook->onInsert($this->style);
  }
}
