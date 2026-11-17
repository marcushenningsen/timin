<?php

declare(strict_types=1);

namespace Drupal\timin_timetracking\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Project and employee lists, rendered as DataTables (replaces two views).
 *
 * ControllerBase autowires the constructor (Drupal 10.2+), no create() needed.
 */
final class ListController extends ControllerBase {

  /**
   * Edit icon from the admin theme, as used in the old views.
   */
  private const EDIT_ICON = '/themes/claro_timin/images/edit_20dp_0D6EFD_FILL0_wght400_GRAD0_opsz20.svg';

  public function __construct(
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * Route callback for /projects
   */
  public function projects(): array {
    $header = [
      $this->t('Name'),
      $this->t('Abbreviation'),
      $this->t('Status'),
      $this->t('Author'),
      $this->t('Created'),
      ['data' => '', 'data-orderable' => 'false', 'data-searchable' => 'false'],
    ];

    $rows = [];
    foreach ($this->loadEntities('project') as $project) {
      $rows[] = [
        ['data' => $project->toLink()],
        ['data' => $project->get('field_abbreviation')->value],
        $this->editableCell($project, 'field_status2'),
        $this->ownerCell($project),
        $this->createdCell($project),
        $this->editCell($project),
      ];
    }

    // Default sort: status, descending (active projects first).
    return $this->buildTable('projects', $header, $rows, [[2, 'desc']], ['project_list']);
  }

  /**
   * Route callback for /employees
   */
  public function employees(): array {
    $header = [
      $this->t('Name'),
      $this->t('Status'),
      $this->t('Count to personnel'),
      $this->t('Author'),
      $this->t('Created'),
      ['data' => '', 'data-orderable' => 'false', 'data-searchable' => 'false'],
    ];

    $rows = [];
    foreach ($this->loadEntities('employee') as $employee) {
      $rows[] = [
        ['data' => $employee->toLink()],
        $this->editableCell($employee, 'field_status'),
        $this->editableCell($employee, 'field_count_to_personnel'),
        $this->ownerCell($employee),
        $this->createdCell($employee),
        $this->editCell($employee),
      ];
    }

    // Default sort: name, ascending.
    return $this->buildTable('employees', $header, $rows, [[0, 'asc']], ['employee_list']);
  }

  /**
   * Loads all entities of a type the current user may see.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface[]
   */
  private function loadEntities(string $entity_type_id): array {
    $storage = $this->entityTypeManager()->getStorage($entity_type_id);
    $ids = $storage->getQuery()->accessCheck(TRUE)->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * Wraps rows in a table that js/list-tables.js turns into a DataTable.
   *
   * The DataTables options are passed as HTML5 data attributes, so the JS
   * stays generic for both lists.
   */
  private function buildTable(string $name, array $header, array $rows, array $order, array $cache_tags): array {
    $build = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No entries found.'),
      '#attributes' => [
        'class' => ['stripe', 'js-timin-list-table', 'timin-list-table--' . $name],
        'data-order' => Json::encode($order),
        'data-page-length' => '25',
        'data-length-change' => 'false',
      ],
      '#attached' => [
        'library' => ['timin_timetracking/list_tables'],
      ],
    ];

    (new CacheableMetadata())
      ->addCacheTags($cache_tags)
      // Editable fields and edit links depend on the user's access.
      ->addCacheContexts(['user', 'user.permissions', 'languages:language_interface'])
      ->applyTo($build);

    return $build;
  }

  /**
   * Inline-editable switch via the editablefields formatter (as in the views).
   */
  private function editableCell(ContentEntityInterface $entity, string $field_name): array {
    return [
      'data' => $entity->get($field_name)->view([
        'label' => 'hidden',
        'type' => 'editablefields_formatter',
        'settings' => [
          'form_mode' => 'default',
          'behaviour' => 'inline',
          'bypass_access' => 0,
          'fallback_access' => 0,
          'display_mode_access' => 'default',
          'fallback_edit' => 0,
          'display_mode_edit' => 'default',
          'fields_ajax_trigger' => $field_name,
          'fields_ajax_trigger_event' => 'change',
        ],
      ]),
      'class' => ['form-switch'],
      // Sort and search on the stored value, not on the rendered form.
      'data-order' => (string) (int) $entity->get($field_name)->value,
      'data-search' => $entity->get($field_name)->value ? '1' : '0',
    ];
  }

  /**
   * Author, linked if the current user may view the user account.
   */
  private function ownerCell(ContentEntityInterface $entity): array {
    $owner = $entity->get('user_id')->entity;
    if (!$owner) {
      return ['data' => ''];
    }
    return ['data' => $owner->access('view') ? $owner->toLink() : $owner->getDisplayName()];
  }

  /**
   * Creation date in the "medium" format, sorted by timestamp.
   */
  private function createdCell(ContentEntityInterface $entity): array {
    $timestamp = (int) $entity->get('created')->value;
    return [
      'data' => $this->dateFormatter->format($timestamp, 'medium'),
      'data-order' => (string) $timestamp,
    ];
  }

  /**
   * Edit icon link, only if the user may edit the entity.
   */
  private function editCell(ContentEntityInterface $entity): array {
    if (!$entity->access('update')) {
      return ['data' => ''];
    }
    return [
      'data' => [
        '#type' => 'link',
        '#title' => [
          '#theme' => 'image',
          '#uri' => self::EDIT_ICON,
          '#alt' => $this->t('Edit @label', ['@label' => $entity->label()]),
        ],
        '#url' => $entity->toUrl('edit-form'),
      ],
    ];
  }

}
