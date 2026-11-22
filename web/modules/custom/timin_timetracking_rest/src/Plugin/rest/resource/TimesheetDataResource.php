<?php

namespace Drupal\timin_timetracking_rest\Plugin\rest\resource;

use Drupal\Core\Database\Connection;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the timesheet data resource.
 *
 * Optional query filters (all combined with AND):
 * - date_from: YYYY-MM-DD, inclusive.
 * - date_to: YYYY-MM-DD, inclusive.
 * - project_id: one ID or a comma-separated list, e.g. 3,7,12.
 * - project_abbr: one exact abbreviation or a comma-separated list.
 * Invalid filter values return an empty list.
 *
 * @RestResource(
 *   id = "timesheet_data_resource",
 *   label = @Translation("Timesheet Data Resource"),
 *   uri_paths = {
 *     "canonical" = "/timesheet_data"
 *   }
 * )
 */
class TimesheetDataResource extends ResourceBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a TimesheetDataResource object.
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
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request, used for the query filters.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The timesheet data rows.
   */
  public function get(Request $request) {
    $data = [];

    $query = $this->database->select('timesheet_data', 'td');
    $query->leftJoin('calendar', 'c', 'td.date = c.date');
    $query->leftJoin('employee', 'e', 'td.employee_id = e.id');
    $query->leftJoin('project', 'p', 'td.project_id = p.id');
    $query->leftJoin('project__field_abbreviation', 'pfa', 'p.id = pfa.entity_id');
    $query->leftJoin('project__field_status2', 'pfs', 'p.id = pfs.entity_id');
    $query->leftJoin('paragraph__field_name', 'pfn', 'td.service_type_id = pfn.entity_id');
    $query->fields('td', ['timesheet_id', 'service_type_id', 'employee_id', 'date', 'project_id', 'time']);
    $query->fields('c', ['year', 'month_text', 'week_text', 'weekday_text']);
    $query->addField('e', 'name', 'employee_name');
    $query->addField('p', 'name', 'project_name');
    $query->addField('pfa', 'field_abbreviation_value', 'project_abbr');
    $query->addField('pfs', 'field_status2_value', 'project_status');
    $query->addField('pfn', 'field_name_value', 'service_type_name');

    if ($this->applyFilters($query, $request)) {
      foreach ($query->execute()->fetchAll() as $value) {
        $data[] = [
          'timesheet_id' => $value->timesheet_id,
          'project_id' => $value->project_id,
          'project_name' => $value->project_name,
          'project_abbr' => $value->project_abbr,
          'project_status' => $value->project_status,
          'date' => $value->date,
          'service_type_id' => $value->service_type_id,
          'service_type_name' => $value->service_type_name,
          'employee_id' => $value->employee_id,
          'employee_name' => $value->employee_name,
          'year' => $value->year,
          'month_text' => $value->month_text,
          'week_text' => $value->week_text,
          'weekday_text' => $value->weekday_text,
          'time' => $value->time,
        ];
      }
    }

    $response = new ResourceResponse($data);
    // Always build a fresh result.
    $response->getCacheableMetadata()->setCacheMaxAge(0);
    return $response;
  }

  /**
   * Adds the query string filters to the query.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The timesheet data query.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return bool
   *   FALSE if a filter value is invalid, so the result must be empty.
   */
  protected function applyFilters($query, Request $request) {
    foreach (['date_from' => '>=', 'date_to' => '<='] as $param => $operator) {
      $date = (string) $request->query->get($param, '');
      if ($date === '') {
        continue;
      }
      $parsed = \DateTime::createFromFormat('!Y-m-d', $date);
      if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return FALSE;
      }
      $query->condition('td.date', $date, $operator);
    }

    $project_ids = (string) $request->query->get('project_id', '');
    if ($project_ids !== '') {
      $ids = array_map('trim', explode(',', $project_ids));
      foreach ($ids as $id) {
        if (!ctype_digit($id)) {
          return FALSE;
        }
      }
      $query->condition('td.project_id', $ids, 'IN');
    }

    $project_abbr = (string) $request->query->get('project_abbr', '');
    if ($project_abbr !== '') {
      $abbrs = array_map('trim', explode(',', $project_abbr));
      $query->condition('pfa.field_abbreviation_value', $abbrs, 'IN');
    }

    return TRUE;
  }

}
