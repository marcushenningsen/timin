<?php

namespace Drupal\timin_timetracking;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Project entity.
 *
 * @see \Drupal\timin_timetracking\Entity\ProjectEntity.
 */
class ProjectEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\timin_timetracking\Entity\ProjectEntityInterface $entity */

    switch ($operation) {

      case 'view':

        return AccessResult::allowedIfHasPermission($account, 'view project entities');

      case 'update':

        return AccessResult::allowedIfHasPermission($account, 'edit project entities');

      case 'delete':

        return AccessResult::allowedIfHasPermission($account, 'delete project entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add project entities');
  }


}
