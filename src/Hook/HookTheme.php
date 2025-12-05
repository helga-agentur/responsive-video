<?php

namespace Drupal\responsive_video\Hook;

use Drupal\Core\Hook\Attribute\Hook;

class HookTheme {

  #[Hook('theme')]
  public function registerTheme() {
    return [
      'media__responsive_video' => [
        'base hook' => 'media',
      ]
    ];
  }

}
