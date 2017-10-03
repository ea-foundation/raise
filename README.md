# Raise
The Free Donation Plugin for WordPress

Main featuers:
* Handle one-time donations from Stripe, PayPal, GoCardless (direct debit), BitPay (bitcoin), Skrill and bank transactions
* Handle recurring donations from Stripe, PayPal, GoCardless, Skrill and bank transactions
* Configure several form instances (property inheritance support)
* Assign donations to different accounts depending on currency or donor country
* Sandbox API support for Stripe, PayPal, GoCardless, BitPay and Skrill
* Send confirmation emails (Twig support) and notification emails
* Send webhooks for logging and mailing list signup
* Fully translatable (currently available in en and de)
* Load and cache tax deduction settings from master instance
* Country and currency detection based on donor's IP address (powered by freegeoip.net)
* Compatible to the matching campaign plugin for fundraisers

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes.

### Prerequisites

* PHP 5.6.3 or greater
* WordPress 4.8 or greater

### Installation

#### Manual

1. Download the latest zip from https://github.com/ea-foundation/eas-donation-processor.
2. On the WordPress plugin admin page, click install and upload the zip file (or upload the zip to the folder wp-content/plugins of your wordpress installation and extract all the files.)
3. Activate the plugin.

#### Git

Using git, browse to your /wp-content/plugins/ directory and clone this repository:

```
git clone https://github.com/ea-foundation/eas-donation-processor
```

Go to your Plugins screen and click Activate.

#### With Github Updater

1. Install the Github Updater plugin and activate it.
2. Go to Settings > Github Updater and open the **Install Plugin** tab.
3. Enter https://github.com/ea-foundation/eas-donation-processor and the Github access token and click **Install Plugin**.

## Tests

Requires `phpunit`

```
bash bin/install-wp-tests.sh wordpress_test <mysql_user> <mysql_pass> <mysql_host> latest
phpunit
```

## Documentation

See doc/index.html

## Built With

* [Stripe Checkout](https://stripe.com/checkout) - Stripe front-end integration
* [stripe/stripe-php](https://github.com/stripe/stripe-php) - Stripe back-end SDK
* [paypal/paypal-checkout](https://github.com/paypal/paypal-checkout) - PayPal front-end integration
* [paypal/rest-api-sdk-php](https://github.com/paypal/rest-api-sdk-php) - PayPal back-end SDK
* [gocardless/gocardless-pro-php](https://github.com/gocardless/gocardless-pro-php) - GoCardless SDK
* [bitpay/php-client](https://github.com/bitpay/php-client) - BitPay SDK
* [twigphp/Twig](https://github.com/twigphp/twig) - Template Engine (used for formatting confirmation emails)
* [josdejong/jsoneditor](https://github.com/josdejong/jsoneditor) - Editor for options page

## License

GNU GPLv3
