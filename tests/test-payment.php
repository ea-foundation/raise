<?php
/**
 * Class SampleTest
 *
 * @package Raise
 */

/**
 * Payment test case.
 */
class PaymentTest extends WP_UnitTestCase {
    /**
     * Test JSON logic rule for stripe public keys
     */
    function test_raise_get_stripe_public_keys_rule()
    {
        $formSettings = $this->getFormSettings();
        $rule         = raise_get_stripe_public_keys_rule($formSettings, 'live');

        $this->assertEquals(
            '{"if":[{"===":[{"var":"currency"},"EUR"]},"pk_live_eur",{"===":[{"var":"country_code"},"CH"]},"pk_live_ch",true,"pk_live_default"]}',
            json_encode($rule)
        );
    }

    /**
     * Test JSON logic rule for checkbox settings
     */
    function test_raise_get_checkbox_rule_jsonlogic()
    {
        $formSettings = $this->getFormSettings();
        $rule         = raise_get_checkbox_rule($formSettings['payment']['form_elements']['share_data']);

        $this->assertEquals(
            '{"if":[{"in":[{"var":"purpose"},["external_purpose_1","external_purpose_2","external_purpose_3"]]},"{\"label\":\"Share my data with %purpose_label%\",\"disabled\":false}",null]}',
            json_encode($rule)
        );
    }

    /**
     * Test JSON logic rule for checkbox settings with non JsonLogic object
     */
    function test_raise_get_checkbox_rule_object()
    {
        $formSettings = $this->getFormSettings();
        $rule         = raise_get_checkbox_rule($formSettings['payment']['form_elements']['tax_receipt']);

        $this->assertEquals(
            '"{\"label\":\"I need a tax receipt\"}"', // JSON encoded to avoid evaluation by JsonLogic
            json_encode($rule)
        );
    }

    /**
     * Test compulsory paypal settings
     */
    function test_paypal_settings()
    {
        $formSettings = $this->getFormSettings();
        $this->assertTrue(raise_payment_provider_settings_complete(
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
        $this->assertTrue(raise_payment_provider_settings_complete(
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
        $this->assertTrue(raise_payment_provider_settings_complete(
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
        $this->assertTrue(raise_payment_provider_settings_complete(
            'skrill',
            $formSettings['payment']['provider']['skrill']['live']
        ));
    }

    /**
     * Test compulsory bankaccount settings
     */
    function test_banktransfer_settings()
    {
        $this->assertTrue(raise_payment_provider_settings_complete('banktransfer', array()));
    }

    /**
     * Test all payment providers are configured correctly in live mode
     */
    function test_payment_providers_complete_in_live_mode()
    {
        $formSettings = $this->getFormSettings();
        $providers    = raise_enabled_payment_providers($formSettings, 'live');

        $this->assertEquals(count($providers), 6);
    }

    /**
     * Test all payment providers are configured correctly except paypal in sandbox mode
     */
    function test_payment_providers_complete_except_paypal_in_sandbox()
    {
        $formSettings = $this->getFormSettings();
        $providers    = raise_enabled_payment_providers($formSettings, 'sandbox');

        $this->assertEquals(count($providers), 5);
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
        $providers = raise_enabled_payment_providers($formSettings, 'live');

        $this->assertEmpty($providers);
    }

    function getFormSettings()
    {
        return json_decode(<<<'EOD'
{
  "payment": {
    "form_elements": {
        "tax_receipt": {
            "label": {
                "en": "I need a tax receipt",
                "de": "Ich brauche eine Steuerbescheinigung"
            }
        },
        "share_data": [
            {
              "value": {
                "label": {
                  "en": "Share my data with %purpose_label%",
                  "de": "Meine Daten mit %purpose_label% teilen"
                },
                "disabled": false
              },
              "if": {
                "in": [
                  {
                    "var": "purpose"
                  },
                  [
                    "external_purpose_1",
                    "external_purpose_2",
                    "external_purpose_3"
                  ]
                ]
              }
            }
        ]
    },
    "provider": {
      "stripe": [
        {
          "value": {
            "account": "EUR",
            "live": {
            "secret_key": "sk_live_eur",
            "public_key": "pk_live_eur"
            },
            "sandbox": {
            "secret_key": "sk_test_eur",
            "public_key": "pk_test_eur"
            },
            "tooltip": ""
          },
          "if": {
            "===": [
              {
                "var": "currency"
              },
              "EUR"
            ]
          }
        },
        {
          "value": {
            "account": "CH",
            "live": {
              "secret_key": "sk_live_ch",
              "public_key": "pk_live_ch"
            },
            "sandbox": {
              "secret_key": "sk_test_ch",
              "public_key": "pk_test_ch"
            },
            "tooltip": ""
          },
          "if": {
            "===": [
              {
                "var": "country_code"
              },
              "CH"
            ]
          }
        },
        {
          "value": {
            "account": "default",
            "live": {
              "secret_key": "sk_live_default",
              "public_key": "pk_live_default"
            },
            "sandbox": {
              "secret_key": "sk_test_default",
              "public_key": "pk_test_default"
            },
            "tooltip": ""
          },
          "if": true
        }
      ],
      "gocardless": {
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
          "client_secret_missing": "paypal_sandbox_client_secret"
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
        "details": {
          "Beneficiary": "Effective Altruism Foundation, Efringerstrasse 25, CH-4057 Basel, Switzerland",
          "IBAN CHF": "CH67 0023 3233 1775 4501 N",
          "IBAN EUR": "CH20 0023 3233 1775 4560 D",
          "IBAN USD": "CH79 0023 3233 1775 4561 F",
          "IBAN GBP": "CH08 0023 3233 1775 4562 T",
          "BIC/SWIFT": "UBSWCHZH80A",
          "Bank": "UBS Switzerland AG, Aeschenvorstadt 1, CH-4051 Basel, Switzerland",
          "Purpose": "%reference_number%"
        }
      }
    }
  }
}
EOD
        , true);
    }
}
