<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of video formats.
 */
final class VideoFormatListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('Machine name');
    $header['label'] = $this->t('Label');
    $header['format'] = $this->t('Format');
    $header['mimeType'] = $this->t('Mime type');
    $header['codec'] = $this->t('Codec');
    $header['codecLong'] = $this->t('Codec long');
    $header['weight'] = $this->t('Weight');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\responsive_video\VideoFormatInterface $entity */
    $row['id'] = $entity->id();
    $row['label'] = $entity->label();
    $row['format'] = $entity->getFormat() ?? '';
    $row['mimeType'] = $entity->getMimeType();
    $row['codec'] = $entity->getCodec();
    $row['codecLong'] = $entity->getCodecLong();
    $row['weight'] = $entity->getWeight();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

}
