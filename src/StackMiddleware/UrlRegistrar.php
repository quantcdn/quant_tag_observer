<?php

namespace Drupal\quant_tag_observer\StackMiddleware;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\quant_tag_observer\TrafficRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Core\Routing\AdminContext;

/**
 * Collects URLs that Quant has requested.
 */
class UrlRegistrar implements HttpKernelInterface {

  /**
   * The HTTP kernel object.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * The traffic registry service.
   *
   * @var \Drupal\quant_tag_observer\TrafficRegistryInterface
   */
  protected $registry;

  /**
   * The configuration object for quant purger.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The route context.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $routeContext;

  /**
   * {@inheritdoc}
   */
  public function __construct(HttpKernelInterface $http_kernel, TrafficRegistryInterface $registry, ConfigFactoryInterface $config_factory, AdminContext $routeContext) {
    $this->httpKernel = $http_kernel;
    $this->registry = $registry;
    $this->config = $config_factory->get('quant_tag_observer.settings');
    $this->routeContext = $routeContext;
  }

  /**
   * Determine if we need to track this route.
   *
   * @return bool
   *   If the request can be cached.
   */
  public function determine(Request $request, Response $response) {
    if ($this->config->get('track_admin_routes') && $this->routeContext->isAdminRoute()) {
      return FALSE;
    }

    // Allow paths to be excluded from the traffic repository.
    $blocklist = $this->config->get('path_blocklist');
    $blocklist = array_filter($blocklist);

    if (is_array($blocklist)) {
      $path = $this->generateUrl($request);
      foreach ($blocklist as $needle) {
        if (@strpos($path, $needle) > -1) {
          return FALSE;
        }
      }
    }

    if (!is_a($response, CacheableResponseInterface::class)) {
      return FALSE;
    }

    // Don't gather responses that aren't going to be useful.
    if (!count($response->getCacheableMetadata()->getCacheTags())) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Generates a URL to register.
   *
   * @return string
   *   The URL to register.
   */
  protected function generateUrl(Request $request) {
    if (NULL !== $qs = $request->getQueryString()) {
      $qs = '?' . $qs;
    }
    $path = $request->getBaseUrl() . $request->getPathInfo() . $qs;
    return '/' . ltrim($path, '/');
  }

  protected function filterTags(Response $response) {
    $tag_blocklist = $this->config->get('tag_blocklist');
    $tags = $response->getCacheableMetadata()->getCacheTags();

    return array_filter($tags, function($tag) use ($tag_blocklist) {
      foreach ($tag_blocklist as $bl) {
        if (preg_match("/^$bl/", $tag)) {
          return FALSE;
        }
      }
      return TRUE;
    });
  }

  /**
   * {@inheritdoc}
   */
  public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = TRUE) {
    $response = $this->httpKernel->handle($request, $type, $catch);
    if ($this->determine($request, $response)) {
      $this->registry->add(
        $this->generateUrl($request),
        $this->filterTags($response)
      );
    }
    return $response;
  }

}
