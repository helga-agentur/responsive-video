<?php

declare(strict_types=1);

namespace Drupal\responsive_video\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\responsive_video\Entity\VideoFormat;

/**
 * Video Format form.
 */
final class VideoFormatForm extends EntityForm {

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
        'exists' => [VideoFormat::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['fileEnding'] = [
      '#type' => 'textfield',
      '#title' => $this->t('File ending'),
      '#default_value' => $this->entity->get('fileEnding'),
      '#required' => TRUE,
      '#description' => $this->t('File ending. Just one!'),
    ];

    $form['weight'] = [
      '#type' => 'number',
      '#title' => $this->t('Weight'),
      '#default_value' => $this->entity->get('weight'),
      '#min' => 0,
      '#max' => 100,
      '#step' => 1,
      '#description' => $this->t('The higher the weight, the <b>LOWER</b> the importance. (like a queue)'),
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
