<?php

namespace Drupal\timin_timetracking_rest\Plugin\rest\resource;

use Drupal\Core\Database\Connection;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list of all employees with id, name and status.
 *
 * Shares its path with the HTML employee list; REST answers only when a
 * format is requested, e.g. /employees?_format=json.
 *
 * @RestResource(
 *   id = "employee_resource",
 *   label = @Translation("Employee List Resource"),
 *   uri_paths = {
 *     "canonical" = "/employees"
 *   }
 * )
 */
class EmployeeResource extends ResourceBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs an EmployeeResource object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, Connection $database) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->database = $database;
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
      $container->get('logger.factory')->get('rest'),
      $container->get('database')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   All employees, sorted by name.
   */
  public function get() {
    $data = [];

    $query = $this->database->select('employee', 'e');
    $query->fields('e', ['id', 'name', 'status']);
    $query->orderBy('e.name');

    foreach ($query->execute()->fetchAll() as $value) {
      $data[] = [
        'id' => $value->id,
        'name' => $value->name,
        'status' => $value->status,
      ];
    }

    $response = new ResourceResponse($data);
    // Always build a fresh result.
    $response->getCacheableMetadata()->setCacheMaxAge(0);
    return $response;
  }

}
