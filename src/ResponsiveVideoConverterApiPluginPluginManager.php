<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\responsive_video\Attribute\ResponsiveVideoConverterApiPlugin;

/**
 * ResponsiveVideoConverterApiPlugin plugin manager.
 */
class ResponsiveVideoConverterApiPluginPluginManager extends
  DefaultPluginManager
{
  /**
   * Constructs the object.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      "Plugin/ResponsiveVideoConverterApiPlugin",
      $namespaces,
      $module_handler,
      ResponsiveVideoConverterApiPluginInterface::class,
      ResponsiveVideoConverterApiPlugin::class,
    );
    $this->alterInfo("responsive_video_converter_api_plugin_info");
    $this->setCacheBackend(
      $cache_backend,
      "responsive_video_converter_api_plugin_plugins",
    );
  }
}
