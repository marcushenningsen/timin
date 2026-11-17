<?php

declare(strict_types=1);

namespace Drupal\timin_timetracking;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\flag\FlagLinkBuilderInterface;
use Drupal\flag\FlagServiceInterface;

/**
 * Builds the DataTables timesheet tables (overview and per project).
 */
final class TimesheetTableBuilder {

  use StringTranslationTrait;

  private const FLAG_ID = 'flag';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly FlagServiceInterface $flagService,
    private readonly FlagLinkBuilderInterface $flagLinkBuilder,
  ) {}

  /**
   * Table of all timesheets of active projects, with selection and printing.
   */
  public function buildOverview(): array {
    $ids = $this->entityTypeManager->getStorage('timesheet')->getQuery()
      ->condition('field_project.entity:project.field_status2', 1)
      ->accessCheck(TRUE)
      ->execute();

    return $this->build($ids, TRUE);
  }

  /**
   * Table of all timesheets of one project.
   */
  public function buildForProject(EntityInterface $project): array {
    $ids = $this->entityTypeManager->getStorage('timesheet')->getQuery()
      ->condition('field_project', $project->id())
      ->accessCheck(TRUE)
      ->execute();

    return $this->build($ids, FALSE);
  }

  /**
   * Builds the wrapper with the table.
   *
   * @param array $ids
   *   Timesheet IDs.
   * @param bool $overview
   *   TRUE for the overview (extra columns, selection, print button).
   */
  private function build(array $ids, bool $overview): array {
    $cache = new CacheableMetadata();
    $cache->addCacheTags(['timesheet_list', 'project_list', 'employee_list', 'paragraph_list', 'flagging_list']);
    // The "Flagged" column depends on the current user's flaggings.
    $cache->addCacheContexts(['user', 'user.permissions', 'languages:language_interface']);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['wrapper--timesheets']],
      '#weight' => 5,
    ];

    // The project page only showed the table when there were timesheets.
    if (!$ids && !$overview) {
      $cache->applyTo($build);
      return $build;
    }

    $timesheets = $this->entityTypeManager->getStorage('timesheet')->loadMultiple($ids);
    $works = $this->loadWorks(array_keys($timesheets));
    $employees = $this->loadEmployees(array_keys($timesheets));
    $flag = $this->flagService->getFlagById(self::FLAG_ID);

    $rows = [];
    foreach ($timesheets as $id => $timesheet) {
      $date = $timesheet->get('field_date')->date;
      $project = $timesheet->get('field_project')->entity;
      $author = $timesheet->get('user_id')->entity;

      $cells = [
        ['data' => $flag ? $this->flagLinkBuilder->build('timesheet', $id, self::FLAG_ID) : '', 'class' => ['link-flag']],
        ['data' => $flag && $flag->isFlagged($timesheet) ? $this->t('Yes') : $this->t('No')],
        ['data' => $timesheet->toLink()],
        ['data' => $timesheet->get('field_date')->value],
        ['data' => $date?->format('o-\WW')],
        ['data' => $date?->format('m')],
        ['data' => $date?->format('F')],
        ['data' => $date?->format('Y')],
      ];

      if ($overview) {
        $cells[] = [
          'data' => $project ? [
            '#type' => 'link',
            '#title' => $project->get('field_abbreviation')->value,
            '#url' => $project->toUrl(),
          ] : '',
        ];
      }

      $cells[] = ['data' => $this->itemList($works[$id]['names'] ?? []), 'class' => ['extra_works_list']];
      $cells[] = ['data' => $this->itemList($employees[$id] ?? []), 'class' => ['employee_list']];
      $cells[] = ['data' => $author ? $author->toLink() : ''];
      $cells[] = ['data' => $this->dateFormatter->format((int) $timesheet->get('created')->value, 'custom', 'Y-m-d')];

      if ($overview) {
        // Selection checkbox column comes first.
        array_unshift($cells, ['data' => '']);
        $cells[] = ['data' => $timesheet->get('field_events')->value];
        $cells[] = ['data' => $timesheet->get('field_internal_note')->value];
        $cells[] = ['data' => implode(',', array_filter($works[$id]['descriptions'] ?? []))];
        $rows[] = ['data' => $cells, 'data-timesheet-id' => $id];
      }
      else {
        $rows[] = $cells;
      }
    }

    $settings = [
      'personnel' => $this->loadPersonnelNames(),
      'extra_works' => $this->loadExtraWorksNames(),
    ];
    if ($overview) {
      $url = Url::fromRoute('timin_timetracking.timesheets')->toString(TRUE);
      $cache->addCacheableDependency($url);
      $settings['base_path'] = $url->getGeneratedUrl();
    }

    $build['timesheets_table'] = [
      '#type' => 'table',
      '#header' => $this->header($overview),
      '#rows' => $rows,
      '#attributes' => [
        // Old classes stay for your CSS; js-* classes are for the JS only.
        'class' => $overview
          ? ['dataTable', 'stripe', 'js-timin-timesheets']
          : ['dataTable_project', 'stripe', 'js-timin-project-timesheets'],
      ],
      '#attached' => [
        'library' => ['timin_timetracking/timin_timetracking_datatables'],
        'drupalSettings' => $settings,
      ],
    ];

    if ($overview) {
      $build['print_selected_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['wrapper--print-selected']],
        'print_selected_button' => [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Print selected'),
          '#attributes' => [
            'id' => 'print-selected-btn',
            'type' => 'button',
            'class' => ['button', 'button--primary', 'button--print-selected'],
          ],
        ],
      ];
    }

    $cache->applyTo($build);
    return $build;
  }

  /**
   * Table header; the column order must match js/datatables.js.
   */
  private function header(bool $overview): array {
    $header = [
      ['data' => '', 'class' => ['link-flag']],
      ['data' => $this->t('Flagged')],
      ['data' => $this->t('Name')],
      ['data' => $this->t('Date')],
      ['data' => $this->t('Week')],
      ['data' => $this->t('MonthInteger')],
      ['data' => $this->t('Month')],
      ['data' => $this->t('Year')],
    ];
    if ($overview) {
      array_unshift($header, ['data' => '']);
      $header[] = ['data' => $this->t('Project')];
    }
    $header[] = ['data' => $this->t('Extra Works'), 'class' => ['extra_works_list']];
    $header[] = ['data' => $this->t('Personnel'), 'class' => ['employee_list']];
    $header[] = ['data' => $this->t('Created by')];
    $header[] = ['data' => $this->t('Created')];
    if ($overview) {
      $header[] = ['data' => $this->t('Situation')];
      $header[] = ['data' => $this->t('Interne Notiz')];
      $header[] = ['data' => $this->t('Leistungsbeschreibungen')];
    }
    return $header;
  }

  /**
   * Service type names and descriptions per timesheet, in one query.
   */
  private function loadWorks(array $timesheet_ids): array {
    if (!$timesheet_ids) {
      return [];
    }
    $query = $this->database->select('timesheet_works', 'tw');
    $query->innerJoin('paragraph__field_name', 'pfn', 'tw.service_type_id = pfn.entity_id');
    $query->fields('tw', ['timesheet_id', 'works_description'])
      ->fields('pfn', ['field_name_value'])
      ->condition('tw.timesheet_id', $timesheet_ids, 'IN');

    $works = [];
    foreach ($query->execute() as $record) {
      $works[$record->timesheet_id]['names'][] = $record->field_name_value;
      $works[$record->timesheet_id]['descriptions'][] = $record->works_description;
    }
    return $works;
  }

  /**
   * Employee names per timesheet, in one query.
   */
  private function loadEmployees(array $timesheet_ids): array {
    if (!$timesheet_ids) {
      return [];
    }
    $query = $this->database->select('timesheet_data', 'td');
    $query->innerJoin('employee', 'e', 'td.employee_id = e.id');
    $query->fields('td', ['timesheet_id'])
      ->fields('e', ['name'])
      ->condition('td.timesheet_id', $timesheet_ids, 'IN')
      ->distinct();

    $employees = [];
    foreach ($query->execute() as $record) {
      $employees[$record->timesheet_id][] = $record->name;
    }
    return $employees;
  }

  /**
   * Labels of all employees (SearchPanes options).
   */
  private function loadPersonnelNames(): array {
    $storage = $this->entityTypeManager->getStorage('employee');
    $ids = $storage->getQuery()->accessCheck(TRUE)->execute();
    $names = [];
    foreach ($storage->loadMultiple($ids) as $employee) {
      $names[] = (string) $employee->label();
    }
    return $names;
  }

  /**
   * Names of all service types (SearchPanes options).
   */
  private function loadExtraWorksNames(): array {
    $storage = $this->entityTypeManager->getStorage('paragraph');
    $ids = $storage->getQuery()->condition('type', 'works')->accessCheck(TRUE)->execute();
    $names = [];
    foreach ($storage->loadMultiple($ids) as $paragraph) {
      $name = $paragraph->get('field_name')->value;
      if ($name !== NULL) {
        $names[] = $name;
      }
    }
    return $names;
  }

  /**
   * Compact item list for a table cell.
   */
  private function itemList(array $items): array {
    return [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $items,
      '#attributes' => ['class' => ['ellipsis']],
    ];
  }

}
