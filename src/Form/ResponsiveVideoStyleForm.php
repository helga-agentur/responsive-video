<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\responsive_video\Entity\ResponsiveVideoStyle;

/**
 * Responsive Video Style form.
 */
final class ResponsiveVideoStyleForm extends EntityForm {



  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [ResponsiveVideoStyle::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $videoStyleOptions = $this->entityTypeManager->getStorage('video_style')->loadMultiple();
    $availableStyles = array_filter($videoStyleOptions, fn ($style) => $style->status() == 1);

    $availableStyleNames = [];
    foreach ($availableStyles as $id => $style) {
      $availableStyleNames[$id] = $style->label();
    }

    $form['videoStyles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Video styles'),
      '#options' => $availableStyleNames,
      '#description' => $this->t('Select one or more video styles.'),
      '#default_value' => $this->entity->get('videoStyles') ?? [],
      '#required' => TRUE,
    ];

    $form['breakpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Breakpoints'),
      '#default_value' => $this->entity->get('breakpoint'),
      '#description' => $this->t('Breakpoints'),
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];


    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);

    $this->entity->set('breakpoint', $form_state->getValue('breakpoint'));
    $this->entity->set('videoStyles', $form_state->getValue('videoStyles'));
  }



  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match($result) {
        \SAVED_NEW => $this->t('Created new example %label.', $message_args),
        \SAVED_UPDATED => $this->t('Updated example %label.', $message_args),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
