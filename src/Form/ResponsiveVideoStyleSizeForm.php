<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\responsive_video\Entity\ResponsiveVideoStyleSize;

/**
 * Responsive Video Style Size form.
 */
final class ResponsiveVideoStyleSizeForm extends EntityForm {

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
        'exists' => [ResponsiveVideoStyleSize::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $videoStyleOptions = array_map(fn($videoStyle) => $videoStyle->label(), $this->entityTypeManager->getStorage('video_style')->loadMultiple());
    $form['videoStyle'] = [
      '#type' => 'select',
      '#title' => $this->t('Video Style'),
      '#options' => $videoStyleOptions,
      '#default_value' => $this->entity->get('videoStyle'),
      '#required' => TRUE,
    ];

    $responsiveVideoStyleOptions = array_map(fn($videoStyle) => $videoStyle->label(), $this->entityTypeManager->getStorage('responsive_video_style')->loadMultiple());
    $form['responsiveVideoStyle'] = [
      '#type' => 'select',
      '#title' => $this->t('Responsive Video Style'),
      '#options' => $responsiveVideoStyleOptions,
      '#default_value' => $this->entity->get('responsiveVideoStyle'),
      '#required' => TRUE,
    ];

    $form['breakpoint'] = [
      '#type' => 'number',
      '#title' => $this->t('"min-width" breakpoint in px'),
      '#default_value' => $this->entity->get('breakpoint'),
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
