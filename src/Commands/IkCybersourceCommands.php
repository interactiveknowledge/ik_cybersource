<?php

namespace Drupal\ik_cybersource\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ik_cybersource\CybersourceClient;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * @package Drupal\ik_cybersource
 */
class IkCybersourceCommands extends DrushCommands {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CybersourceClient $cybersourceClient,
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

  /**
   * Run Cybersource test transactions.
   *
   * @command ik_cybersource:test-transactions
   * @aliases test-transactions
   */
  public function testTransactions() {
    if ($this->cybersourceClient->isReady()) {
      $this->logger()->success('The Cybersource Client is ready.');
      $currentEnvironment = $this->cybersourceClient->getEnvironment();
      $this->logger()->success($this->t('Current environment: :env', [
        ':env' => $currentEnvironment,
      ]));

      if ($currentEnvironment !== 'development') {
        $this->cybersourceClient->setEnvironment('development');
      }

      // Defined in
      // https://developer.cybersource.com/hello-world/testing-guide-v1.html
      $this->testTransactionAmount('1', 'SUCCESS');
      $this->testTransactionAmount('-1', 'FAILURE');
      $this->testTransactionAmount('100000000000', 'FAILURE');

      $this->testTransactionCardDetails('4111111111111111', '12', '2031', 'SUCCESS');
      $this->testTransactionCardDetails('42423482938483873', '12', '2031', 'FAILURE');
      $this->testTransactionCardDetails('4111111111111111', '13', '2031', 'FAILURE');
      $this->testTransactionCardDetails('4111111111111111', '13', '1998', 'FAILURE');
      $this->testTransactionCardDetails('4111111111111112', '13', '1998', 'FAILURE');
      $this->testTransactionCardDetails('412345678912345678914', '13', '1998', 'FAILURE');

    }
    else {
      $this->logger()->warning('The Cybersource Client is not ready.');
    }
  }

  /**
   * Run Cybersource test transactions.
   *
   * @param array $response
   *   The response from the Cybersource API.
   */
  protected function analyzeResponse($response): string {
    $responseModel = $response[0] ?? NULL;

    $status = 'FAILURE';

    if (is_null($responseModel) && $response['error'] === TRUE) {
      $status = 'FAILURE';
    }
    elseif (get_class($responseModel) === 'CyberSource\Model\PtsV2PaymentsPost201Response') {
      if ($responseModel->getStatus() === 'AUTHORIZED') {
        $status = 'SUCCESS';
      }
      else {
        $status = 'FAILURE';
      }
    }
    elseif (get_class($responseModel) === 'CyberSource\Model\PtsV2PaymentsPost400Response') {
      $this->logger()->error($this->t('Unauthorized request. Status: :status', [
        ':status' => $responseModel->getStatus(),
      ]));

      $status = 'FAILURE';
    }
    else {
      $this->logger()->error($this->t('An error occurred. Status code: :status', [
        ':status' => $responseModel->getStatus(),
      ]));

      $status = 'FAILURE';
    }

    return $status;
  }

  /**
   * Test a transaction with a specific amount.
   *
   * Pass the amount and if you expect the transaction to be a SUCCESS
   * or FAILURE.
   *
   * @param string $amount
   *   The amount to test.
   * @param string $expectedStatus
   *   The expected status.
   */
  protected function testTransactionAmount(string $amount, string $expectedStatus = 'SUCCESS') {
    $this->logger()->success(
      $this->t('Testing transaction with amount: @amount', [
        '@amount' => $amount,
      ])
    );

    $processingOptions = $this->cybersourceClient->createProcessingOptions([
      'capture' => TRUE,
    ]);

    $billTo = [
      'firstName' => 'John',
      'lastName' => 'Doe',
      'company' => 'JD Inc.',
      'address1' => '123 Main St.',
      'address2' => 'Ste. A',
      'locality' => 'Charlotte',
      'administrativeArea' => 'NC',
      'postalCode' => '28202',
      'country' => 'United States',
      'email' => 'developers@interactiveknowledge.com',
      'phoneNumber' => '9876543210',
    ];

    $orderInfoBilling = $this->cybersourceClient->createBillingInformation($billTo);

    $number1 = rand(1000, 9999);
    $number2 = rand(1000, 9999);
    $code = 'TESTING-' . $number1 . '-' . $number2;

    $clientReferenceInformation = $this->cybersourceClient->createClientReferenceInformation([
      'code' => $code,
    ]);

    $amountDetails = $this->cybersourceClient->createOrderInformationAmountDetails([
      'totalAmount' => $amount,
      'currency' => 'USD',
    ]);

    $orderInformationArr = [
      'amountDetails' => $amountDetails,
      'billTo' => $orderInfoBilling,
    ];

    $orderInformation = $this->cybersourceClient->createOrderInformation($orderInformationArr);

    $cardNumber = '4111111111111111';
    $expirationMonth = '12';
    $expirationYear = '2031';
    $paymentCard = $this->cybersourceClient->createPaymentInformationCard($cardNumber, $expirationMonth, $expirationYear);

    $paymentInformation = $this->cybersourceClient->createPaymentInformation([
      'card' => $paymentCard,
    ]);

    $requestParameters = [
      'clientReferenceInformation' => $clientReferenceInformation,
      'orderInformation' => $orderInformation,
      'paymentInformation' => $paymentInformation,
      'processingInformation' => $processingOptions,
    ];

    $response = $this->makePayment($requestParameters);

    $status = $this->analyzeResponse($response);

    if ($status === $expectedStatus) {
      $this->logger()->success($this->t('Transaction with amount @amount behaved as expected.', [
        '@amount' => $amount,
      ]));
    }
    else {
      $this->logger()->error($this->t('Transaction with amount @amount returned unexpected status.', [
        '@amount' => $amount,
      ]));
    }
  }

  /**
   * Test a transaction with a payment.
   *
   * @param string $cardNumber
   *   The card number.
   * @param string $expirationMonth
   *   The expiration month.
   * @param string $expirationYear
   *   The expiration year.
   * @param string $expectedStatus
   *   The expected status.
   */
  protected function testTransactionCardDetails(
    string $cardNumber = '4111111111111111',
    string $expirationMonth = '12',
    string $expirationYear = '2031',
    string $expectedStatus = 'SUCCESS'
  ) {

    if ($cardNumber !== '4111111111111111') {
      $this->logger()->success(
        $this->t('Testing transaction with card number: @cardNumber', [
          '@cardNumber' => $cardNumber,
        ])
      );
    }
    elseif ($expirationMonth !== '12') {
      $this->logger()->success(
        $this->t('Testing transaction with expiration month: @expirationMonth', [
          '@expirationMonth' => $expirationMonth,
        ])
      );
    }
    elseif ($expirationYear !== '2031') {
      $this->logger()->success(
        $this->t('Testing transaction with expiration year: @expirationYear', [
          '@expirationYear' => $expirationYear,
        ])
      );
    }

    $processingOptions = $this->cybersourceClient->createProcessingOptions([
      'capture' => TRUE,
    ]);

    $billTo = [
      'firstName' => 'John',
      'lastName' => 'Doe',
      'company' => 'JD Inc.',
      'address1' => '123 Main St.',
      'address2' => 'Ste. A',
      'locality' => 'Charlotte',
      'administrativeArea' => 'NC',
      'postalCode' => '28202',
      'country' => 'United States',
      'email' => 'developers@interactiveknowledge.com',
      'phoneNumber' => '9876543210',
    ];

    $orderInfoBilling = $this->cybersourceClient->createBillingInformation($billTo);

    $number1 = rand(1000, 9999);
    $number2 = rand(1000, 9999);
    $code = 'TESTING-' . $number1 . '-' . $number2;

    $clientReferenceInformation = $this->cybersourceClient->createClientReferenceInformation([
      'code' => $code,
    ]);

    $amountDetails = $this->cybersourceClient->createOrderInformationAmountDetails([
      'totalAmount' => '1.00',
      'currency' => 'USD',
    ]);

    $orderInformationArr = [
      'amountDetails' => $amountDetails,
      'billTo' => $orderInfoBilling,
    ];

    $orderInformation = $this->cybersourceClient->createOrderInformation($orderInformationArr);

    $cardNumber = $cardNumber;
    $expirationMonth = $expirationMonth;
    $expirationYear = $expirationYear;
    $paymentCard = $this->cybersourceClient->createPaymentInformationCard($cardNumber, $expirationMonth, $expirationYear);

    $paymentInformation = $this->cybersourceClient->createPaymentInformation([
      'card' => $paymentCard,
    ]);

    $requestParameters = [
      'clientReferenceInformation' => $clientReferenceInformation,
      'orderInformation' => $orderInformation,
      'paymentInformation' => $paymentInformation,
      'processingInformation' => $processingOptions,
    ];

    $response = $this->makePayment($requestParameters);

    $status = $this->analyzeResponse($response);

    if ($status === $expectedStatus) {
      $this->logger()->success($this->t('Transaction with card number @cardNumber behaved as expected.', [
        '@cardNumber' => $cardNumber,
      ]));
    }
    else {
      $this->logger()->error($this->t('Transaction with card number @cardNumber returned unexpected status.', [
        '@cardNumber' => $cardNumber,
      ]));
    }
  }

  /**
   * Make a payment request.
   *
   * @param array $requestParameters
   *   The request parameters.
   */
  protected function makePayment(array $requestParameters) {
    $payRequest = $this->cybersourceClient->createPaymentRequest($requestParameters);

    try {
      $payResponse = $this->cybersourceClient->createPayment($payRequest);
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
    }

    return $payResponse;
  }

}
