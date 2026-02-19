<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for responsive_video_converter_api_plugin plugins.
 */
abstract class ResponsiveVideoConverterApiPluginPluginBase
  extends PluginBase
  implements
    ResponsiveVideoConverterApiPluginInterface,
    ContainerFactoryPluginInterface
{
  use StringTranslationTrait;

  protected LoggerInterface $logger;
  protected ClientInterface $httpClient;
  protected FileSystemInterface $fileSystem;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ClientInterface $http_client,
    FileSystemInterface $file_system,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get("responsive_video");
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get("http_client"),
      $container->get("file_system"),
      $container->get("logger.factory"),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string
  {
    return (string) $this->pluginDefinition["label"];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {}

  /**
   * Returns a specific configuration value.
   */
  protected function getConfigValue(string $key, mixed $default = null): mixed
  {
    return $this->configuration[$key] ?? $default;
  }

  /**
   * Streams a remote URL to a local file path without buffering in memory.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function streamToFile(string $url, string $destination): void
  {
    // Pass the file path directly; Guzzle opens and closes the resource itself.
    $this->httpClient->request("GET", $url, [
      "sink" => $destination,
    ]);
  }
}
