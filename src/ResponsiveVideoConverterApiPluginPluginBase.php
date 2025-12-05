<?php

declare(strict_types=1);

namespace Drupal\responsive_video;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for responsive_video_converter_api_plugin plugins.
 */
abstract class ResponsiveVideoConverterApiPluginPluginBase extends PluginBase implements ResponsiveVideoConverterApiPluginInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The logger.
   */
  protected LoggerInterface $logger;

  /**
   * The HTTP client.
   */
  protected ClientInterface $httpClient;

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Constructs a ResponsiveVideoConverterApiPluginPluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param ClientInterface $http_client
   *   The HTTP client.
   * @param FileSystemInterface $file_system
   *   The file system service.
   * @param LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    array $configuration,
          $plugin_id,
          $plugin_definition,
    ClientInterface $http_client,
    FileSystemInterface $file_system,
    LoggerChannelFactoryInterface $logger_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->httpClient = $http_client;
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('responsive_video');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('file_system'),
      $container->get('logger.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Base validation - override in specific plugins for custom validation
    $values = $form_state->getValue(['settings']);

    if (empty($values['api_key'])) {
      $form_state->setError($form['settings']['api_key'], $this->t('API key is required.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    // Default: nothing to do. Override if needed for special processing.
  }

  /**
   * Gets the plugin configuration.
   *
   * @return array
   *   The configuration array.
   */
  protected function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * Gets a specific configuration value.
   *
   * @param string $key
   *   The configuration key.
   * @param mixed $default
   *   The default value if key doesn't exist.
   *
   * @return mixed
   *   The configuration value.
   */
  protected function getConfigValue(string $key, $default = NULL) {
    return $this->configuration[$key] ?? $default;
  }

  /**
   * Helper method to make HTTP requests with error handling.
   *
   * @param string $method
   *   The HTTP method (GET, POST, etc.).
   * @param string $url
   *   The URL to request.
   * @param array $options
   *   The request options.
   *
   * @return ResponseInterface
   *   The response.
   *
   * @throws \Exception|GuzzleException
   *   When request fails.
   */
  protected function makeRequest(string $method, string $url, array $options = []) {
    try {
      return $this->httpClient->request($method, $url, $options);
    }
    catch (\Exception $e) {
      $this->logger->error('API request failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

}
