<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Plugin\ResponsiveVideoConverterApiPlugin;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use Drupal\responsive_video\Attribute\ResponsiveVideoConverterApiPlugin;
use Drupal\responsive_video\Entity\VideoCodec;
use Drupal\responsive_video\Entity\VideoStyle;
use Drupal\responsive_video\ResponsiveVideoConverterApiPluginPluginBase;
use Drupal\responsive_video\UploadResult;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Cloudinary video converter API plugin.
 */
#[
  ResponsiveVideoConverterApiPlugin(
    id: "cloudinary",
    label: new TranslatableMarkup("Cloudinary"),
    description: new TranslatableMarkup("Convert videos using Cloudinary API."),
  ),
]
final class Cloudinary extends ResponsiveVideoConverterApiPluginPluginBase
{
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ClientInterface $http_client,
    FileSystemInterface $file_system,
    LoggerChannelFactoryInterface $logger_factory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $http_client,
      $file_system,
      $logger_factory,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("http_client"),
      $container->get("file_system"),
      $container->get("logger.factory"),
      $container->get("entity_type.manager"),
    );
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  private function cloudName(): string
  {
    return (string) $this->getConfigValue("cloudName", "");
  }

  private function apiKey(): string
  {
    return (string) $this->getConfigValue("apiKey", "");
  }

  private function apiSecret(): string
  {
    return (string) $this->getConfigValue("apiSecret", "");
  }

  private function deliveryBaseUrl(): string
  {
    $base = rtrim(
      (string) $this->getConfigValue("baseUrl", "https://res.cloudinary.com"),
      "/",
    );
    return $base . "/" . $this->cloudName() . "/video";
  }

  /**
   * Builds the Cloudinary transformation string for a codec/style combination.
   */
  private function buildTransformation(
    VideoCodec $codec,
    VideoStyle $style,
  ): string {
    $parts = [];

    $width = $style->getWidth();
    if ($width) {
      $parts[] = "w_" . (int) $width;
      $parts[] = "c_scale";
    }

    // Map codec file ending + mime to Cloudinary vc_ parameter.
    // av1 in mp4 → vc_av1; h264 in mp4 → vc_h264; webm → vc_vp9 etc.
    $vcMap = [
      "h264" => "h264",
      "av1" => "av1",
      "vp9" => "vp9",
      "vp8" => "vp8",
    ];
    $codecId = $codec->id();
    if (isset($vcMap[$codecId])) {
      $parts[] = "vc_" . $vcMap[$codecId];
    }

    return implode(",", $parts);
  }

  /**
   * Returns the delivery URL for a specific codec/style variant.
   */
  private function deliveryUrl(
    string $remoteId,
    VideoCodec $codec,
    VideoStyle $style,
  ): string {
    $transformation = $this->buildTransformation($codec, $style);
    $fileEnding = $codec->getFileEnding();
    $base = $this->deliveryBaseUrl();
    return "{$base}/upload/{$transformation}/" .
      $this->encodeRemoteId($remoteId) .
      ".{$fileEnding}";
  }

  /**
   * Returns the poster delivery URL (jpg thumbnail at full width).
   */
  private function posterUrl(string $remoteId): string
  {
    $base = $this->deliveryBaseUrl();
    return "{$base}/upload/so_auto,w_1280,c_scale,q_60/" .
      $this->encodeRemoteId($remoteId) .
      ".jpg";
  }

  /**
   * Encodes a remote ID for use in a Cloudinary delivery URL.
   *
   * Only spaces are percent-encoded; other characters (parentheses, etc.)
   * are left as-is since Cloudinary expects them unencoded.
   */
  private function encodeRemoteId(string $remoteId): string
  {
    return str_replace(" ", "%20", $remoteId);
  }

  // ---------------------------------------------------------------------------
  // Interface implementation
  // ---------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function uploadVideo(FileInterface $file): UploadResult
  {
    $cloudName = $this->cloudName();
    $apiKey = $this->apiKey();
    $apiSecret = $this->apiSecret();
    $uploadUrl = "https://api.cloudinary.com/v1_1/{$cloudName}/video/upload";

    $timestamp = time();
    $publicId =
      pathinfo($file->getFilename(), PATHINFO_FILENAME) . "_" . $timestamp;

    // Build eager transformations list for all active codec/style combos so
    // Cloudinary pre-generates them before we poll for readiness.
    $eagerList = $this->buildEagerList();

    $paramsToSign = [
      "eager" => $eagerList,
      "public_id" => $publicId,
      "timestamp" => $timestamp,
    ];
    ksort($paramsToSign);
    $stringToSign =
      implode(
        "&",
        array_map(
          fn($k, $v) => "{$k}={$v}",
          array_keys($paramsToSign),
          $paramsToSign,
        ),
      ) . $apiSecret;
    $signature = sha1($stringToSign);

    $videoPath = $this->fileSystem->realpath($file->getFileUri());
    if ($videoPath === false) {
      throw new \RuntimeException(
        "Cannot resolve local path for file: " . $file->getFileUri(),
      );
    }

    $multipart = [
      ["name" => "file", "contents" => fopen($videoPath, "r")],
      ["name" => "api_key", "contents" => $apiKey],
      ["name" => "timestamp", "contents" => (string) $timestamp],
      ["name" => "signature", "contents" => $signature],
      ["name" => "public_id", "contents" => $publicId],
    ];
    if ($eagerList !== "") {
      $multipart[] = ["name" => "eager", "contents" => $eagerList];
    }

    $response = $this->httpClient->post($uploadUrl, [
      "multipart" => $multipart,
      "timeout" => 2400,
    ]);
    $result = json_decode($response->getBody()->getContents(), true);

    if (empty($result["public_id"])) {
      throw new \RuntimeException(
        "Cloudinary upload did not return a public_id. Response: " .
          json_encode($result),
      );
    }

    return new UploadResult(
      remoteId: $result["public_id"],
      width: (int) ($result["width"] ?? 0),
      height: (int) ($result["height"] ?? 0),
    );
  }

  /**
   * {@inheritdoc}
   *
   * Checks whether the eager transformation is already available by making a
   * HEAD request to the delivery URL and checking for a 200 response.
   */
  public function isReady(
    string $remoteId,
    VideoCodec $codec,
    VideoStyle $style,
  ): bool {
    $url = $this->deliveryUrl($remoteId, $codec, $style);
    try {
      $response = $this->httpClient->request("HEAD", $url);
      return $response->getStatusCode() === 200;
    } catch (\Throwable) {
      return false;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isPosterReady(string $remoteId): bool
  {
    $url = $this->posterUrl($remoteId);
    try {
      $response = $this->httpClient->request("HEAD", $url);
      return $response->getStatusCode() === 200;
    } catch (\Throwable) {
      return false;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function downloadConvertedFile(
    string $remoteId,
    VideoCodec $codec,
    VideoStyle $style,
    string $destination,
  ): void {
    $url = $this->deliveryUrl($remoteId, $codec, $style);
    $this->streamToFile($url, $destination);
  }

  /**
   * {@inheritdoc}
   */
  public function downloadPoster(string $remoteId, string $destination): void
  {
    $url = $this->posterUrl($remoteId);
    $this->streamToFile($url, $destination);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRemote(string $remoteId): void
  {
    $cloudName = $this->cloudName();
    $apiKey = $this->apiKey();
    $apiSecret = $this->apiSecret();
    $timestamp = time();

    $paramsToSign = ["public_id" => $remoteId, "timestamp" => $timestamp];
    ksort($paramsToSign);
    $stringToSign =
      implode(
        "&",
        array_map(
          fn($k, $v) => "{$k}={$v}",
          array_keys($paramsToSign),
          $paramsToSign,
        ),
      ) . $apiSecret;
    $signature = sha1($stringToSign);

    $this->httpClient->post(
      "https://api.cloudinary.com/v1_1/{$cloudName}/video/destroy",
      [
        "form_params" => [
          "public_id" => $remoteId,
          "api_key" => $apiKey,
          "timestamp" => $timestamp,
          "signature" => $signature,
        ],
      ],
    );
  }

  // ---------------------------------------------------------------------------
  // Configuration form
  // ---------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $config = $this->configuration;

    $form["apiKey"] = [
      "#type" => "textfield",
      "#title" => $this->t("API Key"),
      "#default_value" => $config["apiKey"] ?? "",
      "#required" => true,
    ];

    $form["apiSecret"] = [
      "#type" => "textfield",
      "#title" => $this->t("API Secret"),
      "#default_value" => $config["apiSecret"] ?? "",
      "#required" => true,
      "#attributes" => ["autocomplete" => "off"],
    ];

    $form["cloudName"] = [
      "#type" => "textfield",
      "#title" => $this->t("Cloud Name"),
      "#default_value" => $config["cloudName"] ?? "",
      "#required" => true,
    ];

    $form["baseUrl"] = [
      "#type" => "textfield",
      "#title" => $this->t("Delivery Base URL"),
      "#default_value" => $config["baseUrl"] ?? "https://res.cloudinary.com",
      "#required" => true,
      "#description" => $this->t("Usually https://res.cloudinary.com"),
    ];

    return $form;
  }

  // ---------------------------------------------------------------------------
  // Internal helpers
  // ---------------------------------------------------------------------------

  /**
   * Builds the comma-separated eager transformation string for all active
   * codec/style combinations, used at upload time so Cloudinary pre-generates
   * all variants before we start polling.
   */
  private function buildEagerList(): string
  {
    /** @var \Drupal\responsive_video\Entity\VideoCodec[] $codecs */
    $codecs = $this->entityTypeManager
      ->getStorage("video_codec")
      ->loadByProperties(["status" => true]);

    /** @var \Drupal\responsive_video\Entity\VideoStyle[] $styles */
    $styles = $this->entityTypeManager
      ->getStorage("video_style")
      ->loadMultiple();

    $transformations = [];
    foreach ($codecs as $codec) {
      foreach ($styles as $style) {
        $transformations[] =
          $this->buildTransformation($codec, $style) .
          "/f_" .
          $codec->getFileEnding();
      }
    }

    // Include the poster (jpg thumbnail) so it is pre-generated alongside
    // the video variants and ready when we poll for it.
    $transformations[] = "so_auto,w_1280,c_scale,q_60/f_jpg";

    return implode("|", $transformations);
  }
}
