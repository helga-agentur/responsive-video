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
final class ResponsiveVideoSettingsForm extends ConfigFormBase
{
  protected ResponsiveVideoConverterApiPluginPluginManager $pluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static
  {
    $instance = parent::create($container);
    $instance->pluginManager = $container->get(
      "plugin.manager.responsive_video_converter_api_plugin",
    );
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return "responsive_video_settings";
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array
  {
    return ["responsive_video.settings"];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $config = $this->config("responsive_video.settings");
    $selected_plugin = $config->get("converter_plugin");
    $plugin_config = $config->get("converter_plugin_configuration") ?? [];

    // --- Converter plugin ---
    $plugin_definitions = $this->pluginManager->getDefinitions();
    $plugin_options = [];
    foreach ($plugin_definitions as $plugin_id => $definition) {
      $plugin_options[$plugin_id] = $definition["label"];
    }

    $form["converter_plugin"] = [
      "#type" => "select",
      "#title" => $this->t("Video Converter API"),
      "#options" => $plugin_options,
      "#default_value" => $selected_plugin,
      "#required" => true,
      "#empty_option" => $this->t("- Select a converter -"),
      "#ajax" => [
        "callback" => "::ajaxRebuildForm",
        "wrapper" => "plugin-configuration-wrapper",
      ],
    ];

    $form["plugin_configuration"] = [
      "#type" => "container",
      "#prefix" => '<div id="plugin-configuration-wrapper">',
      "#suffix" => "</div>",
    ];

    $selected = $form_state->getValue("converter_plugin") ?? $selected_plugin;
    if (!empty($selected)) {
      try {
        $plugin = $this->pluginManager->createInstance(
          $selected,
          $plugin_config,
        );
        $definition = $this->pluginManager->getDefinition($selected);

        $form["plugin_configuration"]["settings"] = [
          "#type" => "fieldset",
          "#title" => $this->t("@label Configuration", [
            "@label" => $definition["label"],
          ]),
          "#tree" => true,
        ];
        $form["plugin_configuration"][
          "settings"
        ] = $plugin->buildConfigurationForm(
          $form["plugin_configuration"]["settings"],
          $form_state,
        );
      } catch (\Exception $e) {
        $this->messenger()->addError(
          $this->t("Error loading plugin configuration: @message", [
            "@message" => $e->getMessage(),
          ]),
        );
      }
    }

    // --- File storage ---
    $form["output_directory"] = [
      "#type" => "textfield",
      "#title" => $this->t("Output directory"),
      "#description" => $this->t(
        "Directory within public:// for converted files. Default: <code>responsive_videos</code>.",
      ),
      "#default_value" =>
        $config->get("output_directory") ?: "responsive_videos",
      "#required" => true,
    ];

    // --- Staleness recovery ---
    $form["staleness_threshold"] = [
      "#type" => "number",
      "#title" => $this->t("Staleness threshold (seconds)"),
      "#description" => $this->t(
        'Jobs stuck in "processing" state longer than this will be reset by hook_cron. Default: 3600 (1 hour).',
      ),
      "#default_value" => $config->get("staleness_threshold") ?? 3600,
      "#min" => 60,
      "#required" => true,
    ];

    // --- Readiness polling ---
    $form["poll_max_attempts"] = [
      "#type" => "number",
      "#title" => $this->t("Readiness poll: max attempts"),
      "#description" => $this->t(
        "How many times to check whether an eager transformation is ready. Default: 10.",
      ),
      "#default_value" => $config->get("poll_max_attempts") ?? 10,
      "#min" => 1,
      "#required" => true,
    ];

    $form["poll_interval_seconds"] = [
      "#type" => "number",
      "#title" => $this->t("Readiness poll: base interval (seconds)"),
      "#description" => $this->t(
        "Base sleep interval between readiness checks; doubles each attempt (exponential backoff). Default: 5.",
      ),
      "#default_value" => $config->get("poll_interval_seconds") ?? 5,
      "#min" => 1,
      "#required" => true,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback to rebuild the plugin configuration sub-form.
   */
  public function ajaxRebuildForm(
    array &$form,
    FormStateInterface $form_state,
  ): array {
    return $form["plugin_configuration"];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    parent::validateForm($form, $form_state);

    $plugin_id = $form_state->getValue("converter_plugin");
    if (!empty($plugin_id)) {
      try {
        $plugin_config =
          $this->config("responsive_video.settings")->get(
            "converter_plugin_configuration",
          ) ?? [];
        $plugin = $this->pluginManager->createInstance(
          $plugin_id,
          $plugin_config,
        );
        $plugin->validateConfigurationForm($form, $form_state);
      } catch (\Exception) {
        $form_state->setError(
          $form["converter_plugin"],
          $this->t("Invalid plugin selected."),
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $plugin_id = $form_state->getValue("converter_plugin");

    if (!empty($plugin_id)) {
      try {
        $plugin_config =
          $this->config("responsive_video.settings")->get(
            "converter_plugin_configuration",
          ) ?? [];
        $plugin = $this->pluginManager->createInstance(
          $plugin_id,
          $plugin_config,
        );
        $plugin->submitConfigurationForm($form, $form_state);
      } catch (\Exception $e) {
        $this->logger("responsive_video")->error(
          "Plugin submit error: @message",
          ["@message" => $e->getMessage()],
        );
      }
    }

    $this->config("responsive_video.settings")
      ->set("converter_plugin", $plugin_id)
      ->set(
        "converter_plugin_configuration",
        $form_state->getValue(["settings"]) ?? [],
      )
      ->set("output_directory", $form_state->getValue("output_directory"))
      ->set(
        "staleness_threshold",
        (int) $form_state->getValue("staleness_threshold"),
      )
      ->set(
        "poll_max_attempts",
        (int) $form_state->getValue("poll_max_attempts"),
      )
      ->set(
        "poll_interval_seconds",
        (int) $form_state->getValue("poll_interval_seconds"),
      )
      ->save();

    parent::submitForm($form, $form_state);
  }
}
