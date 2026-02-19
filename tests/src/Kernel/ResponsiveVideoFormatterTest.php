<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_video\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\responsive_video\ConversionRepository;
use Drupal\responsive_video\Plugin\Field\FieldFormatter\ResponsiveVideoFormatter;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\responsive_video\Entity\VideoCodec;
use Drupal\responsive_video\Entity\ResponsiveVideoStyle;

/**
 * @coversDefaultClass \Drupal\responsive_video\Plugin\Field\FieldFormatter\ResponsiveVideoFormatter
 * @group responsive_video
 */
#[RunTestsInSeparateProcesses]
class ResponsiveVideoFormatterTest extends KernelTestBase
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

  private function buildFormatter(
    ConversionRepository $repository,
    EntityTypeManagerInterface $etm,
    FileUrlGeneratorInterface $fileUrlGenerator,
  ): ResponsiveVideoFormatter {
    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);

    return new ResponsiveVideoFormatter(
      "responsive_video_formatter",
      [],
      $fieldDefinition,
      [],
      "above",
      "full",
      [],
      $repository,
      $etm,
      $fileUrlGenerator,
    );
  }

  /**
   * @covers ::viewElements
   */
  public function testViewElementsWithNoConversionReturnsEmptySources(): void
  {
    /** @var ConversionRepository $repository */
    $repository = $this->container->get(
      "responsive_video.conversion_repository",
    );

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);

    $formatter = $this->buildFormatter($repository, $etm, $fileUrlGenerator);

    $media = $this->createMock(MediaInterface::class);
    $media->method("id")->willReturn("1");

    $items = $this->createMock(FieldItemListInterface::class);
    $items->method("getEntity")->willReturn($media);
    $items->method("first")->willReturn(null);

    $elements = $formatter->viewElements($items, "en");

    $this->assertCount(1, $elements);
    $this->assertSame("responsive_video", $elements[0]["#theme"]);
    $this->assertSame([], $elements[0]["#sources"]);
  }

  /**
   * @covers ::viewElements
   */
  public function testViewElementsWithCompletedConversionBuildsSources(): void
  {
    /** @var ConversionRepository $repository */
    $repository = $this->container->get(
      "responsive_video.conversion_repository",
    );
    $repository->insert(10);
    $repository->claimJob(10);
    $repository->markCompleted(10, posterFid: 99, width: 1920, height: 1080);
    $repository->insertFile(10, "h264", "720p", 50);

    $posterFile = $this->createMock(FileInterface::class);
    $posterFile->method("getFileUri")->willReturn("public://rv/10/poster.jpg");

    $videoFile = $this->createMock(FileInterface::class);
    $videoFile
      ->method("getFileUri")
      ->willReturn("public://rv/10/720p/h264.mp4");

    $fileStorage = $this->createMock(
      \Drupal\Core\Entity\EntityStorageInterface::class,
    );
    $fileStorage
      ->method("load")
      ->willReturnMap([[99, $posterFile], [50, $videoFile]]);

    $codec = $this->createMock(VideoCodec::class);
    $codec->method("id")->willReturn("h264");
    $codec->method("getWeight")->willReturn(0);
    $codec->method("getMimeType")->willReturn("video/mp4");

    $codecStorage = $this->createMock(
      \Drupal\Core\Entity\EntityStorageInterface::class,
    );
    $codecStorage->method("loadByProperties")->willReturn([$codec]);

    $rvStyle = $this->createMock(ResponsiveVideoStyle::class);
    $rvStyle->method("getBreakpoint")->willReturn(0);
    $rvStyle->method("getVideoStyles")->willReturn(["720p"]);

    $rvStyleStorage = $this->createMock(
      \Drupal\Core\Entity\EntityStorageInterface::class,
    );
    $rvStyleStorage->method("loadMultiple")->willReturn([$rvStyle]);

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $etm
      ->method("getStorage")
      ->willReturnMap([
        ["file", $fileStorage],
        ["video_codec", $codecStorage],
        ["responsive_video_style", $rvStyleStorage],
      ]);

    $fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);
    $fileUrlGenerator
      ->method("generateAbsoluteString")
      ->willReturnCallback(
        fn($uri) => "https://example.com/" . ltrim($uri, "public://"),
      );

    $formatter = $this->buildFormatter($repository, $etm, $fileUrlGenerator);

    $media = $this->createMock(MediaInterface::class);
    $media->method("id")->willReturn("10");

    $items = $this->createMock(FieldItemListInterface::class);
    $items->method("getEntity")->willReturn($media);
    $items->method("first")->willReturn(null);

    $elements = $formatter->viewElements($items, "en");

    $this->assertCount(1, $elements);
    $this->assertSame("responsive_video", $elements[0]["#theme"]);
    $this->assertNotEmpty($elements[0]["#sources"]);
    $this->assertStringContainsString(
      "h264.mp4",
      $elements[0]["#sources"][0]["url"],
    );
    $this->assertSame("video/mp4", $elements[0]["#sources"][0]["type"]);
    $this->assertStringContainsString(
      "poster.jpg",
      $elements[0]["#poster_url"],
    );
    $this->assertContains("media:10", $elements[0]["#cache"]["tags"]);
  }

  /**
   * @covers ::viewElements
   */
  public function testViewElementsIncludesMediaCacheTag(): void
  {
    /** @var ConversionRepository $repository */
    $repository = $this->container->get(
      "responsive_video.conversion_repository",
    );

    $etm = $this->createMock(EntityTypeManagerInterface::class);
    $fileUrlGenerator = $this->createMock(FileUrlGeneratorInterface::class);

    $formatter = $this->buildFormatter($repository, $etm, $fileUrlGenerator);

    $media = $this->createMock(MediaInterface::class);
    $media->method("id")->willReturn("77");

    $items = $this->createMock(FieldItemListInterface::class);
    $items->method("getEntity")->willReturn($media);
    $items->method("first")->willReturn(null);

    $elements = $formatter->viewElements($items, "en");

    $this->assertContains("media:77", $elements[0]["#cache"]["tags"]);
  }
}
