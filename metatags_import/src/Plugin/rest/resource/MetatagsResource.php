<?php

namespace Drupal\metatags_import\Plugin\rest\resource;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Language\LanguageManager;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ModifiedResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "metatags",
 *   label = @Translation("Globant metatags"),
 *   uri_paths = {
 *     "canonical" = "/api/metatags/set",
 *     "https://www.drupal.org/link-relations/create" = "/api/metatags/set"
 *   }
 * )
 */
class MetatagsResource extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManager
   */
  protected $languageManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * Constructs a GlobantMetatagsResource instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   * @param Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManager $language
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user,
    EntityTypeManager $entity_type_manager,
    LanguageManager $language,
    EntityFieldManager $entity_field_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageManager = $language;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('cms_rest'),
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('language_manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * Get field definitions of a entity type.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle
   *   Bundle.
   *
   * @return mixed
   *   Field definitions of the bundle.
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
   * Returns a list of bundles for specified entity.
   *
   * @param mixed $data
   *   Current data.
   *
   * @return mixed
   *   Response object.
   */
  public function post($data) {
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $headers = array_keys($data);

    foreach ($headers as $item) {
      if ($item === 'entity_id' || $item === 'lang_code') {
        ${$item} = $data[$item];
        unset($data[$item]);
        unset($headers[$item]);
      }
    }

    $node = $this->entityTypeManager
      ->getStorage('node')
      ->load($entity_id);

    if ($lang_code !== $this->languageManager->getCurrentLanguage()->getId()) {
      $node = $node->getTranslation($lang_code);
    }

    $fields = $this->entityFieldManager
      ->getFieldDefinitions(
        $node->getEntityTypeId(),
        $node->bundle()
      );

    foreach ($fields as $field_name => $field) {
      if ($field->getType() === 'metatag') {
        $node->set($field_name, serialize($data));
        $node->save();
        break;
      }
    }

    return new ModifiedResourceResponse($node, 200);
  }

}
