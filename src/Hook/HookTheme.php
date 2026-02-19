<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Registers theme hooks for the Responsive Video module.
 */
final class HookTheme
{
  #[Hook("theme")]
  public function registerTheme(): array
  {
    return [
      // Theme hook used by the field formatter.
      "responsive_video" => [
        "variables" => [
          "sources" => [],
          "poster_url" => null,
          "fallback_url" => null,
        ],
        "template" => "responsive-video",
      ],
      // Override for the Media entity template (keeps existing behaviour).
      "media__responsive_video" => [
        "base hook" => "media",
      ],
    ];
  }
}
