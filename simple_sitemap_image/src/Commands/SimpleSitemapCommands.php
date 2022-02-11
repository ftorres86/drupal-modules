<?php

// @codingStandardsIgnoreStart
namespace Drupal\simple_sitemap_image\Commands;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\simple_sitemap_image\SimpleSitemapImageManager;
use Drush\Commands\DrushCommands;

/**
 * SimpleSitemapImageCommands command class.
 */
class SimpleSitemapImageCommands extends DrushCommands {

  /**
   * Config name.
   */
  const SIMPLE_SITEMAP_IMAGE_SETTINGS = 'simple_sitemap_image.settings';

  /**
   * \Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var ConfigFactoryInterface.
   */
  protected $configFactory;

  /**
   * \Drupal\Core\Entity\EntityFieldManagerInterface definition.
   *
   * @var EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * \Drupal\simple_sitemap_image\SimpleSitemapImageManager definition.
   *
   * @var SocialMediaFeedsBuilder.
   */
  protected $simpleSitemapImageManager;

  /**
   * Constructor class.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param SimpleSitemapImageManager $simple_sitemap
   * @param ConfigFactoryInterface $config_factory
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, SimpleSitemapImageManager $simple_sitemap) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->simpleSitemapImageManager = $simple_sitemap;
  }

  /**
   * Drush command to rebuild image sitemap.xml file.
   *
   * @command simple_sitemap:rebuild
   * @aliases gsm_rebuild
   * @usage simple_sitemap:rebuild
   */
  public function rebuild() {
    $bundles = $this->configFactory->get(self::SIMPLE_SITEMAP_IMAGE_SETTINGS)
      ->get('bundles');

    if (empty($bundles)) {
      $this->output()->writeln('There isn\'t content type selected');
      return;
    }

    $entities = $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties(['type' => $bundles]);

    if (empty($entities)) {
      $this->output()->writeln('There isn\'t content created');
      return;
    }

    foreach ($entities as $entity) {
      $url = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
      $images_url[$url] = $this->simpleSitemapImageManager->entityMapping($entity);
    }

    $file = $this->simpleSitemapImageManager->fileCreate($images_url);

    if ($file) {
      $this->output()->writeln('Process finished successfully.');
    }
  }

}
// @codingStandardsIgnoreEnd
