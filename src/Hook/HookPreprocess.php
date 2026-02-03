<?php

namespace Drupal\responsive_video\Hook;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\File\FileUrlGenerator;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;
use Drupal\responsive_video\FilesystemManager;
use Drupal\responsive_video\StyleFormatMixer;

class HookPreprocess {

  #[Hook('preprocess_media__responsive_video')]
  public function preprocess(&$variables) {
    /** @var MediaInterface $media */
    $media = $variables['media'];

    if ($media->bundle() !== 'responsive_video') {
      return;
    }

    $videoFileId = \Drupal::service('responsive_video.filesystem_manager')->getMediumLocalFileTargetId($media);
    if (!$videoFileId) {
      return;
    }

    $videoFile = File::load($videoFileId);
    $fullVideoFileName = $videoFile->getFilename();
    $videoFileName = pathinfo($fullVideoFileName, PATHINFO_FILENAME);
    $videoPath = $videoFile->getFileUri();
    $datePart = basename(dirname($videoPath));

    /**
     * <video>
     * foreach responsive style as name -> values
     *   get breakpoint
     *   foreach format as format
     *     print <source src="public://responsive_videos/dateOfVideo/style/videoName.format" media="(min-width: breakpoint)">
     *   endforeach
     * endforeach
     * <video>
     */

    $html = '<video class="e-video" style="width: 500px; height: 500px;" controls>';

    /** @var StyleFormatMixer $styleFormatMixer */
    $styleFormatMixer = \Drupal::service('responsive_video.style_format_mixer');

    /** @var FilesystemManager $fileSytemManager */
    $fileSytemManager = \Drupal::service('responsive_video.filesystem_manager');

    /** @var FileUrlGenerator $fileUrlGenerator */
    $fileUrlGenerator = \Drupal::service('file_url_generator');

    $responsiveVideoStyles = $styleFormatMixer->getResponsiveVideoStyles();
    $activeVideoStyles = $styleFormatMixer->getVideoStylesFromResponsiveVideoStyles($responsiveVideoStyles);
    $formats = $styleFormatMixer->getFormatFileEndings();

    $responsiveVideoDirectory = $fileSytemManager::PUBLIC_DIRECTORY . $fileSytemManager::BASE_DIRECTORY;

    foreach ($responsiveVideoStyles as $responsiveVideoStyle) {
      $breakpoint = $responsiveVideoStyle->getBreakpoint();
      foreach ($formats as $format) {
        foreach (array_keys($activeVideoStyles) as $activeVideoStyleName) {
          $urlString = $responsiveVideoDirectory . '/' . $datePart . '/' . $activeVideoStyleName .'/' . $videoFileName . '.' . $format;
          $url = $fileUrlGenerator->generateAbsoluteString($urlString);
          $html .= '<source src="' . $url . '" media="(min-width: ' . $breakpoint . ')" type="video/' . $format . '">';

          // av1
          // kind of stupid like that... maybe there's a better solution. but I have to finish...
          if ($format == 'mp4') {
            if (is_dir($responsiveVideoDirectory . '/' . $datePart . '/' . $activeVideoStyleName . '/av1')) {
              $urlString = $responsiveVideoDirectory . '/' . $datePart . '/' . $activeVideoStyleName .'/av1/' . $videoFileName . '.' . $format;
              $url = $fileUrlGenerator->generateAbsoluteString($urlString);
              $html .= '<source src="' . $url . '" media="(min-width: ' . $breakpoint . ')" type=\'video/' . $format . '; codecs="av01"\'>';
            }
          }
        }
      }
    }

    $html .= '</video>';

    $variables['responsive_video'] = [
      '#markup' => new FormattableMarkup($html, []),
    ];
  }


}
