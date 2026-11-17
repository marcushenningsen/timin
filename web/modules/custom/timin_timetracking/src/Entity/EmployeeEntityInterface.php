<?php

namespace Drupal\timin_timetracking\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Employee entities.
 *
 * @ingroup timin_timetracking
 */
interface EmployeeEntityInterface extends ContentEntityInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Employee name.
   *
   * @return string
   *   Name of the Employee.
   */
  public function getName();

  /**
   * Sets the Employee name.
   *
   * @param string $name
   *   The Employee name.
   *
   * @return \Drupal\timin_timetracking\Entity\EmployeeEntityInterface
   *   The called Employee entity.
   */
  public function setName($name);

  /**
   * Gets the Employee creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Employee.
   */
  public function getCreatedTime();

  /**
   * Sets the Employee creation timestamp.
   *
   * @param int $timestamp
   *   The Employee creation timestamp.
   *
   * @return \Drupal\timin_timetracking\Entity\EmployeeEntityInterface
   *   The called Employee entity.
   */
  public function setCreatedTime($timestamp);

}
