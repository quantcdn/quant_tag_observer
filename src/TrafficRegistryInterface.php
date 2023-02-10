<?php

namespace Drupal\quant_tag_observer;

/**
 * Describes the traffic registry for URLS and tags.
 */
interface TrafficRegistryInterface {

  /**
   * Register a new URL or path with its associated cache tags.
   *
   * @param string $url
   *   The URL to register.
   * @param string[] $tags
   *   List of tags to associate with the URL.
   *
   * @throws \LogicException
   *   Thrown when $tags is empty.
   */
  public function add($url, array $tags);

  /**
   * Remove a URL from the registry.
   *
   * @param string $url
   *   The url to remove from the registry.
   */
  public function remove($url);

  /**
   * Clear the registry.
   */
  public function clear();

  /**
   * Return a list of paths that match the given tags.
   *
   * @param string[] $tags
   *   List of tags that are associated with URLs.
   *
   * @return string[]
   *   List of paths that match tags.
   */
  public function getPaths(array $tags);


  /**
   * Return a list of related paths from a given URL.
   *
   * @param string $url
   *   A URL to find tag relations for.
   *
   * @return string[]
   *   List of URLs.
   */
  public function getRelatedPaths($url);

}
