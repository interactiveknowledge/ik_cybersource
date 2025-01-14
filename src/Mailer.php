<?php

namespace Drupal\ik_cybersource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\webform\WebformTokenManagerInterface;

/**
 * Defines a mailer service.
 *
 * @package Drupal\ik_cybersource
 */
class Mailer {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new Mailer object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   * @param \Drupal\webform\WebformTokenManagerInterface $tokenManager
   *   The webform token manager.
   * @param \Drupal\webform\LanguageManagerInterface $languageManager
   *   The Drupal language manager.
   * @param \Drupal\webform\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected MailManagerInterface $mailManager,
    protected WebformTokenManagerInterface $tokenManager,
    protected LanguageManagerInterface $languageManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $this->loggerFactory->get('ik_cybersource');
  }

  /**
   * Sends a mail message.
   *
   * @param string $key
   *   Email unique key.
   * @param string $to
   *   The email address to send the message to.
   * @param string $subject
   *   The subject of the message.
   * @param string $body
   *   The body of the message.
   */
  public function sendMail($key, $to, $subject, $body) {
    $global = $this->configFactory->get('ik_cybersource.settings')->get('global');
    $current_langcode = $this->languageManager->getCurrentLanguage()->getId();
    if (isset($global['receipt_sender']) === TRUE) {
      $site_mail = $global['receipt_sender'];
    }
    else {
      $site_mail = $this->tokenManager->replace('[site:mail]', NULL, [], []);
    }

    $site_name = $this->tokenManager->replace('[site:name]', NULL, [], []);

    $result = $this->mailManager->mail(
      'ik_cybersource',
      $key,
      $to,
      $current_langcode,
      [
        'from_mail' => $site_mail,
        'from_name' => $site_name,
        'subject' => $subject,
        'body' => $body,
        'bcc_mail' => '',
      ],
      $site_mail,
    );

    return $result;
  }

}
