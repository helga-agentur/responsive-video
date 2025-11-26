<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Plugin\ResponsiveVideoConverterApiPlugin;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\responsive_video\Attribute\ResponsiveVideoConverterApiPlugin;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\responsive_video\ResponsiveVideoConverterApiPluginPluginBase;
use Drupal\responsive_video\VideoStyleInterface;
use Drupal\responsive_video\VideoFormatInterface;
use Drupal\file\Entity\File;
use Drupal\Core\File\FileSystemInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Cloudinary video converter API plugin.
 */
#[ResponsiveVideoConverterApiPlugin(
  id         : 'cloudinary',
  label      : new TranslatableMarkup('Cloudinary'),
  description: new TranslatableMarkup('Convert videos using Cloudinary API.'),
)]
final class Cloudinary extends ResponsiveVideoConverterApiPluginPluginBase {

  protected string $baseUrl;
  protected string $apiKey;
  protected string $apiSecret;

  protected string $cloudName;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $http_client, FileSystemInterface $file_system, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $http_client, $file_system, $logger_factory);
    $cloudinaryBase = $this->getConfigValue('baseUrl');
    $this->cloudName = $this->getConfigValue('cloudName');
    $this->baseUrl = $cloudinaryBase . '/' . $this->cloudName . '/video';
    $this->apiSecret = $this->getConfigValue('apiSecret');
    $this->apiKey = $this->getConfigValue('apiKey');

  }

  /**
   * @param File $video
   *    The original video File
   * @return \Psr\Http\Message\ResponseInterface
   *
   * @todo maybe don't use this...
   */
  public function fetchVideo(
    File   $video,
    string $format,
    float  $width = 0.0,
    float  $height = 0.0,
    float  $aspectRatio = 0.0,
  ): \Psr\Http\Message\ResponseInterface {
    // fetch uses a URL
    $fileUrl = $video->getFileUri();
    $absoluteUrl = \Drupal::service('file_url_generator')->generateAbsoluteString($fileUrl);

    // for transformation types see https://cloudinary.com/documentation/video_resizing_and_cropping
    $transformations = [
      'width' => $width ? 'w_' . $width : null,
      'height' => $height ? 'h_' . $height : null,
      'aspectRatio' => $aspectRatio ? 'ar_' . $aspectRatio : null,
      'cropResize' => 'c_crop',
    ];

    $usedTransformations = array_filter($transformations);

    $transformationString = implode(',', $usedTransformations);

    // todo remove dummy
    $absoluteUrl = "https://www.helga.ch/sites/default/files/2025-08/Raphel_uebersicht.mp4";


    //$url = $this->baseUrl . '/fetch/' . $transformationString . '/f_' . $format . '/' . $absoluteUrl;
    // fix av1 format
    $url = $this->baseUrl . '/fetch/' . $transformationString . '/f_webm/' . $absoluteUrl;


    return $this->makeRequest('GET', $url);


  }

  /**
   * {@inheritdoc}
   */
  public function uploadVideo(File   $video): string {


    $cloudName = $this->cloudName;
    $apiKey = $this->apiKey;
    $apiSecret = $this->apiSecret;
    $uploadUrl = "https://api.cloudinary.com/v1_1/{$cloudName}/video/upload";

    $timestamp = time();
    $publicId = pathinfo($video->getFilename(), PATHINFO_FILENAME);

// Signatur erstellen
    $stringToSign = "public_id={$publicId}&timestamp={$timestamp}{$apiSecret}";
    $signature = sha1($stringToSign);

    $videoPath = \Drupal::service('file_system')->realpath($video->getFileUri());


    $response = $this->httpClient->post($uploadUrl, [
      'multipart' => [
        [
          'name' => 'file',
          'contents' => fopen($videoPath, 'r'),
        ],
        [
          'name' => 'api_key',
          'contents' => $apiKey,
        ],
        [
          'name' => 'timestamp',
          'contents' => $timestamp,
        ],
        [
          'name' => 'signature',
          'contents' => $signature,
        ],
        [
          'name' => 'public_id',
          'contents' => $publicId,
        ],
      ],
    ]);

    $result = json_decode($response->getBody()->getContents(), true);
    return $result['public_id'];
  }

  /**
   * @throws GuzzleException
   */
  public function downloadConvertedVideo(string $publicId, string $format, float $width = 0, float $height = 0, float $aspectRatio = 0): string {
    $transformations = [
      'width' => $width ? 'w_' . $width : null,
      'height' => $height ? 'h_' . $height : null,
      'aspectRatio' => $aspectRatio ? 'ar_' . $aspectRatio : null,
      'cropResize' => 'c_crop',
    ];

    $usedTransformations = array_filter($transformations);

    $transformationString = implode(',', $usedTransformations);

    $url = $this->baseUrl . '/upload/' . $transformationString . '/' . $publicId . '.' . $format;

    $result = $this->makeRequest('GET', $url);

    return $result->getBody()->getContents();
  }

  /**
   * {@inheritdoc}
   */
  public function requestConversionByRemoteId(string $remoteVideoId, VideoStyleInterface $style, VideoFormatInterface $format): string {
    // Cloudinary uses transformations on-the-fly
    // Return a transformation string as job_id
    $transformation = $this->buildTransformation($style, $format);

    // Job ID format: remoteVideoId|transformation
    return $remoteVideoId . '|' . $transformation;
  }

  /**
   * {@inheritdoc}
   */
  public function requestConversionByFile(File $file, VideoStyleInterface $style, VideoFormatInterface $format): string {
    // Upload file first, then request conversion
    $remoteVideoId = $this->uploadVideo($file);

    return $this->requestConversionByRemoteId($remoteVideoId, $style, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function downloadConvertedVideoById(string $remoteVideoId): string {
    $config = $this->getConfiguration();
    $cloud_name = $config['cloud_name'];

    // If remoteVideoId contains transformation (format: id|transformation)
    if (str_contains($remoteVideoId, '|')) {
      [$videoId, $transformation] = explode('|', $remoteVideoId, 2);
      $download_url = "https://res.cloudinary.com/{$cloud_name}/video/upload/{$transformation}/{$videoId}";
    } else {
      // No transformation, download original
      $download_url = "https://res.cloudinary.com/{$cloud_name}/video/upload/{$remoteVideoId}";
    }

    $response = $this->makeRequest('GET', $download_url);

    // Extract filename from URL or use a generated one
    $filename = 'cloudinary_' . basename(parse_url($download_url, PHP_URL_PATH));
    $destination = 'temporary://' . $filename;

    $this->fileSystem->saveData(
      $response->getBody()->getContents(),
      $destination,
      FileSystemInterface::EXISTS_REPLACE
    );

    return $destination;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $config = $this->getConfiguration();

    $form['apiKey'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $config['apiKey'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Your Cloudinary API key.'),
    ];

    $form['apiSecret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Secret'),
      '#default_value' => $config['apiSecret'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Your Cloudinary API secret.'),
      '#attributes' => [
        'autocomplete' => 'off',
      ],
    ];

    $form['cloudName'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Cloudinary Name'),
      '#default_value' => $config['cloudName'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Your Cloudinary name.'),
    ];

    $form['baseUrl'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Base URL'),
      '#default_value' => $config['baseUrl'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Your API base URL. Probably https://res.cloudinary.com/'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // todo validate
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Nothing special needed - configuration is saved by the form itself
  }

  /**
   * Builds Cloudinary transformation string from style and format.
   *
   * @param VideoStyleInterface $style
   *   The video style.
   * @param VideoFormatInterface $format
   *   The video format.
   *
   * @return string
   *   The transformation string.
   */
  private function buildTransformation(VideoStyleInterface $style, VideoFormatInterface $format): string {
    return '';
  }

  /**
   * Tests connection to Cloudinary API (optional helper).
   *
   * @param array $config
   *   Configuration array with api_key, api_secret, cloud_name.
   *
   * @return bool
   *   TRUE if connection successful.
   * @throws GuzzleException
   */
  private function testConnection(array $config): bool {
    try {
      $url = "{$config['baseUrl']}/resources/video";

      $response = $this->makeRequest('GET', $url, [
        'auth' => [$config['api_key'], $config['api_secret']],
        'query' => ['max_results' => 1],
      ]);

      return $response->getStatusCode() === 200;
    } catch (\Exception $e) {
      $this->logger->warning('Cloudinary connection test failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  public function requestConversion(string $remote_video_id, VideoStyleInterface $style, VideoFormatInterface $format): string {
    // todo
    return '';
  }

  public function checkConversionStatus(string $job_id): array {
    // todo
    return [];
  }


  public function deleteVideo(string $remote_video_id): bool {
    // todo
    return false;
  }

  public function validateConfiguration(FormStateInterface $form_state): array {
    // todo
    return [];
  }


}
