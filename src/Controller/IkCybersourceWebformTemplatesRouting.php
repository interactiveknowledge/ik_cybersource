<?php

namespace Drupal\ik_cybersource\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Cybersource Webform Templates routes.
 */
class IkCybersourceWebformTemplatesRouting extends ControllerBase {

  /**
   * Redirects to the Cybersource webform templates.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function routeToCybersourceTemplates(): RedirectResponse {
    $url = Url::fromRoute('entity.webform.templates.manage', [], ['query' => ['category' => 'Cybersource']])->toString();
    return new RedirectResponse($url, 307);
  }

}
