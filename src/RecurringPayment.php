<?php

namespace Drupal\ik_cybersource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ik_cybersource\Entity\Payment;

/**
 * Recurring Payment manager.
 */
class RecurringPayment {
  /**
   * Storage interface for the Payment entity.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * Logger interface.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Database datetime format.
   *
   * @var string
   */
  protected $dateTimeFormat = 'Y-m-d\TH:i:s';

  /**
   * Construct this manager.
   *
   * @param Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config factory interface.
   * @param Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Drupal logger channel interface.
   * @param Drupal\Core\Entity\EntityTypeManager $manager
   *   Entitytype manager.
   * @param Drupal\ik_cybersource\CybersourceClient $cybersourceClient
   *   CyberSource clients.
   * @param Drupal\ik_cybersource\Mailer $mailer
   *   Mailer manager.
   * @param Drupal\ik_cybersource\Receipts $receiptHandler
   *   Receipt handler.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected EntityTypeManager $manager,
    protected CybersourceClient $cybersourceClient,
    protected Mailer $mailer,
    protected Receipts $receiptHandler,
  ) {
    $this->storage = $this->manager->getStorage('payment');
    $this->logger = $this->loggerFactory->get('ik_cybersource');
  }

  /**
   * Get list of recurring payments.
   *
   * @return array
   *   List of Payments.
   */
  public function query() {
    return $this->storage->getQuery()
      // Recurring only.
      ->condition('recurring', 1)
      // Recurring is active.
      ->condition('recurring_active', 1)
      // Must have payment id.
      ->condition('payment_id', NULL, 'IS NOT NULL')
      // Must have customer stored.
      ->condition('customer_id', NULL, 'IS NOT NULL')
      // Initial payment must have been Transmitted.
      ->condition('status', 'TRANSMITTED')
      // Stored recurring_next date must be passed.
      ->condition('recurring_next', gmdate($this->dateTimeFormat), '<')
      ->accessCheck(FALSE)
      ->execute();
  }

  /**
   * Get list of recurring payments but missing customer.
   *
   * @return array
   *   List of Payments.
   */
  public function queryMissingCustomer() {
    return $this->storage->getQuery()
      // Recurring only.
      ->condition('recurring', 1)
      // Recurring is active.
      ->condition('recurring_active', 1)
      // Must have payment id.
      ->condition('payment_id', NULL, 'IS NOT NULL')
      // Must have customer stored.
      ->condition('customer_id', NULL, 'IS NULL')
      ->accessCheck(FALSE)
      ->execute();
  }

  /**
   * Get list of recurring payments but missing customer.
   *
   * @return array
   *   List of Payments.
   */
  public function queryMissingPaymentId() {
    return $this->storage->getQuery()
      // Recurring only.
      ->condition('recurring', 1)
      // Recurring is active.
      ->condition('recurring_active', 1)
      // Must have payment id.
      ->condition('payment_id', NULL, 'IS NULL')
      ->accessCheck(FALSE)
      ->execute();
  }

  /**
   * Build a recurring payment.
   *
   * @param Drupal\ik_cybersource\Entity\Payment $payment
   *   Payment entity.
   *
   * @return bool
   *   TRUE if the transaction was made successfully.
   */
  public function buildRecurringPayment(Payment $payment) {
    $payment_id = $payment->get('payment_id')->value;
    $customer_id = $payment->get('customer_id')->value;
    $amount = $payment->get('authorized_amount')->value;
    $currency = $payment->get('currency')->value;
    $environment = $payment->get('environment')->value;
    $code = $payment->get('code')->value;
    $recurring_payments_count = $payment->get('recurring_payments')->count();
    $recurring_payments_max = $payment->get('recurring_max')->value;

    if (($recurring_payments_count + 1) >= $recurring_payments_max) {
      $this->logger->info('Payment @code recurring charge will not be processed. Number of payments exceeds the maximum value.', [
        '@code' => $code,
      ]);

      // Disable recurring active. Maximum number met or exceeded.
      $payment->set('recurring_active', FALSE);
      $payment->save();

      return FALSE;
    }

    // Set up the payment request.
    $this->cybersourceClient->setEnvironment($environment);

    $processingOptions = $this->cybersourceClient->createProcessingOptionsForRecurringPayment($payment_id);
    $processingOptions->setCapture(TRUE);

    $newCode = $code . '-' . ($recurring_payments_count + 1);
    $clientReferenceInformation = $this->cybersourceClient->createClientReferenceInformation([
      'code' => $newCode,
    ]);

    $amount = strpos($amount, '.') > 0 ? $amount : $amount . '.00';
    $amountDetails = $this->cybersourceClient->createOrderInformationAmountDetails([
      'totalAmount' => $amount,
      'currency' => $currency,
    ]);

    $orderInformationArr = [
      'amountDetails' => $amountDetails,
    ];

    $orderInformation = $this->cybersourceClient->createOrderInformation($orderInformationArr);

    $customerInformation = $this->cybersourceClient->createPaymentInformationCustomer([
      'customerId' => $customer_id,
    ]);

    $paymentInformation = $this->cybersourceClient->createPaymentInformation([
      'customer' => $customerInformation,
    ]);

    $paymentRequestInfo = [
      'clientReferenceInformation' => $clientReferenceInformation,
      'orderInformation' => $orderInformation,
      'paymentInformation' => $paymentInformation,
      'processingInformation' => $processingOptions,
    ];

    $paymentRequest = $this->cybersourceClient->createPaymentRequest($paymentRequestInfo);

    $payResponse = $this->cybersourceClient->createPayment($paymentRequest);

    // Check for Returned errors.
    if (isset($payResponse['error']) === TRUE && $payResponse['error'] === TRUE) {
      return FALSE;
    }

    // Save the Payment entity.
    $newPaymentId = $payResponse[0]['id'];
    $submitted = $payResponse[0]['submitTimeUtc'];
    $status = $payResponse[0]['status'];
    // Not recurring.
    $isRecurring = FALSE;

    $newPayment = Payment::create([]);
    $newPayment->set('code', $newCode);
    $newPayment->set('payment_id', $newPaymentId);
    $newPayment->set('currency', 'USD');
    $newPayment->set('authorized_amount', $amount);
    $newPayment->set('submitted', $submitted);
    $newPayment->set('status', $status);
    $newPayment->set('recurring', $isRecurring);
    $newPayment->set('environment', $environment);
    $newPayment->set('recurring_active', FALSE);

    // Save new payment entity.
    $newPayment->save();
    $pid = $newPayment->id();

    // Update "parent" recurring payment.
    $payment->get('recurring_payments')->appendItem([
      'target_id' => $pid,
    ]);

    // Check if max number of payments is exceeded after this transaction.
    if (($payment->get('recurring_payments')->count() + 1) < $recurring_payments_max) {
      // Set the next recurring payment date time.
      $lastRecurringPayment = $newPayment->get('created')->value;
      $payment->set('recurring_next', ik_cybersource_get_next_recurring_payment_date($lastRecurringPayment));
    }
    else {
      /*
       * Disable active recurring flag when the number of recurring payments
       * meets the maximum.
       */
      $payment->set('recurring_active', FALSE);
    }

    $payment->save();

    // Give platform time to process.
    sleep(5);

    // Create and send receipt.
    $key = 'rpayment_id_' . $payment->id() . '_recurring';
    $this->receiptHandler->trySendReceipt($this->cybersourceClient, $newPayment, $key);

    return TRUE;
  }

  /**
   * Check for customer id.
   *
   * @param Drupal\ik_cybersource\Entity\Payment $payment
   *   Payment entity.
   */
  public function checkForCustomerId(Payment $payment): void {
    $payment_id = $payment->get('payment_id')->value;
    $environment = $payment->get('environment')->value;

    $this->cybersourceClient->setEnvironment($environment);

    $transaction = $this->cybersourceClient->getTransaction($payment_id);

    $customerId = $transaction[0]->getPaymentInformation()->getCustomer()->getCustomerId();

    if (!is_null($customerId)) {
      $payment->set('customer_id', $customerId);
      $payment->save();
    }
  }

  /**
   * Check for payment id.
   *
   * @param Drupal\ik_cybersource\Entity\Payment $payment
   *   Payment entity.
   */
  public function checkForPaymentId(Payment $payment): void {
    $code = $payment->get('code')->value;
    $environment = $payment->get('environment')->value;

    $this->cybersourceClient->setEnvironment($environment);

    $search = [
      'query' => 'clientReferenceInformation.code:' . $code,
      'sort' => 'submitTimeUtc:desc',
      'offset' => '0',
      'limit' => '1',
    ];

    $createSearchRequest = $this->cybersourceClient->createSearchRequest($search);

    $searchRequest = $this->cybersourceClient->createSearch($createSearchRequest);

    $transactions = $searchRequest[0]->getEmbedded()->getTransactionSummaries();

    if (count($transactions) > 0) {
      $paymentId = $transactions[0]->getId();

      $payment->set('payment_id', $paymentId);
      $payment->save();
    }
  }

}
