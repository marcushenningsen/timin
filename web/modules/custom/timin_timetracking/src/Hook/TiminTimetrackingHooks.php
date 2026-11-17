<?php

declare(strict_types=1);

namespace Drupal\timin_timetracking\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\timin_timetracking\TimesheetTableBuilder;

/**
 * Entity hooks for timin_timetracking.
 *
 * Replaces the procedural timin_timetracking_project_view() and
 * timin_timetracking_timesheet_delete(). Remove both from the .module file,
 * otherwise they run twice.
 */
final class TiminTimetrackingHooks {

  public function __construct(
    private readonly TimesheetTableBuilder $tableBuilder,
    private readonly Connection $database,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_view() for project entities.
   */
  #[Hook('project_view')]
  public function projectView(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display, string $view_mode): void {
    $build['timesheets'] = $this->tableBuilder->buildForProject($entity);
  }

  /**
   * Implements hook_ENTITY_TYPE_delete() for timesheet entities.
   */
  #[Hook('timesheet_delete')]
  public function timesheetDelete(EntityInterface $entity): void {
    $this->database->delete('timesheet_works')
      ->condition('timesheet_id', $entity->id())
      ->execute();
    $this->database->delete('timesheet_data')
      ->condition('timesheet_id', $entity->id())
      ->execute();
  }

}
