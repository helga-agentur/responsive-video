<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\responsive_video\Form\VideoFormatForm;
use Drupal\responsive_video\VideoFormatInterface;
use Drupal\responsive_video\VideoFormatListBuilder;

/**
 * Defines the video format entity type.
 */
#[ConfigEntityType(
  id: 'video_format',
  label: new TranslatableMarkup('Video Format'),
  label_collection: new TranslatableMarkup('Video Formats'),
  label_singular: new TranslatableMarkup('video format'),
  label_plural: new TranslatableMarkup('video formats'),
  config_prefix: 'video_format',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => VideoFormatListBuilder::class,
    'form' => [
      'add' => VideoFormatForm::class,
      'edit' => VideoFormatForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'collection' => '/admin/structure/video-format',
    'add-form' => '/admin/structure/video-format/add',
    'edit-form' => '/admin/structure/video-format/{video_format}',
    'delete-form' => '/admin/structure/video-format/{video_format}/delete',
  ],
  admin_permission: 'administer video_format',
  label_count: [
    'singular' => '@count video format',
    'plural' => '@count video formats',
  ],
  config_export: [
    'id',
    'label',
    'fileEnding',
    'weight'
  ],
)]
final class VideoFormat extends ConfigEntityBase implements VideoFormatInterface {

  protected string $id;

  protected string $label;

  protected string $fileEnding;

  protected ?int $weight = 0;

  public function getWeight(): int {
    return $this->weight;
  }

}
