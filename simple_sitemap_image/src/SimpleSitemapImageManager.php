<?php

namespace Drupal\simple_sitemap_image;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\FieldConfigInterface;

/**
 * Class SimpleSitemapImageManager to create the sitemap.
 *
 * @package Drupal\simple_sitemap_image
 */
class SimpleSitemapImageManager {

  /**
   * List field types references.
   */
  const FIELD_TYPES_REFERENCES = [
    'image',
    'entity_reference',
    'entity_reference_revisions',
  ];

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructor class.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(
    EntityFieldManagerInterface $entity_field_manager,
    EntityTypeManagerInterface $entity_type_manager,
    FileSystemInterface $file_system
  ) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileSystem = $file_system;
  }

  /**
   * Get field definitions of a entity type.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle
   *   Bundle.
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
   * Gets all images of each entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Current entity.
   *
   * @return mixed
   *   All images.
   */
  public function entityMapping(EntityInterface $entity) {
    $images_url = [];

    if ($entity->getEntityTypeId() === 'file') {
      $images_url[] = file_create_url($entity->uri->value);
      return $images_url;
    }

    $fields = $this->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    if (empty($fields)) {
      return [];
    }

    foreach ($fields as $field_name => $field) {
      $field_type = $field->getType();

      if (in_array($field_type, self::FIELD_TYPES_REFERENCES)) {
        $sub_entities = $entity->get($field_name)->referencedEntities();

        foreach ($sub_entities as $subEntity) {
          $images_url = array_merge($images_url, $this->entityMapping($subEntity));
        }
      }
      elseif ($field_type === 'block_field') {
        $blocks_id = $entity->get($field_name)->getValue();
        $images_url = array_merge($images_url, $this->blockLoad($blocks_id));
      }
    }

    return $images_url;
  }

  /**
   * Loads block entity.
   *
   * @param mixed $blocks_id
   *   List of block ids.
   */
  public function blockLoad($blocks_id = []) {
    $images_url = [];

    foreach ($blocks_id as $bid) {
      if (strpos($bid['plugin_id'], 'block_content') !== FALSE) {
        $blockEntity = $this->entityTypeManager
          ->getStorage('block_content')
          ->loadByProperties([
            'uuid' => str_replace('block_content:', '', $bid['plugin_id']),
          ]);

        if (!empty($blockEntity)) {
          $images_url = array_merge($images_url, $this->entityMapping(reset($blockEntity)));
        }
      }
    }

    return $images_url;
  }

  /**
   * Creates image sitemap file.
   *
   * @param array $images_urls
   *   List of urls.
   */
  public function fileCreate(array $images_urls) {
    if (empty($images_urls)) {
      return;
    }

    $output = '<?xml version="1.0" encoding="UTF-8"?>';
    $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
      xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';

    foreach ($images_urls as $path => $entity_images) {
      $output .= '<url>';
      $output .= '<loc>' . $path . '</loc>';

      foreach ($entity_images as $image) {
        $output .= '<image:image>';
        $output .= '<image:loc>' . $image . '</image:loc>';
        $output .= '</image:image>';
      }
      $output .= '</url>';
    }

    $output .= '</urlset>';

    $path = $this->fileSystem->realpath(DRUPAL_ROOT);

    if (!is_dir($path)) {
      $this->fileSystem->mkdir($path);
    }

    // @codingStandardsIgnoreStart
    $file = $this->fileSystem
      ->saveData(
        $output,
$path . '/' . 'image_sitemap.xml',
        FileSystemInterface::EXISTS_REPLACE
      );
    // @codingStandardsIgnoreEnd

    return is_file($file);
  }

}
