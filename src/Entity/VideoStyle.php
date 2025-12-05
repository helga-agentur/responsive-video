<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\responsive_video\Form\VideoStyleForm;
use Drupal\responsive_video\VideoStyleInterface;
use Drupal\responsive_video\VideoStyleListBuilder;

/**
 * Defines the video style entity type.
 */
#[ConfigEntityType(
  id: 'video_style',
  label: new TranslatableMarkup('Video Style'),
  label_collection: new TranslatableMarkup('Video Styles'),
  label_singular: new TranslatableMarkup('video style'),
  label_plural: new TranslatableMarkup('video styles'),
  config_prefix: 'video_style',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => VideoStyleListBuilder::class,
    'form' => [
      'add' => VideoStyleForm::class,
      'edit' => VideoStyleForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'collection' => '/admin/structure/video-style',
    'add-form' => '/admin/structure/video-style/add',
    'edit-form' => '/admin/structure/video-style/{video_style}',
    'delete-form' => '/admin/structure/video-style/{video_style}/delete',
  ],
  admin_permission: 'administer video_style',
  label_count: [
    'singular' => '@count video style',
    'plural' => '@count video styles',
  ],
  config_export: [
    'id',
    'label',
    'width',
    'height',
  ],
)]
final class VideoStyle extends ConfigEntityBase implements VideoStyleInterface {

  protected string $id;

  protected string $label;

  protected ?string $width = null;

  protected ?string $height = null;

  public function getWidth(): string {
    return $this->width;
  }

  public function getHeight(): string {
    return $this->height;
  }

}
