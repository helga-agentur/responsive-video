<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_video\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\responsive_video\Plugin\QueueWorker\Cleanupqueue;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\responsive_video\Plugin\QueueWorker\Cleanupqueue
 * @group responsive_video
 */
class CleanupqueueTest extends UnitTestCase {

  private EntityStorageInterface $fileStorage;
  private FileUsageInterface $fileUsage;
  private LoggerInterface $logger;
  private Cleanupqueue $worker;

  protected function setUp(): void {
    parent::setUp();

    $this->fileStorage = $this->createMock(EntityStorageInterface::class);
    $this->fileUsage = $this->createMock(FileUsageInterface::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('file')->willReturn($this->fileStorage);

    $this->worker = new Cleanupqueue(
      [],
      'responsive_video_cleanupqueue',
      [],
      $entityTypeManager,
      $this->fileUsage,
      $this->logger,
    );
  }

  /**
   * @covers ::processItem
   */
  public function testDeletesFilesAndDecrementsUsage(): void {
    $file = $this->createMock(FileInterface::class);
    $this->fileStorage->method('load')->willReturn($file);

    $this->fileUsage->expects($this->once())
      ->method('delete')
      ->with($file, 'responsive_video', 'media', '3');
    $file->expects($this->once())->method('delete');

    $this->worker->processItem(['mid' => 3, 'fids' => [42]]);
  }

  /**
   * @covers ::processItem
   */
  public function testSkipsMissingFiles(): void {
    $this->fileStorage->method('load')->willReturn(NULL);

    $this->fileUsage->expects($this->never())->method('delete');
    $this->logger->expects($this->never())->method('warning');

    $this->worker->processItem(['mid' => 3, 'fids' => [99]]);
  }

  /**
   * @covers ::processItem
   */
  public function testLogsWarningOnDeleteException(): void {
    $file = $this->createMock(FileInterface::class);
    $this->fileStorage->method('load')->willReturn($file);
    $file->method('delete')->willThrowException(new \RuntimeException('locked'));

    $this->logger->expects($this->once())->method('warning');

    $this->worker->processItem(['mid' => 3, 'fids' => [42]]);
  }

  /**
   * @covers ::processItem
   */
  public function testProcessesMultipleFiles(): void {
    $file = $this->createMock(FileInterface::class);
    $this->fileStorage->method('load')->willReturn($file);

    $this->fileUsage->expects($this->exactly(3))->method('delete');
    $file->expects($this->exactly(3))->method('delete');

    $this->worker->processItem(['mid' => 3, 'fids' => [1, 2, 3]]);
  }

  /**
   * @covers ::processItem
   */
  public function testContinuesAfterOneFileFails(): void {
    $file1 = $this->createMock(FileInterface::class);
    $file2 = $this->createMock(FileInterface::class);

    $this->fileStorage->method('load')
      ->willReturnOnConsecutiveCalls($file1, $file2);

    $file1->method('delete')->willThrowException(new \RuntimeException('err'));
    $file2->expects($this->once())->method('delete');

    $this->logger->expects($this->once())->method('warning');

    $this->worker->processItem(['mid' => 3, 'fids' => [1, 2]]);
  }

}
