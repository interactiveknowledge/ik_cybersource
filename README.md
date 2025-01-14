# IK Cybersource module
Integrates [Drupal](https://www.drupal.org/home), [Webform](https://www.drupal.org/project/webform), and the [Cybersource](https://www.cybersource.com/en-us.html) payments API.

## Requirements
This module extends the Drupal Webform module which must be installed.

## Installation
Add the custom github repositories to the site `composer.json` file to the repositories object so that composer knows where to find the custom Drupal modules.

```
"repositories": [
    ...
    {
        "type": "vcs",
        "url": "https://github.com/interactiveknowledge/ik_cybersource.git"
    },
    {
        "type": "vcs",
        "url": "https://github.com/interactiveknowledge/cybersource-rest-client-php.git"
    }
    ...
]
```

Require this module using composer `composer require "interactiveknowledge/ik_cybersource": "dev-main"` to install.

Enable the `ik_cybersource` module on Drupal.

### Drupal Configuration
Ensure that the private filesystem is enabled (`/admin/config/media/file-system`). The JWT Certificate can not be publicly accessible or else account security will be compromised.

## Configuration
The configuration base route is at `/admin/config/cybersource`.

### Cybersource Form Settings
This form configures the global and per-form settings related to CyberSource and other various options.

It is necessary to obtain a Merchant ID and a JWT Certificate[^1] from CyberSource.

When you add new forms their individual options will appear at the bottom of the page.

## Creating a new form
Create a new form from the Donation Webform Template installed by this module. Go to all Webform Templates (`admin/structure/webform/templates`) and select the Donation form that also has Category Cybersource. You will be presented with a form for some additional options and then to Save to create the new form. **It's necessary that the webform Category is Cybersource**.

All the necessary elements are already added to the form but most elements outside the "Payment Details" group can be changed and edited. The only other necessary element is an "amount" element which should return an integer or decimal amount of currency.

## Payment entity
Webform submissions will store incoming data from the forms. However it's not a good permanent solution to storing payment data because submissions can be deleted when forms are removed and because form submissions exist as a record of what the form receieved. The Payment entity will exist to record and track the payment and transaction information. They will not be removed if forms are deleted at a future date.

[^1]: Create a P12 Certificate for JSON Web Token Authentication https://developer.cybersource.com/docs/cybs/en-us/payouts/developer/all/rest/payouts/authentication/createCert.html
