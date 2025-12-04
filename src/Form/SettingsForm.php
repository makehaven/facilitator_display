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

    $default_css = $config->get('custom_css') ?: "
      body { font-family: sans-serif; background-color: #f0f0f0; }
      .facilitator-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; padding: 20px; }
      .facilitator-card { background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
      .facilitator-card.present { border-left: 5px solid #4CAF50; }
      .facilitator-photo { width: 100%; height: 200px; object-fit: cover; }
      .facilitator-info { padding: 15px; }
      .facilitator-name { font-size: 1.2em; font-weight: bold; }
      .facilitator-focus, .facilitator-schedule, .facilitator-status { margin-top: 10px; }
    ";

    $form['custom_css'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom CSS'),
      '#description' => $this->t('Add custom CSS to style the display page.'),
      '#default_value' => $default_css,
      '#rows' => 10,
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
      ->set('custom_css', $form_state->getValue('custom_css'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}