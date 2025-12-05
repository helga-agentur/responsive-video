<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\responsive_video\Form\ResponsiveVideoStyleForm;
use Drupal\responsive_video\ResponsiveVideoStyleInterface;
use Drupal\responsive_video\ResponsiveVideoStyleListBuilder;

/**
 * Defines the responsive video style entity type.
 */
#[ConfigEntityType(
  id: 'responsive_video_style',
  label: new TranslatableMarkup('Responsive Video Style'),
  label_collection: new TranslatableMarkup('Responsive Video Styles'),
  label_singular: new TranslatableMarkup('responsive video style'),
  label_plural: new TranslatableMarkup('responsive video styles'),
  config_prefix: 'responsive_video_style',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => ResponsiveVideoStyleListBuilder::class,
    'form' => [
      'add' => ResponsiveVideoStyleForm::class,
      'edit' => ResponsiveVideoStyleForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'collection' => '/admin/structure/responsive-video-style',
    'add-form' => '/admin/structure/responsive-video-style/add',
    'edit-form' => '/admin/structure/responsive-video-style/{responsive_video_style}',
    'delete-form' => '/admin/structure/responsive-video-style/{responsive_video_style}/delete',
  ],
  admin_permission: 'administer responsive_video_style',
  label_count: [
    'singular' => '@count responsive video style',
    'plural' => '@count responsive video styles',
  ],
  config_export: [
    'id',
    'label',
    'videoStyles',
    'breakpoint'
  ],
)]
final class ResponsiveVideoStyle extends ConfigEntityBase implements ResponsiveVideoStyleInterface {

  protected string $id;

  protected string $label;
  protected array $videoStyles = [];

  protected string $breakpoint;

  public function getBreakPoint(): string {
    return $this->breakpoint;
  }

}
