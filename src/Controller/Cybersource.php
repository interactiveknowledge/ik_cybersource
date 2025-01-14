<?php

namespace Drupal\ik_cybersource\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepository;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ik_cybersource\CybersourceClient;

use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Returns responses for Cybersource routes.
 */
class Cybersource extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Cybersource controller constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *   The Filesystem factory.
   * @param \Drupal\ik_cybersource\CybersourceClient $cybersourceClient
   *   The Cybersource Client service.
   * @param \Drupal\Core\Entity\EntityRepository $entityRepository
   *   The Entity Repository service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Request Stack service.
   */
  public function __construct(
    protected $configFactory,
    protected ClientInterface $httpClient,
    protected LoggerChannelFactoryInterface $logger,
    protected FileSystem $fileSystem,
    protected CybersourceClient $cybersourceClient,
    protected EntityRepository $entityRepository,
    protected RequestStack $requestStack
    ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): Cybersource|static {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('logger.factory'),
      $container->get('file_system'),
      $container->get('ik_cybersource.cybersource_client'),
      $container->get('entity.repository'),
      $container->get('request_stack'),
    );
  }

  /**
   * Return a flex token for front-end operations.
   *
   * @return Symfony\Component\HttpFoundation\JsonResponse
   *   The Flex Token.
   */
  public function getFlexToken($webform): JsonResponse {
    $settings = $this->configFactory->get('ik_cybersource.settings');
    $request = $this->requestStack->getCurrentRequest();
    $host = 'https://' . $request->headers->get('host');

    if (isset($webform) === TRUE) {
      $environment = $settings->get($webform->get('uuid') . '_environment');
    }

    if (empty($environment) === TRUE) {
      $global = $settings->get('global');
      $environment = $global['environment'];
    }

    $this->cybersourceClient->setEnvironment($environment);
    $flexToken = $this->cybersourceClient->getFlexToken($host);

    return new JsonResponse($flexToken);
  }

}
