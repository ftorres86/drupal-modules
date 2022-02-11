<?php

namespace Drupal\simple_sitemap_image\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * SimpleSitemapImageBatchForm form class.
 */
class SimpleSitemapImageBatchForm extends FormBase {

  /**
   * Config name.
   */
  const SIMPLE_SITEMAP_IMAGE_SETTINGS = 'simple_sitemap_image.settings';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationManager
   */
  protected $translationManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructor class.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationManager $translation_manager
   *   The translation manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    TranslationManager $translation_manager,
    ConfigFactoryInterface $config_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->translationManager = $translation_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('string_translation'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'simple_sitemap_image_batch_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->configFactory->get(self::SIMPLE_SITEMAP_IMAGE_SETTINGS);
    $bundles = $config->get('bundles');

    if (empty($bundles)) {
      return [
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->translationManager->translate('There isn\'t content type selected to create image sitemap.'),
        ],
      ];
    }

    $form['message'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->translationManager->translate('Use to recreate the image sitemap file.'),
    ];

    $form['bundles'] = [
      '#type' => 'hidden',
      '#value' => array_keys($bundles),
    ];

    $form['generate'] = [
      '#type' => 'submit',
      '#value' => $this->translationManager->translate('Generate'),
    ];

    $form['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->translationManager->translate('Cancel'),
      '#submit' => ['::cancelForm'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['type' => $form_state->getValue('bundles')]);

    $batch = [
      'title' => $this->translationManager->translate('Generating image sitemap ...'),
      'operations' => [],
      'finished' => [get_class($this), 'finishedCallback'],
    ];

    foreach ($entities as $entity) {
      $batch['operations'][] = [
        [get_class($this), 'getImageSitemapCallback'],
        [$entity],
      ];
    }

    batch_set($batch);
  }

  /**
   * Gets all images of each block assigned to parent entity.
   *
   * @param mixed $entity
   *   Entity.
   * @param mixed $context
   *   Current context.
   */
  public static function getImageSitemapCallback($entity, &$context) {
    $images_url = \Drupal::service('simple_sitemap_image.services')
      ->entityMapping($entity);

    if (!empty($images_url)) {
      $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
      $context['results']['success'][$url] = $images_url;
    }
    else {
      $context['results']['fails'][] = \Drupal::translation()
        ->translate('Error');
    }

  }

  /**
   * Calls fileCreate method to create sitemap file.
   *
   * @param mixed $success
   *   Indicate that the batch API tasks were all completed successfully.
   * @param mixed $results
   *   An array of all the results that were updated in update_do_one().
   * @param mixed $operations
   *   A list of the operations that had not been completed by the batch API.
   */
  public static function finishedCallback($success, $results, $operations) {
    $simpleSitemap = \Drupal::service('simple_sitemap_image.services');
    $translation = \Drupal::translation();
    $messenger = \Drupal::messenger();

    if (isset($results['success'])) {
      $file = $simpleSitemap->fileCreate($results['success']);

      if ($success && $file) {
        $message = $translation->translate('Process finished successfully.');
      }
      else {
        $message = $translation->translate('Process finished with errors.');
      }

      $messenger->addMessage($message);
    }
  }

}
