# Raise

Free donation plugin for Wordpress. Supports one-time and monthly payments, confirmation and notification-emails, webhooks, a newsletter checkbox, a tax deductibility checkbox, multiple purposes, custom colors, Javascript events, single form inheritance, sandbox mode, centralized settings and translations (German, French, Russian).

Accept donations via [Stripe](#stripe), [PayPal](#paypal), [GoCardless](#gocardless), [BitPay](#bitpay), [Coinbase](#coinbase), [Skrill](#skrill) or [bank transfers](#bank-transfer).

![Screenshot of Raise - The Free Donation Plugin for WordPress](/assets/images/screenshot.png?raw=true)

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
        "frequency": {
          "default": "once"
        },
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
            "minimum": 5,
            "minimum_monthly": 3,
            "below_minimum_message": "Please donate more than %minimum_amount%."
          }
        },
        "helper_texts": {
          "average_amount_once": {
            "en": "The average one-time donation is $84.",
            "de": "Die durschnittliche Einmalspende beträgt €84."
          },
          "average_amount_monthly": {
            "en": "The average monthly donation is $50.",
            "de": "Die durschnittliche monatliche Spende beträgt €50."
          },
          "monthly_donation_teaser": {
            "en": "popular",
            "de": "beliebt"
          }
        }
      },
      "payment": {
        "order": {
          "purpose_first": false,
          "checkboxes_last": false
        },
        "purpose": {
            "my_org": "My organisation"  # If first element has an empty key, no purpose is selected by default
        },
        "<a href="#payment-methods">provider</a>": {
          "stripe": {
            "account": "DE",
            "live": {
              "secret_key": "sk_live_mykey",
              "public_key": "pk_live_mykey"
            },
            "sandbox": {
              "secret_key": "sk_test_mykey",
              "public_key": "pk_test_mykey"
            }
          },
          "<a href="#bank-transfer">banktransfer</a>": [
            {
              "value": {
                "account": "UK",
                "details": {
                  "Beneficiary": "My Organization UK",
                  "Account number": 1234567,
                  "Sort code": "12-34-56",
                  "IBAN": "GB50 1234 1234 1234 1234 A",
                  "BIC/SWIFT": "LOYDGB2L",
                  "Bank": "Lloyds Bank Plc",
                  "Purpose": "%reference_number%"
                },
                "tooltip": {
                  "en": "No fees",
                  "de": "Gebührenfrei"
                }
              },
              "if": {
                "and": [
                  {
                    "===": [
                      {
                        "var": "country_code"
                      },
                      "GB"
                    ]
                  },
                  {
                    "!!": [
                      {
                        "var": "tax_receipt"
                      }
                    ]
                  }
                ]
              }
            },
            {
              "value": {
                "account": "US",
                "details": {
                  "Beneficiary": "My Organization USA",
                  "Account number": 123456789,
                  "Routing number": 123456789,
                  "Bank": "JPMorgan Chase Bank, 188 Spear St, Ste 190, San Francisco, CA 94105, United States",
                  "Purpose": "%reference_number%"
                },
                "tooltip": {
                  "en": "No fees",
                  "de": "Gebührenfrei"
                }
              },
              "if": {
                "===": [
                  {
                    "var": "country_code"
                  },
                  "US"
                ]
              }
            },
            {
              "value": {
                "account": "DE",
                "details": {
                  "Beneficiary": "My Organization Germany",
                  "IBAN CHF": "DE67 1234 1234 1234 1234 N",
                  "IBAN EUR": "DE20 1234 1234 1234 1234 D",
                  "IBAN USD": "DE79 1234 1234 1234 1234 F",
                  "IBAN GBP": "DE08 1234 1234 1234 1234 T",
                  "BIC/SWIFT": "DEUTINBBPBC",
                  "Bank": "Deutsche Bank",
                  "Purpose": "%reference_number%"
                },
                "tooltip": {
                  "en": "Banks may charge a fee for international transactions.",
                  "de": "Banken können Gebühren auf internationale Überweisungen erheben."
                }
              },
              "if": true
            }
          ]
        },
        "account_description": {
            "DE": "Your donation will be received by Your Charity Germany, a German charitable association which collects donations on our behalf.",
            "US": "Your donation will be received by Your Charity US, an American charitable association which collects donations on our behalf.",
            "UK": "Your donation will be received directly by Your Charity UK, a registered charity in England and Wales (charity no. 123)."
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
        "helper_texts": {
          "donor_extra_info_start": {
            "en": "To issue a tax receipt we need your address.",
            "de": "Um Ihnen eine Spendenbescheinigung auszustellen, benötigen wir Ihre Adresse."
          }
        },
        "country": {
          "initial": "ipstack",  # Initial value for country dropdown, e.g. "US". Default is "ipstack"
          "ipstack_access_key": "abc1234", # Necessary if initial is "ipstack". See ipstack.com
          "ipstack_fallback": "US" # Fallback country if ipstack API is not available
        },
        "recaptcha": {
          "site_key": "my_recaptcha_site_key",
          "secret_key": "my_secret_key"
        },
        "labels": {
          "purpose": {
            "en": "Charity",
            "de": "Organisation"
          }
        },
        "form_elements": {
          "tip": [
            {
              "value": {
                "label": {
                  "en": "Add 5% tip",
                  "de": "5% Trinkgeld hinzufügen"
                },
                "tip_percentage": 5,
                "checked": true
              },
              "if": {
                "in": [
                  {
                    "var": "purpose"
                  },
                  [
                    "purpose_1",
                    "purpose_2"
                  ]
                ]
              }
            }
          ],
          "gift_aid": [
            {
              "value": {
                "label": {
                  "en": "I want to claim Gift Aid on this donation (and recurring instances of it).",
                  "de": "Ich möchte für diese Spende (und für wiederkehrende Spenden) Gift Aid beantragen."
                }
              },
              "if": {
                "in": [
                  {
                    "var": "country_code"
                  },
                  [
                    "GB"
                  ]
                ]
              }
            }
          ],
          "tax_receipt": [
            {
              "info": "DE: tax-deductible", // Label
              "value": {
                "label": {
                  "en": "I need a tax receipt for Germany.",
                  "de": "Ich benötige eine Steuerbescheinigung für Deutschland."
                },
                "checkbox_hidden": false,
              },
              "if": {
                "===": [
                  {
                    "var": "country_code"
                  },
                  "DE"
                ]
              }
            },
            {
              "info": "not supported",
              "value": {
                "label": {
                  "en": "We currently don't offer tax receipts for %country%.",
                  "de": "Wir können zurzeit leider keine Steuerbescheinigungen für %country% ausstellen."
                },
                "disabled": true
              },
              "if": true
            }
          ],
          "share_data": [
            {
              "value": {
                "label": null,
                "disabled": false
              },
              "if": {
                "in": [
                  {
                    "var": "purpose"
                  },
                  [
                    "purpose_1",
                    "purpose_2",
                    "purpose_3"
                  ]
                ]
              }
            },
            {
              "value": {
                "label": {
                  "en": "Share my data with %purpose_label%",
                  "de": "Meine Daten mit %purpose_label% teilen"
                },
                "disabled": false
              },
              "if": true
            }
          ],
          "mailing_list": {
            "en": "Subscribe me to EA updates.",
            "de": "Updates abonnieren"
          }
        }
      },
      "finish": {
        "success_message": {
          "en": "Thank you very much for your donation!",
          "de": "Vielen Dank für Ihre Spende!"
        },
        "post_donation_instructions": [
          {
            "info": "banktransfer",
            "value": {
              "en": "You can now make your transfer using the bank details below.\n\n%bank_account_formatted%\n\nIf you would like to make several donations right now, you can transfer the total sum in one payment and include the individual purpose numbers in the bank transfer annotation field.",
              "de": "Sie können nun Ihre Überweisung anhand der Bankverbindung unten tätigen.\n\n%bank_account_formatted%\n\nFür die Überweisung mehrerer Spenden können Sie eine Sammelüberweisung über die Gesamtsumme aufsetzen und alle Referenznummern im Verwendungszweck vermerken."
            },
            "if": {
              "===": [
                {
                  "var": "payment_provider"
                },
                "Bank Transfer"
              ]
            }
          },
          {
            "info": "show nothing",
            "value": "",
            "if": true
          }
        ],
        "<a href="#confirmation-email">email</a>": {
          "sender": {
            "en": "My Organization",
            "de": "Meine Organisation"
          },
          "address": "alice@example.com",
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

:warning: The values in arrays (such as `forms > my_form > amount > button`) are prepended to their parent counterpart.

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
          75
        ],
...
}
EOD
    , true);
}
```

#### JsonLogic

The following properties can be specified in an array format that lets you encode `if ... else if ... else` rules:
- `forms > my_form > payment > provider > (banktransfer|stripe|paypal|gocardless|bitpay|coinbase|skrill)`
- `forms > my_form > payment > form_elements > tax_receipt`
- `forms > my_form > payment > form_elements > gift_aid`
- `forms > my_form > payment > form_elements > share_data`
- `forms > my_form > payment > form_elements > tip`
- `forms > my_form > finish > post_donation_instructions`

The `value` objects for the checkbox form elements (`forms > my_form > form_elements > *`) can take the following properties:
- `label`: Checkbox label (string or object)
- `disabled`: Checkbox disabled (boolean). Default is `false`.
- `checked`: Checkbox checked by default (boolean). Default is `false`.

The `value` objects for `forms > my_form > form_elements > tip` also take `tip_percentage`.

Note: Replace `my_form` with the name of the corresponding form in your config.

Essentially, instead of assigning the usual object to the above properties, you assign an array of objects that each have a condition. So instead of 

```json
"some_obejct_property": {
  "subproperty1": "foo",
  "subproperty2": "foo"
}
```

you get

```json
"some_obejct_property": [
  {
    "value": {
      "subproperty1": "foo 1",
      "subproperty2": "foo 2"
    },
    "if": {
      "===": [
        {
          "var": "purpose"
        },
        "purpose_1"
      ]
    }
  },
  {
    "value": {
      "subproperty1": "foo 3",
      "subproperty2": "foo 4"
    },
    "if": {
      "in": [
        {
          "var": "country_code"
        },
        [
          "CH",
          "DE",
          "AT"
        ]
      ]
    }
  },
  {
    "value": {
      "subproperty1": "foo 5",
      "subproperty2": "foo 6"
    },
    "if": true
  }
]
```

All objects in the array must have a `value` property and an `if` property. `value` has whatever type the corresponding property supports. The `if` property contains a [JsonLogic](http://jsonlogic.com/) rule.

The objects are evaluated top down. As soon as one `if` rule matches, the property is assigned the corresponding `value` object.

If you leave away the final catch-all node (`"if": true`), the value `null` is returned.

See list of supported [JsonLogic operations](http://jsonlogic.com/operations.html).

### Donation property placeholders

The following placeholder can be used in strings: `%currency%`, `%amount%`, `%frequency%`, `%frequency_label%` (localized), `%payment_provider%`, `%payment_provider_label%` (localized), `%email%`, `%name%`, `%purpose%` (key), `%purpose_label%`, `%address%`, `%zip%`, `%city%`, `%country_code%`, `%country%` (in English), `%comment%`, `%account%`, `%reference%` (in post_donation_instruction only), `%tax_receipt_label%` (yes/no, localized), `%share_data_label%` (yes/no, localized), `%tip_label%` (yes/no, localized), `%mailinglist_label%` (yes/no, localized)

## Payment methods
Each payment method except bank transfer object is further nested into `live` and `sandbox`.

### Bank transfer
Has a `details` property with a object of key-value pairs which can be displayed on the confirmation page using the `%bank_account_formatted%` placeholder in `post_donation_instructions`. The optional `account` property is sent in the webhook payload.

Each key will be printed in bold and translated if a [translation](#translations) is found. Currently translated: "Bank", "Beneficiary", "BIC/SWIFT", "IBAN", "Purpose", "Reference number", "Sort code".

Supports the `%reference_number%` placeholder.

```json
"banktransfer": {
  "account": "Optional identifier for the bank account the donation is eventually transferred to",
  "tooltip": "Something you want the donor to know",
  "details": {
    "Beneficiary": "My organisation",
    "IBAN": "DE12500105170648489890",
    "Purpose": "%reference_number%"
  }
}
```

The following detail keys are localized:
- Beneficiary
- IBAN
- BIC/SWIFT
- Sort code
- Routing number
- Bank
- Purpose

You can use other keys as well, but they won't be localized.

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
Requires `secret_key`, `public_key` and `signing_secret` (see `Developers` page in dashboard).

```json
"stripe": {
  "account": "Optional identifier for the bank account the donation is eventually transferred to",
  "tooltip": "Something you want the donor to know",
  "live": {
    "secret_key": "sk_live_mykey",
    "public_key": "pk_live_mykey",
    "signing_secret": "whsec_mysecret"
  },
  "sandbox": {
    "secret_key": "sk_test_mykey",
    "public_key": "pk_test_mykey",
    "signing_secret": "whsec_mysecret"
  }
}
```

Note: The Raise webhooks for logging and adding donors to mailinglists are only triggered if you also [set up a webhook in Stripe](https://dashboard.stripe.com/webhooks). Make it fire  on the `checkout.session.completed` event and point it to https://your-site.com/wp-json/raise/v1/stripe/log. Make sure you copy the signing secret correctly into your Raise configuration.

Additional webhook data:
- `vendor_transaction_id`: Stripe charge ID (for one-time donations)
- `vendor_subscription_id`: Stripe subscription ID (for recurring donations)
- `vendor_customer_id`: Stripe customer ID

![Stripe flow](/doc/images/stripe_flow.png?raw=true)



### PayPal
Requires `client_id` and `client_secret`. Generate credentials on PayPal Dashboard > My Apps & Credentials > [REST API apps](https://developer.paypal.com/developer/applications/).

```json
"paypal": {
  "account": "Optional identifier for the bank account the donation is eventually transferred to",
  "tooltip": "Something you want the donor to know",
  "live": {
    "client_id": "paypal_live_client_id",
    "client_secret": "paypal_live_client_secret"
  },
  "sandbox": {
    "client_id": "sandbox",
    "client_secret": "paypal_sandbox_client_id"
  }
}
```

Additional webhook data:
- `vendor_transaction_id`: Transaction ID (one-time)
- `vendor_subscription_id`: Agreement/Profile ID (recurring)
- `vendor_customer_id`: Payer ID

![PayPal flow](/doc/images/paypal_flow.png?raw=true)



### GoCardless
Requires `access_token` (read-write access).

```json
"gocardless": {
  "account": "Optional identifier for the bank account the donation is eventually transferred to",
  "tooltip": "Something you want the donor to know",
  "live": {
    "access_token": "gocardless_live_access_token"
  },
  "sandbox": {
    "access_token": "gocardless_sandbox_access_token"
  }
}
```

You can generate sandbox access tokens [here](https://manage-sandbox.gocardless.com/signup).

GoCardless is currently only available in the Eurozone, UK, and Sweden. Beware of [maximum amounts](https://gocardless.com/faq/merchants/).

Additional webhook data:
- `vendor_customer_id`: Customer ID

![GoCardless flow](/doc/images/gocardless_flow.png?raw=true)



### BitPay
Requires `pairing_code`.

```json
"bitpay": {
  "account": "Optional identifier for the bank account the donation is eventually transferred to",
  "tooltip": "Something you want the donor to know",
  "live": {
    "pairing_code": "bitpay_live_pairing_code"
  },
  "sandbox": {
    "pairing_code": "bitpay_sandbox_pairing_code"
  }
}
```

Bitpay does not support recurring donations.

You can make a sandbox account [here](https://test.bitpay.com/get-started). Go to Payment Tools > Manage API Tokens > Add New Token to generate a pairing code.

**Note:** Donations are registered only if the donor clicks continue in the BitPay modal.

Additional webhook data:
- `vendor_transaction_id`: Invoice ID

![Bitpay flow](/doc/images/bitpay_flow.png?raw=true)


### Coinbase
Requires `api_key`.

```json
"coinbase": {
  "account": "Optional identifier for the bank account the donation is eventually transferred to",
  "tooltip": "Something you want the donor to know",
  "live": {
    "api_key": "coinbase_sandbox_api_key"
  }
}
```

Coinbase does not support recurring donations. Also, it does not have a sandbox environment for testing.

**Note:** Donations are registered only if the donor waits until the transaction is fully verified without closing the popup.

Additional webhook data:
- `vendor_transaction_id`: Charge code

![Coinbase flow](/doc/images/coinbase_flow.png?raw=true)


### Skrill
Requires `merchant_account`.

```json
"skrill": {
  "account": "Optional identifier for the bank account the donation is eventually transferred to",
  "tooltip": "Something you want the donor to know",
  "live": {
    "merchant_account": "skrill_live_merchang_account"
  },
  "sandbox": {
    "merchant_account": "skrill_sandbox_merchang_account"
  }
}
```

You the following email as sandbox `merchant_account`: demoqcoflexible@sun-fish.com

The following credit card can be used for testing: 5438311234567890

![Skrill flow](/doc/images/skrill_flow.png?raw=true)
 

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

See the [Twig 1.x documentation](https://twig.symfony.com/doc/1.x/) for placeholders. Available variables: `form`, `mode`, `url`, `language`, `time`, `currency`, `amount`, `frequency`, `payment_provider`, `email`, `name`, `purpose`, `address`, `zip`, `city`, `country` (in English), `comment`, `post_donation_instructions`, `receipt_text`, `deductible`

If an `account` is referenced in `tax_deduction`, the `bank_account` variable can be dumped using the `raise.dump` macro.

```
{{ raise.dump(bank_account, 'html') }} // use 'text' if email is sent as text
```
Internally, Raise uses `wp_mail` to send email. If you prefer sending email over SMTP, you can use a plugin like [WP Mail SMTP](https://wordpress.org/plugins/wp-mail-smtp/) or direct a [webhook](#webhooks) to an external service like [Zapier](https://zapier.com/zapbook/email/webhook/).


## Notification emails
Send a notification email whenever a donation was completed. Can be a comma-separated list of email addresses or an array of objects. The optional `filter` property contains a JsonLogic expression. The email is only sent if it evaluates to `true`.

```json
{
  "notification_email": [
    {
      "to": "email@example.org",
      "subject": "New donation by %name%",
      "text": "See donation details below and reach out to donor",
      "filter": {
        "and": [
          {
            "===": [
              {
                "var": "payment_provider"
              },
              "Bank Transfer"
            ]
          },
          {
            "===": [
              {
                "var": "country_code"
              },
              "us"
            ]
          }
        ]
      }
    },
    {
      "to": "new-donation@example.org"
    }
  ]
}
```

All keys sent in webhooks can be used as rule conditions. If at least one condition does not match, the notification email is skipped. An empty object will always pass.

Note: The possible `payment_provider` values are `Stripe`, `PayPal`, `GoCardless`, `Skrill`, `BitPay`, `Coinbase` and `Bank Transfer`


## Webhooks
Array of webhook URLs. There are currently two options, `logging` and `mailing_list`, the latter of which will only get triggered when the subscribe checkbox was ticked. Upon successful donation a JSON object will be sent to each webhook, containing these parameters for `logging`:

`form`, `url`, `mode` (sandbox/live), `language` (ISO-639-1), `time`, `currency`, `amount` (includes tip), `payment_provider`, `email`, `frequency`, `purpose`, `name`, `address`, `zip`, `city`, `country` (in English), `country_code` (ISO-3166-1 alpha-2, e.g. `US`), `comment`, `anonymous` (yes/no), `tax_receipt` (yes/no), `gift_aid` (yes/no), `mailinglist` (yes/no), `account`, `deductible` (yes/no), `share_data` (yes/no), `share_data_offered` (yes/no), `tip` (yes/no), `tip_offered` (yes/no), `tip_amount`, `tip_percentage` (the _offered_ percentage), `post_donation_instructions` (text on confirmation page), `reference`, `referrer`, `vendor_transaction_id`, `vendor_subscription_id`, `vendor_customer_id`

And these for `mailing_list`:
`form`, `mode`, `email`, `name`, `language`

## Events
The following events will get dispatched to the window object. Each event is thrown only once per page impression.

- `raise_loaded_donation_form`: The user has visited the page of the donation form. The detail property of the event has the following keys: `form`.
- `raise_interacted_with_donation_form`: The user has reached the second slide. The detail property of the event has the following keys: `form`, `amount`, `currency`.
- `raise_initiated_donation`: The user has clicked on the donate button on the second slide. The detail property of the event has the following keys: `form`, `amount`, `currency`, `payment_provider`, `purpose`, `account`
- `raise_completed_donation`: The user has completed a donation. Note that the payment provider may differ from the one in raise_initiated_donation if the user has aborted a previous checkout process with a different payment provider. The detail property of the event has the following keys: `form`, `amount`, `currency`, `payment_provider`, `purpose`, `account`.


## Translations
Each key containing a localizable string displayed to the user can also be specified as an object with multiple languages.


```json
{
  "organization": "My organization"
}
```

```json
{
  "organization": {
    "en": "My organization",
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