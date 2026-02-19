<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\responsive_video\Entity\ResponsiveVideoStyle;

/**
 * Responsive Video Style form.
 */
final class ResponsiveVideoStyleForm extends EntityForm
{
  public function form(array $form, FormStateInterface $form_state): array
  {
    $form = parent::form($form, $form_state);

    $form["label"] = [
      "#type" => "textfield",
      "#title" => $this->t("Label"),
      "#maxlength" => 255,
      "#default_value" => $this->entity->label(),
      "#required" => true,
    ];

    $form["id"] = [
      "#type" => "machine_name",
      "#default_value" => $this->entity->id(),
      "#machine_name" => [
        "exists" => [ResponsiveVideoStyle::class, "load"],
      ],
      "#disabled" => !$this->entity->isNew(),
    ];

    $videoStyleOptions = $this->entityTypeManager
      ->getStorage("video_style")
      ->loadMultiple();
    $availableStyleNames = [];
    foreach ($videoStyleOptions as $id => $style) {
      if ($style->status()) {
        $availableStyleNames[$id] = $style->label();
      }
    }

    $form["videoStyles"] = [
      "#type" => "checkboxes",
      "#title" => $this->t("Video styles"),
      "#options" => $availableStyleNames,
      "#description" => $this->t(
        "Select the video sizes to generate for this breakpoint.",
      ),
      "#default_value" => $this->entity->getVideoStyles() ?? [],
      "#required" => true,
    ];

    $form["breakpoint"] = [
      "#type" => "number",
      "#title" => $this->t("Min-width breakpoint (px)"),
      "#default_value" => $this->entity->getBreakpoint(),
      "#description" => $this->t(
        "The minimum viewport width in pixels at which this set of video sizes applies.",
      ),
      "#min" => 0,
      "#required" => true,
    ];

    $form["status"] = [
      "#type" => "checkbox",
      "#title" => $this->t("Enabled"),
      "#default_value" => $this->entity->status(),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    parent::submitForm($form, $form_state);
    $this->entity->set("breakpoint", (int) $form_state->getValue("breakpoint"));
    $this->entity->set(
      "videoStyles",
      array_values(array_filter($form_state->getValue("videoStyles"))),
    );
  }

  public function save(array $form, FormStateInterface $form_state): int
  {
    $result = parent::save($form, $form_state);
    $message_args = ["%label" => $this->entity->label()];
    $this->messenger()->addStatus(
      match ($result) {
        \SAVED_NEW => $this->t(
          "Created responsive video style %label.",
          $message_args,
        ),
        \SAVED_UPDATED => $this->t(
          "Updated responsive video style %label.",
          $message_args,
        ),
      },
    );
    $form_state->setRedirectUrl($this->entity->toUrl("collection"));
    return $result;
  }
}
