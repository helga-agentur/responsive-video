<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a video codec entity type.
 */
interface VideoCodecInterface extends ConfigEntityInterface {

  public function getFileEnding(): string;

  public function getMimeType(): string;

  public function getWeight(): int;

}
