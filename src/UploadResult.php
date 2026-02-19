<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

/**
 * Value object returned by a converter plugin after uploading a source video.
 */
final class UploadResult {

  public function __construct(
    public readonly string $remoteId,
    public readonly int $width,
    public readonly int $height,
  ) {}

}
