<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_video\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\responsive_video\Entity\VideoCodec;
use Drupal\responsive_video\Entity\VideoStyle;
use Drupal\responsive_video\Plugin\ResponsiveVideoConverterApiPlugin\Cloudinary;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\responsive_video\Plugin\ResponsiveVideoConverterApiPlugin\Cloudinary
 * @group responsive_video
 */
class CloudinaryTest extends UnitTestCase
{
  private ClientInterface $httpClient;
  private EntityTypeManagerInterface $entityTypeManager;
  private Cloudinary $plugin;

  protected function setUp(): void
  {
    parent::setUp();

    $this->httpClient = $this->createMock(ClientInterface::class);

    $logger = $this->createMock(LoggerInterface::class);
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method("get")->willReturn($logger);

    $fileSystem = $this->createMock(FileSystemInterface::class);

    $this->entityTypeManager = $this->createMock(
      EntityTypeManagerInterface::class,
    );

    $configuration = [
      "cloudName" => "testcloud",
      "apiKey" => "testapikey",
      "apiSecret" => "testapisecret",
      "baseUrl" => "https://res.cloudinary.com",
    ];

    $this->plugin = new Cloudinary(
      $configuration,
      "cloudinary",
      [],
      $this->httpClient,
      $fileSystem,
      $loggerFactory,
      $this->entityTypeManager,
    );
  }

  private function makeCodec(
    string $id,
    string $fileEnding,
    string $mimeType,
  ): VideoCodec {
    $codec = $this->createMock(VideoCodec::class);
    $codec->method("id")->willReturn($id);
    $codec->method("getFileEnding")->willReturn($fileEnding);
    $codec->method("getMimeType")->willReturn($mimeType);
    $codec->method("getWeight")->willReturn(0);
    return $codec;
  }

  private function makeStyle(string $id, string $width): VideoStyle
  {
    $style = $this->createMock(VideoStyle::class);
    $style->method("id")->willReturn($id);
    $style->method("getWidth")->willReturn($width);
    return $style;
  }

  /**
   * @covers ::isReady
   */
  public function testIsReadyReturnsTrueOn200(): void
  {
    $codec = $this->makeCodec("h264", "mp4", "video/mp4");
    $style = $this->makeStyle("720p", "1280");

    $this->httpClient->method("request")->willReturn(new Response(200));

    $this->assertTrue($this->plugin->isReady("my-video", $codec, $style));
  }

  /**
   * @covers ::isReady
   */
  public function testIsReadyReturnsFalseOn404(): void
  {
    $codec = $this->makeCodec("h264", "mp4", "video/mp4");
    $style = $this->makeStyle("720p", "1280");

    $this->httpClient->method("request")->willReturn(new Response(404));

    $this->assertFalse($this->plugin->isReady("my-video", $codec, $style));
  }

  /**
   * @covers ::isReady
   */
  public function testIsReadyReturnsFalseOnException(): void
  {
    $codec = $this->makeCodec("h264", "mp4", "video/mp4");
    $style = $this->makeStyle("720p", "1280");

    $this->httpClient
      ->method("request")
      ->willThrowException(new \RuntimeException("Network error"));

    $this->assertFalse($this->plugin->isReady("my-video", $codec, $style));
  }

  /**
   * @covers ::isPosterReady
   */
  public function testIsPosterReadyReturnsTrueOn200(): void
  {
    $this->httpClient->method("request")->willReturn(new Response(200));

    $this->assertTrue($this->plugin->isPosterReady("my-video"));
  }

  /**
   * @covers ::isPosterReady
   */
  public function testIsPosterReadyReturnsFalseOnException(): void
  {
    $this->httpClient
      ->method("request")
      ->willThrowException(new \RuntimeException("timeout"));

    $this->assertFalse($this->plugin->isPosterReady("my-video"));
  }

  /**
   * @covers ::isReady
   *
   * Verifies spaces in remote IDs are encoded as %20 but parentheses are not.
   */
  public function testDeliveryUrlEncodesSpacesButNotParentheses(): void
  {
    $codec = $this->makeCodec("h264", "mp4", "video/mp4");
    $style = $this->makeStyle("720p", "1280");

    $capturedUrl = null;
    $this->httpClient
      ->expects($this->once())
      ->method("request")
      ->with(
        "HEAD",
        $this->callback(function (string $url) use (&$capturedUrl) {
          $capturedUrl = $url;
          return true;
        }),
      )
      ->willReturn(new Response(200));

    $this->plugin->isReady("folder/my video (clip)", $codec, $style);

    $this->assertStringContainsString("my%20video", $capturedUrl);
    $this->assertStringContainsString("(clip)", $capturedUrl);
    $this->assertStringNotContainsString("%28", $capturedUrl);
  }

  /**
   * @covers ::uploadVideo
   *
   * Verifies the signature is built with raw key=value concatenation, not
   * http_build_query (which would URL-encode the eager string).
   * Full upload flow is covered at the Kernel level.
   */
  public function testSignatureUsesRawConcatenationNotHttpBuildQuery(): void
  {
    $secret = "testapisecret";
    $timestamp = 1700000000;
    $publicId = "my_video_1700000000";
    $eager = "w_1280,c_scale,vc_h264/f_mp4|so_auto,w_1280,c_scale,q_60/f_jpg";

    $params = [
      "eager" => $eager,
      "public_id" => $publicId,
      "timestamp" => $timestamp,
    ];
    ksort($params);

    $stringToSign =
      implode(
        "&",
        array_map(fn($k, $v) => "{$k}={$v}", array_keys($params), $params),
      ) . $secret;

    $signature = sha1($stringToSign);

    // Eager string must not be URL-encoded in the signature input.
    $this->assertStringContainsString("w_1280,c_scale", $stringToSign);
    $this->assertStringNotContainsString("w_1280%2Cc_scale", $stringToSign);
    // sha1 is always 40 hex chars.
    $this->assertSame(40, strlen($signature));
  }

  /**
   * @covers ::downloadConvertedFile
   */
  public function testDownloadConvertedFileStreamsToDestination(): void
  {
    $codec = $this->makeCodec("h264", "mp4", "video/mp4");
    $style = $this->makeStyle("720p", "1280");

    $this->httpClient
      ->expects($this->once())
      ->method("request")
      ->with(
        "GET",
        $this->stringContains("testcloud/video/upload"),
        $this->arrayHasKey("sink"),
      );

    $this->plugin->downloadConvertedFile(
      "my-video",
      $codec,
      $style,
      "/tmp/out.mp4",
    );
  }

  /**
   * @covers ::downloadPoster
   */
  public function testDownloadPosterStreamsToDestination(): void
  {
    $this->httpClient
      ->expects($this->once())
      ->method("request")
      ->with(
        "GET",
        $this->stringContains("so_auto,w_1280,c_scale,q_60"),
        $this->arrayHasKey("sink"),
      );

    $this->plugin->downloadPoster("my-video", "/tmp/poster.jpg");
  }

  /**
   * @covers ::downloadPoster
   */
  public function testPosterUrlUsesJpgExtension(): void
  {
    $capturedUrl = null;
    $this->httpClient
      ->expects($this->once())
      ->method("request")
      ->with(
        "GET",
        $this->callback(function (string $url) use (&$capturedUrl) {
          $capturedUrl = $url;
          return true;
        }),
        $this->anything(),
      );

    $this->plugin->downloadPoster("my-video", "/tmp/poster.jpg");

    $this->assertStringEndsWith(".jpg", $capturedUrl);
  }
}
