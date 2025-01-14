<?php

namespace Drupal\ik_cybersource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ik_cybersource\Entity\Payment;

/**
 * Defines a receipts service with helpful methods to build receipts.
 *
 * @package Drupal\ik_cybersource
 */
class Receipts {

  use StringTranslationTrait;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * Constructs a new Receipt object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Date formatter functions.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   Logging in drupal.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connection.
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   Queue factory.
   * @param \Drupal\ik_cybersource\Mailer $mailer
   *   Mailer service.
   * @param \Drupal\ik_cybersource\CybersourceClient $cybersourceClient
   *   Cybersource client.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected DateFormatterInterface $dateFormatter,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected Connection $connection,
    protected QueueFactory $queue,
    protected Mailer $mailer,
    protected CybersourceClient $cybersourceClient
  ) {
    $this->logger = $this->loggerFactory->get('ik_cybersource');
  }

  /**
   * Build receipt element.
   *
   * @param Drupal\ik_cybersource\Entity\Payment $payment
   *   Payment entity.
   * @param array $transaction
   *   Transaction array from Cybersource payment processor.
   *
   * @return array
   *   Build array.
   */
  public function buildReceiptElements(Payment $payment, array $transaction) {
    $billTo = $transaction[0]->getOrderInformation()->getBillTo();
    $paymentInformation = $transaction[0]->getPaymentInformation();
    $card = $paymentInformation->getCard();
    $amountDetails = $transaction[0]->getOrderInformation()->getAmountDetails();
    $datetime = $transaction[0]->getSubmitTimeUTC();
    $donationType = strpos($payment->get('code')->value, 'GALA') > -1 ? 'GALA' : 'DONATION';

    // Build receipt.
    $build['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h1',
      '#value' => $this->t('Receipt'),
    ];

    $build['meta'] = [
      '#type' => 'container',
    ];

    $build['meta']['date'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => 'Date: ' . $this->dateFormatter->format(strtotime($datetime), 'long'),
    ];

    $build['meta']['order_number'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('Order Number: :number', [':number' => $payment->get('code')->value]),
      '#attributes' => [
        'style' => ['margin-bottom: 25px'],
      ],
    ];

    $build['break_1'] = [
      '#type' => 'html_tag',
      '#tag' => 'hr',
    ];

    $build['billing_information'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['billing-information'],
      ],
    ];

    $build['billing_information']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Billing Information'),
    ];

    $build['billing_information']['address'] = [
      '#type' => 'html_tag',
      '#tag' => 'address',
    ];

    $build['billing_information']['address']['name'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t(':firstName :lastName', [
        ':firstName' => $billTo->getFirstName(),
        ':lastName' => $billTo->getLastName(),
      ]),
      '#attributes' => [
        'class' => ['name'],
      ],
    ];

    if (!empty($billTo->getCompany())) {
      $build['billing_information']['address']['company'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t(':value', [':value' => $billTo->getCompany()]),
        '#attributes' => [
          'class' => ['company'],
        ],
      ];
    }

    $build['billing_information']['address']['address'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t(':value', [':value' => $billTo->getAddress1()]),
      '#attributes' => [
        'class' => ['address'],
      ],
    ];

    if (!empty($billTo->getAddress2())) {
      $build['billing_information']['address']['address_2'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t(':value', [':value' => $billTo->getAddress2()]),
        '#attributes' => [
          'class' => ['address-2'],
        ],
      ];
    }

    $build['billing_information']['address']['locality'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t(':value', [':value' => $billTo->getLocality()]),
      '#attributes' => [
        'class' => ['locality'],
      ],
    ];

    $build['billing_information']['address']['area'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t(':value', [':value' => $billTo->getAdministrativeArea()]),
      '#attributes' => [
        'class' => ['area'],
      ],
    ];

    $build['billing_information']['address']['postal_code'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t(':value', [':value' => $billTo->getPostalCode()]),
      '#attributes' => [
        'class' => ['postal-code'],
      ],
    ];

    $build['billing_information']['address']['email'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t(':value', [':value' => $billTo->getEmail()]),
      '#attributes' => [
        'class' => ['email'],
      ],
    ];

    $build['billing_information']['address']['phone'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t(':value', [':value' => $billTo->getPhoneNumber()]),
      '#attributes' => [
        'class' => ['phone'],
      ],
    ];

    $build['payment_details'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['billing-information'],
      ],
    ];

    $build['payment_details']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Payment Details'),
    ];

    $build['payment_details']['list'] = [
      '#type' => 'html_tag',
      '#tag' => 'dl',
    ];

    $build['payment_details']['list']['card_type_term'] = [
      '#type' => 'html_tag',
      '#tag' => 'dd',
      '#value' => 'Card Type',
    ];

    $build['payment_details']['list']['card_type_value'] = [
      '#type' => 'html_tag',
      '#tag' => 'dd',
      '#value' => $this->t(':value', [':value' => ik_cybersource_card_type_number_to_string($card->getType())]),
    ];

    $build['payment_details']['list']['card_number_term'] = [
      '#type' => 'html_tag',
      '#tag' => 'dd',
      '#value' => 'Card Number',
    ];

    $build['payment_details']['list']['card_number_value'] = [
      '#type' => 'html_tag',
      '#tag' => 'dd',
      '#value' => $this->t('xxxxxxxxxxxx:value', [':value' => $card->getSuffix()]),
    ];

    $build['payment_details']['list']['card_expiration_term'] = [
      '#type' => 'html_tag',
      '#tag' => 'dd',
      '#value' => 'Expiration',
    ];

    $build['payment_details']['list']['card_expiration_value'] = [
      '#type' => 'html_tag',
      '#tag' => 'dd',
      '#value' => $this->t(':month-:year', [
        ':month' => $card->getExpirationMonth(),
        ':year' => $card->getExpirationYear(),
      ]),
    ];

    if ($donationType === 'GALA' || !is_null($payment->get('order_details_long')->value)) {
      $build['order_details'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['order-details'],
        ],
      ];

      $build['order_details']['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Order Details'),
      ];

      $build['order_details']['content'] = [
        '#type' => 'container',
      ];

      $details = explode('; ', $payment->get('order_details_long')->value);

      foreach ($details as $i => $detail) {
        $build['order_details']['content'][$i] = [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $detail,
        ];

        if ($i === count($details) - 1) {
          $build['order_details']['content'][$i]['#attributes'] = [
            'style' => ['margin-bottom: 25px'],
          ];
        }
      }
    }

    $build['total'] = [
      '#type' => 'container',
    ];

    $build['total']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Total Amount'),
    ];

    $amount = number_format($amountDetails->getTotalAmount(), 2);
    $build['total']['amount'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->t('$:amount', [':amount' => $amount]),
      '#attributes' => [
        'style' => ['margin-bottom: 25px'],
      ],
    ];

    $build['break_2'] = [
      '#type' => 'html_tag',
      '#tag' => 'hr',
    ];

    $build['message'] = [
      '#type' => 'container',
    ];

    // @todo Thank you message should be configurable.
    $markup = '<p>Configurable message</p>';

    $build['message']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Thank You'),
    ];

    $build['message']['message'] = [
      '#markup' => $markup,
    ];

    return $build;
  }

  /**
   * Build an email body. This is the email template.
   *
   * @param Drupal\ik_cybersource\Entity\Payment $payment
   *   Payment entity.
   * @param \Drupal\ik_cybersource\Entity\Payment $billTo
   *   Bill to entity.
   * @param mixed $paymentInformation
   *   Payment information.
   * @param mixed $amountDetails
   *   Amount details.
   * @param mixed $datetime
   *   Datetime.
   *
   * @return string
   *   Email body.
   */
  public function buildReceiptEmailBody(Payment $payment, $billTo, $paymentInformation, $amountDetails, $datetime) {
    $card = $paymentInformation->getCard();
    $amount = number_format($amountDetails->getAuthorizedAmount(), 2);
    $webform = $payment->getWebform();
    $handler = $webform->getHandler('ik_cybersource_webform_handler');
    $configuration = $handler->getConfiguration();
    $message = $configuration['settings']['email_receipt_message']['value'];

    $body = '';

    $body .= "
$message
";

    $body .= "
RECEIPT

Date: {$this->dateFormatter->format(strtotime($datetime), 'long')}
Order Number: {$payment->get('code')->value}

------------------------------------

BILLING INFORMATION

{$billTo->getFirstName()} {$billTo->getLastName()}";

    if (!empty($billTo->getCompany())) {
      $body .= "
{$billTo->getCompany()}";
    }

    $body .= "
{$billTo->getAddress1()}";

    if (!empty($billTo->getAddress2())) {
      $body .= "
{$billTo->getAddress2()}";
    }

    $body .= "
{$billTo->getLocality()}
{$billTo->getAdministrativeArea()}
{$billTo->getPostalCode()}
{$billTo->getEmail()}
{$billTo->getPhoneNumber()}

------------------------------------

PAYMENT DETAILS

Card Type {$this->cardTypeNumberToString($card->getType())}
Card Number xxxxxxxxxxxxx{$card->getSuffix()}
Expiration {$card->getExpirationMonth()}-{$card->getExpirationYear()}

------------------------------------
";

// @todo Order table details should be expanded.
//     if ($donationType === 'GALA' || !is_null($payment->get('order_details_long')->value)) {
//       $details = explode('; ', $payment->get('order_details_long')->value);

//       $body .= "
// ORDER DETAILS
// ";

//       foreach ($details as $detail) {
//         $body .= "
// {$detail}
// ";
//       }
//     }

    $body .= "
TOTAL AMOUNT

$ {$amount}
";

    return $body;
  }

  /**
   * Given payment information and the receipient, send a receipt.
   *
   * @param \Drupal\ik_cybersource\Entity\Payment $payment
   *   Payment entity.
   * @param array $transaction
   *   Transaction array from Cybersource payment processor.
   * @param string $key
   *   Email key.
   * @param string $to
   *   Email address.
   *
   * @return bool
   *   Returns send status.
   */
  protected function sendReceipt(Payment $payment, $transaction, $key = 'receipt', $to = NULL) {
    $billTo = $transaction[0]->getOrderInformation()->getBillTo();
    $paymentInformation = $transaction[0]->getPaymentInformation();
    $amountDetails = $transaction[0]->getOrderInformation()->getAmountDetails();
    $datetime = $transaction[0]->getSubmitTimeUTC();
    $body = $this->buildReceiptEmailBody($payment, $billTo, $paymentInformation, $amountDetails, $datetime);
    $webform = $payment->getWebform();
    $webformHandler = $webform->getHandler('ik_cybersource_webform_handler');
    $configuration = $webformHandler->getConfiguration();
    $subject = $configuration['settings']['email_receipt_subject'];

    if (is_null($to) === TRUE) {
      $to = $billTo->getEmail();
    }

    $result = $this->mailer->sendMail($key, $to, $subject, $body);

    if ($result['send'] === TRUE) {
      $context = [
        '@code' => $payment->get('code')->value ?? 'unknown code',
      ];

      // Attempt to link to the payment.
      try {
        $link = $payment->toLink('View', 'canonical')->toString();

        $context['link'] = $link;
      }
      catch (\Exception $e) {
        $this->logger->error('Error generating link: @error', ['@error' => $e->getMessage()]);
      }

      $this->logger->info('Payment code @code receipt emailed.', $context);
    }

    return $result['send'];
  }

  /**
   * Attempt to send a receipt.
   *
   * If the transaction isn't available send a copy of it to the queue.
   *
   * @param \Drupal\ik_cybersource\CybersourceClient $client
   *   Cybersource client.
   * @param \Drupal\ik_cybersource\Entity\Payment $payment
   *   Payment entity.
   * @param string $key
   *   Email key.
   * @param string|null $to
   *   Email address.
   *
   * @return bool
   *   Returns send status.
   */
  public function trySendReceipt(CybersourceClient $client, Payment $payment, $key = 'receipt', $to = NULL) {
    $paymentId = $payment->get('payment_id')->value;
    $transaction = $client->getTransaction($paymentId);

    // If there is an exception, queue this to send next cron.
    if (is_array($transaction) === FALSE && get_class($transaction) === 'CyberSource\ApiException') {
      $environment = $client->getEnvironment();
      $pid = $payment->id();

      if ($this->isPaymentInQueue($pid) !== TRUE) {
        $this->sendToQueue($environment, $pid, $key, $to);
      }

      return FALSE;
    }

    $sent = $this->sendReceipt($payment, $transaction, $key, $to);

    // If sending failed, queue this for later.
    if ($sent !== TRUE) {
      $environment = $client->getEnvironment();
      $pid = $payment->id();

      if ($this->isPaymentInQueue($pid) !== TRUE) {
        $this->sendToQueue($environment, $pid, $key, $to);
      }
    }

    return $sent;
  }

  /**
   * Send receipt email data to the queue.
   *
   * @param string $environment
   *   Environment.
   * @param int|string $payment_entity_id
   *   Payment entity id.
   * @param string $key
   *   Email key.
   * @param string $to
   *   Email address.
   */
  protected function sendToQueue($environment, $payment_entity_id, $key, $to) {
    $queue = $this->queue->get('receipt_queue');

    $queueItem = new \stdClass();
    $queueItem->environment = $environment;
    $queueItem->pid = (int) $payment_entity_id;
    $queueItem->key = $key;
    $queueItem->to = $to;

    $queue->createItem($queueItem);
  }

  /**
   * Check queue for existing record.
   *
   * @param int $pid
   *   Payment entity id.
   *
   * @return bool
   *   Returns TRUE if payment is in queue.
   */
  protected function isPaymentInQueue(int $pid) {
    $queued = $this->connection->select('queue', 'q', [])
      ->condition('q.name', 'receipt_queue', '=')
      ->fields('q', ['name', 'data', 'item_id'])
      ->execute();

    foreach ($queued as $item) {
      $data = unserialize($item->data);

      if (is_numeric($data->pid) === TRUE && $pid == $data->pid) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Takes code and returns human readable name.
   *
   * @param string $code
   *   The card type symbol.
   *
   * @return string
   *   human readable card type.
   */
  protected function cardTypeNumberToString($code) {
    return ik_cybersource_card_type_number_to_string($code);
  }

}
