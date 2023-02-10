<?php

namespace Drupal\quant_tag_observer;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Condition;

/**
 * The quant traffic registry.
 */
class TrafficRegistry implements TrafficRegistryInterface {

  /**
   * The active database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a quant traffic registry event.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The active database connection.
   */
  public function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function add($url, array $tags) {
    $tags = ';' . implode(';', $tags);
    $fields = ['url' => $url, 'tags' => $tags];

    $this->connection->merge('quant_observed_tags')
      ->insertFields($fields)
      ->updateFields($fields)
      ->key(['url' => $url])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function remove($url) {
    $this->connection->delete('quant_observed_tags')
      ->condition('url', $url)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function clear() {
    $this->connection->truncate('quant_observed_tags')->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getPaths(array $tags) {
    $urls = [];

    if (empty($tags)) {
      return $urls;
    }

    $or = new Condition('OR');
    foreach ($tags as $tag) {
      $condition = '%;' . $this->connection->escapeLike($tag) . ';%';
      $or->condition('tags', $condition, 'LIKE');
    }

    $results = $this->connection->select('quant_observed_tags', 'q')
      ->fields('q', ['url'])
      ->condition($or)
      ->execute();

    foreach ($results as $result) {
      $urls[] = $result->url;
    }

    return $urls;
  }

  /**
   * {@inheritdoc}
   */
  public function getRelatedPaths($url) {
    $results = $this->connection->select('quant_observed_tags', 'q')
      ->fields('q', ['tags'])
      ->execute();

    if (empty($results)) {
      return [];
    }

    $tag_list = [];
    foreach ($results as $result) {
      $tags = explode(';', $result->tags);
      $tag_list = [...$tag_list, ...$tags];
    }

    $tag_list = array_filter($tag_list);
    return $this->getPaths($tag_list);
  }

}
