<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_video\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\MediaInterface;
use Drupal\responsive_video\ConversionRepository;
use Drupal\responsive_video\Plugin\QueueWorker\Converterqueue;
use Drupal\responsive_video\VideoConverterService;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\responsive_video\Plugin\QueueWorker\Converterqueue
 * @group responsive_video
 */
class ConverterqueueTest extends UnitTestCase {

  private ConversionRepository $repository;
  private VideoConverterService $converterService;
  private EntityStorageInterface $mediaStorage;
  private LoggerInterface $logger;
  private Converterqueue $worker;

  protected function setUp(): void {
    parent::setUp();

    $this->repository = $this->createMock(ConversionRepository::class);
    $this->converterService = $this->createMock(VideoConverterService::class);
    $this->logger = $this->createMock(LoggerInterface::class);

    $this->mediaStorage = $this->createMock(EntityStorageInterface::class);
    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('media')->willReturn($this->mediaStorage);

    $this->worker = new Converterqueue(
      [],
      'responsive_video_converterqueue',
      [],
      $this->converterService,
      $this->repository,
      $entityTypeManager,
      $this->logger,
    );
  }

  /**
   * @covers ::processItem
   */
  public function testSuccessfulJobProcessing(): void {
    $this->repository->method('claimJob')->with(3)->willReturn(TRUE);

    $media = $this->createMock(MediaInterface::class);
    $this->mediaStorage->method('load')->with(3)->willReturn($media);

    $this->converterService->expects($this->once())->method('convertMedia')->with($media);
    $this->repository->expects($this->never())->method('markFailed');

    $this->worker->processItem(3);
  }

  /**
   * @covers ::processItem
   */
  public function testClaimFailureSkipsProcessing(): void {
    $this->repository->method('claimJob')->with(3)->willReturn(FALSE);

    $this->mediaStorage->expects($this->never())->method('load');
    $this->converterService->expects($this->never())->method('convertMedia');

    $this->worker->processItem(3);
  }

  /**
   * @covers ::processItem
   */
  public function testMediaNotFoundMarksFailed(): void {
    $this->repository->method('claimJob')->willReturn(TRUE);
    $this->mediaStorage->method('load')->willReturn(NULL);

    $this->logger->expects($this->once())->method('warning');
    $this->repository->expects($this->once())->method('markFailed')->with(3, TRUE);
    $this->converterService->expects($this->never())->method('convertMedia');

    $this->worker->processItem(3);
  }

  /**
   * @covers ::processItem
   */
  public function testConverterExceptionMarksFailed(): void {
    $this->repository->method('claimJob')->willReturn(TRUE);

    $media = $this->createMock(MediaInterface::class);
    $this->mediaStorage->method('load')->willReturn($media);

    $this->converterService->method('convertMedia')
      ->willThrowException(new \RuntimeException('Cloudinary error'));

    $this->logger->expects($this->once())->method('error');
    $this->repository->expects($this->once())->method('markFailed')->with(3, TRUE);

    $this->worker->processItem(3);
  }

}
