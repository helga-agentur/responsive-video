<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/**
 * Interface for responsive_video_converter_api_plugin plugins.
 */
interface ResponsiveVideoConverterApiPluginInterface {

  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Uploads a video to the converter service.
   *
   * @param File $video
   *   The video entity to upload.
   *
   * @return string
   *   The remote video ID or identifier from the converter service.
   *
   * @throws \Exception When upload fails.
   */
  public function uploadVideo(File $video): string;

  /**
   * Downloads the converted video.
   *
   * @param string $publicId
   * @param string $format
   * @param float $width
   * @param float $height
   * @param float $aspectRatio
   * @return string
   *   The local file path of the downloaded video.
   *
   * @throws \Exception When download fails.
   */
  public function downloadConvertedVideo(
    string $publicId,
    string $format,
    float $width = 0,
    float $height = 0,
    float $aspectRatio = 0
  ): string;

  /**
   * Gets the configuration form for this API plugin.
   *
   * @param array $form
   *   The form array.
   * @param FormStateInterface $form_state
   * @return array
   *   The form array with plugin-specific configuration fields.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array;
}
