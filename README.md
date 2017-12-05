# Raise

Free donation plugin for Wordpress. Supports confirmation and notification-emails, webhooks, a newsletter checkbox, a tax deductibility checkbox, multiple purposes, custom colors, Javascript events, single form inheritance, sandbox mode, centralized settings and translations.

Accept donations via [Stripe](#stripe), [PayPal](#paypal), [GoCardless](#gocardless), [BitPay](#bitpay), [Skrill](#skrill) or [bank transfers](#bank-transfer).

![Screenshot of Raise - The Free Donation Plugin for WordPress](/images/screenshot.png?raw=true)

## Prerequisites
* PHP ≥5.6.3
* WordPress ≥4.8

## Installation
For a manual installation, download this repository and follow the instructions for a [manual install](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

To receive updates, install [Github Updater](https://github.com/afragen/github-updater).

To embed the form in a page, use the shortcode `[raise_form form="<form_name>" live="<true or false>"]`.

If the `live` parameter is set to `false`, the plugin will use the sandbox settings for each payment provider.


## Configuration
Configuration is done in JSON. The plugin comes with a visual JSON editor.

Initially, the default settings are loaded from `_parameters.js.php.dist`. Once a modified version is saved, the plugin fetches settings from the database.

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
        "currency": {
          "eur": {
            "pattern": "%amount% €",
            "country_flag": "eu",
            "minimum": 4.5
          },
          "usd": {
            "pattern": "$%amount%",
            "country_flag": "us",
            "minimum": 5
          }
        }
      },
      "payment": {
        "purpose": {
            "my_org": "My organisation"  # If first element has an empty key, no purpose is selected by default
        },
        "<a href="#payment-methods">provider</a>": {
          "stripe": {
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
          "my_org": "ORG",
          "default": "XRG"
        },
        "<a href="#integration-with-fundraising-plugin">fundraiser</a>": 145,
        "extra_fields": {
          "country": false,  # move country dropdown up to required fields
          "anonymous": false,  # add anonynmous checkbox
          "comment": false # add comment textarea
        },
        "country": {
          "initial": "geoip",  # Initial value for country dropdown, e.g. "US"
          "fallback": "US"  # Fallback value if GeoIP is not available
        },
        "recaptcha": {
          "site_key": "my_recaptcha_site_key",
          "secret_key": "my_secret_key"
        },
        "labels": {
          "purpose": "Purpose",
          "mailing_list": "Subscribe to newsletter",
          "tax_receipt": "I need a tax receipt",  # not necessary if tax deduction labels are set
          "<a href="#tax-deduction">tax_deduction</a>": {
            "default": { # country
              "default": { # payment provider
                "default": { # purpose
                  "account": "DE",
                  "deductible": false,
                  "receipt_text": "We currently do not offer tax deductibility for %country%.",
                  "success_text": ""
                }
              },
              "banktransfer": {
                "success_text": "You can now make your transfer using the bank details below.\n\n%bank_account_formatted%"
              }
            }
          }
        }
      },
      "finish": {
        "success_message": "Many thanks for your donation!",  # not necessary if tax deduction labels are set
        "<a href="#confirmation-email">email</a>": {
          "sender": {
            "en": "Effective Altruism Foundation",
            "de": "Stiftung für Effektiven Altruismus"
          },
          "address": "anne.wissemann@ea-foundation.org",
          "subject": {
            "en": "Thank you for your donation",
            "de": "Vielen Dank für Ihre Spende"
          },
          "text": {
            "en": "Dear {{ name }} ...",
            "de": "Liebe/r {{ name }} ..."
          },
          "html": true
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
          "sandbox": [
            "https://example.org/my-sandbox-logging-webhook/"
          ],
          "live": [
            "https://example.org/my-logging-webhook/"
          ]
        },
        "mailing_list": {
          "sandbox": [
            "https://example.org/my-sandbox-mailinglist-webhook/"
          ],
          "live": [
            "https://example.org/my-mailinglist-webhook/"
          ]
        }
      }
    }
  }
}
</pre>


### Inheritance
Each form can specify a parent form from which to inherit settings. To unset options inherited, set the value to `null`.

Example: `forms > my_form > inherits: "default"`

:warning: Arrays (such as `forms > my_form > webhook > logging > live`) are treated as literals. Inherited elements aren't merged.

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
Each payment key except bank transfer can be written as `{method}` (= default, always required), `{method}_{xx_country code}` and `{method}_{xxx_currency_code)`. The plugin will select the appropriate key according to this logic:

- If the donor does not need a tax receipt AND country field is not compulsory: currency-specific, country-specific (any country with same currency), default

- If the donor needs a tax receipt OR country field is compulsory: country-specific, currency-specific, default

If you want to be more explicit, you can choose custom suffixes and reference them with the `account` property in `tax_deduction`. E.g. setting it to `foo` will use `{method}_foo` (or the `foo` account in `banktransfer > accounts` for bank transfers, see below).

Each payment method except bank transfer object is further nested into `live` and `sandbox`.

### Bank transfer
Consists of an identifier and a list of key-value pairs which will be displayed on the confirmation page. The identifier is also used for the `account` property in `tax_deduction` and sent in the payload.

Each key will be printed in bold and translated if a [translation](#translations) is found. Currently translated: "Bank", "Beneficiary", "BIC/SWIFT", "IBAN", "Purpose", "Reference number", "Sort code".

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


#### Reference numbers
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

Additional webhook data:
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

Additional webhook data:
- `vendor_customer_id`: Customer ID

![GoCardless flow](/doc/images/gocardless_flow.png?raw=true)



### BitPay
Requires `pairing_code`. Get a sandbox account [here](https://test.bitpay.com/get-started).

Does not support recurring donations. BitPay donations are registered only if the donor clicks continue in the BitPay modal.

Additional webhook data:
- `vendor_transaction_id`: Invoice ID

![Bitpay flow](/doc/images/bitpay_flow.png?raw=true)


### Skrill
Requires `merchant_account`. Sandbox account: demoqcoflexible@sun-fish.com

![Skrill flow](/doc/images/skrill_flow.png?raw=true)
 



## Tax deduction
Can be used to define tax deductibility rules based on country, payment method and purpose. Each level can and should have a `default` value. 

- Level 1: lowercase 2-letter country code (`us`, `gb`, `de`, `fr`, etc. or `default`)
- Level 2: payment method (`stripe`, `paypal`, `gocardless`, `bitpay`, `skrill`, `banktransfer` or `default`)
- Level 3: purpose key (keys from `forms > my_form > payment > purpose` like `my_org` or `default`)
- Level 4: actual rule object

```json
"tax_deduction": {
  "default": {
    "default": {
      "default": {
        "deductible": true,
        "receipt_text": "Your donation is tax-deductible in %country%.",
        "success_text": "You will receive a tax receipt early next year."
      }
    },
    "paypal": {
      "SpecialPurpose": {
        "deductible": false,
        "receipt_text": "Unfortunately we cannot offer tax deductibility for donations to SpecialPurpose issued via PayPal."
      }
    }
  }
}
```

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
- `account`: Account used to populate `account` in the webhook payload and select the appropriate bank details from [bank accounts](#bank-transfer). It can also be used to enforce one particular provider account. E.g. if the account is set to `FOO` and a donor chooses Stripe, the provider `stripe_foo` is used (see `forms > my_form > payment > provider`).
- `provider_hover_text`: Object with language properties (e.g. "en") if you have several languages. The values are objects with payment provider properties (e.g. "stripe"). The values are strings that are inserted into the title property of the corresponding payment provider labels.

Supports placeholders: `%address%`, `%amount%`, `%bank_account_formatted%` (dumps bank account information referenced by the `acccount` parameter), `%city%`, `%country%`, `%currency%`, `%email%`, `%frequency%`, `%mailinglist%` (yes/no), `%name%`, `%payment_provider%`, `%reference_number%` (for bank transfers), `%tax_receipt%` (yes/no), `%zip%`


## Confirmation email

```json
"email": {
  "sender": "My organisation",
  "address": "my.org@example.org",
  "subject": "Thank you for your donation",
  "text": "Dear {{ name }},\n\nNew line.",
  "html": true
}
```

Keys are lowercase 2-letter language codes.
- `sender` defaults to `wp_mail` default sender.
- `address` defaults to `wp_mail` default sender
- `subject` supports variables via Twig
- `html` will send the email as `text/html` if true, else `text/plain` (Note: In `html` mode new lines (`\n`) are converted to `<br>` tags at runtime.)
- `text` supports variables via Twig

See the [Twig 1.x documentation](https://twig.symfony.com/doc/1.x/) for placeholders. Available variables: `form`, `mode`, `url`, `language`, `time`, `currency`, `amount`, `frequency`, `payment_provider`, `email`, `name`, `purpose`, `address`, `zip`, `city`, `country` (in English), `comment`, `success_text`, `receipt_text`, `deductible`

If an `account` is referenced in `tax_deduction`, the `bank_account` variable can be dumped using the `raise.dump` macro.

```
{{ raise.dump(bank_account, 'html') }} // use 'text' if email is sent as text
```
Internally, Raise uses `wp_mail` to send email. If you prefer sending email over SMTP, you can use a plugin like [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/) or direct a [webhook](#webhooks) to an external service like [Zapier](https://zapier.com/zapbook/email/webhook/).


## Notification emails
Send a notification email whenever a donation was completed. Can be a comma-separated list of email addresses or an object with sending rules:

```json
{
  "notification_email": {
    "partner-charity@example.org": {
      "payment_provider": "Bank Transfer",
      "country_code": "us"
    },
    "new-donation@example.org": {}
  }
}
```

All keys sent in webhooks can be used as rule conditions. If at least one condition does not match, the notification email is skipped. An empty object will always pass.

Note: The possible `payment_provider` values are `Stripe`, `PayPal`, `GoCardless`, `Skrill`, `BitPay` and `Bank Transfer`


## Webhooks
Array of webhook URLs. There are currently two options, `logging` and `mailing_list`, the latter of which will only get triggered when the subscribe checkbox was ticked. Upon successful donation a JSON object will be sent to each webhook, containing these parameters for `logging`:

`donation[form]`, `donation[url]`, `donation[mode]` (sandbox/live), `donation[language]` (ISO-639-1), `donation[time]`, `donation[currency]`, `donation[amount]`, `donation[payment_provider]`, `donation[type]` (deprecated, same as `payment_provider`), `donation[email]`, `donation[frequency]`, `donation[purpose]`, `donation[name]`, `donation[address]`, `donation[zip]`, `donation[city]`, `donation[country]` (in English), `donation[country_code]` (ISO-3166-1 alpha-2, e.g. `US`), `donation[comment]`, `donation[anonymous]` (yes/no), `donation[tax_receipt]` (yes/no), `donation[mailinglist]` (yes/no), `donation[account]`, `donation[deductible]` (yes/no), `donation[success_text]` (text on confirmation page), `donation[reference]`, `donation[referrer]`, `donation[vendor_transaction_id]`, `donation[vendor_subscription_id]`, `donation[vendor_customer_id]`

And these for `mailing_list`:
`subscription[form]`, `subscription[mode]`, `subscription[email]`, `subscription[name]`, `subscription[language]`

## Events
The following events will get dispatched to the window object. Each event is thrown only once per page impression.

- `raise_loaded_donation_form`: The user has visited the page of the donation form. The detail property of the event has the following keys: `form`.
- `raise_interacted_with_donation_form`: The user has reached the second slide. The detail property of the event has the following keys: `form`, `amount`, `currency`.
- `raise_initiated_donation`: The user has clicked on the donate button on the second slide. The detail property of the event has the following keys: `form`, `amount`, `currency`, `payment_provider`, `purpose`, `account`
- `raise_completed_donation`: The user has completed a donation. Note that the payment provider may differ from the one in raise_initiated_donation if the user has aborted a previous checkout process with a different payment provider. The detail property of the event has the following keys: `form`, `amount`, `currency`, `payment_provider`, `purpose`, `account`.


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

If the key for the language of the website is missing, the first language is used as a fallback (`en` in this case).

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
To reference the post ID of a [fundraiser](https://github.com/ea-foundation/matching-campaigns), set the fundraiser option. The fundraiser ID can be found in the URL, e.g. `https://example.org/wp-admin/post.php?post=<fundraiser_id>&action=edit`.

```json
{
  "forms": {
    "my_form": {
      "payment": {
        "fundraiser": 159
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
This project is licensed under the GNU GPLv3 - see the [LICENSE](LICENSE) file for details.