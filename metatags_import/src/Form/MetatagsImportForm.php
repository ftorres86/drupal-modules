<?php

namespace Drupal\metatags_import\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGenerator;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Form class to import metatags.
 */
class MetatagsImportForm extends FormBase {

  /**
   * Config factory definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Entity type bundle definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundle;

  /**
   * Entity field manager definition.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Entity type manager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * File system definition.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Language manager definition.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The File URL Generator Service.
   *
   * @var Drupal\Core\File\FileUrlGenerator
   */
  protected $fileUrlGenerator;

  /**
   * Constructor class.
   *
   * @param Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Instance of config factory service.
   * @param Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Instance of entity field service.
   * @param Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle
   *   Instance of entity type bundle service.
   * @param Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Instance of entity type manager service.
   * @param Drupal\Core\File\FileSystemInterface $file_system
   *   Instance of file system service.
   * @param Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Instance of language manager service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\File\FileUrlGenerator $file_url_generator
   *   File Url Generator.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeBundleInfoInterface $entity_type_bundle,
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system,
    LanguageManagerInterface $language_manager,
    MessengerInterface $messenger,
    FileUrlGenerator $file_url_generator
  ) {
    $this->configFactory = $config_factory;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundle = $entity_type_bundle;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
    $this->languageManager = $language_manager;
    $this->messenger = $messenger;
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('language_manager'),
      $container->get('messenger'),
      $container->get('file_url_generator')
    );
  }

  /**
   * Gets field definitions of a entity type.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle
   *   Entity bundle.
   */
  protected function getFieldDefinitions($entity_type, $bundle) {
    return array_filter(
      $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle),
      function ($field_definition) {
        return $field_definition instanceof FieldConfigInterface;
      }
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'metatags_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $bundles = $this->entityTypeBundle->getBundleInfo('node');
    $languages = $this->languageManager->getLanguages();
    $bundle_options = ['all' => $this->t('All')];
    $language_options = ['all' => $this->t('All')];

    foreach ($bundles as $key => $bundle) {
      $bundle_options[$key] = $bundle['label'];
    }

    foreach ($languages as $key => $language) {
      $language_options[$key] = $language->getName();
    }

    $form['example'] = [
      '#open' => FALSE,
      '#type' => 'details',
      '#title' => $this->t('File Example'),
    ];

    $form['example']['bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Content types'),
      '#options' => $bundle_options,
      '#require' => TRUE,
      '#attributes' => ['class' => ['bundle-list']],
      '#ajax' => [
        'callback' => '::updateLink',
        'wrapper' => 'link-container',
      ],
    ];

    $form['example']['language'] = [
      '#type' => 'select',
      '#title' => $this->t('languages'),
      '#options' => $language_options,
      '#require' => TRUE,
      '#attributes' => ['class' => ['language-list']],
      '#ajax' => [
        'callback' => '::updateLink',
        'wrapper' => 'link-container',
      ],
    ];

    $bundle = !empty($form_state->getValue('bundle'))
      ? $form_state->getValue('bundle')
      : array_key_first($bundle_options);

    $langcode = !empty($form_state->getValue('language'))
      ? $form_state->getValue('language')
      : array_key_first($language_options);

    $form['example']['file_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Download file'),
      '#url' => Url::fromRoute(
        'metatags_import.download',
        [
          'bundle' => $bundle,
          'language' => $langcode,
        ]
      ),
      '#attributes' => ['class' => ['file-link', 'use-ajax']],
      '#prefix' => '<div id="link-container">',
      '#suffix' => '</div>',
    ];

    $form['example']['message'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Use <em>Download file</em> link to get an example csv file'),
    ];

    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File'),
      '#description' => $this->t('Format csv.'),
      '#upload_location' => 'public://metatags_import/',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
    ];

    return $form;
  }

  /**
   * Ajax callback.
   *
   * @param array $form
   *   Array form with all fields.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Instance form state interface.
   *
   * @return array
   *   Form element.
   */
  public function updateLink(array &$form, FormStateInterface $form_state) {
    return $form['example']['file_link'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($file_id = reset($form_state->getValue('file'))) {
      $data_file = $this->getFileValue($file_id);
    }

    if (empty($data_file)) {
      return;
    }

    $headers = array_shift($data_file);

    $batch = [
      'title' => $this->t('Updating ...'),
      'operations' => [],
      'finished' => [get_class($this), 'finishedCallback'],
    ];

    foreach ($data_file as $data) {
      if (!empty($data)) {
        $batch['operations'][] = [
          [
            get_class($this),
            'updateEntityContent',
          ],
          [
            $headers,
            $data,
            $file_id,
          ],
        ];
      }
    }

    batch_set($batch);
  }

  /**
   * Updates the entities with metatags information.
   *
   * @param mixed $headers
   *   Headers of file.
   * @param mixed $data
   *   Data of each row.
   * @param mixed $file_id
   *   File ID.
   * @param mixed $context
   *   Context array.
   */
  public static function updateEntityContent($headers, $data, $file_id, &$context) {

    $context['results']['file_id'] = $file_id;

    foreach ($headers as $key => $item) {
      if ($item === 'entity_id' || $item === 'lang_code') {
        ${$item} = $data[$key];
        unset($data[$key]);
        unset($headers[$key]);
      }
    }
    $entity = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->load($entity_id);

    if (!($entity instanceof EntityInterface)) {
      $context['results']['fail'][] = t('It was not possible update the entity with ID: <em>@id</em>', ['@id' => $entity_id]);
      return;
    }

    if (empty($lang_code) || !$entity->hasTranslation($lang_code)) {
      $context['results']['fail'][] = t(
        'It was not possible update the entity with ID: <em>@id</em> because this entity doesn"t have a translation for <em>@lang_code</em>', [
          '@id' => $entity_id,
          '@lang_code' => $lang_code,
        ]
      );
      return;
    }

    if ($lang_code !== \Drupal::languageManager()->getCurrentLanguage()->getId()) {
      $entity = $entity->getTranslation($lang_code);
    }

    $fields = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions(
        $entity->getEntityTypeId(),
        $entity->bundle()
      );

    foreach ($fields as $field_name => $field) {
      if ($field->getType() === 'metatag') {
        $new_metatags = array_combine($headers, $data);
        $current_metatags = [];

        if (!$entity->{$field_name}->isEmpty()) {
          $current_metatags = unserialize($entity->{$field_name}->value);
        }

        $entity->set($field_name, serialize(array_replace($current_metatags, $new_metatags)));
        $entity->save();

        $context['results']['success'][] = t('Meta tags values were updated for <em>@label</em> entity', ['@label' => $entity->label()]);
        break;
      }
    }
  }

  /**
   * Finishes the batch proccess.
   *
   * @param mixed $success
   *   Callback's status.
   * @param mixed $results
   *   Callback's results.
   * @param mixed $operations
   *   List of operations.
   */
  public static function finishedCallback($success, $results, $operations) {

    foreach ($results as $key => $messages) {
      $message_type = $key === 'success' ? 'status' : 'warning';

      foreach ($messages as $message) {
        \Drupal::messenger()->addMessage($message, $message_type);
      }
    }

    if (!empty($results['file_id'])) {
      $file = \Drupal::entityTypeManager()
        ->getStorage('file')
        ->load($results['file_id']);

      $file->delete();
    }
  }

  /**
   * Get csv content.
   *
   * @param mixed $file_id
   *   List of file ids.
   *
   * @return array
   *   Payload.
   */
  public function getFileValue($file_id) {
    $file_entity = $this->entityTypeManager
      ->getStorage('file')
      ->load($file_id);

    $real_path = $this->fileSystem
      ->realpath($file_entity->get('uri')->value);

    $file = fopen($real_path, "r");
    $data_file = [];

    while (!feof($file)) {
      $data_file[] = fgetcsv($file);
    }

    fclose($file);

    return $data_file;
  }

  /**
   * Creates and gets file.
   *
   * @param string $bundle
   *   Entity bundle.
   * @param string $language
   *   Language code.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Instance AjaxResponse object.
   */
  public function getFile($bundle, $language) {
    $response = new AjaxResponse();
    $bundle_list = [$bundle];
    $languages = [$language];

    $header = [
      'entity_id',
      'lang_code',
      'title',
      'description',
      'abstract',
      'keywords',
      'canonical_url',
      'robots',
      'image_src',
      'og_determiner',
      'og_site_name',
      'og_type',
      'og_url',
      'og_title',
      'og_description',
      'og_image',
      'og_video',
      'og_image_url',
      'og_image_secure_url',
      'og_video_secure_url',
      'og_image_type',
      'og_video_type',
      'og_image_width',
      'og_video_width',
      'og_image_height',
      'og_video_height',
      'og_image_alt',
      'og_updated_time',
      'og_video_duration',
      'twitter_cards_type',
      'twitter_cards_description',
      'twitter_cards_site',
      'twitter_cards_title',
      'twitter_cards_site_id',
      'twitter_cards_creator',
      'twitter_cards_creator_id',
      'twitter_cards_donottrack',
      'twitter_cards_page_url',
      'twitter_cards_image',
      'twitter_cards_image_alt',
      'twitter_cards_image_height',
      'twitter_cards_image_width',
      'twitter_cards_gallery_image0',
      'twitter_cards_gallery_image1',
      'twitter_cards_gallery_image2',
      'twitter_cards_gallery_image3',
    ];

    $strings = [
      'title',
      'description',
      'keywords',
      'robots',
      'og_title',
      'og_description',
      'og_image_alt',
      'twitter_cards_title',
      'twitter_cards_description',
      'twitter_cards_image_alt',
    ];

    $array_default = array_fill_keys($header, '');

    $output = implode(',', $header) . PHP_EOL;

    if ($bundle === 'all') {
      foreach ($this->entityTypeBundle->getBundleInfo('node') as $key => $bundle_item) {
        $bundle_list[] = $bundle_item['label'];
      }
    }

    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['type' => $bundle_list]);

    foreach ($entities as $entity) {
      $fields = $this->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
      $metatag_field_name = $this->getMetatagField($entity->getEntityTypeId(), $entity->bundle());

      if (!$metatag_field_name) {
        continue;
      }

      if ($language === 'all') {
        $languages = array_keys($entity->getTranslationLanguages());
      }

      foreach ($languages as $language) {
        if ($language !== $this->languageManager->getCurrentLanguage()->getId() && $entity->hasTranslation($langcode)) {
          $entity = $entity->getTranslation($language);
        }

        $data = [
          'entity_id' => $entity->id(),
          'lang_code' => $language,
          'title' => '"' . $entity->label() . '"',
          'description' => '',
          'canonical_url' => $entity->toUrl()->toString(),
        ];

        if (!$entity->{$metatag_field_name}->isEmpty()) {
          $data = array_replace($data, unserialize($entity->{$metatag_field_name}->value));

          foreach ($data as $key => $value) {
            if (in_array($key, $strings)) {
              $value = preg_replace('/\r|\n/', '', $value);
              $data[$key] = '"' . $value . '"';
            }
          }
        }

        $output .= implode(',', array_replace($array_default, $data)) . PHP_EOL;
      }
    }

    $path = $this->fileSystem
      ->realpath(
        $this->configFactory
          ->get('system.file')
          ->get('default_scheme') . "://"
      ) . '/metatags_import';

    if (!is_dir($path)) {
      $this->fileSystem->mkdir($path);
    }

    $name = '/metatags-' . strtotime('now') . '.csv';

    $file = $this->fileSystem
      ->saveData(
        $output,
        $path . $name,
        FileSystemInterface::EXISTS_REPLACE
      );

    $response->addCommand(
      new RedirectCommand(
        $this->fileUrlGenerator->generateAbsoluteString(
          $this->configFactory->get('system.file')
            ->get('default_scheme') . '://metatags_import/' . $name
        )
      )
    );

    return $response;
  }

  /**
   * Gets field name metatag.
   *
   * @param string $entity_type_id
   *   Entity type id.
   * @param string $bundle
   *   Entity bundle.
   *
   * @return string
   *   Return field name.
   */
  public function getMetatagField(string $entity_type_id, string $bundle) {
    $fields = $this->getFieldDefinitions($entity_type_id, $bundle);

    foreach ($fields as $field_name => $field) {
      if ($field->getType() === 'metatag') {
        return $field_name;
      }
    }

    return FALSE;
  }

}
