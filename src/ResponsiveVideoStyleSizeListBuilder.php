<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of responsive video style sizes.
 */
final class ResponsiveVideoStyleSizeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('Machine name');
    $header['label'] = $this->t('Label');
    $header['videoStyle'] = $this->t('Video style');
    $header['responsiveVideoStyle'] = $this->t('Responsive video style');
    $header['breakpoint'] = $this->t('Breakpoint');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\responsive_video\ResponsiveVideoStyleSizeInterface $entity */
    $row['id'] = $entity->id();
    $row['label'] = $entity->label();
    $row['videoStyle'] = $entity->getVideoStyleLabel();
    $row['responsiveVideoStyle'] = $entity->getResponsiveVideoStyleLabel();
    $row['breakpoint'] = $entity->getBreakpoint();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

}
