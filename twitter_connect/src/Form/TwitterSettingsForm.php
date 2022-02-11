<?php

namespace Drupal\twitter_connect\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * TwitterSettingsForm config form class.
 */
class TwitterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'twitter_connect_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['twitter_connect.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('twitter_connect.settings');
    $form['twitter_access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access token'),
      '#default_value' => $config->get('twitter_access_token'),
      '#required' => TRUE,
    ];
    $form['twitter_access_token_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access token secret'),
      '#default_value' => $config->get('twitter_access_token_secret'),
      '#required' => TRUE,
    ];
    $form['twitter_consumer_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer key'),
      '#default_value' => $config->get('twitter_consumer_key'),
      '#required' => TRUE,
    ];
    $form['twitter_consumer_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer secret'),
      '#default_value' => $config->get('twitter_consumer_secret'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('twitter_connect.settings');
    $values = $form_state->getValues();

    foreach ($values as $key => $value) {
      if (strpos($key, 'twitter_') !== FALSE) {
        $config->set($key, $value);
      }
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
