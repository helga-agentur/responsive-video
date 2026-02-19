<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Plugin\media\Source;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Drupal\media\MediaTypeInterface;
use Drupal\responsive_video\ConversionRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Media source plugin for responsive_video.
 *
 * @MediaSource(
 *   id = "responsive_video",
 *   label = @Translation("Responsive Video"),
 *   description = @Translation("Source plugin for locally-converted responsive video files."),
 *   allowed_field_types = {"file"},
 *   default_thumbnail_filename = "video.png",
 * )
 */
final class ResponsiveVideo extends MediaSourceBase
{
  /**
   * Metadata keys this source exposes.
   */
  const METADATA_THUMBNAIL_URI = "thumbnail_uri";
  const METADATA_FILENAME = "filename";
  const METADATA_MIME_TYPE = "mime_type";
  const METADATA_FILE_SIZE = "file_size";

  /**
   * Allowed source MIME types for upload validation.
   */
  const ALLOWED_MIME_TYPES = [
    "video/mp4",
    "video/webm",
    "video/ogg",
    "video/quicktime",
    "video/x-msvideo",
    "video/x-ms-wmv",
  ];

  /**
   * Allowed source file extensions for upload validation.
   */
  const ALLOWED_EXTENSIONS = "mp4 webm ogv mov avi wmv";

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    FieldTypePluginManagerInterface $field_type_manager,
    ConfigFactoryInterface $config_factory,
    private readonly ConversionRepository $conversionRepository,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $entity_type_manager,
      $entity_field_manager,
      $field_type_manager,
      $config_factory,
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("entity_type.manager"),
      $container->get("entity_field.manager"),
      $container->get("plugin.manager.field.field_type"),
      $container->get("config.factory"),
      $container->get("responsive_video.conversion_repository"),
      $container->get("file_url_generator"),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes(): array
  {
    return [
      self::METADATA_THUMBNAIL_URI => $this->t("Poster image URI"),
      self::METADATA_FILENAME => $this->t("Filename"),
      self::METADATA_MIME_TYPE => $this->t("MIME type"),
      self::METADATA_FILE_SIZE => $this->t("File size"),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name): mixed
  {
    /** @var \Drupal\file\FileInterface|null $file */
    $file = $this->getSourceFieldValue($media);

    switch ($attribute_name) {
      case self::METADATA_THUMBNAIL_URI:
        $mid = (int) $media->id();
        $row = $this->conversionRepository->load($mid);
        if ($row && !empty($row["poster_fid"])) {
          /** @var \Drupal\file\FileInterface|null $poster */
          $poster = $this->entityTypeManager
            ->getStorage("file")
            ->load((int) $row["poster_fid"]);
          if ($poster !== null) {
            return $poster->getFileUri();
          }
        }
        return null;

      case self::METADATA_FILENAME:
        return $file ? $file->getFilename() : null;

      case self::METADATA_MIME_TYPE:
        return $file ? $file->getMimeType() : null;

      case self::METADATA_FILE_SIZE:
        return $file ? $file->getSize() : null;

      default:
        return parent::getMetadata($media, $attribute_name);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceFieldConstraints(): array
  {
    return [
      "file_validate_is_video" => [],
      "file_validate_extensions" => [
        ["extensions" => self::ALLOWED_EXTENSIONS],
      ],
      "file_validate_mime_types" => [
        ["allowed_mime_types" => self::ALLOWED_MIME_TYPES],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareViewDisplay(
    MediaTypeInterface $type,
    EntityViewDisplayInterface $display,
  ): void {
    $display->setComponent($this->getSourceFieldDefinition($type)->getName(), [
      "type" => "responsive_video_formatter",
      "weight" => 0,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function prepareFormDisplay(
    MediaTypeInterface $type,
    EntityFormDisplayInterface $display,
  ): void {
    // Nothing specific; default widget handles file upload.
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    // No additional config needed beyond source field selection.
    return $form;
  }
}
