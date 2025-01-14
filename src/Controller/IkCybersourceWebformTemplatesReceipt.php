<?php

namespace Drupal\ik_cybersource\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\ik_cybersource\CybersourceClient;
use Drupal\ik_cybersource\Entity\Payment;
use Drupal\ik_cybersource\Receipts;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformRequestInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns responses for Cybersource Webform Templates routes.
 */
class IkCybersourceWebformTemplatesReceipt extends ControllerBase {

  /**
   * The controller constructor.
   *
   * @param \Drupal\ik_cybersource\CybersourceClient $cybersourceClient
   *   The cybersource client.
   * @param \Drupal\webform\WebformRequestInterface $requestHandler
   *   The webform request handler.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   Entity repository.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Date formatter.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Config Factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Current User session.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel.
   * @param \Drupal\ik_cybersource\Receipts $receiptHandler
   *   Receipt handler.
   */
  public function __construct(
    protected CybersourceClient $cybersourceClient,
    protected WebformRequestInterface $requestHandler,
    protected EntityRepositoryInterface $entityRepository,
    protected DateFormatterInterface $dateFormatter,
    protected $configFactory,
    protected $currentUser,
    protected LoggerChannelInterface $logger,
    protected Receipts $receiptHandler
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ik_cybersource.cybersource_client'),
      $container->get('webform.request'),
      $container->get('entity.repository'),
      $container->get('date.formatter'),
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('logger.factory')->get('ik_cybersource'),
      $container->get('ik_cybersource.receipts')
    );
  }

  /**
   * Returns a webform receipt page.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\webform\WebformInterface|null $webform
   *   A webform.
   * @param \Drupal\webform\WebformSubmissionInterface|null $webform_submission
   *   A webform submission.
   *
   * @return array
   *   A render array representing a webform confirmation page
   */
  public function webformReceipt(Request $request, WebformInterface $webform = NULL, WebformSubmissionInterface $webform_submission = NULL) {
    /** @var \Drupal\Core\Entity\EntityInterface $source_entity */
    if (!$webform) {
      [$webform] = $this->requestHandler->getWebformEntities();
    }

    // Find the Submission token so that data may be loaded. Otherwise send the
    // user to 404.
    if ($token = $request->get('token')) {
      /** @var \Drupal\webform\WebformSubmissionStorageInterface $webform_submission_storage */
      $webform_submission_storage = $this->entityTypeManager()->getStorage('webform_submission');
      if ($entities = $webform_submission_storage->loadByProperties(['token' => $token])) {
        /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
        $webform_submission = reset($entities);
      }

      if (is_null($webform_submission)) {
        $this->logger->debug('Cybersource Receipt Not Found: No webform found.');
        throw new NotFoundHttpException();
      }

      $settings = $this->configFactory->get('ik_cybersource.settings');
      $receipt_availability = $settings->get('global')['receipt_availability'];
      $webform_submission_created = $webform_submission->get('created')->value;

      // Allow authenticated users to access.
      if (
        $this->currentUser->isAnonymous() === TRUE
        && time() > $webform_submission_created + ($receipt_availability * 60 * 60 * 24)
      ) {
        $this->logger->debug('Cybersource Receipt Not Found: receipt availability expired.');
        throw new NotFoundHttpException();
      }
      // Check expiry.
      if (time() > $webform_submission_created + ($receipt_availability * 60 * 60 * 24)) {
        if ($this->currentUser->isAnonymous() === TRUE) {
          $this->logger->debug('Cybersource Receipt Not Found: receipt availability expired.');
          throw new NotFoundHttpException();
        }
        // If authenticated user has permissions then allow them to view.
        elseif ($this->currentUser->hasPermission('view ik_cybersource receipts') === FALSE) {
          $this->logger->debug('Cybersource Receipt Not Found: user lacks permission to view expired receipt.');
          throw new NotFoundHttpException();
        }
      }
    }
    else {
      $this->logger->debug('Cybersource Receipt Not Found: no token found.');
      throw new NotFoundHttpException();
    }

    // Submission Data and Payment entity.
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $data = $webform_submission->getData();
    /** @var \Drupal\ik_cybersource\Entity\Payment $payment */
    $payment = $this->entityRepository->getActive('payment', $data['payment_entity']);
    $transaction = $this->getTransactionFromPayment($payment);

    $this->checkTransactionResponse($transaction);

    return $this->receiptHandler->buildReceiptElements($payment, $transaction);
  }

  /**
   * Returns a webform receipt page for admin and privileged users.
   *
   * @param \Drupal\ik_cybersource\Entity\Payment $payment
   *   Payment entity.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return array
   *   A render array representing a webform confirmation page
   */
  public function paymentReceipt(Payment $payment = NULL, Request $request) {
    $transaction = $this->getTransactionFromPayment($payment);

    $this->checkTransactionResponse($transaction);

    return $this->receiptHandler->buildReceiptElements($payment, $transaction);
  }

  /**
   * Checks if the object is valid.
   *
   * @throws NotFoundException
   */
  protected function checkTransactionResponse(&$transaction) {
    if (is_array($transaction) === FALSE && get_class($transaction) === 'CyberSource\ApiException') {
      $this->logger->warning('Cybersource API Error.');
      throw new NotFoundHttpException('Error finding transaction');
    }
  }

  /**
   * Get the transaction object.
   *
   * @param Drupal\ik_cybersource\Entity\Payment $payment
   *   Payment entity.
   *
   * @return array
   *   Transaction object.
   */
  protected function getTransactionFromPayment(Payment $payment) {
    $payment_id = $payment->get('payment_id')->value;

    return $this->cybersourceClient->getTransaction($payment_id);
  }

}
