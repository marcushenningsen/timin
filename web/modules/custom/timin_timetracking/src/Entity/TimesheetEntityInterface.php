<?php

namespace Drupal\timin_timetracking\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Timesheet entities.
 *
 * @ingroup timin_timetracking
 */
interface TimesheetEntityInterface extends ContentEntityInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Timesheet name.
   *
   * @return string
   *   Name of the Timesheet.
   */
  public function getName();

  /**
   * Sets the Timesheet name.
   *
   * @param string $name
   *   The Timesheet name.
   *
   * @return \Drupal\timin_timetracking\Entity\TimesheetEntityInterface
   *   The called Timesheet entity.
   */
  public function setName($name);

  /**
   * Gets the Timesheet creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Timesheet.
   */
  public function getCreatedTime();

  /**
   * Sets the Timesheet creation timestamp.
   *
   * @param int $timestamp
   *   The Timesheet creation timestamp.
   *
   * @return \Drupal\timin_timetracking\Entity\TimesheetEntityInterface
   *   The called Timesheet entity.
   */
  public function setCreatedTime($timestamp);

}
