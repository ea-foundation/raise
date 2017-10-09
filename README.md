# Raise

Donation plugin for Wordpress. Supports [confirmation](#confirmation-email) and [notification](#notification-emails) mails, [webhooks](#webhooks), a newsletter checkbox, a [tax deductibility checkbox](#tax-deduction), multiple purposes, custom colors, multiple [form inheritance](#inheritance), sandbox mode, [centralized settings](#dedicated-plugin) and [i18n/translations](#translations). Accept donations via [Stripe](#stripe), [PayPal](#paypal), [GoCardless](#gocardless), [BitPay](#bitpay), [Skrill](#skrill) or [bank transfers](#bank-transfers).

![Screenshot of Raise - The Free Donation Plugin for WordPress](/images/screenshot.png?raw=true)

## Prerequisites
* PHP ≥5.6.3
* WordPress ≥4.8

## Installation
For a manual installation, download this repository and follow the instructions for a [manual install](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

To receive updates, install [Github Updater](https://github.com/afragen/github-updater).

To embed the form in a page use the shortcode `[raise_form form="<form_name>" live="<true or false"][/raise_form]`.

If the `live` parameter is set to `false`, the plugin will use sandbox settings for each payment provider.


## Configuration
Configuration is done in JSON. The plugin comes with a visual JSON editor.

Intially, the default settings are loaded from `_parameters.js.php.dist`. Once a modified version is saved, the plugin fetches settings from the database.

### Full example

<pre>
{
  "organization": {
    "en": "Effective Altruism Foundation",
    "de": "Stiftung für Effektiven Altruismus"
  },
  "forms": {
    "my_form": {
      "<a href="#inheritance">inherits</a>": "parent_form",
      "amount": {
        "button": [
          10,
          30
        ],
        "button_monthly": [
          5,
          10
        ],
        "custom": true,
        "columns": 3,  # use 1,2,3,4,6,12 for optiomal display
        "currency": {  # required; cannot be overridden partially
          "eur": {
            "pattern": "%amount% €",
            "country_flag": "eu"
          },
          "usd": {
            "pattern": "USD %amount%",
            "country_flag": "us"  # Replace css/flags-few.css with flags-some.css or flags-most.css if you need more flags than CH, EU, GB, US
          }
        }
      },
      "payment": {
        "purpose": {
            "my_org": "My organisation"
        },
        "<a href="#payment-methods">provider</a>": {
          "stripe_ch": {
            "live": {
              "secret_key": "sk_live_mykey",
              "public_key": "pk_live_mykey"
            },
            "sandbox": {
              "secret_key": "sk_test_mykey",
              "public_key": "pk_test_mykey"
            }
          },
          "<a href="#bank-transfer">banktransfer</a>": {
            "accounts": {
              "DE": {
                "Beneficiary": "My organisation",
                "IBAN": "DE12500105170648489890",
                "Purpose": "%reference_number%"
              }
            }
          }
        },
        "<a href="#reference-numbers">reference_number_prefix</a>": {
          "purpose1": "ORG",
          "default": "XRG"
        },
        "<a href="#integration-with-fundraising-plugin">campaign</a>": 145,
        "extra_fields": {
          "country": false,  # move country dropdown up to required fields
          "anonymous": false,  # add anonynmous checkbox
          "comment": false. # add comment textarea
        },
        "country": {
          "initial": "geoip",  # Initial value for country dropdown
          "fallback": null  # Fallback value if GeoIP is not available, e.g. "us"
        },
        "recaptcha": {
          "site_key": "my_recaptcha_site_key",
          "secret_key": "my_secret_key"
        },
        "labels": {
          "purpose": "Purpose",
          "mailing_list": "Subscribe to updates",
          "<a href="#tax-deduction">tax_deduction</a>": {
            "default": {
              "default": {
                "default": {
                  "account": "DE",
                  "deductible": false,
                  "receipt_text": "We currently do not offer tax deductibility for %country%.",
                  "success_text": ""
                }
              }
            }
          }
        }
      },
      "finish": {
        "success_message": "Many thanks for your donation!",  # not necessary if tax deduction labels are set
        "<a href="#confirmation-email">email</a>": {
          "en": {
            "sender": "Effective Altruism Foundation",
            "address": "anne.wissemann@ea-foundation.org",
            "subject": "Thank you for your donation",
            "text": "Dear {{name}} ...",
            "html": true
          }
        },
        "<a href="#notification-emails">notification_email</a>": {
          "notify-me@example.org": {
            "country": "United States"
          }
        }
      },
      "<a href="#logging">log</a>": {
        "max": 50
      },
      "<a href="#webhooks">webhook</a>": {
        "logging": {
          "live": [
            "https://example.org/my-webhook/"
          ]
        },
        "mailing_list": {}
      }
    }
  }
}
</pre>


### Inheritance
Each form can specify a parent form from which to inherit settings. To unset options inherited, set the value to `null`.

Example: `forms > my_form > inherits: "default"`


### Dedicated plugin
If the plugin is used on several sites, it can be convenient to specify one or several distributed parent forms. Local settings can inherit from these forms. To take advantage of this feature, create a new Wordpress plugin with the function `raise_donation_processor_config`.

```php
<?php
/**
 * Plugin Name: Your config plugin
 * Plugin URI: https://github.com/your_github_account/your_plugin
 * GitHub Plugin URI: your_github_account/your_plugin
 * Description: Contains default form of the donation plugin
 * Author: Your Name
 * Version: 0.1
 */
function raise_donation_processor_config() {
    return json_decode(<<<'EOD'
{
  "forms": {
    "default": {
      "amount": {
        "button": [
          35,
          75,
        ],
...
}
EOD
    , true);
}
```



## Payment methods
Each payment key except bank transfer can be written as `{method}`, `{method}_{xx_country code}` and `{method}_{xxx_currency_code)`. The plugin will select the appropriate key according to this logic:

- If the donor does not need a tax receipt AND country field is not compulsory: currency-specific, country-specific, default

- If the donor needs a tax receipt OR country field is compulsory: country-specific, currency-specific, default

Each payment method except bank transfer object is further nested into `live` and `sandbox`.

### Bank transfer
Consists of an identifier and a list of key-value pairs which will be displayed on the confirmation page. The identifier is also used for the `account` property in `tax_deduction` and sent in the payload.

Each key will be printed in bold and translated if a [translation](#i18n) is found. Currently translated: "Bank", "Beneficiary", "BIC/SWIFT", "IBAN", "Purpose", "Reference number", "Sort code".

Supports the `%reference_number%` placeholder.

```json
"accounts": {
  "DE": {
    "Beneficiary": "My organisation",
    "IBAN": "DE12500105170648489890",
    "Purpose": "%reference_number%"
  }
}
```

![Bank transfer flow](/doc/images/bank_flow.png?raw=true)

### Reference numbers
To match bank transfers with registrations, you can declare a reference number prefix per purpose. This will get joined with a random 10-letter string and becomes available as the `%reference_number%` placeholder, e.g. "ORG-6FD5-7H91".

```json
{
  "forms": {
    "my_form": {
      "payment": {
        "reference_number_prefix": {
          "purpose1": "ORG",
          "default": "DONATION"
        }
      }
    }
  }
}
```


### Stripe
Requires `secret_key` and `public_key`. The organisation logo used in the checkout modal can be configured on the settings page.

Webhook data:
- `vendor_transaction_id`: Charge ID
- `vendor_subscription:id`: Subscription ID
- `vendor_customer_id`: Customer ID

![Stripe flow](/doc/images/stripe_flow.png?raw=true)


### PayPal
Requires `client_id` and `client_secret`. Generate credentials on PayPal Dashboard > My Apps & Credentials > [REST API apps](https://developer.paypal.com/developer/applications/).

![PayPal flow](/doc/images/paypal_flow.png?raw=true)


### GoCardless
Requires `access_token` (read-write access). Generate sandbox credentials [here](https://manage-sandbox.gocardless.com/signup).

GoCardless is currently only available in the Eurozone, UK, and Sweden. Beware of [maximum amounts](https://gocardless.com/faq/merchants/).

Webhook data:
- `vendor_customer_id`: Customer ID

![GoCardless flow](/doc/images/gocardless_flow.png?raw=true)


### BitPay
Requires `pairing_code`. Get a sandbox account [here](https://test.bitpay.com/get-started).

Does not support recurring donations. BitPay donations are registered only if the donor clicks continue in the BitPay modal.

Webhook data:
- `vendor_transaction_id`: Invoice ID

![Bitpay flow](/doc/images/bitpay_flow.png?raw=true)


### Skrill
Requires `merchant_account`. Sandbox account: "demoqcoflexible@sun-fish.com"

![Skrill flow](/doc/images/skrill_flow.png?raw=true)


## Tax deduction
Can be used to define tax deductibility rules based on country, payment method and purpose. Each level can and should have a `default` value. 

- Level 1: lowercase 2-letter country code
- Level 2: payment method
- Level 3: purpose key
- Level 4: actual rule object

More explicit settings will overwrite general ones in this order:

1. country - method - purpose
2. country - method - default
3. country - default - purpose
4. country - default - default
5. default - method - purpose
6. default - method - default
7. default - default - purpose
8. default - default - default

The rule object can contain the following parameters:

- `deductible` (boolean): If false, the tax deduction checkbox will be disabled
- `receipt_text`: Text to show next to the checkbox
- `success_text`: Text to show upon confirmation in step 3
- `account`: Account used to populate `account` in the webhook payload and select the appropriate bank details from [bank accounts](#bank-transfer).
- `provider_hover_text`: Object with language properties (e.g. "en"). The values are objects with payment provider properties (e.g. "stripe"). The values are strings that are inserted into the title property of the corresponding payment provider labels.

Non-default label settings can not be overridden partially.

Supports placeholders: `%country%`, `%payment_method%`, `%purpose%`, `%reference_number%`, `%bank_transfer_formatted%`


## Confirmation email
Keys are lowercase 2-letter language codes.
- `sender` defaults to `wp_mail` default sender.
- `address` defaults to `wp_mail` default sender
- `subject` supports variables via Twig
- `html` will send the email as `text/html` if true, else `text/plain`
- `text` supports variables via Twig

See the [Twig documentation](https://twig.symfony.com/) for placeholders. Available variables: form, mode, url, language, time, currency, amount, frequency, type, email, name, purpose, address, zip, city, country (in English), comment, success_text, receipt_text, deductible

If an `account` is referenced in `tax_deduction`, the `bank_account` variable can be dumped using the `raise.dump` macro.

```
{{ raise.dump(bank_account, 'html') }} // use 'text' if email is sent as text
```

Full example:

```json
"email": {
  "en": {
    "sender": "My organisation",
    "address": "my.org@example.org",
    "subject": "Thank you for your donation",
    "text": "Dear {{name}},\n\nNew line.",
    "html": true
  }
}
```

:warning: The email object cannot be overridden partially.


## Notifications emails
Send a notification email whenever a donation was completed. Can be a comma-separated list of email addresses or an object with sending rules:

```json
{
  "notification_email": {
    "partner-charity@example.org": {
      "type": "Bank Transfer",
      "country_code": "us"
    },
    "new-donation@example.org": {} # empty objects always pass
  }
}
```

All keys sent in webhooks can be used as rule conditions. If at least one condition does not match, the notification email is skipped.

:warning: The notification email object cannot be overridden partially.


## Webhooks
Array of webhook URLs. There are currently two options, `logging` and `mailing_list`, the latter of which will only get triggered when the subscribe checkbox was ticked. Upon successful donation a JSON object will be sent to each webhook, containing these parameters for `logging`:

form, url, mode (sandbox/live), language (ISO-639-1), time, currency, amount, type (payment provider), email, frequency, purpose, name, address, zip, city, country (in English), country_code (ISO 3166-1 alpha-2, e.g. US), comment, anonymous (yes/no), tax_receipt (yes/no), mailinglist (yes/no), account, reference, referrer, vendor_transaction_id, vendor_subscription_id, vendor_customer_id

And these for `mailing_list`:
subscription[email], subscription[name], subscription[language]

## Events
The following events are dispatched on the window object. Each event is thrown only once per page impression.

- `raise_interacted_with_donation_form`: The user has reached the second slide. The detail property of the event has the following keys: `form`, `amount`, `currency`.
- `raise_initiated_donation`: The user has clicked on the donate button on the second slide. The detail property of the event has the following keys: `form`, `amount`, `currency`, `type` (payment method), `purpose`, `account`
- `raise_completed_donation`: The user has completed a donation. Note that the payment provider may differ from the one in raise_initiated_donation if the user has aborted a previous checkout process with a different payment provider. The detail property of the event has the following keys: `form`, `amount`, `currency`, `type`, `purpose`, `account`.


## Translations
Each key containing a string displayed to the user can also be specified as an object with multiple languages.

```json
{
  "organization": {
    "en": "My organisation",
    "de": "Meine Organisation"
  }
}
```

Use [Poedit](https://poedit.net/) or a similar tool to translate or modify the `.pot` and `.po` files that come with this plugin.

## Logging
If set, the plugin will save the specified number of donations as a custom post type in the database. If the `max` number is reached, older donations will get pushed out (first-in first-out). This can be used for backup purposes, in case the webhooks fail. 

```json
{
  "forms": {
    "my_form": {
      "log": {
        "max": 50
      },
    }
  }
}

```

## Integration with fundraising plugin
To reference the post ID of a [fundraiser](https://github.com/ea-foundation/matching-campaigns), set the campaign option. The fundraiser ID can be found in the URL, e.g. `https://example.org/wp-admin/post.php?post=<fundraiser_id>&action=edit`.

```json
{
  "forms": {
    "my_form": {
      "payment": {
        "campaign": 159
      },
    }
  }
}
```

## Tests
Requires `phpunit`.

```
bash bin/install-wp-tests.sh wordpress_test <mysql_user> <mysql_pass> <mysql_host> latest
phpunit
```

## Built with

* [Stripe Checkout](https://stripe.com/checkout) - Stripe front-end integration
* [stripe/stripe-php](https://github.com/stripe/stripe-php) - Stripe back-end SDK
* [paypal/paypal-checkout](https://github.com/paypal/paypal-checkout) - PayPal front-end integration
* [paypal/rest-api-sdk-php](https://github.com/paypal/rest-api-sdk-php) - PayPal back-end SDK
* [gocardless/gocardless-pro-php](https://github.com/gocardless/gocardless-pro-php) - GoCardless SDK
* [bitpay/php-client](https://github.com/bitpay/php-client) - BitPay SDK
* [twigphp/Twig](https://github.com/twigphp/twig) - Template Engine (used for formatting confirmation emails)
* [google/recaptcha](https://github.com/google/recaptcha) - reCAPTCHA PHP client library
* [twbs/bootstrap](https://github.com/twbs/bootstrap/tree/v3-dev) - Bootstrap
* [danielfarrell/bootstrap-combobox](https://github.com/danielfarrell/bootstrap-combobox) - Combobox plugin for Bootstrap
* [josdejong/jsoneditor](https://github.com/josdejong/jsoneditor) - Editor for options page

## Contributing
A new version is released by adding the appropriate tag, matching the version number in the plugin header. Pull requests welcome.


## Versioning
We use [SemVer](http://semver.org/) for versioning.


## License
This project is licensed under the GNU GPLv3 - see the [LICENSE.md](LICENSE.md) file for details.