<?php

namespace Drupal\simple_sitemap_image\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * SimpleSitemapImageSettingsForm class.
 */
class SimpleSitemapImageSettingsForm extends ConfigFormBase {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundle;

  /**
   * The translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $translationManager;

  /**
   * Constructor class.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The Drupal config factory service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entity_type_bundle
   *   Entity type bundle info.
   * @param \Drupal\Core\StringTranslation\TranslationManager $translation_manager
   *   The translation manager.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityTypeBundleInfo $entity_type_bundle,
    TranslationManager $translation_manager
  ) {
    parent::__construct($config_factory);
    $this->entityTypeBundle = $entity_type_bundle;
    $this->translationManager = $translation_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.bundle.info'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simple_sitemap_image_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames(): array {
    return ['simple_sitemap_image.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('simple_sitemap_image.settings');
    $bundles = $this->entityTypeBundle->getBundleInfo('node');

    foreach ($bundles as $bundle_id => $bundle) {
      $options[$bundle_id] = $bundle['label'];
    }

    $form['message'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->translationManager->translate('Select the content types to create image sitemap.'),
    ];

    $form['bundles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->translationManager->translate('Content types'),
      '#options' => $options,
      '#default_value' => !empty($config->get('bundles')) ? $config->get('bundles') : [],
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('simple_sitemap_image.settings');
    $values = $form_state->getValue('bundles');

    foreach ($values as $key => $value) {
      if ($value !== 0) {
        $bundles_selected[$key] = $value;
      }
    }

    $config->set('bundles', $bundles_selected);
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
