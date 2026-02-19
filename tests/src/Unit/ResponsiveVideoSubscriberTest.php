<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_video\Unit;

use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\media\MediaInterface;
use Drupal\responsive_video\ConversionRepository;
use Drupal\responsive_video\Event\ResponsiveVideoEvent;
use Drupal\responsive_video\EventSubscriber\ResponsiveVideoSubscriber;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\responsive_video\EventSubscriber\ResponsiveVideoSubscriber
 * @group responsive_video
 */
class ResponsiveVideoSubscriberTest extends UnitTestCase
{
  private ConversionRepository $repository;
  private QueueFactory $queueFactory;
  private QueueInterface $converterQueue;
  private QueueInterface $cleanupQueue;
  private ResponsiveVideoSubscriber $subscriber;

  protected function setUp(): void
  {
    parent::setUp();

    $this->repository = $this->createMock(ConversionRepository::class);
    $this->converterQueue = $this->createMock(QueueInterface::class);
    $this->cleanupQueue = $this->createMock(QueueInterface::class);

    $this->queueFactory = $this->createMock(QueueFactory::class);
    $this->queueFactory->method("get")->willReturnCallback(
      fn(string $name) => match ($name) {
        "responsive_video_converterqueue" => $this->converterQueue,
        "responsive_video_cleanupqueue" => $this->cleanupQueue,
        default => null,
      },
    );

    $this->subscriber = new ResponsiveVideoSubscriber(
      $this->repository,
      $this->queueFactory,
    );
  }

  private function event(int $mid = 3): ResponsiveVideoEvent
  {
    $media = $this->createMock(MediaInterface::class);
    $media->method("id")->willReturn((string) $mid);
    return new ResponsiveVideoEvent($media);
  }

  // ---------------------------------------------------------------------------
  // getSubscribedEvents
  // ---------------------------------------------------------------------------

  /**
   * @covers ::getSubscribedEvents
   */
  public function testSubscribedEvents(): void
  {
    $events = ResponsiveVideoSubscriber::getSubscribedEvents();
    $this->assertArrayHasKey(ResponsiveVideoEvent::CREATE, $events);
    $this->assertArrayHasKey(ResponsiveVideoEvent::UPDATE, $events);
    $this->assertArrayHasKey(ResponsiveVideoEvent::DELETE, $events);
  }

  // ---------------------------------------------------------------------------
  // onCreate
  // ---------------------------------------------------------------------------

  /**
   * @covers ::onCreate
   */
  public function testOnCreateInsertsRowAndEnqueuesConversion(): void
  {
    $this->repository->expects($this->once())->method("insert")->with(3);
    $this->converterQueue
      ->expects($this->once())
      ->method("createItem")
      ->with(3);
    $this->cleanupQueue->expects($this->never())->method("createItem");

    $this->subscriber->onCreate($this->event(3));
  }

  // ---------------------------------------------------------------------------
  // onUpdate
  // ---------------------------------------------------------------------------

  /**
   * @covers ::onUpdate
   */
  public function testOnUpdateWithOldFilesEnqueuesCleanupAndConversion(): void
  {
    $this->repository
      ->method("deleteFiles")
      ->with(3)
      ->willReturn([4, 5]);
    $this->repository
      ->expects($this->once())
      ->method("resetToPending")
      ->with(3);
    $this->cleanupQueue
      ->expects($this->once())
      ->method("createItem")
      ->with([
        "mid" => 3,
        "fids" => [4, 5],
      ]);
    $this->converterQueue
      ->expects($this->once())
      ->method("createItem")
      ->with(3);

    $this->subscriber->onUpdate($this->event(3));
  }

  /**
   * @covers ::onUpdate
   */
  public function testOnUpdateWithNoOldFilesSkipsCleanupQueue(): void
  {
    $this->repository->method("deleteFiles")->willReturn([]);
    $this->cleanupQueue->expects($this->never())->method("createItem");
    $this->converterQueue
      ->expects($this->once())
      ->method("createItem")
      ->with(3);

    $this->subscriber->onUpdate($this->event(3));
  }

  // ---------------------------------------------------------------------------
  // onDelete
  // ---------------------------------------------------------------------------

  /**
   * @covers ::onDelete
   */
  public function testOnDeleteEnqueuesCleanupWithFilesAndPoster(): void
  {
    $this->repository
      ->method("deleteFiles")
      ->with(3)
      ->willReturn([4, 5]);
    $this->repository
      ->method("load")
      ->with(3)
      ->willReturn([
        "mid" => 3,
        "poster_fid" => 10,
        "status" => "completed",
      ]);
    $this->repository->expects($this->once())->method("delete")->with(3);
    $this->cleanupQueue
      ->expects($this->once())
      ->method("createItem")
      ->with([
        "mid" => 3,
        "fids" => [4, 5, 10],
      ]);

    $this->subscriber->onDelete($this->event(3));
  }

  /**
   * @covers ::onDelete
   */
  public function testOnDeleteWithNoPosterOrFiles(): void
  {
    $this->repository->method("deleteFiles")->willReturn([]);
    $this->repository
      ->method("load")
      ->willReturn(["mid" => 3, "poster_fid" => null, "status" => "pending"]);
    $this->cleanupQueue->expects($this->never())->method("createItem");
    $this->repository->expects($this->once())->method("delete")->with(3);

    $this->subscriber->onDelete($this->event(3));
  }
}
