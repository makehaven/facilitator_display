<?php

namespace Drupal\facilitator_display\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure facilitator display settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'facilitator_display_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['facilitator_display.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('facilitator_display.settings');

    $form['code_word'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Code Word'),
      '#description' => $this->t('A secret code word to include in the URL for simple protection against scraping. If left blank, no code word is required.'),
      '#default_value' => $config->get('code_word'),
    ];

    $form['refresh_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Refresh Interval'),
      '#description' => $this->t('The refresh interval for the display in seconds.'),
      '#default_value' => $config->get('refresh_interval') ?: 30,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('facilitator_display.settings')
      ->set('code_word', $form_state->getValue('code_word'))
      ->set('refresh_interval', $form_state->getValue('refresh_interval'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}