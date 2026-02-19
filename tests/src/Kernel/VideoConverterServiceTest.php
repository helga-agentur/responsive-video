<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_video\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\responsive_video\ConversionRepository;
use Drupal\responsive_video\ConverterPluginManager;
use Drupal\responsive_video\FilesystemManager;
use Drupal\responsive_video\ResponsiveVideoConverterApiPluginInterface;
use Drupal\responsive_video\UploadResult;
use Drupal\responsive_video\VideoConverterService;
use Drupal\responsive_video\Entity\VideoCodec;
use Drupal\responsive_video\Entity\VideoStyle;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\media\MediaInterface;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\responsive_video\VideoConverterService
 * @group responsive_video
 */
#[RunTestsInSeparateProcesses]
class VideoConverterServiceTest extends KernelTestBase
{
  protected static $modules = [
    "responsive_video",
    "media",
    "file",
    "user",
    "image",
    "field",
    "system",
  ];

  protected function setUp(): void
  {
    parent::setUp();
    $this->installSchema("responsive_video", [
      "responsive_video_conversion",
      "responsive_video_conversion_file",
    ]);
    $this->installEntitySchema("user");
    $this->installEntitySchema("file");
  }

  /**
   * Builds a VideoConverterService with the given plugin mock.
   */
  private function buildService(
    ResponsiveVideoConverterApiPluginInterface $plugin,
    ConversionRepository $repository,
  ): VideoConverterService {
    $pluginManager = $this->createMock(ConverterPluginManager::class);
    $pluginManager->method("getActivePlugin")->willReturn($plugin);

    $filesystemManager = $this->createMock(FilesystemManager::class);
    $filesystemManager
      ->method("convertedFileUri")
      ->willReturnCallback(
        fn(
          $mid,
          $codec,
          $style,
          $ext,
        ) => "public://rv/{$mid}/{$style}/{$codec}.{$ext}",
      );
    $filesystemManager
      ->method("posterUri")
      ->willReturnCallback(fn($mid) => "public://rv/{$mid}/poster.jpg");
    $filesystemManager->method("prepareOutputDirectory");
    $filesystemManager
      ->method("moveToFinal")
      ->willReturnCallback(function (string $src, string $dst) {
        // Create the destination directory and touch the file so managed file
        // registration works.
        $dir = dirname($dst);
        @mkdir($dir, 0755, true);
        file_put_contents($dst, "fake");
      });

    $fileSystem = $this->container->get("file_system");
    $fileUsage = $this->createMock(FileUsageInterface::class);
    $cacheInvalidator = $this->createMock(CacheTagsInvalidatorInterface::class);

    $config = $this->config("responsive_video.settings");
    $configFactory = $this->container->get("config.factory");

    $logger = $this->createMock(LoggerInterface::class);

    return new VideoConverterService(
      $pluginManager,
      $repository,
      $filesystemManager,
      $this->container->get("entity_type.manager"),
      $fileSystem,
      $fileUsage,
      $cacheInvalidator,
      $configFactory,
      $logger,
    );
  }

  /**
   * @covers ::convertMedia
   */
  public function testNoPluginThrowsRuntimeException(): void
  {
    $pluginManager = $this->createMock(ConverterPluginManager::class);
    $pluginManager->method("getActivePlugin")->willReturn(null);

    $repository = $this->container->get(
      "responsive_video.conversion_repository",
    );

    $service = new VideoConverterService(
      $pluginManager,
      $repository,
      $this->createMock(FilesystemManager::class),
      $this->container->get("entity_type.manager"),
      $this->container->get("file_system"),
      $this->createMock(FileUsageInterface::class),
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->container->get("config.factory"),
      $this->createMock(LoggerInterface::class),
    );

    $media = $this->createMock(MediaInterface::class);
    $media->method("id")->willReturn("1");

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage("No converter plugin configured.");

    $service->convertMedia($media);
  }

  /**
   * @covers ::convertMedia
   *
   * Happy-path: upload → download variants → download poster → mark completed.
   */
  public function testConvertMediaHappyPath(): void
  {
    /** @var ConversionRepository $repository */
    $repository = $this->container->get(
      "responsive_video.conversion_repository",
    );
    $repository->insert(42);
    $repository->claimJob(42);

    $plugin = $this->createMock(
      ResponsiveVideoConverterApiPluginInterface::class,
    );
    $plugin
      ->method("uploadVideo")
      ->willReturn(new UploadResult("remote-42", 1920, 1080));
    $plugin->method("isReady")->willReturn(true);
    $plugin->method("isPosterReady")->willReturn(true);
    $plugin
      ->method("downloadConvertedFile")
      ->willReturnCallback(function (
        string $remoteId,
        $codec,
        $style,
        string $dest,
      ) {
        file_put_contents($dest, "fake-video");
      });
    $plugin
      ->method("downloadPoster")
      ->willReturnCallback(function (string $remoteId, string $dest) {
        file_put_contents($dest, "fake-poster");
      });
    $plugin->method("deleteRemote");

    // Need a real media entity with a file field. We mock MediaInterface here.
    $sourceFile = $this->createMock(\Drupal\file\FileInterface::class);
    $sourceFile->method("getFileUri")->willReturn("public://source.mp4");
    $sourceFile->method("id")->willReturn("10");

    // Service reads ->target_id directly on the field list; use stdClass so
    // target_id is a declared property rather than a dynamic mock property.
    $fieldList = new \stdClass();
    $fieldList->target_id = 10;

    $media = $this->createMock(MediaInterface::class);
    $media->method("id")->willReturn("42");
    $media->method("get")->willReturn($fieldList);

    // Swap out the real entity type manager's file storage.
    $fileStorage = $this->createMock(
      \Drupal\Core\Entity\EntityStorageInterface::class,
    );
    $fileStorage->method("load")->with(10)->willReturn($sourceFile);
    $fileStorage->method("loadByProperties")->willReturn([]);

    $codecStorage = $this->createMock(
      \Drupal\Core\Entity\EntityStorageInterface::class,
    );
    $styleStorage = $this->createMock(
      \Drupal\Core\Entity\EntityStorageInterface::class,
    );

    $codec = $this->createMock(VideoCodec::class);
    $codec->method("id")->willReturn("h264");
    $codec->method("getFileEnding")->willReturn("mp4");
    $codec->method("getWeight")->willReturn(0);

    $style = $this->createMock(VideoStyle::class);
    $style->method("id")->willReturn("720p");

    $codecStorage->method("loadByProperties")->willReturn([$codec]);
    $styleStorage->method("loadMultiple")->willReturn([$style]);

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm
      ->method("getStorage")
      ->willReturnMap([
        ["file", $fileStorage],
        ["video_codec", $codecStorage],
        ["video_style", $styleStorage],
      ]);

    $pluginManager = $this->createMock(ConverterPluginManager::class);
    $pluginManager->method("getActivePlugin")->willReturn($plugin);

    $filesystemManager = $this->createMock(FilesystemManager::class);
    $filesystemManager
      ->method("convertedFileUri")
      ->willReturn("public://rv/42/720p/h264.mp4");
    $filesystemManager
      ->method("posterUri")
      ->willReturn("public://rv/42/poster.jpg");
    $filesystemManager->method("prepareOutputDirectory");
    $filesystemManager->method("moveToFinal");

    $fileUsage = $this->createMock(FileUsageInterface::class);
    $cacheInvalidator = $this->createMock(CacheTagsInvalidatorInterface::class);
    $cacheInvalidator
      ->expects($this->once())
      ->method("invalidateTags")
      ->with(["media:42"]);

    $service = new VideoConverterService(
      $pluginManager,
      $repository,
      $filesystemManager,
      $etm,
      $this->container->get("file_system"),
      $fileUsage,
      $cacheInvalidator,
      $this->container->get("config.factory"),
      $this->createMock(LoggerInterface::class),
    );

    $service->convertMedia($media);

    $row = $repository->load(42);
    $this->assertSame("completed", $row["status"]);
    $this->assertNull($row["remote_id"]);
  }

  /**
   * @covers ::convertMedia
   *
   * When plugin throws, convertMedia re-throws and remote is cleaned up.
   */
  public function testConvertMediaCleansUpRemoteOnFailure(): void
  {
    /** @var ConversionRepository $repository */
    $repository = $this->container->get(
      "responsive_video.conversion_repository",
    );
    $repository->insert(43);
    $repository->claimJob(43);

    $plugin = $this->createMock(
      ResponsiveVideoConverterApiPluginInterface::class,
    );
    $plugin
      ->method("uploadVideo")
      ->willReturn(new UploadResult("remote-43", 1920, 1080));
    $plugin
      ->method("isReady")
      ->willThrowException(new \RuntimeException("API down"));
    $plugin->expects($this->once())->method("deleteRemote")->with("remote-43");

    $sourceFile = $this->createMock(\Drupal\file\FileInterface::class);
    $sourceFile->method("getFileUri")->willReturn("public://source.mp4");
    $sourceFile->method("id")->willReturn("10");

    $fieldList = new \stdClass();
    $fieldList->target_id = 10;

    $media = $this->createMock(MediaInterface::class);
    $media->method("id")->willReturn("43");
    $media->method("get")->willReturn($fieldList);

    $fileStorage = $this->createMock(
      \Drupal\Core\Entity\EntityStorageInterface::class,
    );
    $fileStorage->method("load")->with(10)->willReturn($sourceFile);
    $fileStorage->method("loadByProperties")->willReturn([]);

    $codec = $this->createMock(VideoCodec::class);
    $codec->method("id")->willReturn("h264");
    $codec->method("getFileEnding")->willReturn("mp4");
    $codec->method("getWeight")->willReturn(0);

    $style = $this->createMock(VideoStyle::class);
    $style->method("id")->willReturn("720p");

    $codecStorage = $this->createMock(
      \Drupal\Core\Entity\EntityStorageInterface::class,
    );
    $codecStorage->method("loadByProperties")->willReturn([$codec]);

    $styleStorage = $this->createMock(
      \Drupal\Core\Entity\EntityStorageInterface::class,
    );
    $styleStorage->method("loadMultiple")->willReturn([$style]);

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm
      ->method("getStorage")
      ->willReturnMap([
        ["file", $fileStorage],
        ["video_codec", $codecStorage],
        ["video_style", $styleStorage],
      ]);

    $pluginManager = $this->createMock(ConverterPluginManager::class);
    $pluginManager->method("getActivePlugin")->willReturn($plugin);

    $service = new VideoConverterService(
      $pluginManager,
      $repository,
      $this->createMock(FilesystemManager::class),
      $etm,
      $this->container->get("file_system"),
      $this->createMock(FileUsageInterface::class),
      $this->createMock(CacheTagsInvalidatorInterface::class),
      $this->container->get("config.factory"),
      $this->createMock(LoggerInterface::class),
    );

    $this->expectException(\RuntimeException::class);
    $service->convertMedia($media);
  }
}
