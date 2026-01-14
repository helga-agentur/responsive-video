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
    $this->cloudName = $this->getConfigValue('cloudName', '');
    $this->baseUrl = $cloudinaryBase . '/' . $this->cloudName . '/video';
    $this->apiSecret = $this->getConfigValue('apiSecret', '');
    $this->apiKey = $this->getConfigValue('apiKey', '');

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

    // use signature
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
  public function downloadConvertedVideo(string $publicId, string $format, float $width = 0, float $height = 0, float $aspectRatio = 0, string $codec = null): string {

    $cropResize = ($width && $height) ? 'c_fill' : 'c_fit';

    $transformations = [
      'width' => $width ? 'w_' . $width : null,
      'height' => $height ? 'h_' . $height : null,
      'aspectRatio' => $aspectRatio ? 'ar_' . $aspectRatio : null,
      'cropResize' => $cropResize,
    ];

    if ($codec) {
      $transformations['codec'] = 'vc_' . $codec;
    }

    $usedTransformations = array_filter($transformations);

    $transformationString = implode(',', $usedTransformations);

    $url = $this->baseUrl . '/upload/' . $transformationString . '/' . $publicId . '.' . $format;

    $result = $this->makeRequest('GET', $url);

    return $result->getBody()->getContents();
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
      '#description' => $this->t('Your API base URL. Probably https://res.cloudinary.com'),
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

}
