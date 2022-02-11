<?php

namespace Drupal\simple_sitemap_render\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\simple_sitemap_render\Controller\OverrideSimplesitemapController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Class alter the controller routes sitemap and sitemap xsl format.
 *
 * @package Drupal\simple_sitemap_render\Routing
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $routeXsl = $collection->get('simple_sitemap.sitemap_xsl');

    if ($routeXsl) {
      $routeXsl->setDefault(
        '_controller',
        OverrideSimplesitemapController::class . '::getSitemapXsl'
      );
    }
  }

}
