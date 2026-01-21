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

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => [VideoFormat::class, 'load'],
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $this->entity->get('label'),
      '#required' => TRUE,
      '#description' => $this->t('Name of the video format.'),
    ];

    $form['format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Format'),
      '#default_value' => $this->entity->get('format'),
      '#required' => TRUE,
      '#description' => $this->t('The desired video format. Make sure your API supports this format. Example: <strong>mp4</strong>'),
    ];

    $form['mimeType'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Mime type'),
      '#default_value' => $this->entity->get('mimeType'),
      '#required' => TRUE,
      '#description' => $this->t('The mime type of the video, this will be printed in the video tag. Example: <strong>video/mp4</strong>'),
    ];

    $form['codec'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codec'),
      '#default_value' => $this->entity->get('codec'),
      '#required' => TRUE,
      '#description' => $this->t('Specify the codec of the video format. Example: <strong>av1</strong>'),
    ];

    $form['codecLong'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Codec Long'),
      '#default_value' => $this->entity->get('codec_long'),
      '#required' => TRUE,
      '#description' => $this->t('Specify the codec in a long version of the video format. This will be printed in the video tag! Example: <strong>av01.0.08M.08</strong>'),
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
