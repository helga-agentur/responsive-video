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
   * Requests video conversion for specific style and format.
   *
   * @param string $remote_video_id
   *   The remote video identifier.
   * @param VideoStyleInterface $style
   *   The video style (dimensions, bitrate, etc.).
   * @param VideoFormatInterface $format
   *   The video format (codec, container, etc.).
   *
   * @return string
   *   The conversion job ID.
   *
   * @throws \Exception When conversion request fails.
   */
  public function requestConversion(string $remote_video_id, VideoStyleInterface $style, VideoFormatInterface $format): string;

  /**
   * Checks the status of a conversion job.
   *
   * @param string $job_id
   *   The conversion job identifier.
   *
   * @return array
   *   Array with keys:
   *   - status: 'pending'|'processing'|'completed'|'failed'
   *   - progress: int (0-100)
   *   - download_url: string|null (available when completed)
   *   - error: string|null (available when failed)
   */
  public function checkConversionStatus(string $job_id): array;

  /**
   * Downloads the converted video.
   *
   * @param string $download_url
   *   The URL to download the converted video from.
   *
   * @return string
   *   The local file path of the downloaded video.
   *
   * @throws \Exception
   *   When download fails.
   */
  public function downloadConvertedVideo(
    string $publicId,
    string $format,
    float $width = 0,
    float $height = 0,
    float $aspectRatio = 0
  ): string;

  /**
   * Deletes a video from the converter service.
   *
   * @param string $remote_video_id
   *   The remote video identifier.
   *
   * @return bool
   *   TRUE on success, FALSE on failure.
   */
  public function deleteVideo(string $remote_video_id): bool;

  /**
   * Gets the configuration form for this API plugin.
   *
   * @param array $form
   *   The form array.
   * @param array $configuration
   *   The current configuration.
   *
   * @return array
   *   The form array with plugin-specific configuration fields.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array;

  /**
   * Validates the configuration.
   *
   * @param FormStateInterface $form_state
   * @return array
   *   Array of error messages, empty if valid.
   */
  public function validateConfiguration(FormStateInterface $form_state): array;

}
