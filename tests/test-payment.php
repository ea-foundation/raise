<?php
/**
 * Class SampleTest
 *
 * @package Eas_Donation_Processor
 */

/**
 * Payment test case.
 */
class PaymentTest extends WP_UnitTestCase {
    /**
     * Test country account of the country in which the donor lives
     */
    function test_stripe_nl_eur_coutry_compulsory_receipt_needed()
    {
        $formSettings = $this->getFormSettings();
        $settings     = eas_get_best_payment_provider_settings(
            $formSettings,
            'stripe',
            'live',
            true,
            'EUR',
            'NL'
        );

        $this->assertArraySubset($settings, [
            "secret_key" => "stripe_nl_live_secret_key",
            "public_key" => "stripe_nl_live_public_key",
        ]);
    }

    /**
     * Test currency account matching the donation currency because no country account
     */
    function test_stripe_de_eur_coutry_compulsory_receipt_needed()
    {
        $formSettings = $this->getFormSettings();
        $settings     = eas_get_best_payment_provider_settings(
            $formSettings,
            'stripe',
            'live',
            true,
            'EUR',
            'DE'
        );

        $this->assertArraySubset($settings, [
            "secret_key" => "stripe_eur_live_secret_key",
            "public_key" => "stripe_eur_live_public_key",
        ]);
    }

    /**
     * Test default account because no account for currency or country
     */
    function test_stripe_gb_gbp_coutry_compulsory_receipt_needed()
    {
        $formSettings = $this->getFormSettings();
        $settings     = eas_get_best_payment_provider_settings(
            $formSettings,
            'stripe',
            'live',
            true,
            'GBP',
            'GB'
        );

        $this->assertArraySubset($settings, [
            "secret_key" => "stripe_live_secret_key",
            "public_key" => "stripe_live_public_key",
        ]);
    }

	  /**
	   * Test country account of the country in which the donor lives
	   */
    function test_stripe_nl_eur_coutry_compulsory_receipt_not_needed()
    {
        $formSettings = $this->getFormSettings();
        $settings     = eas_get_best_payment_provider_settings(
            $formSettings,
            'stripe',
            'live',
            false,
            'EUR',
            'NL'
        );

		    $this->assertArraySubset($settings, [
            "secret_key" => "stripe_nl_live_secret_key",
            "public_key" => "stripe_nl_live_public_key",
        ]);
    }

    /**
     * Test currency account matching the donation currency because no country account
     */
    function test_stripe_de_eur_coutry_compulsory_receipt_not_needed()
    {
        $formSettings = $this->getFormSettings();
        $settings     = eas_get_best_payment_provider_settings(
            $formSettings,
            'stripe',
            'live',
            false,
            'EUR',
            'DE'
        );

        $this->assertArraySubset($settings, [
            "secret_key" => "stripe_eur_live_secret_key",
            "public_key" => "stripe_eur_live_public_key",
        ]);
    }

    /**
     * Test default account because no account for currency or country
     */
    function test_stripe_gb_gbp_coutry_compulsory_receipt_not_needed()
    {
        $formSettings = $this->getFormSettings();
        $settings     = eas_get_best_payment_provider_settings(
            $formSettings,
            'stripe',
            'live',
            false,
            'GBP',
            'GB'
        );

        $this->assertArraySubset($settings, [
            "secret_key" => "stripe_live_secret_key",
            "public_key" => "stripe_live_public_key",
        ]);
    }

    /**
     * Test country account of the country in which the donor lives
     */
    function test_stripe_nl_eur_country_optional_receipt_needed()
    {
        $formSettings = $this->getFormSettings();
        $formSettings['payment']['extra_fields']['country'] = false;
        $settings = eas_get_best_payment_provider_settings(
            $formSettings,
            'stripe',
            'live',
            true,
            'EUR',
            'NL'
        );

        $this->assertArraySubset($settings, [
            "secret_key" => "stripe_nl_live_secret_key",
            "public_key" => "stripe_nl_live_public_key",
        ]);
    }

    /**
     * Test currency account matching the donation currency because no country account
     */
    function test_stripe_de_eur_country_optional_receipt_needed()
    {
        $formSettings = $this->getFormSettings();
        $formSettings['payment']['extra_fields']['country'] = false;
        $settings = eas_get_best_payment_provider_settings(
            $formSettings,
            'stripe',
            'live',
            true,
            'EUR',
            'DE'
        );

        $this->assertArraySubset($settings, [
            "secret_key" => "stripe_eur_live_secret_key",
            "public_key" => "stripe_eur_live_public_key",
        ]);
    }

    /**
     * Test default account because no account for currency or country
     */
    function test_stripe_gb_gbp_country_optional_receipt_needed()
    {
        $formSettings = $this->getFormSettings();
        $formSettings['payment']['extra_fields']['country'] = false;
        $settings = eas_get_best_payment_provider_settings(
            $formSettings,
            'stripe',
            'live',
            true,
            'GBP',
            'GB'
        );

        $this->assertArraySubset($settings, [
            "secret_key" => "stripe_live_secret_key",
            "public_key" => "stripe_live_public_key",
        ]);
    }

    /**
     * Test currency account
     */
    function test_stripe_fr_eur_coutry_optional_receipt_not_needed()
    {
        $formSettings = $this->getFormSettings();
        $formSettings['payment']['extra_fields']['country'] = false;
        $settings = eas_get_best_payment_provider_settings(
            $formSettings,
            'stripe',
            'live',
            false,
            'EUR',
            'FR'
        );

        $this->assertArraySubset($settings, [
            "secret_key" => "stripe_eur_live_secret_key",
            "public_key" => "stripe_eur_live_public_key",
        ]);
    }

    /**
     * Test country account of matching currency because no currency account
     */
    function test_stripe_de_chf_coutry_optional_receipt_not_needed()
    {
        $formSettings = $this->getFormSettings();
        $formSettings['payment']['extra_fields']['country'] = false;
        $settings = eas_get_best_payment_provider_settings(
            $formSettings,
            'stripe',
            'live',
            false,
            'CHF',
            'DE'
        );

        $this->assertArraySubset($settings, [
            "secret_key" => "stripe_ch_live_secret_key",
            "public_key" => "stripe_ch_live_public_key",
        ]);
    }

    /**
     * Test default account
     */
    function test_stripe_us_usd_coutry_optional_receipt_not_needed()
    {
        $formSettings = $this->getFormSettings();
        $formSettings['payment']['extra_fields']['country'] = false;
        $settings = eas_get_best_payment_provider_settings(
            $formSettings,
            'stripe',
            'live',
            false,
            'USD',
            'US'
        );

        $this->assertArraySubset($settings, [
            "secret_key" => "stripe_live_secret_key",
            "public_key" => "stripe_live_public_key",
        ]);
    }

    /**
     * Test fake provider
     * @expectedException \Exception
     */
    function test_fake_provider()
    {
        $formSettings = $this->getFormSettings();
        $settings     = eas_get_best_payment_provider_settings(
            $formSettings,
            'foobar',
            'live',
            true,
            'EUR',
            'DE'
        );
    }

    /**
     * Test incomplete provider
     * @expectedException \Exception
     */
    function test_incomplete_provider()
    {
        $formSettings = $this->getFormSettings();
        unset($formSettings['payment']['provider']['stripe']['live']['secret_key']);
        $settings = eas_get_best_payment_provider_settings(
            $formSettings,
            'stripe',
            'live',
            true,
            'USD',
            'US'
        );

        $this->assertArraySubset($settings, [
            "secret_key" => "stripe_live_secret_key",
            "public_key" => "stripe_live_public_key",
        ]);
    }

    /**
     * Test compulsory paypal settings
     */
    function test_paypal_settings()
    {
        $formSettings = $this->getFormSettings();
        $this->assertTrue(eas_payment_provider_settings_complete(
            'paypal',
            $formSettings['payment']['provider']['paypal']['live']
        ));
    }

    /**
     * Test compulsory gocardless settings
     */
    function test_gocardless_settings()
    {
        $formSettings = $this->getFormSettings();
        $this->assertTrue(eas_payment_provider_settings_complete(
            'gocardless',
            $formSettings['payment']['provider']['gocardless']['live']
        ));
    }

    /**
     * Test compulsory bitpay settings
     */
    function test_bitpay_settings()
    {
        $formSettings = $this->getFormSettings();
        $this->assertTrue(eas_payment_provider_settings_complete(
            'bitpay',
            $formSettings['payment']['provider']['bitpay']['live']
        ));
    }

    /**
     * Test compulsory skrill settings
     */
    function test_skrill_settings()
    {
        $formSettings = $this->getFormSettings();
        $this->assertTrue(eas_payment_provider_settings_complete(
            'skrill',
            $formSettings['payment']['provider']['skrill']['live']
        ));
    }

    /**
     * Test compulsory bankaccount settings
     */
    function test_banktransfer_settings()
    {
        $this->assertTrue(eas_payment_provider_settings_complete('banktransfer', array()));
    }

    /**
     * Test all payment providers are selectable
     */
    function test_payment_providers()
    {
        $formSettings = $this->getFormSettings();
        $providers    = eas_enabled_payment_providers($formSettings, 'live');

        $this->assertArraySubset($providers, [
            'stripe',
            'gocardless',
            'paypal',
            'bitpay',
            'skrill',
            'banktransfer',
        ]);
    }

    /**
     * Test disable banktransfer
     */
    function test_disable_all_payment_providers()
    {
        $formSettings = $this->getFormSettings();
        $formSettings['payment']['provider'] = array_map(function ($val) {
            return null;
        }, $formSettings['payment']['provider']);
        $providers = eas_enabled_payment_providers($formSettings, 'live');

        $this->assertEmpty($providers);
    }

    function getFormSettings()
    {
        return json_decode(<<<'EOD'
{
  "payment": {
    "extra_fields": {
      "country": true
    },
    "provider": {
      "stripe_ch": {
        "live": {
          "secret_key": "stripe_ch_live_secret_key",
          "public_key": "stripe_ch_live_public_key"
        },
        "sandbox": {
          "secret_key": "stripe_ch_sandbox_secret_key",
          "public_key": "stripe_ch_sandbox_public_key"
        }
      },
      "stripe_nl": {
        "live": {
          "secret_key": "stripe_nl_live_secret_key",
          "public_key": "stripe_nl_live_public_key"
        },
        "sandbox": {
          "secret_key": "stripe_nl_sandbox_secret_key",
          "public_key": "stripe_nl_sandbox_public_key"
        }
      },
      "stripe_eur": {
        "live": {
          "secret_key": "stripe_eur_live_secret_key",
          "public_key": "stripe_eur_live_public_key"
        },
        "sandbox": {
          "secret_key": "stripe_eur_sandbox_secret_key",
          "public_key": "stripe_eur_sandbox_public_key"
        }
      },
      "stripe": {
        "live": {
          "secret_key": "stripe_live_secret_key",
          "public_key": "stripe_live_public_key"
        },
        "sandbox": {
          "secret_key": "stripe_sandbox_secret_key",
          "public_key": "stripe_sandbox_public_key"
        }
      },
      "gocardless_de": {
        "live": {
          "access_token": "gocardless_de_live_access_token"
        },
        "sandbox": {
          "access_token": "gocardless_de_sandbox_access_token"
        }
      },
      "gocardless": {
        "live": {
          "access_token": "gocardless_live_access_token"
        },
        "sandbox": {
          "access_token": "gocardless_sandbox_access_token"
        }
      },
      "paypal": {
        "live": {
          "client_id": "paypal_live_client_id",
          "client_secret": "paypal_live_client_secret"
        },
        "sandbox": {
          "client_id": "paypal_sandbox_client_id",
          "client_secret": "paypal_sandbox_client_secret"
        }
      },
      "bitpay": {
        "live": {
          "pairing_code": "bitpay_live_pairing_code"
        },
        "sandbox": {
          "pairing_code": "bitpay_sandbox_pairing_code"
        }
      },
      "skrill": {
        "live": {
          "merchant_account": "skrill_live_merchant_account"
        },
        "sandbox": {
          "merchant_account": "skrill_sandbox_merchant_account"
        }
      },
      "banktransfer": {
        "accounts": {
          "CH": {
            "Beneficiary": "Effective Altruism Foundation, Efringerstrasse 25, CH-4057 Basel, Switzerland",
            "IBAN CHF": "CH67 0023 3233 1775 4501 N",
            "IBAN EUR": "CH20 0023 3233 1775 4560 D",
            "IBAN USD": "CH79 0023 3233 1775 4561 F",
            "IBAN GBP": "CH08 0023 3233 1775 4562 T",
            "BIC/SWIFT": "UBSWCHZH80A",
            "Bank": "UBS Switzerland AG, Aeschenvorstadt 1, CH-4051 Basel, Switzerland",
            "Purpose": "%reference_number%"
          },
          "DE": {
            "Beneficiary": "Stiftung für Effektiven Altruismus e. V., Hardenbergstraße 9, 10623 Berlin, Deutschland",
            "IBAN": "DE30 1001 0010 0914 6391 05",
            "BIC/SWIFT": "PBNKDEFF",
            "Bank": "Postbank, DE-10916 Berlin",
            "Purpose": "%reference_number%"
          },
          "AMF_DE": {
            "Beneficiary": "Against Malaria Foundation",
            "IBAN": "DE43502109000216991001",
            "BIC/SWIFT": "CITIDEFF",
            "Bank": "Citibank N.A. Frankfurt",
            "Purpose": "%reference_number%"
          },
          "AMF_UK": {
            "Beneficiary": "Against Malaria Foundation",
            "Account number": "11193740",
            "Sort Code": "18-50-08",
            "IBAN": "GB26CITI18500811193740",
            "BIC/SWIFT": "CITIGB2L",
            "Bank": "Citibank N.A. London",
            "Purpose": "%reference_number%"
          },
          "GiveWell_US": {
            "Beneficiary": "The Clear Fund DBA GiveWell, 182 Howard St #208, San Francisco, CA 94015, United States",
            "Account number": "732857029",
            "Routing number": "322271627",
            "Bank": "JPMorgan Chase Bank, 188 Spear St, Ste 190, San Francisco, CA 94105, United States",
            "Purpose": "EAF"
          }
        }
      }
    }
  }
}
EOD
        , true);
    }
}
