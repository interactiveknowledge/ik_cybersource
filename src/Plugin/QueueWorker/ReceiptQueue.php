<?php

namespace Drupal\ik_cybersource\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\ik_cybersource\CybersourceClient;
use Drupal\ik_cybersource\Entity\Payment;
use Drupal\ik_cybersource\Receipts;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Receipt queue.
*
* @QueueWorker(
*   id = "receipt_queue",
*   title = @Translation("Receipt Queue."),
*   cron = {"time" = 60}
* )
*/
final class ReceiptQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Main constructor.
   *
   * @param array $configuration
   *   Configuration array.
   * @param mixed $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\ik_cybersource\Receipts $receiptsHandler
   *   Receipts.php.
   * @param \Drupal\ik_cybersource\CybersourceClient $client
   *   Cybersource client.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected Receipts $receiptsHandler,
    protected CybersourceClient $client
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ik_cybersource.receipts'),
      $container->get('ik_cybersource.cybersource_client')
    );
  }

  /**
   * Processes an item in the queue.
   *
   * @param mixed $data
   *   The queue item data.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function processItem($data) {
    $this->client->setEnvironment($data->environment);
    $payment = Payment::load($data->pid);
    $key = $data->key;
    $to = $data->to;

    $sent = $this->receiptsHandler->trySendReceipt($this->client, $payment, $key, $to);

    if ($sent === FALSE) {
      throw new \Exception('Email was not sent. Payment code: ' . $payment->get('code')->value);
    }

    return $sent;
  }

}
