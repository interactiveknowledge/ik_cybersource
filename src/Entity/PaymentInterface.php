<?php

namespace Drupal\ik_cybersource\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Provides an interface defining a payment entity type.
 */
interface PaymentInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the payment creation timestamp.
   *
   * @return int
   *   Creation timestamp of the payment.
   */
  public function getCreatedTime();

  /**
   * Sets the payment creation timestamp.
   *
   * @param int $timestamp
   *   The payment creation timestamp.
   *
   * @return \Drupal\ik_cybersource\Entity\PaymentInterface
   *   The called payment entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Get transaction status value.
   *
   * @return string
   *   A string of the transaction status.
   */
  public function getStatus(): string;

  /**
   * Get recurring status value.
   *
   * @return bool
   *   A boolean value of the recurring status.
   */
  public function getRecurring(): bool;

  /**
   * Check if this is a recurring transaction.
   *
   * @return bool
   *   A boolean value of the recurring status.
   */
  public function isRecurring(): bool;

  /**
   * Is this an active recurring transaction parent.
   *
   * @return bool
   *   A boolean value of the active recurring status.
   */
  public function isActiveRecurring(): bool;

  /**
   * Get the webform submission.
   *
   * @return \Drupal\webform\Entity\WebformSubmissionInterface
   *   The webform submission entity.
   */
  public function getWebformSubmission(): WebformSubmissionInterface;

  /**
   * Get the webform entity.
   *
   * @return \Drupal\webform\WebformInterface
   *   The webform entity.
   */
  public function getWebform(): WebformInterface;

}
