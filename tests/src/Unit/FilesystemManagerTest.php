<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_video\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\responsive_video\FilesystemManager;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\responsive_video\FilesystemManager
 * @group responsive_video
 */
class FilesystemManagerTest extends UnitTestCase {

  private FileSystemInterface $fileSystem;
  private FilesystemManager $manager;

  protected function setUp(): void {
    parent::setUp();

    $this->fileSystem = $this->createMock(FileSystemInterface::class);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('output_directory')->willReturn('responsive_videos');

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')
      ->with('responsive_video.settings')
      ->willReturn($config);

    $this->manager = new FilesystemManager($this->fileSystem, $configFactory);
  }

  /**
   * @covers ::outputDirectory
   */
  public function testOutputDirectoryReturnsConfiguredValue(): void {
    $this->assertSame('responsive_videos', $this->manager->outputDirectory());
  }

  /**
   * @covers ::outputDirectory
   */
  public function testOutputDirectoryFallsBackToDefault(): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('output_directory')->willReturn(null);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $manager = new FilesystemManager($this->fileSystem, $configFactory);
    $this->assertSame('responsive_videos', $manager->outputDirectory());
  }

  /**
   * @covers ::dateBaseUri
   */
  public function testDateBaseUriIncludesYearMonth(): void {
    $uri = $this->manager->dateBaseUri();
    $this->assertStringStartsWith('public://responsive_videos/', $uri);
    $this->assertMatchesRegularExpression('/public:\/\/responsive_videos\/\d{4}-\d{2}/', $uri);
  }

  /**
   * @covers ::prepareOutputDirectory
   */
  public function testPrepareOutputDirectoryCallsFileSystem(): void {
    $this->fileSystem->expects($this->once())
      ->method('prepareDirectory')
      ->with(
        $this->matchesRegularExpression('/public:\/\/responsive_videos\/\d{4}-\d{2}/'),
        FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
      );

    $this->manager->prepareOutputDirectory();
  }

  /**
   * @covers ::convertedFileUri
   */
  public function testConvertedFileUriFormat(): void {
    $uri = $this->manager->convertedFileUri(42, 'h264', '720p', 'mp4');
    $this->assertStringContainsString('/42/720p/h264.mp4', $uri);
    $this->assertStringStartsWith('public://responsive_videos/', $uri);
  }

  /**
   * @covers ::posterUri
   */
  public function testPosterUriFormat(): void {
    $uri = $this->manager->posterUri(42);
    $this->assertStringContainsString('/42/poster.jpg', $uri);
    $this->assertStringStartsWith('public://responsive_videos/', $uri);
  }

  /**
   * @covers ::moveToFinal
   */
  public function testMoveToFinalPreparesDirectoryAndMoves(): void {
    $this->fileSystem->expects($this->once())
      ->method('prepareDirectory')
      ->with(
        'public://responsive_videos/2024-01',
        FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
      );
    $this->fileSystem->expects($this->once())
      ->method('move')
      ->with('/tmp/source.mp4', 'public://responsive_videos/2024-01/video.mp4', FileExists::Replace);

    $this->manager->moveToFinal('/tmp/source.mp4', 'public://responsive_videos/2024-01/video.mp4');
  }

}
