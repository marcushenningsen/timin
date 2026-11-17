<?php

namespace Drupal\timin_timetracking_rest\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;

/**
 * Provides a custom resource
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
   * Responds to entity GET requests.
   * @return \Drupal\rest\ResourceResponse
   */
  public function get() {
    
    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.

    //$projects_entities = \Drupal::entityTypeManager()->getStorage('project')->loadByProperties(['status' => 1]);


    // generate and execute query
    $connection = \Drupal::service('database');

    $timesheet_data_query = $connection->select('timesheet_data', 'td');
		$timesheet_data_query->leftJoin('calendar', 'c', 'td.date = c.date');
		$timesheet_data_query->leftJoin('employee', 'e', 'td.employee_id = e.id');
		$timesheet_data_query->leftJoin('project', 'p', 'td.project_id = p.id');
		$timesheet_data_query->leftJoin('project__field_abbreviation', 'pfa', 'p.id = pfa.entity_id');
		$timesheet_data_query->leftJoin('project__field_status2', 'pfs', 'p.id = pfs.entity_id');
		$timesheet_data_query->leftJoin('paragraph__field_name', 'pfn', 'td.service_type_id = pfn.entity_id');
		$timesheet_data_query->fields('td',['timesheet_id','service_type_id','employee_id','date','project_id','time']);
		$timesheet_data_query->fields('c',['year','month_text','week_text','weekday_text']);
		$timesheet_data_query->addField('e','name','employee_name');
		$timesheet_data_query->addField('p','name','project_name');
		$timesheet_data_query->addField('pfa','field_abbreviation_value','project_abbr');
		$timesheet_data_query->addField('pfs','field_status2_value','project_status');
		$timesheet_data_query->addField('pfn','field_name_value','service_type_name');
    $timesheet_data = array();
		$timesheet_data = $timesheet_data_query->execute()->fetchAll();

		foreach ( $timesheet_data as $value ) {
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

    $response = new ResourceResponse($data);
    // In order to generate fresh result every time (without clearing 
    // the cache), you need to invalidate the cache.
    $response->addCacheableDependency($data);
    return $response;



//    $response = ['message' => 'Hello, this is a rest service'];
//    return new ResourceResponse($response);
  }
}