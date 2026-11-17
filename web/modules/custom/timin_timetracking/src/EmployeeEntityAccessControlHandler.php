<?php

namespace Drupal\timin_timetracking;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Employee entity.
 *
 * @see \Drupal\timin_timetracking\Entity\EmployeeEntity.
 */
class EmployeeEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\timin_timetracking\Entity\EmployeeEntityInterface $entity */

    switch ($operation) {

      case 'view':

        return AccessResult::allowedIfHasPermission($account, 'view employee entities');

      case 'update':

        return AccessResult::allowedIfHasPermission($account, 'edit employee entities');

      case 'delete':

        return AccessResult::allowedIfHasPermission($account, 'delete employee entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add employee entities');
  }


}
