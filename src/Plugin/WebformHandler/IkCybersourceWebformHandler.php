<?php

namespace Drupal\ik_cybersource\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ik_cybersource\Entity\Payment;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Cybersource Payments Form Submission Handler.
 *
 * The webform handler takes care of retrieving information from the payment
 * form, organizing it, passing it to the Cybersource Client, adding submission,
 * sending receipts, and creating entities.
 *
 * @WebformHandler(
 *    id="ik_cybersource_webform_handler",
 *    handler_id="ik_cybersource_webform_handler",
 *    label=@Translation("Cybersource Payments Webform Handler"),
 *    category=@Translation("Payments"),
 *    description=@Translation("Routes submission data to Cybersource Payment Processor and handles data appropriately."),
 *    cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *    results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *    submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 *    tokens = TRUE,
 *    conditions=FALSE,
 * )
 */
class IkCybersourceWebformHandler extends WebformHandlerBase {

  /**
   * Logger Factory Interface.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerFactory;

  /**
   * Config Factory Interface.
   *
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Conditions validator for webforms.
   *
   * @var WebformSubmissionConditionsValidatorInterface
   */
  protected $conditionsValidator;

  /**
   * Entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity repository.
   *
   * @var EntityRepository
   */
  protected $entityRepository;

  /**
   * The cybersource client.
   *
   * @var \Drupal\ik_cybersource\CybersourceClient
   */
  protected $cybersourceClient;

  /**
   * Drupal messenger.
   *
   * @var MessengerInterface
   */
  protected $messenger;

  /**
   * URL Generator.
   *
   * @var UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * A mail manager for sending email.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Date Formatter utility.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * Receipt hander.
   *
   * @var \Drupal\ik_cybersource\Receipts
   */
  protected $receiptHandler;

  /**
   * Mailer.
   *
   * @var \Drupal\ik_cybersource\Mailer
   */
  protected $mailer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->loggerFactory = $container->get('logger.factory')->get('ik_cybersource');
    $instance->configFactory = $container->get('config.factory');
    $instance->conditionsValidator = $container->get('webform_submission.conditions_validator');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityRepository = $container->get('entity.repository');
    $instance->cybersourceClient = $container->get('ik_cybersource.cybersource_client');
    $instance->messenger = $container->get('messenger');
    $instance->urlGenerator = $container->get('url_generator');
    $instance->languageManager = $container->get('language_manager');
    $instance->mailManager = $container->get('plugin.manager.mail');
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->receiptHandler = $container->get('ik_cybersource.receipts');
    $instance->mailer = $container->get('ik_cybersource.mailer');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'email_receipt' => TRUE,
      'email_receipt_subject' => $this->t('Your payment information'),
      'email_receipt_message' => [
        'value' => $this->t('Thank you for your payment. You will find a copy of your receipt below.'),
        'format' => 'plain_text',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): array {
    return [
      '#type' => 'markup',
      '#markup' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getId($webformId) {
    return $webformId;
  }

  /**
   * {@inheritdoc}
   */
  public function overrideSettings(array &$settings, WebformSubmissionInterface $webform_submission) {
    if ($this->cybersourceClient->isReady() !== TRUE) {
      $this->webform->setStatus(WebformInterface::STATUS_CLOSED);
      $this->webform->save();

      $this->messenger->addWarning($this->t(
        'Payment Client is not ready to deliver information to the Processor API. Please <a href="/:href">configure</a> the correct settings.',
        [
          ':href' => $this->urlGenerator->getPathFromRoute('ik_cybersource.settings_form'),
        ]
      ));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function prepareForm(WebformSubmissionInterface $webform_submission, $operation, FormStateInterface $form_state) {
    $environment = $this->getFormEnvironment();
    $form_state->setValue('environment', $environment);

    $data = $webform_submission->getData();
    $data['environment'] = $environment;
    $webform_submission->setData($data);
  }

  /**
   * Checks for form elements and if they exist remove them from check list.
   *
   * @param array $check
   *   Array of field names to check.
   * @param array $formElements
   *   All elements in the webform submission form to check against.
   */
  private function necessaryFieldCheck(array &$check, array $formElements) {
    foreach ($formElements as $element_name => $element) {
      if (count($check) === 0) {
        continue;
      }

      if (is_numeric($element_name) === FALSE && array_search($element_name, $check) !== FALSE) {
        unset($check[array_search($element_name, $check)]);
      }

      if (is_array($element) === TRUE) {
        $this->necessaryFieldCheck($check, $element);
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Transactions with the CyberSource API take place at this step. If there
   * are problems communicating with the payment processor it may be
   * communicated the user.
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    // Run field and element validation in the parent classes.
    parent::validateForm($form, $form_state, $webform_submission);

    /*
     * Validate the necessary fields exist in the form before any processing.
     * Send error if they do not.
     */
    $elements = $this->webform->getElementsDecoded();

    $necessary_fields = [
      'amount',
      'expiration_month',
      'expiration_year',
      'name',
      'address',
      'phone',
      'email',
    ];

    // @todo Turn GALA into order table.
    $isGala = FALSE;
    // $this->necessaryFieldCheck($checkGala, $elements);
    // if (count($checkGala) > 0) {
    //   $necessary_fields[] = 'direction';
    //   $isGala = FALSE;
    // }

    $this->necessaryFieldCheck($necessary_fields, $elements);

    if (count($necessary_fields) > 0) {
      $form_state->setErrorByName(array_pop($necessary_fields), 'Missing necessary fields for payment transaction. Payment transaction not processed. Contact adminstrator to update form configuration.');
    }

    // Check for any form errors, including those from parent classes.
    if ($form_state->hasAnyErrors() === TRUE) {
      return;
    }

    // Set up client and prepare data.
    $data = $webform_submission->getData();
    $form_amount = ik_cybersource_format_amount($data['amount']);

    if (is_null($form_amount) === TRUE || $form_amount < 1) {
      $form_state->setErrorByName('amount', 'Please specify a correct amount using only integers and decimals.');

      return;
    }

    $environment = $this->getFormEnvironment();
    $this->cybersourceClient->setEnvironment($environment);

    // Create JwtToken from the Microform token.
    $microformToken = $data['microform_container']['token'];
    if (is_null($microformToken) === FALSE && empty($microformToken) === FALSE) {
      $tokenInformation = $this->cybersourceClient->createPaymentToken($microformToken);
    }
    else {
      $form_state->setError($form['elements'], $this->t('No payment detected.'));

      return;
    }

    // Processing option to recurring payments.
    $processingOptions = $this->cybersourceClient->createProcessingOptions();
    $isRecurring = FALSE;
    if (isset($data['recurring']) && $data['recurring'] == TRUE) {
      $isRecurring = TRUE;
      $processingOptions = $this->cybersourceClient->createProcessingOptionsForRecurringPayment();
    }

    // Capture when authorized in same transaction.
    $processingOptions->setCapture(TRUE);

    // Client generated code.
    $prefix = $this->getCodePrefix();
    $number1 = rand(1000, 9999);
    $number2 = rand(1000, 9999);
    $data['code'] = $prefix . '-' . $number1 . '-' . $number2;

    // Create Payment Instrument.
    $billTo = [
      'firstName' => $data['name']['first'],
      'lastName' => $data['name']['last'],
      'company' => $data['company'],
      'address1' => $data['address']['address'],
      'address2' => $data['address']['address_2'],
      'locality' => $data['address']['city'],
      'administrativeArea' => $data['address']['state_province'],
      'postalCode' => $data['address']['postal_code'],
      'country' => $data['address']['country'],
      'email' => $data['email'],
      'phoneNumber' => $data['phone'],
    ];

    $orderInfoBilling = $this->cybersourceClient->createBillingInformation($billTo);

    if ($isRecurring === TRUE) {
      $shipTo = $billTo;

      unset($shipTo['email']);
      unset($shipTo['phoneNumber']);

      $orderInfoShipTo = $this->cybersourceClient->createShippingInformation($shipTo);
    }

    $clientReferenceInformation = $this->cybersourceClient->createClientReferenceInformation([
      'code' => $data['code'],
    ]);

    $amount = $this->formatAmountFromWebformData(ik_cybersource_format_amount($data['amount']));
    $amountDetails = $this->cybersourceClient->createOrderInformationAmountDetails([
      'totalAmount' => $amount,
      'currency' => 'USD',
    ]);

    $orderInformationArr = [
      'amountDetails' => $amountDetails,
      'billTo' => $orderInfoBilling,
    ];

    if ($isRecurring === TRUE) {
      $orderInformationArr['shipTo'] = $orderInfoShipTo;
    }

    $orderInformation = $this->cybersourceClient->createOrderInformation($orderInformationArr);

    $requestParameters = [
      'clientReferenceInformation' => $clientReferenceInformation,
      'orderInformation' => $orderInformation,
      'processingInformation' => $processingOptions,
      'tokenInformation' => $tokenInformation,
    ];

    // Set Merchant defined information.
    $paymentOrderDetails = [];
    if ($isGala === TRUE) {
      $merchantDefinedDataKeyValue = [];

      /*
       * THR, 2024-06-18; the old code looped through until it found a missing row, and then it stopped. However, webforms
       * will frequently have missing rows, since they auto-increment when you delete a row. Instead, just set a known limited
       * number of rows (the current index is 8, so pad with a few extra) and loop through all of them. This allows for
       * missing rows. (initialing all code modifications for this change below)
       */
      $numberOfRowsToCheck = 15;

      // Capture the part of the table focused on the gala tickets
      // (this used to be in each loop in the old code,
      // but we only need it once.)
      $galaTable = $form['elements']['tickets']['gala'];

      // $i is the row index
      for ($i = 1; $i < $numberOfRowsToCheck; $i++) {
        // The table uses a two digit code (leading zero) for table fields.
        $twoDigitKey = str_pad($i, 2, '0', STR_PAD_LEFT);
        // Check for the existence of the row -- it might be missing,
        // or we might have gotten to the end of the table (THR).
        if (isset($data['gala_' . $twoDigitKey . '_quantity'])) {
          $ticketQuantity = $data['gala_' . $twoDigitKey . '_quantity'] > 0 ? $data['gala_' . $twoDigitKey . '_quantity'] : '';
          $ticketName = $galaTable['gala_' . $twoDigitKey]['gala_' . $twoDigitKey . '_ticket_level']['#markup'];
          $ticketAmount = $galaTable['gala_' . $twoDigitKey]['gala_' . $twoDigitKey . '_amount']['#markup'];

          $merchantDefinedDataKeyValue[] = $ticketName . ' , Quantity: ' . $ticketQuantity;

          // Add order details for anything the user specified more than 0 of.
          if ($ticketQuantity > 0) {
            // The ticket amount is only relevant for order details that have a
            // price per quantity, like a table sponsorship of $2000/table.
            if (strlen($ticketAmount)) {
              // E.g., "Friend Ticket , Quantity: 1, at: $2000".
              $paymentOrderDetails[] = $ticketName . ' , Quantity: ' . $ticketQuantity . ', at: ' . $ticketAmount;
            }
            // If there's no ticket amount (e.g., for a donation),
            // show the ticket quantity as a total amount.
            else {
              // E.g., "Make a donation: $20" (if the quantity was 20)
              $paymentOrderDetails[] = $ticketName . ': $' . $ticketQuantity;
            }
          }
        }
      }

      $merchantDefinedInformation = $this->cybersourceClient->createMerchantDefinedInformation($merchantDefinedDataKeyValue);
      $requestParameters['merchantDefinedInformation'] = $merchantDefinedInformation;
    }
    else {
      $merchantDefinedDataKeyValue = [];

      if ($data['recurring'] == TRUE) {
        $paymentOrderDetails[] = $merchantDefinedDataKeyValue[] = 'This is a monthly recurring transaction.';
      }

      if (count($merchantDefinedDataKeyValue) > 0) {
        $merchantDefinedInformation = $this->cybersourceClient->createMerchantDefinedInformation($merchantDefinedDataKeyValue);
        $requestParameters['merchantDefinedInformation'] = $merchantDefinedInformation;
      }
    }

    $payRequest = $this->cybersourceClient->createPaymentRequest($requestParameters);

    $payResponse = $this->cybersourceClient->createPayment($payRequest);

    // Check for Returned errors.
    if (isset($payResponse['error']) === TRUE && $payResponse['error'] === TRUE) {
      $form_state->setError($form['elements'], $this->t(':message', [':message' => $payResponse["object"]->getResponseBody()->message]));

      return;
    }

    // @todo reuse
    $data['payment_id'] = $payResponse[0]['id'];
    $submitted = $payResponse[0]['submitTimeUtc'];
    $status = $payResponse[0]['status'];
    $declined = FALSE;

    switch ($status) {
      case 'AUTHORIZED_RISK_DECLINED':
      case 'DECLINED':
        $form_state->setError($form['elements']['payment_details'], 'Your payment request was declined.');
        $context = [
          '@message' => $payResponse[0]->getErrorInformation()->getMessage(),
        ];
        $this->loggerFactory->warning('Payment declined. @message', $context);
        $declined = TRUE;
        break;

      case 'INVALID_REQUEST':
        $form_state->setError($form['elements']['payment_details'], 'Your payment request was invalid.');
        $declined = TRUE;
        break;

      case 'SERVER_ERROR':
        $form_state->setError($form['elements']['payment_details'], 'There was a server error.');
        $declined = TRUE;
        break;

      default:
        // Nothing.
    }

    $payment = Payment::create([]);
    $payment->set('code', $data['code']);
    $payment->set('payment_id', $data['payment_id']);
    $payment->set('currency', 'USD');
    $payment->set('authorized_amount', $amount);
    $payment->set('submitted', $submitted);
    $payment->set('status', $status);
    $payment->set('recurring', $isRecurring);
    $payment->set('environment', $environment);
    $payment->set('recurring_active', FALSE);

    if ($isGala === TRUE || isset($paymentOrderDetails)) {
      $payment->set('order_details_long', implode('; ', $paymentOrderDetails));
    }

    if ($isRecurring === TRUE && $declined !== TRUE) {
      $tokens = $payResponse[0]->getTokenInformation();
      $customer = $tokens->getCustomer();
      $payment->set('customer_id', $customer->getId());
      $payment->set('recurring_active', TRUE);
      $submittedTime = strtotime($submitted);
      $payment->set('recurring_next', ik_cybersource_get_next_recurring_payment_date($submittedTime));
    }

    if ($declined !== TRUE) {
      $payment->save();
    }

    $data['payment_entity'] = $payment->id();
    $form_state->setValue('payment_entity', $payment->id());
    $data['status'] = $status;

    $webform_submission->setData($data);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    // Store the final data.
    $data = $webform_submission->getData();

    // Unset PII and payment information.
    // It is now kept in tokens on the Payment Processor.
    unset($data['name']);
    unset($data['company']);
    unset($data['address']);
    unset($data['phone']);
    unset($data['card_type']);
    unset($data['expiration_month']);
    unset($data['expiration_year']);
    unset($data['microform_container']);

    // Add additional message to the confirmation.
    if ($data['status'] === 'AUTHORIZED' || $data['status'] === 'PENDING') {
      $confirmationMessageId = 'confirmation_message';
      $defaultConfirmationMessage = $this->webform->getSetting($confirmationMessageId, '');
      $message = '<h2>Thank you.</h2><p>Your payment was authorized.<p>' . PHP_EOL . $defaultConfirmationMessage;

      if ($this->configuration['email_receipt'] === TRUE) {
        $message = $message . PHP_EOL . '<p>You will receive an email copy of your receipt.</p>';
      }

      $this->webform->setSetting($confirmationMessageId, $message);
    }
    // Cases when manager must review the payment.
    elseif ($data['status'] === 'AUTHORIZED_PENDING_REVIEW') {
      $confirmationMessageId = 'confirmation_message';
      $defaultConfirmationMessage = $this->webform->getSetting($confirmationMessageId, '');

      $message = '<h2>Thank you.</h2><p>Your payment is authorized pending review.</p>';

      if ($this->configuration['email_receipt'] === TRUE) {
        $message = $message . PHP_EOL . '<p>You will receive an email copy of your receipt once processed.</p>';
      }

      $this->webform->setSetting($confirmationMessageId, $message);
    }

    $webform_submission->setData($data);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    if ($this->configuration['email_receipt'] === TRUE) {
      // Cybersource needs a few seconds before the receipt can be accessed.
      sleep(5);

      $data = $webform_submission->getData();
      $payment = $this->entityRepository->getActive('payment', $data['payment_entity']);

      // Only send email in these cases.
      if ($data['status'] === 'AUTHORIZED' || $data['status'] === 'PENDING' || $data['status'] === 'TRANSMITTED') {
        $key = $this->getWebform()->id() . '_' . $this->getHandlerId();
        $to = $this->replaceTokens('[webform_submission:values:email]', $webform_submission, [], []);
        $this->receiptHandler->trySendReceipt($this->cybersourceClient, $payment, $key, $to);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['email'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Email'),
    ];

    $form['email']['email_description'] = [
      '#type' => 'item',
      '#markup' => $this->t('You may alter information in the email including the email subject and a message which appears before payment information.'),
    ];

    $form['email']['email_receipt'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send email receipt'),
      '#description' => $this->t('Delivers a copy of the receipt to the email.'),
      '#return_value' => TRUE,
      '#default_value' => $this->configuration['email_receipt'],
    ];

    $form['email']['email_receipt_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email receipt subject'),
      '#description' => $this->t('Subject of the email receipt.'),
      '#default_value' => $this->configuration['email_receipt_subject'],
    ];

    $form['email']['email_receipt_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Email receipt message'),
      '#description' => $this->t('Message of the email receipt.'),
      '#default_value' => $this->configuration['email_receipt_message']['value'],
      '#format' => $this->configuration['email_receipt_message']['format'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValues();

    $this->configuration['email_receipt'] = $values['email']['email_receipt'];
    $this->configuration['email_receipt_subject'] = $values['email']['email_receipt_subject'];
    $this->configuration['email_receipt_message'] = $values['email']['email_receipt_message'];

    if (is_null($form_state->getValue('conditions'))) {
      $values = $form_state->setValue('conditions', []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getHandlerId() {
    if (!is_null($this->handler_id)) {
      return $this->handler_id;
    }
    else {
      $this->setHandlerId($this->pluginId);
      return $this->pluginId;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setConditions($conditions) {
    if (is_null($conditions)) {
      $conditions = [];
    }

    $this->conditions = $conditions;
    $this->conditionsResultCache = [];
    return $this;
  }

  /**
   * Parse response error from payment instrument request.
   *
   * @param mixed $error
   *   Response array.
   *
   * @return array
   *   Array of errors with details.
   */
  private function handlePiResponseError($error): array {
    $body = $error->getResponseBody();
    $formError = [];

    foreach ($body->errors as $error) {
      foreach ($error->details as $detail) {
        $formError[] = $detail;
      }
    }

    return $formError;
  }

  /**
   * Grab the environment string for this webform.
   *
   * @return string
   *   Environment name.
   */
  private function getFormEnvironment() {
    $settings = $this->configFactory->get('ik_cybersource.settings');
    $webform_id = $this->webform->get('uuid');
    $environment = $settings->get($webform_id . '_environment');

    if (empty($environment) && $this->cybersourceClient->isReady() === TRUE) {
      $global = $settings->get('global');
      return $global['environment'];
    }
    elseif ($this->cybersourceClient->isReady() === FALSE) {
      return '';
    }
    else {
      return $environment;
    }
  }

  /**
   * Get the code prefix for this form.
   *
   * @return string
   *   Code prefix.
   */
  private function getCodePrefix() {
    $settings = $this->configFactory->get('ik_cybersource.settings');
    $webform_id = $this->webform->get('uuid');
    $prefix = $settings->get($webform_id . '_code');

    if (empty($prefix) === TRUE) {
      return 'DONATE';
    }
    else {
      return $prefix;
    }
  }

  /**
   * Formats the amount received from the form.
   *
   * @param string $dataAmount
   *   The amount from the form.
   *
   * @return string
   *   The formatted amount.
   */
  private function formatAmountFromWebformData(string $dataAmount) {
    return strpos($dataAmount, '.') > 0 ? $dataAmount : $dataAmount . '.00';
  }

}
