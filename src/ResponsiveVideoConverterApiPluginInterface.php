<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Core\Form\FormStateInterface;
use Drupal\file\FileInterface;
use Drupal\responsive_video\Entity\VideoCodec;
use Drupal\responsive_video\Entity\VideoStyle;

/**
 * Interface for responsive_video_converter_api_plugin plugins.
 */
interface ResponsiveVideoConverterApiPluginInterface
{
  /**
   * Returns the translated plugin label.
   */
  public function label(): string;

  /**
   * Uploads a source video to the converter service.
   *
   * @param \Drupal\file\FileInterface $file
   *   The source video file entity.
   *
   * @return \Drupal\responsive_video\UploadResult
   *   Value object containing remoteId, width, height.
   *
   * @throws \Exception When upload fails.
   */
  public function uploadVideo(FileInterface $file): UploadResult;

  /**
   * Checks whether an eager transformation is ready for download.
   *
   * @param string $remoteId
   *   The remote identifier returned by uploadVideo().
   * @param \Drupal\responsive_video\Entity\VideoCodec $codec
   *   The codec to check.
   * @param \Drupal\responsive_video\Entity\VideoStyle $style
   *   The style to check.
   *
   * @return bool TRUE if the transformation is ready.
   */
  public function isReady(
    string $remoteId,
    VideoCodec $codec,
    VideoStyle $style,
  ): bool;

  /**
   * Streams a converted file to a local temp path.
   *
   * @param string $remoteId
   *   The remote identifier returned by uploadVideo().
   * @param \Drupal\responsive_video\Entity\VideoCodec $codec
   *   The codec to download.
   * @param \Drupal\responsive_video\Entity\VideoStyle $style
   *   The style to download.
   * @param string $destination
   *   Absolute path within getTempDirectory(); no in-memory buffering.
   *
   * @throws \Exception When download fails.
   */
  public function downloadConvertedFile(
    string $remoteId,
    VideoCodec $codec,
    VideoStyle $style,
    string $destination,
  ): void;

  /**
   * Checks whether the poster image is ready for download.
   *
   * @param string $remoteId
   *   The remote identifier returned by uploadVideo().
   *
   * @return bool TRUE if the poster is ready.
   */
  public function isPosterReady(string $remoteId): bool;

  /**
   * Streams the poster image to a local temp path.
   *
   * @param string $remoteId
   *   The remote identifier returned by uploadVideo().
   * @param string $destination
   *   Absolute path within getTempDirectory().
   *
   * @throws \Exception When download fails.
   */
  public function downloadPoster(string $remoteId, string $destination): void;

  /**
   * Deletes the source video and all derivatives from the remote service.
   *
   * @param string $remoteId
   *   The remote identifier returned by uploadVideo().
   *
   * @throws \Exception When deletion fails.
   */
  public function deleteRemote(string $remoteId): void;

  /**
   * Builds the plugin-specific configuration form fields.
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ): array;

  /**
   * Validates the plugin-specific configuration form.
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void;

  /**
   * Handles plugin-specific configuration form submission.
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void;
}
