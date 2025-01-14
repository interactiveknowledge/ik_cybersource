<?php

namespace Drupal\ik_cybersource\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the payment entity edit forms.
 */
class PaymentForm extends ContentEntityForm {

  /**
   * Constructs a new PaymentForm object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    protected RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $entity = $this->getEntity();
    $result = $entity->save();
    $link = $entity->toLink($this->t('View'))->toRenderable();

    $message_arguments = ['%label' => $this->entity->label()];
    $logger_arguments = $message_arguments + ['link' => $this->renderer->render($link)];

    if ($result == SAVED_NEW) {
      $this->messenger()->addStatus($this->t('New payment %label has been created.', $message_arguments));
      $this->logger('ik_cybersource')->info('Created new payment %label', $logger_arguments);
    }
    else {
      $this->messenger()->addStatus($this->t('The payment %label has been updated.', $message_arguments));
      $this->logger('ik_cybersource')->info('Updated new payment %label.', $logger_arguments);
    }

    $form_state->setRedirect('entity.payment.canonical', ['payment' => $entity->id()]);
  }

}
