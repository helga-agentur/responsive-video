<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\responsive_video\Form\ResponsiveVideoStyleSizeForm;
use Drupal\responsive_video\ResponsiveVideoStyleSizeInterface;
use Drupal\responsive_video\ResponsiveVideoStyleSizeListBuilder;

/**
 * Defines the responsive video style size entity type.
 */
#[ConfigEntityType(
  id: 'responsive_video_style_size',
  label: new TranslatableMarkup('Responsive Video Style Size'),
  label_collection: new TranslatableMarkup('Responsive Video Style Sizes'),
  label_singular: new TranslatableMarkup('responsive video style size'),
  label_plural: new TranslatableMarkup('responsive video style sizes'),
  config_prefix: 'responsive_video_style_size',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => ResponsiveVideoStyleSizeListBuilder::class,
    'form' => [
      'add' => ResponsiveVideoStyleSizeForm::class,
      'edit' => ResponsiveVideoStyleSizeForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'collection' => '/admin/structure/responsive-video-style-size',
    'add-form' => '/admin/structure/responsive-video-style-size/add',
    'edit-form' => '/admin/structure/responsive-video-style-size/{responsive_video_style_size}',
    'delete-form' => '/admin/structure/responsive-video-style-size/{responsive_video_style_size}/delete',
  ],
  admin_permission: 'administer responsive_video_style_size',
  label_count: [
    'singular' => '@count responsive video style size',
    'plural' => '@count responsive video style sizes',
  ],
  config_export: [
    'id',
    'label',
    'videoStyle',
    'responsiveVideoStyle',
    'breakpoint',
  ],
)]
final class ResponsiveVideoStyleSize extends ConfigEntityBase implements ResponsiveVideoStyleSizeInterface {

  protected string $id;

  protected string $label;

  protected string $videoStyle;

  protected string $responsiveVideoStyle;

  protected int $breakpoint;

  public function getVideoStyle(): string {
    return $this->videoStyle;
  }

  public function getVideoStyleLabel(): string {
    return VideoStyle::load($this->videoStyle)->label();
  }

  public function getResponsiveVideoStyle(): string {
    return $this->responsiveVideoStyle;
  }

  public function getResponsiveVideoStyleLabel(): string {
    return ResponsiveVideoStyle::load($this->responsiveVideoStyle)->label();
  }

  public function getBreakpoint(): int {
    return $this->breakpoint;
  }

}
