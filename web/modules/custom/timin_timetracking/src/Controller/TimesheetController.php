<?php

declare(strict_types=1);

namespace Drupal\timin_timetracking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\timin_timetracking\Entity\TimesheetEntityInterface;
use Drupal\timin_timetracking\TimesheetTableBuilder;

/**
 * Timesheet overview page and clone form title.
 *
 * ControllerBase autowires the constructor (Drupal 10.2+), no create() needed.
 */
final class TimesheetController extends ControllerBase {

  public function __construct(
    private readonly TimesheetTableBuilder $tableBuilder,
  ) {}

  /**
   * Route callback for /timesheets.
   */
  public function timesheets(): array {
    return ['timesheets' => $this->tableBuilder->buildOverview()];
  }

  /**
   * Title callback for the clone form.
   */
  public function getTitle(TimesheetEntityInterface $timesheet): TranslatableMarkup {
    return $this->t('Clone %label', ['%label' => $timesheet->label()]);
  }

}
