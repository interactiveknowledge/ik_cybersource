<?php

namespace Drupal\ik_cybersource;

use CyberSource\Api\CaptureApi;
use CyberSource\Api\CustomerApi;
use CyberSource\Api\CustomerPaymentInstrumentApi;
use CyberSource\Api\InstrumentIdentifierApi;
use CyberSource\Api\MicroformIntegrationApi;
use CyberSource\Api\PaymentInstrumentApi;
use CyberSource\Api\PaymentsApi;
use CyberSource\Api\SearchTransactionsApi;
use CyberSource\Api\TransactionDetailsApi;
use CyberSource\ApiClient;
use CyberSource\ApiException;
use CyberSource\Authentication\Core\MerchantConfiguration;
use CyberSource\Configuration;
use CyberSource\Logging\LogConfiguration;
use CyberSource\Model\CapturePaymentRequest;
use CyberSource\Model\CreatePaymentRequest;
use CyberSource\Model\CreateSearchRequest;
use CyberSource\Model\GenerateCaptureContextRequest;
use CyberSource\Model\PostInstrumentIdentifierRequest;
use CyberSource\Model\Ptsv2paymentsClientReferenceInformation;
use CyberSource\Model\Ptsv2paymentsidcapturesOrderInformation;
use CyberSource\Model\Ptsv2paymentsidcapturesOrderInformationAmountDetails;
use CyberSource\Model\Ptsv2paymentsMerchantDefinedInformation;
use CyberSource\Model\Ptsv2paymentsOrderInformation;
use CyberSource\Model\Ptsv2paymentsOrderInformationAmountDetails;
use CyberSource\Model\Ptsv2paymentsOrderInformationBillTo;
use CyberSource\Model\Ptsv2paymentsOrderInformationShipTo;
use CyberSource\Model\Ptsv2paymentsPaymentInformation;
use CyberSource\Model\Ptsv2paymentsPaymentInformationCard;
use CyberSource\Model\Ptsv2paymentsPaymentInformationCustomer;
use CyberSource\Model\Ptsv2paymentsProcessingInformation;
use CyberSource\Model\Ptsv2paymentsProcessingInformationAuthorizationOptions;
use CyberSource\Model\Ptsv2paymentsProcessingInformationAuthorizationOptionsInitiator;
use CyberSource\Model\Ptsv2paymentsProcessingInformationAuthorizationOptionsInitiatorMerchantInitiatedTransaction;
use CyberSource\Model\Ptsv2paymentsTokenInformation;
use CyberSource\Model\Tmsv2customersEmbeddedDefaultPaymentInstrumentBillTo;
use CyberSource\Model\Tmsv2customersEmbeddedDefaultPaymentInstrumentCard;
use CyberSource\Model\Tmsv2customersEmbeddedDefaultPaymentInstrumentEmbeddedInstrumentIdentifierCard;
use CyberSource\Model\Tmsv2customersEmbeddedDefaultPaymentInstrumentInstrumentIdentifier;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * CybersourceClient service creates Cybersource objects and makes requests.
 */
class CybersourceClient {

  /**
   * The client authentication type.
   *
   * @var mixed
   */
  protected $auth;

  /**
   * Set client authentication.
   *
   * @param mixed $auth
   *   The client authentication type.
   */
  public function setAuth($auth): void {
    $this->auth = $auth;
  }

  /**
   * Set the request host.
   *
   * @param string $requestHost
   *   The request host url.
   */
  public function setRequestHost(string $requestHost): void {
    $this->requestHost = $requestHost;
  }

  /**
   * Set the merchant id.
   *
   * @param mixed $merchantId
   *   The merchant id.
   */
  public function setMerchantId(mixed $merchantId): void {
    $this->merchantId = $merchantId;
  }

  /**
   * Set the merchant key.
   *
   * @param mixed $merchantKey
   *   The merchant key.
   */
  public function setMerchantKey(mixed $merchantKey): void {
    $this->merchantKey = $merchantKey;
  }

  /**
   * Set the merchant secret key.
   *
   * @param mixed $merchantSecretKey
   *   The merchant secret key.
   */
  public function setMerchantSecretKey(mixed $merchantSecretKey): void {
    $this->merchantSecretKey = $merchantSecretKey;
  }

  /**
   * Set the certificate directory.
   *
   * @param mixed $certificateDirectory
   *   The certificate directory.
   */
  public function setCertificateDirectory($certificateDirectory): void {
    $this->certificateDirectory = $certificateDirectory;
  }

  /**
   * Set the certificate file.
   *
   * @param mixed $certificateFile
   *   The certificate file.
   */
  public function setCertificateFile($certificateFile): void {
    $this->certificateFile = $certificateFile;
  }

  /**
   * Set the certificate password.
   *
   * @param string $certificatePassword
   *   The certificate password.
   */
  public function setCertificatePassword(string $certificatePassword): void {
    $this->certificatePassword = $certificatePassword;
  }

  /**
   * Set the payload.
   *
   * @param mixed $payload
   *   The payload.
   */
  public function setPayload($payload): void {
    $this->payload = $payload;
  }

  /**
   * Set the merchant configuration.
   *
   * @param mixed $merchantConfiguration
   *   The merchant configuration.
   */
  public function setMerchantConfiguration($merchantConfiguration): void {
    $this->merchantConfiguration = $merchantConfiguration;
  }

  /**
   * Set the client settings.
   *
   * @param mixed $settings
   *   The client settings.
   */
  public function setSettings($settings): void {
    $this->settings = $settings;
  }

  /**
   * Set the API client.
   *
   * @var mixed $apiClient
   *   The API client.
   */
  public function setApiClient($apiClient): void {
    $this->apiClient = $apiClient;
  }

  /**
   * The request host.
   *
   * @var bool
   *   hostname.
   */
  protected $requestHost;

  /**
   * The merchant id.
   *
   * @var mixed
   */
  protected $merchantId;

  /**
   * The merchant key.
   *
   * @var mixed
   */
  protected $merchantKey;

  /**
   * The merchant secret key.
   *
   * @var mixed
   */
  protected $merchantSecretKey;

  /**
   * The certificate directory.
   *
   * @var mixed
   */
  protected $certificateDirectory;

  /**
   * The certificate file.
   *
   * @var mixed
   */
  protected $certificateFile;

  /**
   * The certificate password.
   *
   * @var string
   */
  protected $certificatePassword;

  /**
   * The payload.
   *
   * @var mixed
   */
  protected $payload;

  /**
   * The merchant configuration.
   *
   * @var mixed
   */
  protected $merchantConfiguration;

  /**
   * The client settings.
   *
   * @var mixed
   */
  protected $settings;

  /**
   * The API client.
   *
   * @var mixed
   */
  protected $apiClient;

  /**
   * Is the client ready to process transactions?
   *
   * @var bool
   */
  private $ready;

  /**
   * Constructs a CybersourceClient object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository
   *   The entity repository.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected FileSystemInterface $fileSystem,
    protected LoggerChannelFactoryInterface $logger,
    protected EntityRepositoryInterface $entityRepository,
    protected RequestStack $requestStack,
    protected MessengerInterface $messenger,
  ) {
    // Client isn't ready.
    $ready = FALSE;
    $this->setReady($ready);

    $settings = $this->configFactory->get('ik_cybersource.settings');
    $global = $settings->get('global');

    if (is_null($global) === FALSE) {
      // Initialize with development host until ready.
      $this->setRequestHost('apitest.cybersource.com');
      $this->setAuth($global['auth']);
      $this->setMerchantId($global[$global['environment']]['organization_id']);

      if (is_null($global[$global['environment']]['certificate']['fid']) === FALSE) {
        $file = $this->entityRepository->getActive('file', $global[$global['environment']]['certificate']['fid']);

        if (is_null($file) === FALSE) {
          /** @var \Drupal\file\FileInterface $file */
          $uri = $file->getFileUri();
          $dir = $this->fileSystem->dirname($uri);
          $realpath = $this->fileSystem->realpath($dir);

          $this->setCertificateDirectory($realpath . DIRECTORY_SEPARATOR);
          $this->setCertificateFile(explode('.', $this->fileSystem->basename($uri))[0]);
          $this->setCertificatePassword($global[$global['environment']]['certificate_password']);

          $ready = TRUE;
        }
      }

      $this->setupSettings();
      $this->setupMerchantConfig();

      $api_client = new ApiClient($this->settings, $this->merchantConfiguration);
      $this->setApiClient($api_client);

      // Client is ready.
      $this->setReady($ready);
      $this->setEnvironment($global['environment']);
    }
  }

  /**
   * Returns the client ID.
   *
   * Client ID is simply the version number of the package extracted from the
   * composer installed manifest. If the manifest isn't where the package
   * assumes it will be then this returns an empty string.
   *
   * @return string
   *   client id.
   */
  public function getClientId() {
    return $this->apiClient->getClientId();
  }

  /**
   * Get a flex token key.
   *
   * @param string $host
   *   Host URL.
   *
   * @return string
   *   The one-time use flex token.
   */
  public function getFlexToken(string $host) {
    if ($this->isReady() === FALSE) {
      return '';
    }

    if (is_null($host) === TRUE || empty($host)) {
      $request = $this->requestStack->getCurrentRequest();
      $targetOrigin = $request->getSchemeAndHttpHost();
    }
    else {
      $targetOrigin = $host;
    }

    // CyberSource can only use unsecured HTTP on localhost development.
    if (strpos($targetOrigin, 'localhost')) {
      $targetOrigin = 'http://localhost';
    }

    $this->setPayload([
      'targetOrigins' => [$targetOrigin],
      'allowedCardNetworks' => [
        "VISA",
        "MAESTRO",
        "MASTERCARD",
        "AMEX",
        "DISCOVER",
        "DINERSCLUB",
        "JCB",
        "CUP",
        "CARTESBANCAIRES",
        "CARNET",
      ],
      "allowedPaymentTypes" => [
        "CARD",
      ],
      "clientVersion" => "v2",
    ]);

    $instance = new MicroformIntegrationApi($this->apiClient);
    $generateCaptureContextRequest = new GenerateCaptureContextRequest($this->payload);

    try {
      $keyResponse = $instance->generateCaptureContext($generateCaptureContextRequest);
      $response = $keyResponse[0];
      return $response;
    }
    catch (ApiException $e) {
      print_r($e->getResponseBody());
      print_r($e->getMessage());

      return '';
    }
  }

  /**
   * Create an instrument identifier token.
   *
   * @param array $data
   *   Instrument (credit card) data.
   *
   * @return array
   *   Response array.
   */
  public function createInstrumentIndentifier(array $data): array {
    $instrumentIdentifierApi = new InstrumentIdentifierApi($this->apiClient);
    $instrumentData = new Tmsv2customersEmbeddedDefaultPaymentInstrumentEmbeddedInstrumentIdentifierCard([
      'number' => $data['card']['number'],
    ]);
    $instrumentIdentifierRequest = new PostInstrumentIdentifierRequest([
      'card' => $instrumentData,
    ]);

    try {
      $response = $instrumentIdentifierApi->postInstrumentIdentifier($instrumentIdentifierRequest);
    }
    catch (ApiException $e) {
      $response['error'] = TRUE;
      $response['message'] = $e->getMessage();
      $response['object'] = $e;
    }
    finally {
      return $response;
    }

    return $response;
  }

  /**
   * Create payment instrument.
   *
   * @param array $data
   *   The arrayed data.
   *
   * @return array
   *   Response array.
   */
  public function createPaymentInstrument(array $data): array {
    $paymentInstrumentApi = new PaymentInstrumentApi($this->apiClient);
    $paymentInstrumentCard = new Tmsv2customersEmbeddedDefaultPaymentInstrumentCard($data['card']);
    $paymentInstrumentBillTo = new Tmsv2customersEmbeddedDefaultPaymentInstrumentBillTo($data['billTo']);
    $paymentInstrumentIdentifier = new Tmsv2customersEmbeddedDefaultPaymentInstrumentInstrumentIdentifier($data['instrumentIdentifier']);

    $paymentInstrumentRequest = [
      'instrumentIdentifier' => $paymentInstrumentIdentifier,
      'billTo' => $paymentInstrumentBillTo,
      'card' => $paymentInstrumentCard,
    ];

    try {
      $response = $paymentInstrumentApi->postPaymentInstrumentWithHttpInfo($paymentInstrumentRequest);
    }
    catch (ApiException $e) {
      $response['error'] = TRUE;
      $response['message'] = $e->getMessage();
      $response['object'] = $e;
    }
    finally {
      return $response;
    }

    return $response;
  }

  /**
   * Create a payment token object.
   *
   * @param string $token
   *   The token.
   *
   * @return \CyberSource\Model\Ptsv2paymentsTokenInformation
   *   The token information object.
   */
  public function createPaymentToken(string $token): Ptsv2paymentsTokenInformation {
    $tokenInformation = new Ptsv2paymentsTokenInformation([
      'transientTokenJwt' => $token,
    ]);

    return $tokenInformation;
  }

  /**
   * Create client reference information object.
   *
   * @param array $data
   *   The arrayed data.
   *
   * @return \CyberSource\Model\Ptsv2paymentsClientReferenceInformation
   *   The client reference information object.
   */
  public function createClientReferenceInformation(array $data) {
    $clientReferenceInformation = new Ptsv2paymentsClientReferenceInformation($data);

    return $clientReferenceInformation;
  }

  /**
   * Create order amount details.
   *
   * @param array $data
   *   The arrayed data.
   *
   * @return \CyberSource\Model\Ptsv2paymentsOrderInformationAmountDetails
   *   The order amount details object.
   */
  public function createOrderInformationAmountDetails(array $data) {
    $orderAmountDetails = new Ptsv2paymentsOrderInformationAmountDetails($data);

    return $orderAmountDetails;
  }

  /**
   * Create billing information object.
   *
   * @param array $data
   *   The arrayed data.
   *
   * @return \CyberSource\Model\Ptsv2paymentsOrderInformationBillTo
   *   The billing information object.
   */
  public function createBillingInformation(array $data) {
    $orderInformation = new Ptsv2paymentsOrderInformationBillTo($data);

    return $orderInformation;
  }

  /**
   * Create shipping info object.
   *
   * @param array $data
   *   The arrayed data.
   *
   * @return \CyberSource\Model\Ptsv2paymentsOrderInformationShipTo
   *   The shipping information object.
   */
  public function createShippingInformation(array $data) {
    $shippingInfo = new Ptsv2paymentsOrderInformationShipTo($data);

    return $shippingInfo;
  }

  /**
   * Create order information object.
   *
   * @param array $data
   *   The arrayed data.
   *
   * @return \CyberSource\Model\Ptsv2paymentsOrderInformation
   *   The order information object.
   */
  public function createOrderInformation(array $data) {
    $orderInformation = new Ptsv2paymentsOrderInformation($data);

    return $orderInformation;
  }

  /**
   * Creates processing options object.
   *
   * @param array $data
   *   The arrayed data.
   *   See \CyberSource\Model\Ptsv2paymentsProcessingInformation::_construct().
   *
   * @return \CyberSource\Model\Ptsv2paymentsProcessingInformation
   *   The processing information object.
   */
  public function createProcessingOptions(array $data = []) {
    return new Ptsv2paymentsProcessingInformation($data);
  }

  /**
   * Create processing options for recurring payments.
   *
   * Necessary for future MIT.
   *
   * @param string $previousId
   *   Set this value if this is a subsequent recurring payment.
   *
   * @return \CyberSource\Model\Ptsv2paymentsProcessingInformation
   *   The processing information object.
   */
  public function createProcessingOptionsForRecurringPayment($previousId = '') {
    // First MIT recurring payment.
    if (empty($previousId) === TRUE) {
      $subsequentPayment = FALSE;
      $initiator = new Ptsv2paymentsProcessingInformationAuthorizationOptionsInitiator([
        'credentialStoredOnFile' => TRUE,
      ]);
    }
    // Subsequent MIT payment.
    else {
      $subsequentPayment = TRUE;
      $mitTransaction = new Ptsv2paymentsProcessingInformationAuthorizationOptionsInitiatorMerchantInitiatedTransaction([
        'previousTransactionID' => $previousId,
      ]);

      $initiator = new Ptsv2paymentsProcessingInformationAuthorizationOptionsInitiator([
        'merchantInitiatedTransaction' => $mitTransaction,
        'storedCredentialUsed' => TRUE,
        'type' => 'merchant',
      ]);
    }

    $authorizationOptions = new Ptsv2paymentsProcessingInformationAuthorizationOptions([
      'initiator' => $initiator,
    ]);

    $processingInfoData = [
      'authorizationOptions' => $authorizationOptions,
    ];

    if ($subsequentPayment === TRUE) {
      $processingInfoData['commerceIndicator'] = 'recurring';
    }
    else {
      $processingInfoData['actionList'] = ['TOKEN_CREATE'];
      $processingInfoData['actionTokenTypes'] = [
        'customer',
        'paymentInstrument',
        'shippingAddress',
      ];
      $processingInfoData['capture'] = FALSE;
    }

    return new Ptsv2paymentsProcessingInformation($processingInfoData);
  }

  /**
   * Create payment information object.
   *
   * @param array $data
   *   The arrayed data.
   *
   * @return \CyberSource\Model\Ptsv2paymentsPaymentInformation
   *   The payment information object.
   */
  public function createPaymentInformation(array $data) {
    return new Ptsv2paymentsPaymentInformation($data);
  }

  /**
   * Undocumented function.
   *
   * @param string $number
   *   The card number.
   * @param string $expirationMonth
   *   The expiration month (MM).
   * @param string $expirationYear
   *   The expiration year (YYYY).
   *
   * @return \CyberSource\Model\Ptsv2paymentsPaymentInformationCard
   *   The card object.
   */
  public function createPaymentInformationCard(string $number, string $expirationMonth, string $expirationYear) {
    $card = new Ptsv2paymentsPaymentInformationCard([
      'number' => $number,
      'expirationMonth' => $expirationMonth,
      'expirationYear' => $expirationYear,
    ]);

    return $card;
  }

  /**
   * Creates the customer object in payment information.
   *
   * @param array $data
   *   The arrayed data.
   *
   * @return \CyberSource\Model\Ptsv2paymentsPaymentInformationCustomer
   *   The customer object.
   */
  public function createPaymentInformationCustomer(array $data) {
    return new Ptsv2paymentsPaymentInformationCustomer($data);
  }

  /**
   * Create a CreatePaymentRequest.
   *
   * @param array $data
   *   The arrayed data.
   *
   * @return \CyberSource\Model\CreatePaymentRequest
   *   The payment request object.
   */
  public function createPaymentRequest(array $data) {
    $paymentRequest = new CreatePaymentRequest($data);

    return $paymentRequest;
  }

  /**
   * Create payment request.
   *
   * @param \CyberSource\Model\CreatePaymentRequest $req
   *   The payment request object.
   *
   * @return \CyberSource\Model\PtsV2PaymentsPost201Response
   *   The payment response object.
   */
  public function createPayment(CreatePaymentRequest $req): array {
    $paymentsApi = new PaymentsApi($this->apiClient);

    try {
      $response = $paymentsApi->createPayment($req);
    }
    catch (ApiException $e) {
      $response['error'] = TRUE;
      $response['message'] = $e->getMessage();
      $response['object'] = $e;
    }
    finally {
      return $response;
    }

    return $response;
  }

  /**
   * Return a payment request object.
   *
   * @param string $id
   *   The transaction id.
   *
   * @return \CyberSource\Model\TssV2TransactionsGet200Response
   *   The transaction object.
   */
  public function getTransaction($id) {
    $transactionDetails = new TransactionDetailsApi($this->apiClient);

    try {
      $transaction = $transactionDetails->getTransactionWithHttpInfo($id);
    }
    catch (ApiException $e) {
      return $e;
    }

    return $transaction;
  }

  /**
   * Return a payment request object.
   *
   * @param string $id
   *   The transaction id.
   *
   * @return mixed
   *   The transaction status.
   */
  public function getTransactionStatus($id) {
    $transaction = $this->getTransaction($id);

    if (is_array($transaction) === FALSE && get_class($transaction) === 'CyberSource\ApiException') {
      $this->logger->get('ik_cybersource')->warning('Cybersource API Error.');
      return 0;
    }

    return (int) $transaction[0]->getApplicationInformation()->getReasonCode();
  }

  /**
   * Set host using an environment name. Updates merchant configuration.
   *
   * @param string $env
   *   The environment name.
   */
  public function setEnvironment(string $env) {
    $this->setReady(FALSE);

    if (!isset($this->merchantConfiguration)) {
      return;
    }

    if (strtolower($env) === 'development' || strtolower($env) === 'sandbox') {
      $this->setRequestHost('apitest.cybersource.com');
    }
    elseif (strtolower($env) === 'production') {
      $this->setRequestHost('api.cybersource.com');
    }
    else {
      $this->setRequestHost('apitest.cybersource.com');
    }

    // Update client settings.
    $configurationSettings = $this->configFactory->get('ik_cybersource.settings');
    $global = $configurationSettings->get('global');
    $environmentalSettings = $global[$env];

    $this->setMerchantId($environmentalSettings['organization_id']);

    // Find and set certificate file.
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->entityRepository->getActive('file', $environmentalSettings['certificate']['fid']);
    $uri = $file->getFileUri();
    $dir = $this->fileSystem->dirname($uri);
    $realpath = $this->fileSystem->realpath($dir);
    $this->setCertificateDirectory($realpath . DIRECTORY_SEPARATOR);
    $this->setCertificateFile(explode('.', $this->fileSystem->basename($uri))[0]);
    $this->setCertificatePassword($environmentalSettings['certificate_password']);

    $this->apiClient->getConfig()->setHost($this->requestHost);

    $this->setupSettings();
    $this->setupMerchantConfig();

    // Set new client.
    $api_client = new ApiClient($this->settings, $this->merchantConfiguration);
    $this->setApiClient($api_client);

    // Ready.
    $this->setReady(TRUE);
  }

  /**
   * Get the current environment name.
   *
   * @return string
   *   Environment name.
   */
  public function getEnvironment() {
    $hosts = [
      'apitest.cybersource.com' => 'development',
      'api.cybersource.com' => 'production',
    ];

    return $hosts[$this->requestHost];
  }

  /**
   * Get Customer data.
   *
   * @param string $customerId
   *   The customer token.
   *
   * @return array
   *   Response array.
   */
  public function getCustomerData(string $customerId) {
    $customerClient = new CustomerApi($this->apiClient);

    try {
      $customer = $customerClient->getCustomer($customerId);
    }
    catch (ApiException $e) {
      print_r($e->getResponseBody());
      print_r($e->getMessage());

      return [];
    }

    return $customer;
  }

  /**
   * Get customer Payment Instrument.
   *
   * @param string $customerId
   *   The customer token.
   * @param string $paymentInstrumentId
   *   The payment instrument token.
   *
   * @return array
   *   Response array.
   */
  public function getPaymentInstrument(string $customerId, string $paymentInstrumentId) {
    $paymentInstrumentApi = new CustomerPaymentInstrumentApi($this->apiClient);

    try {
      $pi = $paymentInstrumentApi->getCustomerPaymentInstrument($customerId, $paymentInstrumentId);
    }
    catch (ApiException $e) {
      print_r($e->getResponseBody());
      print_r($e->getMessage());

      return [];
    }

    return $pi;
  }

  /**
   * Capture an authorized payment.
   *
   * @param string $payment_id
   *   The id of the authorization.
   * @param string $code
   *   The merchant generated code.
   * @param string $amount
   *   The string amount in USD.
   *
   * @return mixed
   *   The response object.
   */
  public function capturePayment(string $payment_id, string $code, string $amount): mixed {
    $clientReferenceInformationArr = [
      "code" => $code,
    ];

    $clientReferenceInformation = new Ptsv2paymentsClientReferenceInformation($clientReferenceInformationArr);

    $orderInformationAmountDetailsArr = [
      "totalAmount" => $amount,
      "currency" => "USD",
    ];

    $orderInformationAmountDetails = new Ptsv2paymentsidcapturesOrderInformationAmountDetails($orderInformationAmountDetailsArr);

    $orderInformationArr = [
      "amountDetails" => $orderInformationAmountDetails,
    ];

    $orderInformation = new Ptsv2paymentsidcapturesOrderInformation($orderInformationArr);

    $requestObjArr = [
      "clientReferenceInformation" => $clientReferenceInformation,
      "orderInformation" => $orderInformation,
    ];

    $requestObj = new CapturePaymentRequest($requestObjArr);
    $api_instance = new CaptureApi($this->apiClient);

    try {
      $api_response = $api_instance->capturePayment($requestObj, $payment_id);

      return $api_response[0];
    }
    catch (ApiException $e) {
      print_r($e->getResponseBody());
      print_r($e->getMessage());

      return '';
    }
  }

  /**
   * Creates merchant defined information for the payment request.
   *
   * @param array $information
   *   The arrayed information.
   *
   * @return \CyberSource\Model\Ptsv2paymentsMerchantDefinedInformation[]
   *   The merchant defined information object.
   */
  public function createMerchantDefinedInformation(array $information) {
    $return = [];

    // Cybersource index starts at 1, ignore existing keys.
    $i = 1;
    foreach ($information as $value) {
      $return[] = new Ptsv2paymentsMerchantDefinedInformation([
        'key' => $i,
        'value' => $value,
      ]);

      $i++;
    }

    return $return;
  }

  /**
   * Creates and returns a Transactions search request.
   *
   * @param array $data
   *   The arrayed data.
   *
   * @return \CyberSource\Model\CreateSearchRequest
   *   The search request object.
   */
  public function createSearchRequest(array $data) {
    return new CreateSearchRequest($data);
  }

  /**
   * Processes a Transaction search.
   *
   * @param \CyberSource\Model\CreateSearchRequest $csr
   *   The search request object.
   *
   * @return array
   *   The search result.
   */
  public function createSearch(CreateSearchRequest $csr): array {
    $api_instance = new SearchTransactionsApi($this->apiClient);
    $api_instance->setApiClient($this->apiClient);
    $result = $api_instance->createSearch($csr);

    return $result;
  }

  /**
   * CyberSource client settings.
   */
  private function setupSettings() {
    $settings = new Configuration();
    $logging = new LogConfiguration();
    $logging->setDebugLogFile(__DIR__ . '/' . "debugTest.log");
    $logging->setErrorLogFile(__DIR__ . '/' . "errorTest.log");
    $logging->setLogDateFormat("Y-m-d\TH:i:s");
    $logging->setLogFormat("[%datetime%] [%level_name%] [%channel%] : %message%\n");
    $logging->setLogMaxFiles(3);
    $logging->setLogLevel("debug");
    $logging->enableLogging(TRUE);
    $settings->setLogConfiguration($logging);
    $this->setSettings($settings);
  }

  /**
   * Merchant configuration.
   */
  private function setupMerchantConfig() {
    $merchantConfiguration = new MerchantConfiguration();
    $merchantConfiguration->setAuthenticationType($this->auth);
    $merchantConfiguration->setMerchantID($this->merchantId);
    $merchantConfiguration->setApiKeyID($this->merchantKey);
    $merchantConfiguration->setSecretKey($this->merchantSecretKey);
    $merchantConfiguration->setKeyAlias($this->merchantId);
    $merchantConfiguration->setKeyFileName($this->certificateFile);
    $merchantConfiguration->setKeysDirectory($this->certificateDirectory);
    $merchantConfiguration->setKeyPassword($this->certificatePassword);
    $merchantConfiguration->setUseMetaKey(FALSE);
    $merchantConfiguration->setRunEnvironment($this->requestHost);
    $merchantConfiguration->setIntermediateHost($this->requestHost);

    $this->setMerchantConfiguration($merchantConfiguration);
  }

  /**
   * Set the client ready status.
   *
   * Leave private for now but it may be useful to have other scripts update
   * the client status later.
   *
   * @param bool $ready
   *   The ready status.
   */
  private function setReady(bool $ready) {
    $this->ready = $ready;
  }

  /**
   * Is the Client ready to make requests.
   *
   * @return bool
   *   The ready status.
   */
  public function isReady(): bool {
    return $this->ready;
  }

}
