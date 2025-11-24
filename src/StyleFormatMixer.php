<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\responsive_video\Entity\ResponsiveVideoStyle;

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
    return array_filter($responsiveVideoStyles, fn ($responsiveVideo) => $responsiveVideo->status == 1);
  }

}
