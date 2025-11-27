<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;
use Drupal\responsive_video\Entity\VideoStyle;
use Drupal\responsive_video\FilesystemManager;
use Drupal\responsive_video\StyleFormatMixer;

/**
 * @todo Add class description.
 */
final class VideoConverterService {

  protected string $currentDate;
  protected ResponsiveVideoConverterApiPluginInterface $activePlugin;

  /**
   * Constructs a VideoConverterService object.
   */
  public function __construct(
    private readonly FilesystemManager      $filesystemManager,
    private readonly StyleFormatMixer       $styleFormatMixer,
    private readonly ConverterPluginManager $pluginManager,
  ) {
    $this->currentDate = date('Y-m');
    $this->activePlugin = $this->pluginManager->getActivePlugin();
  }


  /**
   * @throws PluginException
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws \Exception
   */
  public function convertMediaToAllStyles(MediaInterface $medium) {
    $this->prepareFileSystem();

    $formats = $this->styleFormatMixer->getFormatFileEndings();
    $activeVideoStyles = $this->styleFormatMixer->getResponsiveVideoStyles();

    $file = $this->getFileFromMedium($medium);

    $remoteVideoId = $this->activePlugin->uploadVideo($file);

    $videos = $this->getConvertedVideos($remoteVideoId, $formats, $activeVideoStyles);

    $this->saveConvertedVideos($videos);
  }

  private function prepareFileSystem(): void {
    $this->filesystemManager->prepareDateDirectory($this->currentDate);
  }

  private function getFileFromMedium(MediaInterface $medium, string $fieldName = 'field_media_video_file_1'): File {
    $fileId = $medium->{$fieldName}->target_id;
    return File::load($fileId);
  }

  /**
   * @param string $remoteVideoId
   * @param array $formats
   * @param array $activeResponsiveVideoStyles
   * @return array
   *    Converted Videos
   * @throws \Exception
   */
  private function getConvertedVideos(string $remoteVideoId, array $formats, array $activeResponsiveVideoStyles): array {
    $convertedVideos = [];
    $activeVideoStyles = $this->styleFormatMixer->getVideoStylesFromResponsiveVideoStyles($activeResponsiveVideoStyles);
    foreach ($activeVideoStyles as $activeVideoStyleName => $style) {
      foreach ($formats as $format) {
        [$width, $height] = [$style->getWidth(), $style->getHeight()];

        if ($width && $height) {
          $aspectRatio = $width / $height;
        }

        $convertedVideo = $this->activePlugin->downloadConvertedVideo(
          publicId   : $remoteVideoId,
          format     : $format,
          width      : $width ? (float)$width : 0,
          height     : $height ? (float)$height : 0,
          aspectRatio: $aspectRatio ?? 0,
        );

        $convertedVideos[] = [
          'remoteVideoId' => $remoteVideoId,
          'videoContents' => $convertedVideo,
          'videoStyle' => $activeVideoStyleName,
          'date' => $this->currentDate,
          'format' => $format,
        ];
      }
    }
    return $convertedVideos;
  }

  private function saveConvertedVideos(array $videos) {
    foreach ($videos as $video) {
      $this->filesystemManager->saveFile($video['videoContents'], $this->currentDate . '/' . $video['style'] . '/' . $video['remoteVideoId'] . '.' . $video['format']);
    }
  }
}
