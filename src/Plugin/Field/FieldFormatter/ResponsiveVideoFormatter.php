<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\responsive_video\ConversionRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a responsive_video Media source field as a <video> element.
 */
#[FieldFormatter(
  id: 'responsive_video_formatter',
  label: new TranslatableMarkup('Responsive Video'),
  field_types: ['file'],
)]
final class ResponsiveVideoFormatter extends FormatterBase {

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    private readonly ConversionRepository $repository,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('responsive_video.conversion_repository'),
      $container->get('entity_type.manager'),
      $container->get('file_url_generator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    /** @var \Drupal\media\MediaInterface $media */
    $media = $items->getEntity();
    $mid   = (int) $media->id();

    // Load conversion state (one query per table).
    $row       = $this->repository->load($mid);
    $fileRows  = $row ? $this->repository->loadFiles($mid) : [];

    // Build poster URL if poster_fid is set.
    $posterUrl = NULL;
    if ($row && !empty($row['poster_fid'])) {
      /** @var \Drupal\file\FileInterface|null $poster */
      $poster = $this->entityTypeManager->getStorage('file')->load((int) $row['poster_fid']);
      if ($poster !== NULL) {
        $posterUrl = $this->fileUrlGenerator->generateAbsoluteString($poster->getFileUri());
      }
    }

    // Build fallback URL from the source file.
    $fallbackUrl = NULL;
    $sourceFid = $items->first()?->target_id;
    if ($sourceFid) {
      /** @var \Drupal\file\FileInterface|null $sourceFile */
      $sourceFile = $this->entityTypeManager->getStorage('file')->load($sourceFid);
      if ($sourceFile !== NULL) {
        $fallbackUrl = $this->fileUrlGenerator->generateAbsoluteString($sourceFile->getFileUri());
      }
    }

    // If no converted files, render fallback only.
    if (empty($fileRows)) {
      $elements[0] = [
        '#theme'       => 'responsive_video',
        '#sources'     => [],
        '#poster_url'  => $posterUrl,
        '#fallback_url' => $fallbackUrl,
        '#cache'       => ['tags' => ['media:' . $mid]],
      ];
      return $elements;
    }

    // Index file rows by codec_id + style_id for quick lookup.
    $fileIndex = [];
    foreach ($fileRows as $fileRow) {
      $fileIndex[$fileRow['codec_id']][$fileRow['style_id']] = (int) $fileRow['fid'];
    }

    // Load codec entities (sorted by weight asc).
    /** @var \Drupal\responsive_video\Entity\VideoCodec[] $codecs */
    $codecs = $this->entityTypeManager->getStorage('video_codec')->loadByProperties(['status' => TRUE]);
    usort($codecs, fn($a, $b) => $a->getWeight() <=> $b->getWeight());

    // Load ResponsiveVideoStyle entities (sorted by breakpoint desc for min-width).
    /** @var \Drupal\responsive_video\Entity\ResponsiveVideoStyle[] $rvStyles */
    $rvStyles = $this->entityTypeManager->getStorage('responsive_video_style')->loadMultiple();
    usort($rvStyles, fn($a, $b) => $b->getBreakpoint() <=> $a->getBreakpoint());

    $sources = [];

    foreach ($rvStyles as $rvStyle) {
      $breakpoint = $rvStyle->getBreakpoint();
      $mediaQuery  = $breakpoint > 0 ? "(min-width: {$breakpoint}px)" : NULL;

      foreach ($rvStyle->getVideoStyles() as $styleId) {
        foreach ($codecs as $codec) {
          $codecId = $codec->id();
          $fid = $fileIndex[$codecId][$styleId] ?? NULL;
          if ($fid === NULL) {
            continue;
          }

          /** @var \Drupal\file\FileInterface|null $file */
          $file = $this->entityTypeManager->getStorage('file')->load($fid);
          if ($file === NULL) {
            continue;
          }

          $sources[] = [
            'url'        => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
            'type'       => $codec->getMimeType(),
            'media'      => $mediaQuery,
          ];
        }
      }
    }

    $elements[0] = [
      '#theme'        => 'responsive_video',
      '#sources'      => $sources,
      '#poster_url'   => $posterUrl,
      '#fallback_url' => $fallbackUrl,
      '#cache'        => ['tags' => ['media:' . $mid]],
    ];

    return $elements;
  }

}
