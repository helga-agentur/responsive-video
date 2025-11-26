<?php

declare(strict_types=1);

namespace Drupal\responsive_video\EventSubscriber;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\file\Entity\File;
use Drupal\responsive_video\ConverterPluginManager;
use Drupal\responsive_video\Entity\VideoStyle;
use Drupal\responsive_video\Event\ResponsiveVideoEvent;
use Drupal\responsive_video\FilesystemManager;
use Drupal\responsive_video\StyleFormatMixer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


/**
 * Listens to Entity change events
 */
final readonly class ResponsiveVideoSubscriber implements EventSubscriberInterface {

  public function __construct(
    public FilesystemManager $filesystemManager,
    public StyleFormatMixer $styleFormatMixer,
    public ConverterPluginManager $pluginManager,
  ) {}

  /**
   * @throws InvalidPluginDefinitionException
   * @throws PluginException
   * @throws PluginNotFoundException
   * @throws \Exception
   */
  public function onVideoCreate(ResponsiveVideoEvent $event): void {
    /*
     *  Check if Filesystem (/files/responsive_videos/YYYY-MM) is created
     *  Create directory if not present
     *  get all possible Video Formats
     *  get all responsive-video-styles
     *  get all video-styles activated in responsive-video-styles
     *  get all video formats
     *  send all permutations of combinations to converter
     *  check if filesystem is ready. Every video is saved in /files/responsive_videos/styles/{sylename}/YYYY-MM
     */

    $this->responsiveVideoConverter->convertMediaToAllStyles($event->getMedium());

    $date = date('Y-m');
    $this->filesystemManager->prepareDateDirectory($date);

    $medium = $event->getMedium();
    $formats = $this->styleFormatMixer->getFormatFileEndings();
    $respnsiveVideoStyles = $this->styleFormatMixer->getResponsiveVideoStyles();

    $activeVideoStyles = [];
    foreach ($respnsiveVideoStyles as $respnsiveVideoStyle) {
      // get videostyles
      // it doesn't matter for which responsive video-style they're active. If active in at least one, we'll need the converted video
      $videoStyles = $respnsiveVideoStyle->get('videoStyles');
      foreach ($videoStyles as $videoStyle) {
        if ($videoStyle != 0) {
        $style = VideoStyle::load($videoStyle);
          $activeVideoStyles[$videoStyle] = [
            'width' => $style->getWidth(),
            'height' => $style->getHeight(),
          ];
        }
      }
      $activeVideoStyles = array_unique($activeVideoStyles);
    }

    foreach ($activeVideoStyles as $activeVideoStyleName => $values) {
      foreach ($formats as $format) {
        /*
         * send medium to converter
         * with sizes from videostyle
         * with format
         * save in basepath/Style/YYYY-MM/mediumname.format
         */

        $fileId = $medium->field_media_video_file_1->target_id;
        $file = File::load($fileId);
        $fileName = $file->getFilename();


        $plugin = $this->pluginManager->getActivePlugin();

        $remoteVideoId = $plugin->uploadVideo($file);

        [$width, $height] = [$values['width'], $values['height']];

        if ($width && $height) {
          $aspectRatio = $width / $height;
        }

        $video = $plugin->downloadConvertedVideo(
          publicId: $remoteVideoId,
          format: $format,
          width: $width ? (float) $width : 0,
          height: $height ? (float) $height : 0,
          aspectRatio: $aspectRatio ?? 0,
        );

        $this->filesystemManager->saveFile($video, $date . '/' . $activeVideoStyleName . '/' . $fileName);
      }
    }

  }

  /**
   * Kernel response event handler.
   */
  public function onVideoUpdate(ResponsiveVideoEvent $event): void {
    // delete assets if file of medium changed
    $medium = $event->getMedium();
    // did video file change?
    $original = $medium->getOriginal();
    $currentMediumTargetId = $this->filesystemManager->getMediumFileTargetId($medium);
    $originalMediumTargetId = $this->filesystemManager->getMediumFileTargetId($original);
    if (!$currentMediumTargetId && !$originalMediumTargetId) {
      return;
    }

    if ($currentMediumTargetId !== $originalMediumTargetId) {
      $this->filesystemManager->deleteAssetsOfMedium($original);

      // create new assets
      $this->onVideoCreate($event);
    }

  }

  public function onVideoDelete(ResponsiveVideoEvent $event): void {
    // get all assets and delete them
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ResponsiveVideoEvent::CREATE => ['onVideoCreate'],
      ResponsiveVideoEvent::UPDATE => ['onVideoUpdate'],
      ResponsiveVideoEvent::DELETE => ['onVideoDelete'],
    ];
  }


}
