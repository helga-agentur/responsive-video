<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Service to manage the active converter plugin.
 */
final class ConverterPluginManager {

  /**
   * Constructs a ConverterPluginManager object.
   *
   * @param ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param ResponsiveVideoConverterApiPluginPluginManager $pluginManager
   *   The plugin manager.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly ResponsiveVideoConverterApiPluginPluginManager $pluginManager,
  ) {}

  /**
   * Gets the active converter plugin instance.
   *
   * @return ResponsiveVideoConverterApiPluginInterface|null
   *   The active plugin instance or NULL if none configured.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getActivePlugin(): ?ResponsiveVideoConverterApiPluginInterface {
    $config = $this->configFactory->get('responsive_video.settings');
    $plugin_id = $config->get('converter_plugin');

    if (empty($plugin_id)) {
      return NULL;
    }

    $plugin_config = $config->get('converter_plugin_configuration') ?? [];

    return $this->pluginManager->createInstance($plugin_id, $plugin_config);
  }

  /**
   * Checks if a converter plugin is configured.
   *
   * @return bool
   *   TRUE if a plugin is configured, FALSE otherwise.
   */
  public function hasActivePlugin(): bool {
    $config = $this->configFactory->get('responsive_video.settings');
    return !empty($config->get('converter_plugin'));
  }

  /**
   * Gets the active plugin ID.
   *
   * @return string|null
   *   The plugin ID or NULL if none configured.
   */
  public function getActivePluginId(): ?string {
    $config = $this->configFactory->get('responsive_video.settings');
    return $config->get('converter_plugin');
  }

}
