<?php

namespace Drupal\facilitator_display\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

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

    $form['display_url_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Display URL'),
      '#open' => TRUE,
      '#description' => $this->t('Use the following URL to show the facilitator display on a screen.'),
    ];

    $code_word = $config->get('code_word') ?: '[code_word]';
    $url = Url::fromRoute('facilitator_display.display_page', ['code_word' => $code_word], ['absolute' => TRUE])->toString();

    $form['display_url_details']['display_url'] = [
      '#type' => 'item',
      '#markup' => $this->t('<code>@url</code>', ['@url' => $url]),
      '#prefix' => '<div id="display-url-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['code_word'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Code Word'),
      '#description' => $this->t('A secret code word to include in the URL. If left blank, no code word is required.'),
      '#default_value' => $config->get('code_word'),
      '#ajax' => [
        'callback' => '::updateDisplayUrl',
        'wrapper' => 'display-url-wrapper',
        'event' => 'keyup',
      ],
    ];

    $form['presence_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Presence Timeout'),
      '#description' => $this->t('The time in seconds that a facilitator is considered "present" after a door scan. Defaults to 14400 (4 hours).'),
      '#default_value' => $config->get('presence_timeout') ?: 14400,
      '#min' => 0,
    ];

    $form['refresh_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Refresh Interval'),
      '#description' => $this->t('The refresh interval for the display in seconds.'),
      '#default_value' => $config->get('refresh_interval') ?: 30,
      '#min' => 1,
    ];

    $form['background_image_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Background Image URL'),
      '#description' => $this->t('Optional: Enter a full URL for a background image for the display page.'),
      '#default_value' => $config->get('background_image_url'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * AJAX callback to update the display URL.
   */
  public function updateDisplayUrl(array &$form, FormStateInterface $form_state) {
    $code_word = $form_state->getValue('code_word') ?: '[code_word]';
    $url = Url::fromRoute('facilitator_display.display_page', ['code_word' => $code_word], ['absolute' => TRUE])->toString();
    $form['display_url_details']['display_url']['#markup'] = $this->t('<code>@url</code>', ['@url' => $url]);
    return $form['display_url_details']['display_url'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('facilitator_display.settings')
      ->set('code_word', $form_state->getValue('code_word'))
      ->set('presence_timeout', $form_state->getValue('presence_timeout'))
      ->set('refresh_interval', $form_state->getValue('refresh_interval'))
      ->set('background_image_url', $form_state->getValue('background_image_url'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}