<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\responsive_video\ResponsiveVideoConverterApiPluginPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Responsive Video settings.
 */
final class ResponsiveVideoSettingsForm extends ConfigFormBase {

  /**
   * The converter API plugin manager.
   */
  protected ResponsiveVideoConverterApiPluginPluginManager $pluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->pluginManager = $container->get('plugin.manager.responsive_video_converter_api_plugin');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'responsive_video_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['responsive_video.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('responsive_video.settings');
    $selected_plugin = $config->get('converter_plugin');
    $plugin_config = $config->get('converter_plugin_configuration') ?? [];

    // Get all available plugins
    $plugin_definitions = $this->pluginManager->getDefinitions();
    $plugin_options = [];

    foreach ($plugin_definitions as $plugin_id => $definition) {
      $plugin_options[$plugin_id] = $definition['label'];
    }

    $form['converter_plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Video Converter API'),
      '#description' => $this->t('Select which video converter service to use.'),
      '#options' => $plugin_options,
      '#default_value' => $selected_plugin,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select a converter -'),
      '#ajax' => [
        'callback' => '::ajaxRebuildForm',
        'wrapper' => 'plugin-configuration-wrapper',
      ],
    ];

    // Container for plugin-specific configuration
    $form['plugin_configuration'] = [
      '#type' => 'container',
      '#prefix' => '<div id="plugin-configuration-wrapper">',
      '#suffix' => '</div>',
    ];

    // Get selected plugin from form state or config
    $selected = $form_state->getValue('converter_plugin') ?? $selected_plugin;

    if (!empty($selected)) {
      try {
        // Create plugin instance
        $plugin = $this->pluginManager->createInstance($selected, $plugin_config);

        // Add plugin description
        $definition = $this->pluginManager->getDefinition($selected);
        if (!empty($definition['description'])) {
          $form['plugin_configuration']['description'] = [
            '#type' => 'item',
            '#markup' => '<p>' . $definition['description'] . '</p>',
          ];
        }

        // Build plugin-specific configuration form
        $form['plugin_configuration']['settings'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('@label Configuration', ['@label' => $definition['label']]),
          '#tree' => TRUE,
        ];

        $form['plugin_configuration']['settings'] = $plugin->buildConfigurationForm(
          $form['plugin_configuration']['settings'],
          $form_state
        );
      }
      catch (\Exception $e) {
        $this->messenger()->addError($this->t('Error loading plugin configuration: @message', [
          '@message' => $e->getMessage(),
        ]));
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback to rebuild the form.
   */
  public function ajaxRebuildForm(array &$form, FormStateInterface $form_state): array {
    return $form['plugin_configuration'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $plugin_id = $form_state->getValue('converter_plugin');

    if (!empty($plugin_id)) {
      try {
        $plugin_config = $this->config('responsive_video.settings')->get('converter_plugin_configuration') ?? [];
        $plugin = $this->pluginManager->createInstance($plugin_id, $plugin_config);

        $plugin->validateConfigurationForm($form, $form_state);
      }
      catch (\Exception $e) {
        $form_state->setError($form['converter_plugin'], $this->t('Invalid plugin selected.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $plugin_id = $form_state->getValue('converter_plugin');

    // Let plugin process its own submission if needed
    if (!empty($plugin_id)) {
      try {
        $plugin_config = $this->config('responsive_video.settings')->get('converter_plugin_configuration') ?? [];
        $plugin = $this->pluginManager->createInstance($plugin_id, $plugin_config);

        $plugin->submitConfigurationForm($form, $form_state);
      }
      catch (\Exception $e) {
        // Log error but continue saving
        $this->logger('responsive_video')->error('Plugin submit error: @message', [
          '@message' => $e->getMessage(),
        ]);
      }
    }

    $this->config('responsive_video.settings')
         ->set('converter_plugin', $plugin_id)
         ->set('converter_plugin_configuration', $form_state->getValue(['settings']) ?? [])
         ->save();

    parent::submitForm($form, $form_state);
  }

}
