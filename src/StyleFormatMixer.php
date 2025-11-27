<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\responsive_video\Entity\ResponsiveVideoStyle;
use Drupal\responsive_video\Entity\VideoFormat;
use Drupal\responsive_video\Entity\VideoStyle;

/**
 * Helper Service to get all possible Style/Format mixture names for a given responsive-video File
 */
final class StyleFormatMixer {

  public function __construct(readonly EntityTypeManagerInterface $entityTypeManager) {}

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * @return array<int, string>
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function getFormatFileEndings(): array {
    $videoFormats = $this->entityTypeManager->getStorage('video_format')->loadMultiple();
    uasort($videoFormats, function (VideoFormat $a, VideoFormat $b) {
      return $a->getWeight() <=> $b->getWeight();
    });
    $activeFormats = array_filter($videoFormats, fn ($videoFormat) => $videoFormat->status == 1);
    return array_map(fn ($videoFormat) => $videoFormat->get('fileEnding'), $activeFormats);
  }

  /**
   * @return array<string, ResponsiveVideoStyle>
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function getResponsiveVideoStyles(): array {
    $responsiveVideoStyles = $this->entityTypeManager->getStorage('responsive_video_style')->loadMultiple();
    $activeVideoStyles = array_filter($responsiveVideoStyles, fn ($responsiveVideo) => $responsiveVideo->status == 1);

    $returnable = [];
    foreach ($activeVideoStyles as $activeVideoStyle) {
      // get videostyles
      // it doesn't matter for which responsive video-style they're active. If active in at least one, we'll need the converted video
      /** @var array<string, string> $videoStyles */
      $videoStyles = $activeVideoStyle->get('videoStyles');
      foreach ($videoStyles as $videoStyle) {
        if ($videoStyle != 0) {
          $style = VideoStyle::load($videoStyle);
          $returnable[$videoStyle] = [
            'width' => $style->getWidth(),
            'height' => $style->getHeight(),
          ];
        }
      }
      $returnable = array_unique($activeVideoStyles);
    }
    return $returnable;
  }

  /**
   * @param array $activeResponsiveVideoStyles
   * @return array
   *    Array with loaded VideoStyles
   */
  public function getVideoStylesFromResponsiveVideoStyles(array $activeResponsiveVideoStyles) {
    $videoStyles = [];
    foreach ($activeResponsiveVideoStyles as $activeResponsiveVideoStyle) {
      $stylesOfResponsiveStyle = $activeResponsiveVideoStyle->get('videoStyles');
      foreach ($stylesOfResponsiveStyle as $key => $value) {
        if (!array_key_exists($key, $videoStyles) && $value != 0) {
          $videoStyles[$key] = VideoStyle::load($key);
        }
      }
    }
    return $videoStyles;
  }
}
