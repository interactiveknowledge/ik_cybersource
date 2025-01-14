<?php

namespace Drupal\ik_cybersource\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * @package Drupal\ik_cybersource
 */
class IkCybersourceCommands extends DrushCommands {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager
  ) {}

  /**
   * Deletes all payment entities.
   *
   * @command ik_cybersource:delete-payments
   * @aliases delete-payments
   */
  public function deletePayments() {
    $storage = $this->entityTypeManager->getStorage('payment');
    $entity_ids = $storage->getQuery()->accessCheck(FALSE)->execute();

    if (!empty($entity_ids)) {
      $entities = $storage->loadMultiple($entity_ids);
      $storage->delete($entities);
      $this->logger()->success('All payment entities have been deleted.');
    }
    else {
      $this->logger()->warning('No payment entities found to delete.');
    }
  }

  /**
   * Deletes all webform entities.
   *
   * @command ik_cybersource:delete-webforms
   * @aliases delete-webforms
   */
  public function deleteWebforms() {
    $storage = $this->entityTypeManager->getStorage('webform');
    $entity_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($entity_ids)) {
      $entities = $storage->loadMultiple($entity_ids);
      $storage->delete($entities);
      $this->logger()->success('All webform entities have been deleted.');
    }
    else {
      $this->logger()->warning('No webform entities found to delete.');
    }
  }

}
