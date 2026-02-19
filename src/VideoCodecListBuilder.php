<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of video codecs.
 */
final class VideoCodecListBuilder extends ConfigEntityListBuilder {

  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['fileEnding'] = $this->t('File ending');
    $header['mimeType'] = $this->t('MIME type');
    $header['weight'] = $this->t('Weight');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\responsive_video\VideoCodecInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['fileEnding'] = $entity->getFileEnding();
    $row['mimeType'] = $entity->getMimeType();
    $row['weight'] = $entity->getWeight();
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

}
