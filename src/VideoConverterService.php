<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\responsive_video\FilesystemManager;
use Drupal\responsive_video\StyleFormatMixer;

/**
 * @todo Add class description.
 */
final class VideoConverterService {

  /**
   * Constructs a VideoConverterService object.
   */
  public function __construct(
    private readonly FilesystemManager $responsiveVideoFilesystemManager,
    private readonly StyleFormatMixer $responsiveVideoStyleFormatMixer,
  ) {}

  /**
   * @todo Add method description.
   */
  public function doSomething(): void {
    // @todo Place your code here.
  }

}
