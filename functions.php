<?php if (!defined('ABSPATH')) exit;

const RAISE_WEBHOOK_KEYS = [
    'account',
    'address',
    'amount',
    'anonymous',
    'city',
    'comment',
    'country',
    'country_code',
    'currency',
    'deductible',
    'email',
    'form',
    'frequency',
    'language',
    'mailinglist',
    'mode',
    'name',
    'purpose',
    'reference',
    'referrer',
    'post_donation_instructions',
    'share_data',
    'share_data_offered',
    'tax_receipt',
    'time',
    'tip',
    'tip_amount',
    'tip_offered',
    'tip_percentage',
    'payment_provider',
    'url',
    'vendor_customer_id',
    'vendor_subscription_id',
    'vendor_transaction_id',
    'zip',
];

/**
 * Initialize form and return form settings
 *
 * @param string $form Form name
 * @param string $mode sandbox/live
 * @return array
 */
function raise_init_donation_form($form, $mode)
{
    // Update settings
    raise_update_settings();

    // Load settings
    $formSettings = raise_load_settings($form);

    // Load logo
    $logo = get_option('raise_logo', plugin_dir_url(__FILE__) . 'images/logo.png');
    
    // Make amount patterns
    $amountPatterns = array();
    $amountMinimums = array();
    $currencies     = raise_get($formSettings['amount']['currency'], array());
    foreach ($currencies as $currency => $currencySettings) {
        $cur                  = strtoupper($currency);
        $amountPatterns[$cur] = raise_get($currencySettings['pattern'], '%amount%');
        $minimum              = raise_get($currencySettings['minimum'], 1);
        $amountMinimums[$cur] = array(
            'once'    => $minimum,
            'monthly' => raise_get($currencySettings['minimum_monthly'], $minimum),
        );
    }

    // Get array with custom below minimum amount error messages and upper-case currency keys
    $belowMinimumAmountMessages = raise_monolinguify(array_filter(array_map(
        function ($cur) {
            return raise_get($cur['below_minimum_message']);
        },
        array_change_key_case(raise_get($formSettings['amount']['currency'], []), CASE_UPPER)
    )));

    // Get post_donation_instructions and localize labels
    $postDonationInstructions = raise_get($formSettings['finish']['post_donation_instructions'], "");
    if (is_array($postDonationInstructions) && !raise_has_string_keys($postDonationInstructions)) {
        $postDonationInstructions = array_map(function($item) {
            if (!empty($item['value']) && is_array($item['value'])) {
                $item['value'] = raise_get_localized_value($item['value']);
            }
            return $item;
        }, $postDonationInstructions);
    }
    $postDonationInstructionsRule = raise_get_jsonlogic_if_rule($postDonationInstructions, 'raise_get_localized_value');

    // Get tax_receipt rule and localize labels
    $taxReceiptSettings = raise_get($formSettings['payment']['form_elements']['tax_receipt'], "");
    $taxReceiptRule     = raise_get_checkbox_rule($taxReceiptSettings);

    // Get share_data rule and localize labels
    $shareDataSettings = raise_get($formSettings['payment']['form_elements']['share_data'], "");
    $shareDataRule     = raise_get_checkbox_rule($shareDataSettings);

    // Get tip rule and localize labels
    $tipSettings = raise_get($formSettings['payment']['form_elements']['tip'], "");
    $tipRule     = raise_get_checkbox_rule($tipSettings);

    // Get bank accounts and localize their labels
    $bankAccounts = raise_get($formSettings['payment']['provider']['banktransfer']);
    if (is_array($bankAccounts) && !raise_has_string_keys($bankAccounts)) {
        $bankAccounts = array_map(function($item) {
            if (!empty($item['value']['details']) && is_array($item['value']['details'])) {
                $item['value']['details'] = raise_localize_array_keys($item['value']['details']);
            }
            if (!empty($item['value']['tooltip']) && is_array($item['value']['tooltip'])) {
                $item['value']['tooltip'] = raise_get_localized_value($item['value']['tooltip']);
            }
            return $item;
        }, $bankAccounts);
    }
    $bankAccountsRule = raise_get_jsonlogic_if_rule($bankAccounts, 'json_encode');

    // Get tooltips and payment provider display rules
    $enabledProviders = raise_enabled_payment_providers($formSettings, $mode);
    $providerSettings = raise_get($formSettings['payment']['provider'], []);
    $tooltipMap       = function ($value) { return raise_get_localized_value($value['tooltip'], ""); };
    $displayMap       = function ($value) { return true; };
    $accountMap       = function ($value) { return raise_get($value['account']); };
    $tooltipIf        = [];
    $displayIf        = [];
    $accountIf        = [];
    foreach ($enabledProviders as $provider) {
        // First level rule (provider)
        $tooltipIf[] = $displayIf[] = $accountIf[] = ['===' => [["var" => "payment_provider"], $GLOBALS['pp_key2pp_label'][$provider]]];
        // Second level rule (account)
        if (!is_array($providerSettings[$provider])) {
            $tooltipIf[] = "";
            $displayIf[] = false;
            $accountIf[] = null;
        } elseif (raise_has_string_keys($providerSettings[$provider])) {
            $tooltipIf[] = raise_get_localized_value(raise_get($providerSettings[$provider]['tooltip'], ""));
            $displayIf[] = true;
            $accountIf[] = raise_get($providerSettings[$provider]['account']);
        } else {
            $tooltipIf[] = raise_get_jsonlogic_if_rule($providerSettings[$provider], $tooltipMap, "");
            $displayIf[] = raise_get_jsonlogic_if_rule($providerSettings[$provider], $displayMap, false);
            $accountIf[] = raise_get_jsonlogic_if_rule($providerSettings[$provider], $accountMap);
        }
    }
    $tooltipIf[] = ""; // Default tooltip
    $displayIf[] = false; // Default display mode
    $paymentProviderTooltipRule = ["if" => $tooltipIf];
    $paymentProviderDisplayRule = ["if" => $displayIf];
    $paymentProviderAccountRule = ["if" => $accountIf];

    // Localize script
    wp_localize_script('donation-plugin-form', 'wordpress_vars', array(
        'logo'                            => $logo,
        'ajax_endpoint'                   => admin_url('admin-ajax.php'),
        'amount_patterns'                 => $amountPatterns,
        'amount_minimums'                 => $amountMinimums,
        'post_donation_instructions_rule' => $postDonationInstructionsRule,
        'payment_provider_tooltip_rule'   => $paymentProviderTooltipRule,
        'payment_provider_display_rule'   => $paymentProviderDisplayRule,
        'payment_provider_account_rule'   => $paymentProviderAccountRule,
        'share_data_rule'                 => $shareDataRule,
        'tip_rule'                        => $tipRule,
        'tax_receipt_rule'                => $taxReceiptRule,
        'bank_account_rule'               => $bankAccountsRule,
        'organization'                    => $GLOBALS['raiseOrganization'],
        'currency2country'                => $GLOBALS['currency2country'],
        'monthly_support'                 => $GLOBALS['monthlySupport'],
        'labels'                          => [
            'yes'                   => __("yes", "raise"),
            'no'                    => __("no", "raise"),
            'donate_button_once'    => __("Donate %currency-amount%", "raise"),
            'donate_button_monthly' => __("Donate %currency-amount% per month", "raise"),
            'donation'              => __("Donation", "raise"),
            'amount'                => __("Amount", "raise"),
            'cookie_warning'        => __("Please enable cookies before you proceed with your donation.", "raise"),
        ],
        'error_messages'       => [
            'missing_fields'              => __('Please fill out all required fields.', 'raise'),
            'invalid_email'               => __('Invalid email.', 'raise'),
            'below_minimum_amount'        => __('The minimum donation is %minimum_amount%.', 'raise'),
            'connection_error'            => __('Error establishing a connection. Please try again.', 'raise'),
            'below_minimum_amount_custom' => $belowMinimumAmountMessages,
        ],
    ));

    // Enqueue previously registered scripts and styles (to prevent them loading on every page load)
    wp_enqueue_script('donation-plugin-bootstrapjs');
    wp_enqueue_script('donation-plugin-jqueryformjs');
    wp_enqueue_script('donation-plugin-jquery-slick');
    if (in_array('stripe', $enabledProviders)) {
        wp_enqueue_script('donation-plugin-stripe');
    }
    if (in_array('paypal', $enabledProviders)) {
        wp_enqueue_script('donation-plugin-paypal');
    }
    wp_enqueue_script('donation-plugin-json-logic');
    wp_enqueue_script('donation-plugin-form');
    wp_enqueue_script('donation-plugin-combobox');

    return $formSettings;
}

/**
 * Load form settings
 *
 * @param string $form Form name
 * @return array
 * @throws \Exception
 */
function raise_load_settings($form)
{
    // Checked if loaded already
    if (isset($GLOBALS['raiseForms'][$form])) {
        return $GLOBALS['raiseForms'][$form];
    }

    // Load parameters
    $raiseSettings = json_decode(get_option('raise_settings'), true);

    // Check if config plugin is around
    $externalSettings = array();
    if (function_exists('raise_config')) {
        if ($externalSettings = raise_config()) {
            // Merge
            $raiseSettings = raise_array_replace_recursive($externalSettings, $raiseSettings);
        } else {
            throw new \Exception("Syntax error in config plugin JSON");
        }
    }

    // Load organization in current language
    $organization = !empty($raiseSettings['organization']) ? (raise_get_localized_value($raiseSettings['organization']) ?: '') : '';

    // Resolve form inheritance
    $formSettings = raise_rec_load_settings($form, $raiseSettings['forms']);

    // Remove inherits property
    unset($formSettings['inherits']);

    // Add organization and form settings to GLOBALS
    $GLOBALS['raiseOrganization'] = $organization;
    $GLOBALS['raiseForms']        = array($form => $formSettings);

    return $formSettings;
}

/**
 * Internal: Resolve form inheritance
 *
 * @param string $form
 * @param array $formsSettings
 * @param array $childForms To avoid circular inheritance
 * @return array
 * @throws \Exception
 */
function raise_rec_load_settings($form, $formsSettings, $childForms = array())
{
    if (in_array($form, $childForms)) {
        throw new \Exception("Circular form definition. See Settings > Raise");
    }

    if (!isset($formsSettings[$form])) {
        throw new \Exception("No settings found for form '$form'. See Settings > Raise");
    }

    if (!($parentForm = raise_get($formsSettings[$form]['inherits']))) {
        return $formsSettings[$form];
    }

    // Recurse and merge
    $childForms[]       = $form;
    $parentFormSettings = raise_rec_load_settings($parentForm, $formsSettings, $childForms);

    return raise_array_replace_recursive($parentFormSettings, $formsSettings[$form]);
}

/**
 * Get `if` rule with JSON encoded objects (label, disabled, checked) as return values for checkbox
 *
 * @param array|string $settings
 * @return array
 */
function raise_get_checkbox_rule($settings)
{
    if (is_array($settings)) {
        if (isset($settings['label'])) {
            $settings['label'] = raise_get_localized_value($settings['label']);
        } else {
            $settings = array_map(function($item) {
                if (!empty($item['value']['label']) && is_array($item['value']['label'])) {
                    $item['value']['label'] = raise_get_localized_value($item['value']['label']);
                }
                return $item;
            }, $settings);
        }        
    }

    return raise_get_jsonlogic_if_rule($settings, 'json_encode');
}

/**
 * Return list of enabled providers
 *
 * @param array  $formSettings
 * @param string $mode
 * @return array
 */
function raise_enabled_payment_providers($formSettings, $mode)
{
    // Get provider settings
    $providerSettings = raise_get($formSettings['payment']['provider'], array());

    // Filter out incomplete settings
    $providers = array_filter($providerSettings, function ($settings, $provider) use ($mode) {
        return strpos($provider, '_') === false // No underscores in provider key
               &&
               is_array($settings) // Must be array
               &&
               (
                    (
                        // Make sure all necessary account properties are present
                        raise_has_string_keys($settings)
                        &&
                        raise_payment_provider_settings_complete($provider, raise_get($settings[$mode], []))
                    ) || (
                        // If it's JsonLogic, do the same for all nodes
                        !raise_has_string_keys($settings)
                        &&
                        array_reduce($settings, function ($carry, $item) use ($provider, $mode) {
                            return $carry && raise_payment_provider_settings_complete($provider, raise_get($item['value'][$mode], []));
                        }, true)
                    )
               );
    }, ARRAY_FILTER_USE_BOTH);

    // Only return keys
    return array_keys($providers);
}

/**
 * Are properties complete?
 *
 * @param string $provider
 * @param array $properties
 * @param bool
 */
function raise_payment_provider_settings_complete($provider, array $properties)
{
    $requiredProperties = raise_get_payment_provider_properties($provider);

    return array_reduce($requiredProperties, function ($carry, $item) use ($properties) {
        return $carry && !empty($properties[$item]);
    }, true);
}

/**
 * Print paymnet provider HTML
 *
 * @param array  $formSettings
 * @param string $mode
 * @return string
 */
function raise_print_payment_providers($formSettings, $mode)
{
    // Get enabled providers
    $providers = raise_enabled_payment_providers($formSettings, $mode);
    $checked   = true;
    $result    = '';
    foreach ($providers as $provider) {
        switch ($provider) {
            case 'stripe':
                $value  = 'Stripe';
                $text   = '<div class="payment-method-name sr-only">' . __('credit card', 'raise') . '</div>';
                $images = ['pp cc visa', 'pp cc mastercard', 'pp cc amex'];
                break;
            case 'paypal':
                $value  = 'PayPal';
                $text   = '<div class="payment-method-name sr-only">PayPal</div>';
                $images = ['pp paypal'];
                break;
            case 'bitpay':
                $value  = 'BitPay';
                $text   = '<div class="payment-method-name sr-only">Bitcoin</div>';
                $images = ['pp bitcoin'];
                break;
            case 'coinbase':
                $value  = 'Coinbase';
                $text   = '<span class="payment-method-name sr-only">Coinbase</span>';
                $images = ['pp bitcoin', 'pp bitcoin_cash', 'pp ethereum', 'pp litecoin'];
                break;
            case 'skrill':
                $value  = 'Skrill';
                $text   = '<div class="payment-method-name sr-only">Skrill</div>';
                $images = ['pp skrill'];
                break;
            case 'gocardless':
                $value  = 'GoCardless';
                $text   = '<div class="payment-method-name">' . __('direct debit', 'raise') . '</div>';
                $images = [];
                break;
            case 'banktransfer':
                $value  = 'Bank Transfer';
                $text   = '<div class="payment-method-name">' . __('bank transfer', 'raise') . '</div>';
                $images = [];
                break;
            default:
                // Do nothing
                continue 2;
        }

        // Print radio box
        $id          = str_replace(' ', '', strtolower($value));
        $checkedAttr = $checked ? 'checked' : '';
        $checked     = false;

        // Construct radio and append to result
        $radio  = '<div class="pp-images">' . implode('', array_map(function($val) {
            return '<div class="' . $val . '"></div>';
        }, $images)) . '</div>' . $text;
        $radio  = '<div data-toggle="tooltip" data-container="body" title="">' . $radio . '</div>';
        $radio  = '<input type="radio" name="payment_provider" value="' . $value . '" id="payment-' . $id . '" ' . $checkedAttr . '> ' . $radio;
        $radio  = '<label for="payment-' . $id . '" class="radio-inline">' . $radio . '</label>' . "\n";

        $result .= $radio;
    }

    return $result;
}

/**
 * Get donation from $_POST
 *
 * @return array
 * @throws \Exception
 */
function raise_get_donation_from_post()
{
    // Trim the data
    $post = array_map('trim', $_POST);

    // Replace amount-other
    if (!empty($post['amount_other'])) {
        $post['amount'] = $post['amount_other'];
    }
    unset($post['amount_other']);

    // Add tip to amount
    if (is_numeric($post['amount'])) {
        $amountInt         = (int)($post['amount'] * 100);
        $tipInt            = (int)($post['tip_amount'] * 100);
        $post['amountInt'] = $amountInt + $tipInt;
        $post['amount']    = money_format('%i', $post['amountInt'] / 100);
    } else {
        throw new \Exception('Invalid amount');
    }

    return array(
        'form'                       => $post['form'],
        'mode'                       => $post['mode'],
        'url'                        => $_SERVER['HTTP_REFERER'],
        'language'                   => substr($post['locale'], 0, 2),
        'time'                       => date('c'),
        'currency'                   => $post['currency'],
        'amount'                     => $post['amount'],
        'tip_amount'                 => $post['tip_amount'],
        'tip_percentage'             => $post['tip_percentage'],
        'frequency'                  => $post['frequency'],
        'payment_provider'           => $post['payment_provider'],
        'email'                      => $post['email'],
        'name'                       => stripslashes($post['name']),
        'purpose'                    => raise_get($post['purpose'], ''),
        'address'                    => stripslashes(raise_get($post['address'], '')),
        'zip'                        => raise_get($post['zip'], ''),
        'city'                       => stripslashes(raise_get($post['city'], '')),
        'country_code'               => raise_get($post['country_code'], ''),
        'comment'                    => raise_get($post['comment'], ''),
        'account'                    => raise_get($post['account'], ''),
        'post_donation_instructions' => raise_get($post['post_donation_instructions'], ''),
        'g-recaptcha-response'       => raise_get($post['g-recaptcha-response'], ''),
        'anonymous'                  => (bool) raise_get($post['anonymous'], false),
        'mailinglist'                => (bool) raise_get($post['mailinglist'], false),
        'tax_receipt'                => (bool) raise_get($post['tax_receipt'], false),
        'share_data'                 => (bool) raise_get($post['share_data'], false),
        'share_data_offered'         => (bool) raise_get($post['share_data_offered'], false),
        'tip'                        => (bool) raise_get($post['tip'], false),
        'tip_offered'                => (bool) raise_get($post['tip_offered'], false),
    );
}

/**
 * Get form data from $_POST
 *
 * @return array
 */
function raise_get_form_data()
{
    // Trim the data
    $post = array_map('trim', $_POST);

    // Replace amount_other
    if (!empty($post['amount_other'])) {
        $post['amount'] = $post['amount_other'];
    }
    unset($post['amount_other']);

    // Add tip to amount
    if (is_numeric($post['amount'])) {
        $amountInt      = (int)($post['amount'] * 100);
        $tipInt         = (int)($post['tip_amount'] * 100);
        $amountInt      = $amountInt + $tipInt;
        $post['amount'] = money_format('%i', $amountInt / 100);
    } else {
        throw new \Exception('Invalid amount');
    }

    // Set language
    $post['language'] = substr($post['locale'], 0, 2);

    return $post;
}

/**
 * Sanitize donation
 *
 * @param array $donation
 * @return array Sanitized donation
 */
function raise_sanitize_donation(array $donation) {
    // Trim the data
    $d = array_map('trim', $donation);
    // Replace amount-other
    if (!empty($d['amount_other'])) {
        $d['amount'] = $d['amount_other'];
    }
    unset($d['amount_other']);
    // Add tip to amount
    if (is_numeric($d['amount'])) {
        $amountInt      = (int)($d['amount'] * 100);
        $tipInt         = (int)($d['tip_amount'] * 100);
        $d['amountInt'] = $amountInt + $tipInt;
        $d['amount']    = money_format('%i', $d['amountInt'] / 100);
    } else {
        throw new \Exception('Invalid amount');
    }
    return [
        'form'                       => $d['form'],
        'mode'                       => $d['mode'],
        'url'                        => $_SERVER['HTTP_REFERER'],
        'language'                   => substr($d['locale'], 0, 2),
        'time'                       => date('c'),
        'currency'                   => $d['currency'],
        'amount'                     => $d['amount'],
        'tip_amount'                 => $d['tip_amount'],
        'tip_percentage'             => $d['tip_percentage'],
        'frequency'                  => $d['frequency'],
        'payment_provider'           => $d['payment_provider'],
        'email'                      => $d['email'],
        'name'                       => stripslashes($d['name']),
        'purpose'                    => raise_get($d['purpose'], ''),
        'address'                    => stripslashes(raise_get($d['address'], '')),
        'zip'                        => raise_get($d['zip'], ''),
        'city'                       => stripslashes(raise_get($d['city'], '')),
        'country_code'               => raise_get($d['country_code'], ''),
        'comment'                    => raise_get($d['comment'], ''),
        'account'                    => raise_get($d['account'], ''),
        'post_donation_instructions' => raise_get($d['post_donation_instructions'], ''),
        'vendor_transaction_id'      => raise_get($d['vendor_transaction_id'], ''),
        'vendor_subscription_id'     => raise_get($d['vendor_subscription_id'], ''),
        'vendor_customer_id'         => raise_get($d['vendor_customer_id'], ''),
        'g-recaptcha-response'       => raise_get($d['g-recaptcha-response'], ''),
        'anonymous'                  => (bool) raise_get($d['anonymous'], false),
        'mailinglist'                => (bool) raise_get($d['mailinglist'], false),
        'tax_receipt'                => (bool) raise_get($d['tax_receipt'], false),
        'share_data'                 => (bool) raise_get($d['share_data'], false),
        'share_data_offered'         => (bool) raise_get($d['share_data_offered'], false),
        'tip'                        => (bool) raise_get($d['tip'], false),
        'tip_offered'                => (bool) raise_get($d['tip_offered'], false),
    ];
}

/**
 * AJAX endpoint that creates redirect response in HTML (Stripe)
 *
 * @return string JSON response
 */
function raise_prepare_redirect_html()
{
    try {
        $post = raise_get_form_data();

        // Output
        switch ($post['payment_provider']) {
            case "Stripe":
                $response = raise_prepare_stripe_donation($post);
                break;
            default:
                throw new \Exception('Payment method ' . $post['payment_provider'] . ' is invalid');
        }

        // Return response
        die($response);
    } catch (\Exception $ex) {
        die($ex->getMessage());
    }
}

/**
 * AJAX endpoint that creates redirect response in JSON (PayPal, Skrill, GoCardless, BitPay)
 *
 * @return string JSON response
 */
function raise_prepare_redirect()
{
    try {
        $post = raise_get_form_data();

        // Output
        switch ($post['payment_provider']) {
            case "PayPal":
                $response = raise_prepare_paypal_donation($post);
                break;
            case "Skrill":
                $response = raise_prepare_skrill_donation($post);
                break;
            case "GoCardless":
                $response = raise_prepare_gocardless_donation($post);
                break;
            case "BitPay":
                $response = raise_prepare_bitpay_donation($post);
                break;
            case "Coinbase":
                $response = raise_prepare_coinbase_donation($post);
                break;
            default:
                throw new \Exception('Payment method ' . $post['payment_provider'] . ' is invalid');
        }

        // Return response
        wp_send_json($response);
    } catch (\Exception $ex) {
        wp_send_json(raise_rest_exception_response($ex));
    }
}

/**
 * AJAX endpoint that deals with submitted donation data (bank tarnsfer only)
 *
 * @return string JSON response
 */
function raise_process_banktransfer()
{
    try {
        // Get donation
        $donation = raise_get_donation_from_post();

        // Check honey pot (confirm email)
        raise_check_honey_pot($_POST);

        // Check reCAPTCHA
        $formSettings = raise_load_settings($donation['form']);
        $secret       = raise_get($formSettings['payment']['recaptcha']['secret_key']);
        if (!empty($secret)) {
            $recaptcha          = new \ReCaptcha\ReCaptcha($secret);
            $gRecaptchaResponse = $donation['g-recaptcha-response'];
            $resp               = $recaptcha->verify($gRecaptchaResponse, $_SERVER['REMOTE_ADDR']);
            if (!$resp->isSuccess()) {
                throw new \Exception('Invalid reCAPTCHA');
            }
        }

        // Handle payment
        $reference = raise_handle_banktransfer_payment($donation);

        // Prepare response
        $response = array(
            'success'   => true,
            'reference' => $reference,
        );

        wp_send_json($response);
    } catch (\Exception $ex) {
        wp_send_json(raise_rest_exception_response($ex), 400);
    }
}

/**
 * Get monthly Stripe plan
 *
 * @param int    $amount       Plan amount in cents
 * @param int    $currency     Plan currency
 * @param string $purpose      Plan purpose, e.g. AMF
 * @param string $purposeLabel Plan purpose label, e.g. Against Malaria Foundation
 * @return array
 */
function raise_get_stripe_plan($amount, $currency, $purpose = null, $purposeLabel = null)
{
    $purposeSuffix      = $purpose ? '-' . $purpose : '';
    $purposeLabelSuffix = $purposeLabel ? ' to ' . $purposeLabel : '';

    $planId = 'donation-month-' . $currency . '-' . str_replace('.', '_', money_format('%i', $amount / 100)) . $purposeSuffix;

    try {
        // Try fetching an existing plan
        $plan = \Stripe\Plan::retrieve($planId);
    } catch (\Exception $e) {
        // Create a new plan
        $params = array(
            'amount'   => $amount,
            'interval' => 'month',
            'product'  => [
                'name' => 'monthly donations' . $purposeLabelSuffix,
            ],
            'currency' => $currency,
            'id'       => $planId,
        );

        $plan = \Stripe\Plan::create($params);

        if (!$plan instanceof \Stripe\Plan) {
            throw new \Exception('Credit card API is down. Please try later.');
        }

        $plan->save();
    }

    return $plan->id;
}

/**
 * Process bank transfer payment (simply log it)
 *
 * @param array $donation Donation form data
 * @return string Reference number
 */
function raise_handle_banktransfer_payment(array $donation)
{
    // Generate reference number and add to donation
    $reference             = raise_get_banktransfer_reference($donation['form'], raise_get($donation['purpose']));
    $donation['reference'] = $reference;

    // Inject reference number into post_donation_instructions
    if (!empty($donation['post_donation_instructions'])) {
        $donation['post_donation_instructions'] = str_replace('%reference_number%', $reference, $donation['post_donation_instructions']);
    }
   
    // Do post donation actions
    raise_do_post_donation_actions($donation);

    return $reference;
}

/**
 * Trigger webhooks (logging and mailing list)
 *
 * @param array $donation
 */
function raise_trigger_webhooks(array $donation)
{
    // Filter donation
    $filteredDonation = raise_filter_webhook_payload($donation);

    // Logging
    raise_trigger_logging_webhooks($filteredDonation);

    // Mailing list
    if ($donation['mailinglist'] === 'yes') {
        raise_trigger_mailinglist_webhooks($filteredDonation);
    }
}

/**
 * Send logging web hooks
 *
 * @param array $donation Donation data for logging
 */
function raise_trigger_logging_webhooks($donation)
{
    // Get form and mode
    $form = raise_get($donation['form'], '');
    $mode = raise_get($donation['mode'], '');

    // Trigger hooks for Zapier
    $formSettings = raise_load_settings($form);
    if (isset($formSettings['webhook']['logging'][$mode])) {
        $hooks = raise_csv_to_array($formSettings['webhook']['logging'][$mode]);
        foreach ($hooks as $hook) {
            raise_send_webhook($hook, $donation);
        }
    }
}

/**
 * Transform boolean values to yes/no strings, translate country code to English,
 * add referrer from query string if present
 *
 * @param array $donation
 * @return array
 */
function raise_clean_up_donation_data(array $donation)
{
    // Transform boolean values to yes/no strings
    $donation = array_map(function($val) {
        return is_bool($val) ? ($val ? 'yes' : 'no') : $val;
    }, $donation);

    // Replace \" with "
    $donation = array_map(function($val) {
        return is_string($val) ? str_replace('\"', '"', $val) : $val;
    }, $donation);

    // Translate country code to English
    if (!empty($donation['country_code'])) {
        $donation['country'] = raise_get_english_name_by_country_code($donation['country_code']);
    }

    // Add referrer from query string if present
    $parts = parse_url(raise_get($donation['url']));
    parse_str(raise_get($parts['query'], ''), $query);
    $donation['referrer'] = raise_get($query['referrer']);

    return $donation;
}

/**
 * Remove unncecessary field from webhook data
 *
 * @param array $donation
 * @return array
 */
function raise_filter_webhook_payload(array $donation)
{
    // Set all empty fields to empty string
    $values = array_map(function ($key) use ($donation) {
        return raise_get($donation[$key], '');
    }, RAISE_WEBHOOK_KEYS);

    return array_combine(RAISE_WEBHOOK_KEYS, $values);
}

/**
 * Send mailing_list web hooks
 *
 * @param array $donation Donation data
 */
function raise_trigger_mailinglist_webhooks($donation)
{
    // Get form and mode
    $form = raise_get($donation['form'], '');
    $mode = raise_get($donation['mode'], '');

    // Trigger hooks for Zapier
    $formSettings = raise_load_settings($form);
    if (isset($formSettings['webhook']['mailing_list'][$mode])) {
        // Get subscription data
        $subscription = array(
            'form'     => $donation['form'],
            'mode'     => $donation['mode'],
            'email'    => $donation['email'],
            'name'     => $donation['name'],
            'language' => $donation['language'],
        );

        // Iterate over hooks
        $hooks = raise_csv_to_array($formSettings['webhook']['mailing_list'][$mode]);
        foreach ($hooks as $hook) {
            raise_send_webhook($hook, $subscription);
        }
    }
}

/**
 * Send webhook
 *
 * @param string $url Target URL
 * @param array  $params Arguments
 */
function raise_send_webhook($url, array $params)
{
    global $wp_version;

    if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
        return;
    }

    $version   = raise_get_plugin_version();
    $userAgent = "Raise/{$version} (compatible; WordPress {$wp_version}; +https://github.com/ea-foundation/raise)";
    $args      = array(
        'user-agent' => $userAgent,
        'body'       => $params,
        'timeout'    => 30,
        'referer'    => get_bloginfo('url'),
    );
    
    // Send webhook
    $response = wp_remote_post($url, $args);

    // Make sure it arrived
    if (is_wp_error($response)) {
        throw new \Exception($response->get_error_message());
    }
}

/**
 * Get GoCardless client
 *
 * @param array $donation
 * @return \GoCardlessPro\Client
 */
function raise_get_gocardless_client(array $donation)
{
    // Get GoCardless account settings
    $settings = raise_get_payment_provider_account_settings('gocardless', $donation);
    $mode     = raise_get($donation['mode']);

    return new \GoCardlessPro\Client([
        'access_token' => $settings['access_token'],
        'environment'  => $mode === 'live' ? \GoCardlessPro\Environment::LIVE : \GoCardlessPro\Environment::SANDBOX,
    ]);
}

/**
 * Get payment provider settings properties
 *
 * @param string $provider
 * @return array
 */
function raise_get_payment_provider_properties($provider)
{
    switch (strtolower($provider)) {
        case "stripe":
            return array("secret_key", "public_key");
        case "paypal":
            return array("client_id", "client_secret");
        case "gocardless":
            return array("access_token");
        case "bitpay":
            return array("pairing_code");
        case "coinbase":
            return array("api_key");
        case "skrill":
            return array("merchant_account");
        default:
            return array();
    }
}

/**
 * AJAX endpoint that returns the GoCardless setup URL. It stores
 * user input in session until user is forwarded back from GoCardless
 *
 * @param array $donation
 * @return array
 */
function raise_prepare_gocardless_donation(array $donation)
{
    try {
        // Make GoCardless redirect flow
        $reqId       = uniqid(); // Secret request ID. Needed to prevent replay attack
        $returnUrl   = raise_get_ajax_endpoint() . '?action=gocardless_debit&req=' . $reqId;
        $client      = raise_get_gocardless_client($donation);
        $description = $donation['frequency'] === 'monthly'
            ? __("Monthly payment mandate of %currency% %amount%", "raise")
            : __("One-time payment mandate of %currency% %amount%", "raise");
        $redirectFlow = $client->redirectFlows()->create([
            "params" => [
                "description"          => str_replace('%currency%', $donation['currency'], str_replace('%amount%', money_format('%i', $donation['amount']), $description)),
                "session_token"        => $reqId,
                "success_redirect_url" => $returnUrl,
                "prefilled_customer"   => [
                    "email" => $donation['email'],
                ],
            ]
        ]);

        // Save flow ID to session
        $_SESSION['raise-gocardless-flow-id'] = $redirectFlow->id;

        // Save rest to session
        raise_set_donation_data_to_session($donation, $reqId);

        // Return redirect URL
        return array(
            'success' => true,
            'url'     => $redirectFlow->redirect_url,
            'reqId'   => $reqId,
        );
    } catch (\Exception $ex) {
        return raise_rest_exception_response($ex);
    }
}

/**
 * Return exception response
 *
 * @param \Exception $ex
 * @return array
 */
function raise_rest_exception_response($ex)
{
    return array(
        'success' => false,
        'error'   => "An error occured and your donation could not be processed.\n\n" .  $ex->getMessage() . "\n\nPlease contact us.",
    );
}

/**
 * AJAX endpoint that debits donor with GoCardless.
 * The user is redirected here after successful signup.
 */
function raise_process_gocardless_donation()
{
    try {
        // Get donation from session
        $donation = raise_get_donation_from_session();

        // Verify session and purge reqId from session
        raise_verify_session();

        // Get client
        $reqId  = $donation['reqId'];
        $client = raise_get_gocardless_client($donation);

        if (!isset($_GET['redirect_flow_id']) || $_GET['redirect_flow_id'] != $_SESSION['raise-gocardless-flow-id']) {
            throw new \Exception('Invalid flow ID');
        }

        // Complete flow
        $redirectFlow = $client->redirectFlows()->complete(
            $_GET['redirect_flow_id'],
            ["params" => [
                "session_token" => $reqId
            ]]
        );

        // Get other parameters
        $form      = $donation['form'];
        $currency  = $donation['currency'];
        $url       = $donation['url'];
        $amount    = $donation['amount'];
        $amountInt = floor($amount * 100);
        $frequency = $donation['frequency'];
        $purpose   = $donation['purpose'];

        $payment = [
            "params" => [
                "amount"   => $amountInt, // in cents!
                "currency" => $currency,
                "links" => [
                    "mandate" => $redirectFlow->links->mandate,
                ],
                "metadata" => [
                    "Form"     => $form,
                    "URL"      => $url,
                    "Purpose"  => $purpose,
                ]
            ],
            "headers" => [
                "Idempotency-Key" => $reqId
            ],
        ];

        // Add subscription fields if necessary and execute payment
        if ($frequency === 'monthly') {
            // Start paying in a week, unless it's the 29th, 30th, or 31st day of the month.
            // If that's the case, start on the first day of the following month.
            $startDate                          = new \DateTime('+7 days');
            $payment['params']['day_of_month']  = $startDate->format('d') <= 28 ? $startDate->format('d') : 1;
            $payment['params']['interval_unit'] = 'monthly';

            $client->subscriptions()->create($payment);
        } else {
            $client->payments()->create($payment);
        }

        // Add vendor customer ID to donation
        $donation['vendor_customer_id'] = $redirectFlow->links->customer;

        // Do post donation actions
        raise_do_post_donation_actions($donation);

        $script = "var mainWindow = (window == top) ? /* mobile */ opener : /* desktop */ parent; mainWindow.showConfirmation('gocardless'); mainWindow.hideModal();";
    } catch (\Exception $e) {
        $script = "var mainWindow = (window == top) ? /* mobile */ opener : /* desktop */ parent; alert('" . $e->getMessage() . "'); mainWindow.hideModal();";
    }

    // Die and send script to close flow
    die('<!doctype html>
         <html><head><meta charset="utf-8"><title>Closing flow...</title></head>
         <body><script>' . $script . '</script></body></html>');
}

/**
 * Get BitPay key IDs
 *
 * @param string $pairingCode
 * @return array
 */
function raise_get_bitpay_key_ids($pairingCode)
{
    return array(
        'raise_bitpay_private_key_' . $pairingCode,
        'raise_bitpay_public_key_' . $pairingCode,
        'raise_bitpay_token_' . $pairingCode,
    );
}

/**
 * Load BitPay credentials
 *
 * @param string $pairingCode
 * @return array
 */
function raise_load_bitpay_credentials($pairingCode)
{
    // Get key IDs
    list($privateKeyId, $publicKeyId, $tokenId) = raise_get_bitpay_key_ids($pairingCode);

    // Load keys
    $keyStorage = new \Raise\Bitpay\EncryptedWPOptionStorage($pairingCode);
    $privateKey = $keyStorage->load($privateKeyId);
    $publicKey  = $keyStorage->load($publicKeyId);

    return [$privateKey, $publicKey];
}

/**
 * Generate BitPay credentials
 *
 * @param string $pairingCode
 * @return array
 */
function raise_generate_bitpay_credentials($pairingCode)
{
    // Get BitPay pairing code as well as key/token IDs
    list($privateKeyId, $publicKeyId, $tokenId) = raise_get_bitpay_key_ids($pairingCode);
    
    // Generate keys
    $privateKey = \Bitpay\PrivateKey::create($privateKeyId)
        ->generate();
    $publicKey  = \Bitpay\PublicKey::create($publicKeyId)
        ->setPrivateKey($privateKey)
        ->generate();

    // Save keys (abuse pairing code as encryption password)
    $keyStorage = new \Raise\Bitpay\EncryptedWPOptionStorage($pairingCode);
    $keyStorage->persist($privateKey);
    $keyStorage->persist($publicKey);

    return [$privateKey, $publicKey];
}

/**
 * Get payment provider account settings
 *
 * @param string $name
 * @param array  $donation
 * @return array
 */
function raise_get_payment_provider_account_settings($name, $donation)
{
    $formSettings = raise_load_settings($donation['form']);
    $mode         = raise_get($donation['mode'], "");
    $map          = function ($item) use ($mode) { return json_encode(raise_get($item[$mode], [])); };
    $settings     = json_decode(raise_get_conditional_value($formSettings['payment']['provider'][$name], $donation, $map), true);

    if (!raise_payment_provider_settings_complete($name, $settings)) {
        throw new \Exception("Incomplete account settings");
    }

    return $settings;
}

/**
 * Get BitPay client
 *
 * @param array $donation
 * @return \Bitpay\Client\Client
 */
function raise_get_bitpay_client(array $donation)
{
    // Get BitPay pairing code
    $settings    = raise_get_payment_provider_account_settings('bitpay', $donation);
    $pairingCode = $settings['pairing_code'];

    // Get credentials
    list($privateKeyId, $publicKeyId, $tokenId) = raise_get_bitpay_key_ids($pairingCode);
    $tokenString = get_option($tokenId);
    if (empty($tokenString)) {
        // First time. Generate credentials
        list($privateKey, $publicKey) = raise_generate_bitpay_credentials($pairingCode);
    } else {
        // Get credentials from key storage
        list($privateKey, $publicKey) = raise_load_bitpay_credentials($pairingCode);
    }

    // Get network
    $mode    = raise_get($donation['mode']);
    $network = $mode === 'live' ? new \Bitpay\Network\Livenet() : new \Bitpay\Network\Testnet();

    // Get adapter
    $adapter = new \Bitpay\Client\Adapter\CurlAdapter();

    // Configure client
    $client = new \Bitpay\Client\Client();
    $client->setPrivateKey($privateKey);
    $client->setPublicKey($publicKey);
    $client->setNetwork($network);
    $client->setAdapter($adapter);

    // Set token
    if (empty($tokenString)) {
        // Generate new token
        $urlParts = parse_url(home_url());
        $label    = $urlParts['host'];
        // Generate token
        $sin    = \Bitpay\SinKey::create()->setPublicKey($publicKey)->generate();
        $token  = $client->createToken(array(
            'pairingCode' => $pairingCode,
            'label'       => $label,
            'id'          => (string) $sin,
        ));
        // Save token
        update_option($tokenId, $token->getToken());
    } else {
        // Get token from token string
        $token = new \Bitpay\Token();
        $token->setToken($tokenString);
    }
    $client->setToken($token);

    return $client;
}

/**
 * Returns the Skrill URL. It stores
 * user input in session until user is forwarded back from Skrill
 *
 * @param array $donation
 * @return array
 */
function raise_prepare_skrill_donation(array $donation)
{
    try {
        // Save request ID to session
        $reqId = uniqid(); // Secret request ID. Needed to prevent replay attack

        // Put user data in session
        raise_set_donation_data_to_session($donation, $reqId);

        // Get Skrill URL
        $url = raise_get_skrill_url($reqId, $donation);

        // Return URL
        return array(
            'success' => true,
            'url'     => $url,
        );
    } catch (\Exception $ex) {
        return raise_rest_exception_response($ex);
    }
}

/**
 * Get Skrill URL
 *
 * @param string $reqId
 * @param array  $donation
 * @return string
 */
function raise_get_skrill_url($reqId, $donation)
{
    // Get Skrill account settings
    $settings = raise_get_payment_provider_account_settings('skrill', $donation);

    // Prepare parameter array
    $params = array(
        'pay_to_email'      => $settings['merchant_account'],
        'pay_from_email'    => $donation['email'],
        'amount'            => $donation['amount'],
        'currency'          => $donation['currency'],
        'return_url'        => raise_get_ajax_endpoint() . '?action=skrill_log&req=' . $reqId,
        'return_url_target' => 3, // _self
        'logo_url'          => preg_replace("/^http:/i", "https:", get_option('raise_logo', plugin_dir_url(__FILE__) . 'images/logo.png')),
        'language'          => strtoupper($donation['language']),
        'transaction_id'    => $reqId,
        'payment_methods'   => "WLT", // Skrill comes first
        'prepare_only'      => 1, // Return URL instead of form HTML
    );

    // Add parameters for monthly donations
    if ($donation['frequency'] === 'monthly') {
        $recStartDate = new \DateTime('+1 month');
        $params['rec_amount']     = $donation['amount'];
        $params['rec_start_date'] = $recStartDate->format('d/m/Y');
        $params['rec_period']     = 1;
        $params['rec_cycle']      = 'month';
    }

    // Make options
    $options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($params),
        ),
    );

    //FIXME Remove this when XAMPP problem is fixed
    if ($donation['mode'] === 'sandbox') {
        // Disable verify peer for local development
        $options['ssl'] = array('verify_peer' => false);
    }

    $context = stream_context_create($options);
    $sid     = file_get_contents($GLOBALS['SkrillApiEndpoint'], false, $context);

    return $GLOBALS['SkrillApiEndpoint'] . '/?sid=' . $sid;
}

/**
 * AJAX endpoint that returns the BitPay URL. It stores
 * user input in session until user is forwarded back from BitPay
 *
 * @param array $donation
 * @return array
 */
function raise_prepare_bitpay_donation(array $donation)
{
    try {
        $form       = $donation['form'];
        $mode       = $donation['mode'];
        $email      = $donation['email'];
        $name       = $donation['name'];
        $amount     = $donation['amount'];
        $currency   = $donation['currency'];
        $frequency  = $donation['frequency'];
        $reqId      = uniqid(); // Secret request ID. Needed to prevent replay attack
        $returnUrl  = raise_get_ajax_endpoint() . '?action=bitpay_log&req=' . $reqId;
        // $returnUrl  = raise_get_ajax_endpoint() . '?action=bitpay_confirm';

        // Get BitPay object and token
        $client = raise_get_bitpay_client($donation);

        // Make item
        $item = new \Bitpay\Item();
        $item
            ->setCode("$form.$mode.$frequency.$currency.$amount")
            ->setDescription("$name ($email)")
            ->setPrice(money_format('%i', $amount));

        // Prepare buyer
        $buyer = new \Bitpay\Buyer();
        $buyer->setEmail($email);

        // Prepare invoice
        $invoice = new \Bitpay\Invoice();
        $invoice
            ->setCurrency(new \Bitpay\Currency($currency))
            ->setItem($item)
            ->setBuyer($buyer)
            ->setRedirectUrl($returnUrl);
            //->setNotificationUrl($notificationUrl);

        // Create invoice
        try {
            $client->createInvoice($invoice);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            throw new \Exception($message);
        }

        // Save invoice ID to session
        $_SESSION['raise-vendor-transaction-id'] = $invoice->getId();

        // Save user data to session
        raise_set_donation_data_to_session($donation, $reqId);

        // Return pay key
        return array(
            'success' => true,
            'url'     => $invoice->getUrl(),
        );
    } catch (\Exception $ex) {
        return raise_rest_exception_response($ex);
    }
}

/**
 * AJAX endpoint that returns the Coinbase URL. It stores
 * user input in session until user is forwarded back from Coinbase
 *
 * @param array $donation
 * @return array
 */
function raise_prepare_coinbase_donation(array $donation)
{
    try {
        $reqId      = uniqid(); // Secret request ID. Needed to prevent replay attack
        $returnUrl  = raise_get_ajax_endpoint() . '?action=coinbase_log&req=' . $reqId;

        // Get client
        $client = raise_get_coinbase_client($donation);

        // Create checkout
        $res = $client->request('POST', $GLOBALS['CoinbaseApiEndpoint'] . '/charges', [
            "json" => [
                "name"         => "Donation",
                "description"  => $donation['name'] . ' (' . $donation['email'] . ')',
                "local_price"  => [
                    "amount"   => $donation['amount'],
                    "currency" => $donation['currency'],
                ],
                "pricing_type" => "fixed_price",
                "redirect_url" => $returnUrl,
            ],
        ]);
        $body       = json_decode($res->getBody(), true);
        $chargeCode = $body['data']['code'];

        // Save charge code to session
        $_SESSION['raise-vendor-transaction-id'] = $chargeCode;

        // Save user data to session
        raise_set_donation_data_to_session($donation, $reqId);

        // Return URL
        return array(
            'success' => true,
            'url'     => $GLOBALS['CoinbaseChargeEndpoint'] . '/' . $chargeCode,
        );
    } catch (\Exception $e) {
        return array(
            'success' => false,
            'error'   => "An error occured and your donation could not be processed (" .  $e->getMessage() . "). Please contact us.",
        );
    }
}

/**
 * Get Coinbase client (Guzzle)
 *
 * @param array $donation
 * @return \GuzzleHttp\Client
 */
function raise_get_coinbase_client(array $donation)
{
    $settings = raise_get_payment_provider_account_settings('coinbase', $donation);

    return new \GuzzleHttp\Client([
        'headers' => [
            'X-CC-Version' => '2018-03-22',
            'X-CC-Api-Key' => $settings['api_key'],
        ],
    ]);
}

/**
 * Verify session and reset request ID
 *
 * @throws \Exception
 */
function raise_verify_session()
{
    if (!isset($_GET['req']) || $_GET['req'] !== $_SESSION['raise-req-id']) {
        throw new \Exception('Invalid request');
    }

    // Reset request ID to prevent replay attacks
    raise_reset_request_id();
}

/**
 * Reset request ID from session (used for payment providers with redirect)
 */
function raise_reset_request_id()
{
    $_SESSION['raise-req-id'] = uniqid();
}

function raise_process_log($paymentProvider)
{
    try {
        // Get donation from session
        $donation = raise_get_donation_from_session();

        // Verify session and purge reqId
        raise_verify_session();

        // Do post donation actions
        raise_do_post_donation_actions($donation);
    } catch (\Exception $e) {
        // No need to say anything. Just show confirmation.
    }

    die('<!doctype html>
         <html><head><meta charset="utf-8"><title>Closing flow...</title></head>
         <body><script>parent.showConfirmation("' . $paymentProvider . '"); parent.hideModal();</script></body></html>');
}

/**
 * AJAX endpoint for handling donation logging for Stripe.
 * User is forwarded here after successful Stripe transaction.
 * Takes user data from session and triggers the web hooks.
 *
 * @return string HTML with script that terminates the Stripe flow and shows the thank you step
 */
function raise_process_stripe_log()
{
    raise_process_log('stripe');
}

/**
 * AJAX endpoint for handling donation logging for Skrill.
 * User is forwarded here after successful Skrill transaction.
 * Takes user data from session and triggers the web hooks.
 *
 * @return string HTML with script that terminates the Skrill flow and shows the thank you step
 */
function raise_process_skrill_log()
{
    raise_process_log('skrill');
}

/**
 * AJAX endpoint for handling donation logging for BitPay.
 * User is forwarded here after successful BitPay transaction.
 * Takes user data from session and triggers the web hooks.
 *
 * @return string HTML with script that terminates the BitPay flow and shows the thank you step
 */
function raise_process_bitpay_log()
{
    try {
        // Verify session and purge reqId
        raise_verify_session();

        // Get donation from session
        $donation = raise_get_donation_from_session();

        // Add vendor transaction ID (BitPay invoice ID)
        $donation['vendor_transaction_id'] = $_SESSION['raise-vendor-transaction-id'];

        // Make sure the payment is paid
        $client      = raise_get_bitpay_client($donation);
        $invoice     = $client->getInvoice($_SESSION['raise-vendor-transaction-id']);
        $status      = $invoice->getStatus();
        $validStates = array(
            \Bitpay\Invoice::STATUS_PAID,
            \Bitpay\Invoice::STATUS_CONFIRMED,
            \Bitpay\Invoice::STATUS_COMPLETE,
        );
        if (!in_array($status, $validStates)) {
            throw new \Exception('Not paid');
        }

        // Do post donation actions
        raise_do_post_donation_actions($donation);
    } catch (\Exception $e) {
        // No need to say anything. Just show confirmation.
    }

    die('<!doctype html>
         <html><head><meta charset="utf-8"><title>Closing flow...</title></head>
         <body><script>var mainWindow = (window == top) ? /* mobile */ opener : /* desktop */ parent; mainWindow.showConfirmation("bitpay"); mainWindow.hideModal();</script></body></html>');
}

/**
 * AJAX endpoint for handling donation logging for Coinbase.
 * Takes user data from session and triggers the web hooks.
 *
 * @return string HTML with script that terminates the BitPay flow and shows the thank you step
 */
function raise_process_coinbase_log()
{
    try {
        // Get donation from session
        $donation = raise_get_donation_from_session();

        // Verify session and purge reqId
        raise_verify_session();

        // Add vendor transaction ID (Coinbase charge code)
        $chargeCode = $_SESSION['raise-vendor-transaction-id'];
        $donation['vendor_transaction_id'] = $chargeCode;

        // Make sure the payment is paid
        $client       = raise_get_coinbase_client($donation);
        $res          = $client->request('GET', $GLOBALS['CoinbaseApiEndpoint'] . '/charges/' . $chargeCode);
        $body         = json_decode($res->getBody(), true);
        $coinfirmedAt = $body['data']['confirmed_at'];
        if (!$coinfirmedAt) {
            throw new \Exception("Charge isn't confirmed");
        }

        // Do post donation actions
        raise_do_post_donation_actions($donation);
    } catch (\Exception $e) {
        // No need to say anything. Just show confirmation.
    }

    die('<!doctype html>
         <html><head><meta charset="utf-8"><title>Closing flow...</title></head>
         <body><script>var mainWindow = (window == top) ? /* mobile */ opener : /* desktop */ parent; mainWindow.showConfirmation("coinbase"); mainWindow.hideModal();</script></body></html>');
}

/**
 * Get donation data from session
 *
 * @return array
 */
function raise_get_donation_from_session()
{
    return [
        "time"                       => date('c'), // new
        "form"                       => $_SESSION['raise-form'],
        "mode"                       => $_SESSION['raise-mode'],
        "language"                   => $_SESSION['raise-language'],
        "url"                        => $_SESSION['raise-url'],
        "reqId"                      => $_SESSION['raise-req-id'],
        "email"                      => $_SESSION['raise-email'],
        "name"                       => $_SESSION['raise-name'],
        "currency"                   => $_SESSION['raise-currency'],
        "country_code"               => $_SESSION['raise-country_code'],
        "amount"                     => $_SESSION['raise-amount'],
        "tip"                        => $_SESSION['raise-tip'],
        "tip_amount"                 => $_SESSION['raise-tip-amount'],
        "tip_offered"                => $_SESSION['raise-tip-offered'],
        "tip_percentage"             => $_SESSION['raise-tip-percentage'],
        "frequency"                  => $_SESSION['raise-frequency'],
        "tax_receipt"                => $_SESSION['raise-tax-receipt'],
        "share_data"                 => $_SESSION['raise-share-data'],
        "share_data_offered"         => $_SESSION['raise-share-data-offered'],
        "payment_provider"           => $_SESSION['raise-payment-provider'],
        "purpose"                    => $_SESSION['raise-purpose'],
        "address"                    => $_SESSION['raise-address'],
        "zip"                        => $_SESSION['raise-zip'],
        "city"                       => $_SESSION['raise-city'],
        "mailinglist"                => $_SESSION['raise-mailinglist'],
        "comment"                    => $_SESSION['raise-comment'],
        "post_donation_instructions" => $_SESSION['post_donation_instructions'],
        "anonymous"                  => $_SESSION['raise-anonymous'],
    ];
}

/**
 * Set donation data to session
 *
 * @param array  $donation Form post
 * @param string $reqId    Request ID (against replay attack)
 */
function raise_set_donation_data_to_session(array $donation, $reqId = null)
{
    // Required fields
    $_SESSION['raise-form']             = $donation['form'];
    $_SESSION['raise-mode']             = $donation['mode'];
    $_SESSION['raise-language']         = $donation['language'];
    $_SESSION['raise-url']              = $_SERVER['HTTP_REFERER'];
    $_SESSION['raise-req-id']           = $reqId;
    $_SESSION['raise-email']            = $donation['email'];
    $_SESSION['raise-name']             = stripslashes($donation['name']);
    $_SESSION['raise-currency']         = $donation['currency'];
    $_SESSION['raise-country_code']     = $donation['country_code'];
    $_SESSION['raise-amount']           = money_format('%i', $donation['amount']);
    $_SESSION['raise-tip-amount']       = money_format('%i', $donation['tip_amount']);
    $_SESSION['raise-tip-percentage']   = $donation['tip_percentage'];
    $_SESSION['raise-frequency']        = $donation['frequency'];
    $_SESSION['raise-payment-provider'] = $donation['payment_provider'];

    // Optional fields
    $_SESSION['raise-purpose']              = raise_get($donation['purpose'], '');
    $_SESSION['raise-address']              = stripslashes(raise_get($donation['address'], ''));
    $_SESSION['raise-zip']                  = raise_get($donation['zip'], '');
    $_SESSION['raise-city']                 = stripslashes(raise_get($donation['city'], ''));
    $_SESSION['raise-comment']              = raise_get($donation['comment'], '');
    $_SESSION['post_donation_instructions'] = raise_get($donation['post_donation_instructions'], '');
    $_SESSION['raise-share-data']           = (bool) raise_get($donation['share_data'], false);
    $_SESSION['raise-share-data-offered']   = (bool) raise_get($donation['share_data_offered'], false);
    $_SESSION['raise-tip']                  = (bool) raise_get($donation['tip'], false);
    $_SESSION['raise-tip-offered']          = (bool) raise_get($donation['tip_offered'], false);
    $_SESSION['raise-tax-receipt']          = (bool) raise_get($donation['tax_receipt'], false);
    $_SESSION['raise-mailinglist']          = (bool) raise_get($donation['mailinglist'], false);
    $_SESSION['raise-anonymous']            = (bool) raise_get($donation['anonymous'], false);
}

/**
 * Make PayPal payment (= one-time payment)
 *
 * @param array $donation
 * @return PayPal\Api\Payment
 */
function raise_create_paypal_payment(array $donation)
{
    // Make payer
    $payer = new \PayPal\Api\Payer();
    $payer->setPaymentMethod("paypal");

    // Make amount
    $amount = new \PayPal\Api\Amount();
    $amount->setCurrency($donation['currency'])
        ->setTotal($donation['amount']);

    // Make transaction
    $transaction = new \PayPal\Api\Transaction();
    $transaction->setAmount($amount)
        ->setDescription($donation['name'] . ' (' . $donation['email'] . ')')
        ->setInvoiceNumber(uniqid());

    // Make redirect URLs
    $returnUrl    = raise_get_ajax_endpoint() . '?action=paypal_execute';
    $redirectUrls = new \PayPal\Api\RedirectUrls();
    $redirectUrls->setReturnUrl($returnUrl)
        ->setCancelUrl($returnUrl);

    // Make payment
    $payment = new \PayPal\Api\Payment();
    $payment->setIntent("sale")
        ->setPayer($payer)
        ->setTransactions(array($transaction))
        ->setRedirectUrls($redirectUrls);

    // Get API context end create payment
    $apiContext = raise_get_paypal_api_context($donation);

    return $payment->create($apiContext);
}

/**
 * Make PayPal billing agreement (= recurring payment)
 *
 * @param array $donation
 * @return \PayPal\Api\Agreement
 */
function raise_create_paypal_billing_agreement(array $donation)
{
    // Make new plan
    $plan = new \PayPal\Api\Plan();
    $plan->setName('Monthly Donation')
        ->setDescription('Monthly donation of ' . $donation['currency'] . ' ' . $donation['amount'])
        ->setType('INFINITE');

    // Make payment definition
    $paymentDefinition = new \PayPal\Api\PaymentDefinition();
    $paymentDefinition->setName('Regular Payments')
        ->setType('REGULAR')
        ->setFrequency('Month')
        ->setFrequencyInterval('1')
        ->setCycles('0')
        ->setAmount(new \PayPal\Api\Currency(array('value' => $donation['amount'], 'currency' => $donation['currency'])));

    // Make merchant preferences
    $returnUrl           = raise_get_ajax_endpoint() . '?action=paypal_execute';
    $merchantPreferences = new \PayPal\Api\MerchantPreferences();
    $merchantPreferences->setReturnUrl($returnUrl)
        ->setCancelUrl($returnUrl)
        ->setAutoBillAmount("yes")
        ->setInitialFailAmountAction("CONTINUE")
        ->setMaxFailAttempts("0");

    // Put things together and create
    $apiContext = raise_get_paypal_api_context($donation);
    $plan->setPaymentDefinitions(array($paymentDefinition))
        ->setMerchantPreferences($merchantPreferences)
        ->create($apiContext);

    // Activate plan
    $patch = new \PayPal\Api\Patch();
    $value = new \PayPal\Common\PayPalModel('{
       "state":"ACTIVE"
     }');
    $patch->setOp('replace')
        ->setPath('/')
        ->setValue($value);
    $patchRequest = new PayPal\Api\PatchRequest();
    $patchRequest->addPatch($patch);
    $plan->update($patchRequest, $apiContext);

    // Make payer
    $payer = new \PayPal\Api\Payer();
    $payer->setPaymentMethod('paypal');

    // Make a fresh plan
    $planID = $plan->getId();
    $plan   = new \PayPal\Api\Plan();
    $plan->setId($planID);

    // Make agreement
    $agreement = new \PayPal\Api\Agreement();
    $startDate = new \DateTime('+1 day'); // Activation can take up to 24 hours
    $agreement->setName(__("Monthly Donation", "raise") . ': ' . $donation['currency'] . ' ' . $donation['amount'])
        ->setDescription(__("Monthly Donation", "raise") . ': ' . $donation['currency'] . ' ' . $donation['amount'])
        ->setStartDate($startDate->format('c'))
        ->setPlan($plan)
        ->setPayer($payer);

    return $agreement->create($apiContext);
}

/**
 * Returns Stripe session ID for donation. It stores
 * user input in session until user is forwarded back from Stripe
 *
 * @param array $donation
 * @return array
 */
function raise_prepare_stripe_donation(array $donation)
{
    $reqId     = uniqid(); // Secret request ID. Needed to prevent replay attack
    $returnUrl = raise_get_ajax_endpoint() . '?action=stripe_log&req=' . $reqId;
    $cancelUrl = raise_get_ajax_endpoint() . '?action=cancel_payment';

    $settings = raise_get_payment_provider_account_settings('stripe', $donation);

    \Stripe\Stripe::setApiKey($settings['secret_key']);

    // Add custom purpose label if purpose is defined
    $recipient    = __('Donation', 'raise');
    $purposeLabel = '';
    if ($purpose = raise_get($donation['purpose'])) {
        $formSettings = raise_load_settings($donation['form']);
        $purposeLabel = raise_get_localized_value(raise_get($formSettings['payment']['purpose'][$purpose], ''));
        $recipient    = str_replace(
            '%recipient%',
            $purposeLabel,
            __('Donation to %recipient%', 'raise')
        );
    }

    // Session params
    $sessionParams = [
        'customer_email'       => $donation['email'],
        'payment_method_types' => ['card'],
        'success_url'          => $returnUrl,
        'cancel_url'           => $cancelUrl,
    ];

    // Make charge/subscription
    $amountInt = floor($donation['amount'] * 100); // cents
    if ($donation['frequency'] === 'monthly') {
        // Get plan
        $plan = raise_get_stripe_plan($amountInt, $donation['currency'], $purpose, $purposeLabel);

        // Define subscription
        $sessionParams['subscription_data'] = [
            'metadata' => [
                'purpose' => $donation['purpose'],
            ],
            'items' => [[
                'plan'     => $plan,
                'quantity' => 1,
            ]],
        ];
    } else {
        // Define one-time charge
        $sessionParams['payment_intent_data'] = [
            'metadata' => [
                'purpose' => $donation['purpose'],
            ]
        ];
        $sessionParams['line_items'] = [[
            'name'     => $recipient,
            'amount'   => $amountInt,
            'currency' => $donation['currency'],
            'quantity' => 1,
            'images'   => [get_option('raise_logo')],
        ]];
    }

    $session = \Stripe\Checkout\Session::create($sessionParams);

    // Save donation as transient for 2h
    set_site_transient('raise_stripe_' . $session->id, raise_sanitize_donation($donation), 60*60*2);

    $script = 'var stripe = Stripe("' . $settings['public_key'] . '");
      stripe.redirectToCheckout({
        sessionId: "' . $session->id . '",
      }).then(function (result) {
        alert(result.error.message);
      });';
    
    return '<!doctype html>
            <html><head>
            <meta charset="utf-8"><title>Forwarding to Stripe...</title>
            <script src="https://js.stripe.com/v3/"></script></head>
            <body><script>' . $script . '</script></body></html>';
}

function raise_cancel_payment()
{
    $script = "var mainWindow = (window == top) ? /* mobile */ opener : /* desktop */ parent; mainWindow.raisePopup.close();";
    // Die and send script to close flow
    die('<!doctype html>
         <html><head><meta charset="utf-8"><title>Closing flow...</title></head>
         <body><script>opener.close()</script></body></html>');
}

/**
 * AJAX endpoint for finish Stripe donation flow.
 * User is forwarded here after finishing donation.
 *
 * @return string HTML with script that terminates the PayPal flow and shows the thank you step
 */
function raise_finish_stripe_donation_flow()
{
    // Just close popup and show confirmation
    die('<!doctype html>
         <html><head><meta charset="utf-8"><title>Closing flow...</title></head>
         <body><script>var mainWindow = (window == top) ? /* mobile */ opener : /* desktop */ parent; mainWindow.showConfirmation("stripe"); mainWindow.raisePopup.close();</script></body></html>');
}

/**
 * Log Stripe donation
 * Endpoint for webhooks from Stripe
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function raise_log_stripe_donation(WP_REST_Request $request)
{
    try {
        $providedSignature = $request->get_header('stripe_signature');
        if (!$providedSignature) {
            throw new \Exception('Missing stripe-signature header');
        }

        // Get donation
        $sessionId = raise_get($request['data']['object']['id'], '');
        $donation  = get_site_transient('raise_stripe_' . $sessionId);
        if (!$donation) {
            // Probably donation was made on different WordPress instance
            $response = new WP_REST_Response([
                'success' => false,
                'message' => 'Session ID not found. Probably donation was made on different WordPress instance',
            ]);
            $response->set_status(202);
            return $response;
        }

        // Check hash to make sure it comes from Stripe
        $settings      = raise_get_payment_provider_account_settings('stripe', $donation);
        $signingSecret = raise_get($settings['signing_secret'], '');
        \Stripe\Stripe::setApiKey($settings['secret_key']);

        // Verify signature
        $payload = @file_get_contents('php://input');
        $event   = \Stripe\Webhook::constructEvent(
            $payload, $providedSignature, $signingSecret
        );

        // Make sure it's the checkout.session.completed event
        if ($event->type !== 'checkout.session.completed') {
            // Irrelevant event
            $response = new WP_REST_Response([
                'success' => false,
                'message' => 'Irrelevant event',
            ]);
            $response->set_status(202);
            return $response;
        }

        // Set some vendor IDs
        $customerId = raise_get($request['data']['object']['customer']);
        $donation['vendor_customer_id'] = $customerId;
        if ($donation['frequency'] === 'once') {
            // Get charge ID
            $paymentIntent = \Stripe\PaymentIntent::retrieve($event->data->object->payment_intent);
            $charges       = $paymentIntent->charges->data;
            if ($charge = reset($charges)) {
                $donation['vendor_transaction_id'] = $charge->id;
            }
        } else {
            // Get subscription ID
            $donation['vendor_subscription_id'] = raise_get($request['data']['object']['subscription']);
        }

        // Do post donation actions
        raise_do_post_donation_actions($donation);

        // Make response
        $response = new WP_REST_Response(['success' => true]);
        $response->set_status(201);

        // Delete transient
        // delete_site_transient('raise_stripe_' . $sessionId);

        return $response;
    } catch (\Exception $ex) {
        $response = new WP_REST_Response(['success' => false, 'message' => $ex->getMessage()]);
        $response->set_status(400);

        return $response;
    }
}

/**
 * Returns Paypal pay key for donation. It stores
 * user input in session until user is forwarded back from Paypal
 *
 * @param array $donation
 * @return array
 */
function raise_prepare_paypal_donation(array $donation)
{
    try {
        if ($donation['frequency'] === 'monthly') {
            $billingAgreement = raise_create_paypal_billing_agreement($donation);

            // Save doantion to session
            raise_set_donation_data_to_session($donation);

            // Parse approval link
            $approvalLinkParts = parse_url($billingAgreement->getApprovalLink());
            parse_str($approvalLinkParts['query'], $query);

            return array(
                'success' => true,
                'token'   => $query['token'],
            );
        } else {
            $payment = raise_create_paypal_payment($donation);

            // Save doantion to session
            raise_set_donation_data_to_session($donation);

            return array(
                'success'   => true,
                'paymentID' => $payment->getId(),
            );
        }
    } catch (\PayPal\Exception\PayPalConnectionException $ex) {
        return array(
            'success' => false,
            'error'   => "An error occured and your donation could not be processed (" .  $ex->getData() . "). Please contact us.",
        );
    } catch (\Exception $ex) {
        return raise_rest_exception_response($ex);
    }
}

/**
 * AJAX endpoint for executing and logging PayPal donations.
 * Takes user data from session and triggers the web hooks.
 *
 * @return string HTML with script that terminates the PayPal flow and shows the thank you step
 */
function raise_execute_paypal_donation()
{
    try {
        // Get donation from session
        $donation = raise_get_donation_from_session();

        // Get API context
        $apiContext = raise_get_paypal_api_context($donation);

        if (!empty($_POST['paymentID']) && !empty($_POST['payerID'])) {
            // Execute payment (one-time)
            $paymentId = $_POST['paymentID'];
            $payment   = \PayPal\Api\Payment::get($paymentId, $apiContext);
            $execution = new \PayPal\Api\PaymentExecution();
            $execution->setPayerId($_POST['payerID']);
            $payment->execute($execution, $apiContext);

            // Add vendor transaction ID and customer ID
            /** @var \PayPal\Api\Transaction $transaction */
            $transaction = reset($payment->getTransactions());
            /** @var \PayPal\Api\RelatedResources $relatedResources */
            $relatedResources = reset($transaction->getRelatedResources());
            $donation['vendor_transaction_id'] = $relatedResources->getSale()->getId();
            $donation['vendor_customer_id']    = $_POST['payerID'];
        } else if (!empty($_POST['token'])) {
            // Execute billing agreement (monthly)
            $agreement = new \PayPal\Api\Agreement();
            $agreement->execute($_POST['token'], $apiContext);

            // Add vendor subscription ID and customer ID
            $donation['vendor_subscription_id'] = $agreement->getId();
            $donation['vendor_customer_id']     = $agreement->getPayer()->getPayerInfo()->getPayerId();
        } else {
            throw new \Exception("An error occured. Payment aborted.");
        }

        // Do post donation actions
        raise_do_post_donation_actions($donation);

        // Send response
        wp_send_json(['success' => true]);
    } catch (\Exception $ex) {
        wp_send_json([
            'success' => false,
            'error'   => $ex->getMessage(),
        ]);
    }
}

/**
 * Save donation log (custom post) if enabled
 *
 * @param array $donation
 */
function raise_save_donation_log_post(array $donation)
{
    // Check if max defined
    $formSettings = raise_load_settings($donation['form']);
    if (empty($formSettings['log']['max'])) {
        // Logs disabled
        return;
    }

    $logMax    = (int) $formSettings['log']['max'];
    $form      = $donation['form'];
    $name      = $donation['name'];
    $currency  = $donation['currency'];
    $amount    = $donation['amount'];
    $frequency = $donation['frequency'];

    // Save donation as a custom post
    $newPost = array(
        "post_title"  => "$name donated $currency $amount ($frequency) on $form",
        "post_type"   => "raise_donation_log",
        "post_status" => "private",
    );
    $postId = wp_insert_post($newPost);

    // Add custom fields
    foreach ($donation as $key => $value) {
        add_post_meta($postId, $key, $value);
    }

    // Delete old post from queue
    $args = array(
        'post_type'   => 'raise_donation_log',
        'post_status' => array('private'),
        'meta_key'    => 'form',
        'meta_value'  => $form,
        'offset'      => $logMax,
        'orderby'     => 'ID',
        'order'       => 'DESC',
    );
    $query = new WP_Query($args);
    while ($query->have_posts()) {
        $query->the_post();
        wp_delete_post(get_the_ID());
    }
    wp_reset_postdata();
}

/**
 * Save custom posts (fundraiser donation post, donation log post)
 *
 * @param array $donation
 */
function raise_save_custom_posts(array $donation)
{
    // Fundraiser donation post (if it's the case)
    raise_save_fundraiser_donation_post($donation);

    // Donation log post (if enabled)
    raise_save_donation_log_post($donation);
}

/**
 * Save fundraiser donation (custom post) if a fundraiser is linked to the form
 *
 * @param array $donation
 */
function raise_save_fundraiser_donation_post(array $donation)
{
    $form      = $donation['form'];
    $name      = $donation['anonymous'] === 'yes' ? 'Anonymous' : $donation['name'];
    $currency  = $donation['currency'];
    $amount    = $donation['amount'];
    $frequency = $donation['frequency'];
    $comment   = $donation['comment'];
    $purpose   = raise_get($donation['purpose']);

    $formSettings = raise_load_settings($form);

    if (empty($formSettings['fundraiser'])) {
        // No fundraiser fundraiser set
        return;
    }

    $fundraiserId = $formSettings['fundraiser'];

    // Save donation as a custom post
    $newPost = array(
        "post_title"  => "$name contributed $currency $amount ($frequency) to fundraiser (ID = $fundraiserId)",
        "post_type"   => "raise_fundraiser_don",
        "post_status" => "private",
    );
    $postId = wp_insert_post($newPost);

    // Add custom fields
    add_post_meta($postId, 'name', $name);
    add_post_meta($postId, 'currency', $currency);
    add_post_meta($postId, 'amount', preg_replace('#\.00$#', '', $amount));
    add_post_meta($postId, 'frequency', $frequency);
    add_post_meta($postId, 'fundraiser', $fundraiserId);
    add_post_meta($postId, 'comment', $comment);
    if (!empty($purpose)) {
        // Find purpose labels
        $label = raise_get($formSettings['payment']['purpose'][$purpose], '');
        if (is_array($label)) {
            $label = array_map('htmlentities', $label);
            add_post_meta($postId, 'purpose', json_encode($label));
        } else {
            add_post_meta($postId, 'purpose', $purpose);
        }
    }
}

/**
 * Filter for changing sender email address
 *
 * @param string $original_email_address
 * @return string
 */
function raise_get_email_address($original_email_address)
{
    return !empty($GLOBALS['raiseEmailAddress']) ? $GLOBALS['raiseEmailAddress'] : $original_email_address;
}

/**
 * Filter for changing email sender
 *
 * @param string $original_email_sender
 * @return string
 */
function raise_get_email_sender($original_email_sender)
{
    return !empty($GLOBALS['raiseEmailSender']) ? $GLOBALS['raiseEmailSender'] : $original_email_sender;
}

/**
 * Filter for changing email content type
 *
 * @param string $original_content_type
 * @return string
 */
function raise_get_email_content_type($original_content_type)
{
    return $GLOBALS['raiseEmailContentType'];
}

/**
 * Send notification email to admin (if email set)
 *
 * @param array  $donation
 */
function raise_send_notification_email(array $donation)
{
    $form = raise_get($donation['form'], '');

    // Return if admin email not set
    $formSettings = raise_load_settings($form);
    if (empty($formSettings['finish']['notification_email'])) {
        return;
    }

    $emails = $formSettings['finish']['notification_email'];

    // Run email filters if array
    if (is_array($emails)) {
        $matchingEmails = array();

        // Loop over emails and keep only those who have no condition mismatches
        foreach ($emails as $email => $conditions) {
            if (!is_array($conditions)) {
                continue;
            }

            foreach ($conditions as $field => $requiredValue) {
                if (!isset($donation[$field]) || strtolower($donation[$field]) != strtolower($requiredValue)) {
                    continue 2;
                }
            }

            $matchingEmails[] = $email;
        }

        if (count($matchingEmails) > 0) {
            $emails = implode(', ', $matchingEmails);
        } else {
            // No matching emails. Nothing to do.
            return;
        }
    }

    // Trim amount
    if (!empty($donation['amount'])) {
        $donation['amount'] = preg_replace('#\.00$#', '', $donation['amount']);
    }

    // Prepare email
    $freq    = !empty($donation['frequency']) && $donation['frequency'] === 'monthly' ? ' (monthly)' : '';
    $subject = $form
               . ' : ' . raise_get($donation['currency'], '') . ' ' . raise_get($donation['amount'], '') . $freq
               . ' : ' . raise_get($donation['name'], '');
    $text    = '';
    foreach ($donation as $key => $value) {
        $text .= $key . ' : ' . $value . "\n";
    }

    // Send email
    wp_mail($emails, $subject, $text);
}

/**
 * Send email mit thank you message
 *
 * @param array  $donation Donation
 * @param string $form     Form name
 */
function raise_send_confirmation_email(array $donation)
{
    $form         = raise_get($donation['form'], '');
    $formSettings = raise_load_settings($form);

    // Only send email if we have settings (might not be the case if we're dealing with script kiddies)
    if (isset($formSettings['finish']['email'])) {
        $emailSettings = $formSettings['finish']['email'];
        $language      = raise_get($donation['language']);
        $sender        = raise_get_localized_value($emailSettings['sender'], $language);
        $address       = raise_get_localized_value($emailSettings['address'], $language);
        $subject       = raise_get_localized_value($emailSettings['subject'], $language);
        $text          = raise_get_localized_value($emailSettings['text'], $language);
        $html          = raise_get_localized_value(raise_get($emailSettings['html'], false), $language);

        // Get email subject and text and pass it through twig
        $twig    = raise_get_twig($text, $subject, $html);
        $subject = $twig->render('finish.email.subject', $donation);
        $text    = $twig->render('finish.email.text', $donation);

        // Repalce %bank_account_formatted% from post_donation_instructions with macro
        if (!empty($donation['bank_account']) && strpos($text, '%bank_account_formatted%') !== false) {
            $bankAccount = $html ? $twig->render('bank_account_formatted_html', $donation)
                                 : $twig->render('bank_account_formatted_text', $donation);
            $text = str_replace('%bank_account_formatted%', $bankAccount, $text);
        }

        // The filters below need to access the email settings
        $GLOBALS['raiseEmailSender']      = $sender;
        $GLOBALS['raiseEmailAddress']     = $address;
        $GLOBALS['raiseEmailContentType'] = $html ? 'text/html' : 'text/plain';

        // Add email hooks
        add_filter('wp_mail_from', 'raise_get_email_address', RAISE_PRIORITY, 1);
        add_filter('wp_mail_from_name', 'raise_get_email_sender', RAISE_PRIORITY, 1);
        add_filter('wp_mail_content_type', 'raise_get_email_content_type', RAISE_PRIORITY, 1);

        // Send email
        wp_mail($donation['email'], $subject, $text);

        // Remove email hooks
        remove_filter('wp_mail_from', 'raise_get_email_address', RAISE_PRIORITY);
        remove_filter('wp_mail_from_name', 'raise_get_email_sender', RAISE_PRIORITY);
        remove_filter('wp_mail_content_type', 'raise_get_email_content_type', RAISE_PRIORITY);
    }
}

/**
 * Auxiliary function for checking if array has string keys
 *
 * @param array $array The array in question
 * @return bool
 */
function raise_has_string_keys(array $array) {
    return count(array_filter(array_keys($array), 'is_string')) > 0;
}

/**
 * Get user country from ipstack.com, e.g. as ['code' => 'CH', 'name' => 'Switzerland']
 *
 * @param string $accessKey Access key from ipstack.com
 * @param string $userIp
 * @param array  $default
 * @return array
 */
function raise_get_user_country($accessKey, $userIp = null, array $default = array())
{
    if (!$userIp) {
        $userIp = $_SERVER['REMOTE_ADDR'];
    }

    try {
        if (!empty($accessKey) && !empty($userIp)) {
            $client   = new \GuzzleHttp\Client();
            $response = $client->get('http://api.ipstack.com/' . $userIp . '?access_key=' . $accessKey);
            $body     = json_decode($response->getBody(), true);

            if (empty($body['country_name']) || empty($body['country_code'])) {
                throw new \Exception('Invalid response');
            }

            return array(
                'code' => $body['country_code'],
                'name' => $body['country_name'],
            );
        } else {
            return $default;
        }
    } catch (\Exception $ex) {
        return $default;
    }
}

/**
 * Get initial country for form
 *
 * @param  array $formSettings
 * @return array
 */
function raise_get_initial_country(array $formSettings)
{
    $initialCountry   = strtoupper(raise_get($formSettings['payment']['country']['initial'], 'ipstack'));
    $ipstackAccessKey = raise_get($formSettings['payment']['country']['ipstack_access_key']);

    if (!empty($ipstackAccessKey) && ($initialCountry === 'IPSTACK' || $initialCountry === 'GEOIP')) {
        // Do IP lookup
        $legacyFallbackCode = raise_get($formSettings['payment']['country']['fallback'], '');
        $fallbackCode       = strtoupper(raise_get($formSettings['payment']['country']['ipstack_fallback'], $legacyFallbackCode));
        $fallbackName       = raise_get($GLOBALS['code2country'][$fallbackCode]);
        $fallback           = !empty($fallbackName) ? [
            'code' => $fallbackCode,
            'name' => $fallbackName,
        ] : [];

        return raise_get_user_country($ipstackAccessKey, $_SERVER['REMOTE_ADDR'], $fallback);
    } else {
        // Return predefined country
        return isset($GLOBALS['code2country'][$initialCountry]) ? [
            'code' => $initialCountry,
            'name' => $GLOBALS['code2country'][$initialCountry],
        ] : [];
    }
}

/**
 * Get user currency
 *
 * @param string $countryCode E.g. 'CH'
 * @return string|null
 */
function raise_get_user_currency($countryCode)
{
    if (!$countryCode) {
        return null;
    }

    $mapping = $GLOBALS['country2currency'];

    return raise_get($mapping[$countryCode]);
}

/**
 * Get list of countries. Keys is country code, value a numeric
 * array in which the first element is the translated country
 * name and the second element the English country name.
 *
 * E.g. for a visitor on the German website you get
 * [
 *   "DE" => [0 => "Deutschland", 1 => "Germany"],
 *   "CH" => [0 => "Schweiz", 1 => "Switzerland"],
 *   ...
 * ]
 *
 * @param array|string[] Country list gets filtered, e.g. array('CH') will only return Switzerland
 * @return array
 */
function raise_get_sorted_country_list($countryCodeFilters = array())
{
    $countries = array(
        "AF" => __("Afghanistan", "raise"),
        "AX" => __("Åland Islands", "raise"),
        "AL" => __("Albania", "raise"),
        "DZ" => __("Algeria", "raise"),
        "AS" => __("American Samoa", "raise"),
        "AD" => __("Andorra", "raise"),
        "AO" => __("Angola", "raise"),
        "AI" => __("Anguilla", "raise"),
        "AQ" => __("Antarctica", "raise"),
        "AG" => __("Antigua and Barbuda", "raise"),
        "AR" => __("Argentina", "raise"),
        "AM" => __("Armenia", "raise"),
        "AW" => __("Aruba", "raise"),
        "AU" => __("Australia", "raise"),
        "AT" => __("Austria", "raise"),
        "AZ" => __("Azerbaijan", "raise"),
        "BS" => __("Bahamas", "raise"),
        "BH" => __("Bahrain", "raise"),
        "BD" => __("Bangladesh", "raise"),
        "BB" => __("Barbados", "raise"),
        "BY" => __("Belarus", "raise"),
        "BE" => __("Belgium", "raise"),
        "BZ" => __("Belize", "raise"),
        "BJ" => __("Benin", "raise"),
        "BM" => __("Bermuda", "raise"),
        "BT" => __("Bhutan", "raise"),
        "BO" => __("Bolivia, Plurinational State of", "raise"),
        "BQ" => __("Bonaire, Sint Eustatius and Saba", "raise"),
        "BA" => __("Bosnia and Herzegovina", "raise"),
        "BW" => __("Botswana", "raise"),
        "BV" => __("Bouvet Island", "raise"),
        "BR" => __("Brazil", "raise"),
        "IO" => __("British Indian Ocean Territory", "raise"),
        "BN" => __("Brunei Darussalam", "raise"),
        "BG" => __("Bulgaria", "raise"),
        "BF" => __("Burkina Faso", "raise"),
        "BI" => __("Burundi", "raise"),
        "KH" => __("Cambodia", "raise"),
        "CM" => __("Cameroon", "raise"),
        "CA" => __("Canada", "raise"),
        "CV" => __("Cape Verde", "raise"),
        "KY" => __("Cayman Islands", "raise"),
        "CF" => __("Central African Republic", "raise"),
        "TD" => __("Chad", "raise"),
        "CL" => __("Chile", "raise"),
        "CN" => __("China", "raise"),
        "CX" => __("Christmas Island", "raise"),
        "CC" => __("Cocos (Keeling) Islands", "raise"),
        "CO" => __("Colombia", "raise"),
        "KM" => __("Comoros", "raise"),
        "CG" => __("Congo, Republic of", "raise"),
        "CD" => __("Congo, Democratic Republic of the", "raise"),
        "CK" => __("Cook Islands", "raise"),
        "CR" => __("Costa Rica", "raise"),
        "CI" => __("Côte d'Ivoire", "raise"),
        "HR" => __("Croatia", "raise"),
        "CU" => __("Cuba", "raise"),
        "CW" => __("Curaçao", "raise"),
        "CY" => __("Cyprus", "raise"),
        "CZ" => __("Czech Republic", "raise"),
        "DK" => __("Denmark", "raise"),
        "DJ" => __("Djibouti", "raise"),
        "DM" => __("Dominica", "raise"),
        "DO" => __("Dominican Republic", "raise"),
        "EC" => __("Ecuador", "raise"),
        "EG" => __("Egypt", "raise"),
        "SV" => __("El Salvador", "raise"),
        "GQ" => __("Equatorial Guinea", "raise"),
        "ER" => __("Eritrea", "raise"),
        "EE" => __("Estonia", "raise"),
        "ET" => __("Ethiopia", "raise"),
        "FK" => __("Falkland Islands (Malvinas)", "raise"),
        "FO" => __("Faroe Islands", "raise"),
        "FJ" => __("Fiji", "raise"),
        "FI" => __("Finland", "raise"),
        "FR" => __("France", "raise"),
        "GF" => __("French Guiana", "raise"),
        "PF" => __("French Polynesia", "raise"),
        "TF" => __("French Southern Territories", "raise"),
        "GA" => __("Gabon", "raise"),
        "GM" => __("Gambia", "raise"),
        "GE" => __("Georgia", "raise"),
        "DE" => __("Germany", "raise"),
        "GH" => __("Ghana", "raise"),
        "GI" => __("Gibraltar", "raise"),
        "GR" => __("Greece", "raise"),
        "GL" => __("Greenland", "raise"),
        "GD" => __("Grenada", "raise"),
        "GP" => __("Guadeloupe", "raise"),
        "GU" => __("Guam", "raise"),
        "GT" => __("Guatemala", "raise"),
        "GG" => __("Guernsey", "raise"),
        "GN" => __("Guinea", "raise"),
        "GW" => __("Guinea-Bissau", "raise"),
        "GY" => __("Guyana", "raise"),
        "HT" => __("Haiti", "raise"),
        "HM" => __("Heard Island and McDonald Islands", "raise"),
        "VA" => __("Holy See (Vatican City State)", "raise"),
        "HN" => __("Honduras", "raise"),
        "HK" => __("Hong Kong", "raise"),
        "HU" => __("Hungary", "raise"),
        "IS" => __("Iceland", "raise"),
        "IN" => __("India", "raise"),
        "ID" => __("Indonesia", "raise"),
        "IR" => __("Iran, Islamic Republic of", "raise"),
        "IQ" => __("Iraq", "raise"),
        "IE" => __("Ireland", "raise"),
        "IM" => __("Isle of Man", "raise"),
        "IL" => __("Israel", "raise"),
        "IT" => __("Italy", "raise"),
        "JM" => __("Jamaica", "raise"),
        "JP" => __("Japan", "raise"),
        "JE" => __("Jersey", "raise"),
        "JO" => __("Jordan", "raise"),
        "KZ" => __("Kazakhstan", "raise"),
        "KE" => __("Kenya", "raise"),
        "KI" => __("Kiribati", "raise"),
        "KP" => __("Korea, Democratic People's Republic of", "raise"),
        "KR" => __("Korea, Republic of", "raise"),
        "KW" => __("Kuwait", "raise"),
        "KG" => __("Kyrgyzstan", "raise"),
        "LA" => __("Lao People's Democratic Republic", "raise"),
        "LV" => __("Latvia", "raise"),
        "LB" => __("Lebanon", "raise"),
        "LS" => __("Lesotho", "raise"),
        "LR" => __("Liberia", "raise"),
        "LY" => __("Libya", "raise"),
        "LI" => __("Liechtenstein", "raise"),
        "LT" => __("Lithuania", "raise"),
        "LU" => __("Luxembourg", "raise"),
        "MO" => __("Macao", "raise"),
        "MK" => __("Macedonia, Former Yugoslav Republic of", "raise"),
        "MG" => __("Madagascar", "raise"),
        "MW" => __("Malawi", "raise"),
        "MY" => __("Malaysia", "raise"),
        "MV" => __("Maldives", "raise"),
        "ML" => __("Mali", "raise"),
        "MT" => __("Malta", "raise"),
        "MH" => __("Marshall Islands", "raise"),
        "MQ" => __("Martinique", "raise"),
        "MR" => __("Mauritania", "raise"),
        "MU" => __("Mauritius", "raise"),
        "YT" => __("Mayotte", "raise"),
        "MX" => __("Mexico", "raise"),
        "FM" => __("Micronesia, Federated States of", "raise"),
        "MD" => __("Moldova, Republic of", "raise"),
        "MC" => __("Monaco", "raise"),
        "MN" => __("Mongolia", "raise"),
        "ME" => __("Montenegro", "raise"),
        "MS" => __("Montserrat", "raise"),
        "MA" => __("Morocco", "raise"),
        "MZ" => __("Mozambique", "raise"),
        "MM" => __("Myanmar", "raise"),
        "NA" => __("Namibia", "raise"),
        "NR" => __("Nauru", "raise"),
        "NP" => __("Nepal", "raise"),
        "NL" => __("Netherlands", "raise"),
        "NC" => __("New Caledonia", "raise"),
        "NZ" => __("New Zealand", "raise"),
        "NI" => __("Nicaragua", "raise"),
        "NE" => __("Niger", "raise"),
        "NG" => __("Nigeria", "raise"),
        "NU" => __("Niue", "raise"),
        "NF" => __("Norfolk Island", "raise"),
        "MP" => __("Northern Mariana Islands", "raise"),
        "NO" => __("Norway", "raise"),
        "OM" => __("Oman", "raise"),
        "PK" => __("Pakistan", "raise"),
        "PW" => __("Palau", "raise"),
        "PS" => __("Palestinian Territory, Occupied", "raise"),
        "PA" => __("Panama", "raise"),
        "PG" => __("Papua New Guinea", "raise"),
        "PY" => __("Paraguay", "raise"),
        "PE" => __("Peru", "raise"),
        "PH" => __("Philippines", "raise"),
        "PN" => __("Pitcairn", "raise"),
        "PL" => __("Poland", "raise"),
        "PT" => __("Portugal", "raise"),
        "PR" => __("Puerto Rico", "raise"),
        "QA" => __("Qatar", "raise"),
        "RE" => __("Réunion", "raise"),
        "RO" => __("Romania", "raise"),
        "RU" => __("Russian Federation", "raise"),
        "RW" => __("Rwanda", "raise"),
        "SH" => __("Saint Helena, Ascension and Tristan da Cunha", "raise"),
        "KN" => __("Saint Kitts and Nevis", "raise"),
        "LC" => __("Saint Lucia", "raise"),
        "PM" => __("Saint Pierre and Miquelon", "raise"),
        "VC" => __("Saint Vincent and the Grenadines", "raise"),
        "WS" => __("Samoa", "raise"),
        "SM" => __("San Marino", "raise"),
        "ST" => __("Sao Tome and Principe", "raise"),
        "SA" => __("Saudi Arabia", "raise"),
        "SN" => __("Senegal", "raise"),
        "RS" => __("Serbia", "raise"),
        "SC" => __("Seychelles", "raise"),
        "SL" => __("Sierra Leone", "raise"),
        "SG" => __("Singapore", "raise"),
        "SK" => __("Slovakia", "raise"),
        "SI" => __("Slovenia", "raise"),
        "SB" => __("Solomon Islands", "raise"),
        "SO" => __("Somalia", "raise"),
        "ZA" => __("South Africa", "raise"),
        "GS" => __("South Georgia and the South Sandwich Islands", "raise"),
        "SS" => __("South Sudan", "raise"),
        "ES" => __("Spain", "raise"),
        "LK" => __("Sri Lanka", "raise"),
        "SD" => __("Sudan", "raise"),
        "SR" => __("Suriname", "raise"),
        "SJ" => __("Svalbard and Jan Mayen", "raise"),
        "SZ" => __("Swaziland", "raise"),
        "SE" => __("Sweden", "raise"),
        "CH" => __("Switzerland", "raise"),
        "SY" => __("Syrian Arab Republic", "raise"),
        "TW" => __("Taiwan, Province of China", "raise"),
        "TJ" => __("Tajikistan", "raise"),
        "TZ" => __("Tanzania, United Republic of", "raise"),
        "TH" => __("Thailand", "raise"),
        "TL" => __("Timor-Leste", "raise"),
        "TG" => __("Togo", "raise"),
        "TK" => __("Tokelau", "raise"),
        "TO" => __("Tonga", "raise"),
        "TT" => __("Trinidad and Tobago", "raise"),
        "TN" => __("Tunisia", "raise"),
        "TR" => __("Turkey", "raise"),
        "TM" => __("Turkmenistan", "raise"),
        "TC" => __("Turks and Caicos Islands", "raise"),
        "TV" => __("Tuvalu", "raise"),
        "UG" => __("Uganda", "raise"),
        "UA" => __("Ukraine", "raise"),
        "AE" => __("United Arab Emirates", "raise"),
        "GB" => __("United Kingdom", "raise"),
        "US" => __("United States", "raise"),
        "UM" => __("United States Minor Outlying Islands", "raise"),
        "UY" => __("Uruguay", "raise"),
        "UZ" => __("Uzbekistan", "raise"),
        "VU" => __("Vanuatu", "raise"),
        "VE" => __("Venezuela, Bolivarian Republic of", "raise"),
        "VN" => __("Viet Nam", "raise"),
        "VG" => __("Virgin Islands, British", "raise"),
        "VI" => __("Virgin Islands, U.S.", "raise"),
        "WF" => __("Wallis and Futuna", "raise"),
        "EH" => __("Western Sahara", "raise"),
        "YE" => __("Yemen", "raise"),
        "ZM" => __("Zambia", "raise"),
        "ZW" => __("Zimbabwe", "raise"),
    );

    $countriesEn = $GLOBALS['code2country'];

    // Sort by value
    asort($countries);

    // Merge
    $result = array_merge_recursive($countries, $countriesEn);

    // Filter
    if ($countryCodeFilters) {
        $resultSubset = array();
        foreach ($countryCodeFilters as $countryCodeFilter) {
            if (isset($result[$countryCodeFilter])) {
                $resultSubset[$countryCodeFilter] = $result[$countryCodeFilter];
            }
        }
        $result = $resultSubset;
    }

    return $result;
}

/**
 * Get English country name
 *
 * @param string $countryCode E.g. "CH" or "US"
 * @return string E.g. "Switzerland" or "United States"
 */
function raise_get_english_name_by_country_code($countryCode)
{
    $countryCode = strtoupper($countryCode);
    return raise_get($GLOBALS['code2country'][$countryCode], $countryCode);
}

/**
 * Get array with country codes where currency is used
 *
 * @param string $currency E.g. "CHF"
 * @return array E.g. array("LI", "CH")
 */
function raise_get_countries_by_currency($currency)
{
    $mapping = $GLOBALS['currency2country'];

    return raise_get($mapping[strtoupper($currency)], array());
}

/**
 * Get best localized value for settings that can be either a literal
 * or an array with a value per locale
 *
 * @param mixed   $setting
 * @param string  $language en|de|...
 * @return mixed|null
 */
function raise_get_localized_value($setting, $language = null)
{
    if (!is_array($setting)) {
        return $setting;
    }

    if (count($setting) > 0) {
        // Choose the best translation
        if (empty($language)) {
            $language = substr(get_locale(), 0, 2);
        }
        return raise_get($setting[$language], reset($setting));
    }

    return null;
}

/**
 * Get conditional value
 *
 * @param mixed         $setting
 * @param array         $donation
 * @param callable|null $valueMap
 * @param mixed         $default
 * @return mixed
 */
function raise_get_conditional_value($setting, $donation, $valueMap = null, $default = null)
{
    // If literal or associative array, return that value
    if (!is_array($setting) || raise_has_string_keys($setting)) {
        return $valueMap ? $valueMap($setting) : $setting;
    }

    // Setting is numeric --> JsonLogic nodes. Construct one big if operation
    $rule = raise_get_jsonlogic_if_rule($setting, $valueMap, $default);

    // Apply JsonLogic
    return JWadhams\JsonLogic::apply($rule, $donation);
}

/**
 * Return JsonLogic `if` rule or (localized) string
 * 
 * { if : [cond_1, val_1, cond_2, val_2, ..., cond_n, val_n, val_else ] }
 *
 * @param mixed         $setting
 * @param callable|null $valueMap A function run on all value nodes
 * @param mixed         $default  Default value
 * @return array
 */
function raise_get_jsonlogic_if_rule($setting, $valueMap = null, $default = null)
{
    // If literal or associative array, return that value (JSON encoded to avoid evaluation)
    if (empty($setting) || !is_array($setting) || raise_has_string_keys($setting)) {
        return $valueMap ? $valueMap($setting) : $setting;
    }

    // Iterate over array and construct if rule
    $if = [];
    foreach ($setting as $settingNode) {
        if (!isset($settingNode['value']) || !isset($settingNode['if'])) {
            throw new \Exception("Invalid JsonLogic node. Make sure value property and if property are present.");
        }

        // Add condition
        $if[] = $settingNode['if'];

        // Run mapping function and add result to rule (JSON encoded to avoid evaluation)
        $if[] = $valueMap ? $valueMap($settingNode['value']) : $settingNode['value'];
    }

    // Add catch-all default value
    $if[] = $default;

    return [
        'if' => $if
    ];
}
/**
 * Retruns value if exists, otherwise default
 *
 * @param mixed $var
 * @param mixed $default
 * @return mixed
 */
function raise_get(&$var, $default = null) {
    return isset($var) ? $var : $default;
}

/**
 * Get Ajax endpoint
 *
 * @return string
 */
function raise_get_ajax_endpoint()
{
    return admin_url('admin-ajax.php');
}

/**
 * Takes a CSV string (or array) and returns an array
 *
 * @param string|array $var
 * @return array
 */
function raise_csv_to_array($var)
{
    if (is_array($var)) {
        return $var;
    }

    return array_map('trim', explode(',', $var));
}

/**
 * Check honey pot (email-confirm). This value must be empty.
 *
 * @param array $post
 */
function raise_check_honey_pot($post)
{
    if (!empty($post['email-confirm'])) {
        throw new \Exception('bot');
    }
}

/**
 * Get twig singleton for form emails
 *
 * @param string $text    Email template text
 * @param string $subject Email template subject
 * @param bool   $html    Is email HTML?
 * @return Twig_Environment
 */
function raise_get_twig($text, $subject, $html)
{
    if (isset($GLOBALS['raise-twig'])) {
        return $GLOBALS['raise-twig'];
    }

    // Load macros
    $macros = <<<'EOD'
{% macro dump(array, mode) %}
{% if array|length %}
{% set lastKey   = array|keys|last %}
{% set lastValue = array|last %}
{% if mode == 'html' %}
    {% for key, val in array|slice[:-1] %}
        <strong>{{ key }}</strong>: {{ val }}<br>
    {% endfor %}
    <strong>{{ lastKey }}</strong>: {{ lastValue }}
{% else %}{% for key, val in array[:-1] %}{{ key }}: {{ val ~ "\n"}}{% endfor %}{{ lastKey }}: {{ lastValue }}{% endif %}
{% endif %}
{% endmacro %}
{% import _self as raise %}
EOD;

    // Prepare email text
    if ($html) {
        $emailText = <<<'EOD'
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Donation</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
</head>
<body>
EOD;
        $emailText .= $macros . nl2br($text);
        $emailText .= <<<'EOD'
</body>
</html>
EOD;
    } else {
        $emailText = $macros . $text;
    }

    $twigSettings      = array(
        'finish.email.subject'        => $subject,
        'finish.email.text'           => $emailText,
        'bank_account_formatted_html' => $macros . "{{ raise.dump(bank_account, 'html') }}",
        'bank_account_formatted_text' => $macros . "{{ raise.dump(bank_account, 'text') }}",
    );

    // Instantiate twig
    $loader = new Twig_Loader_Array($twigSettings);
    $twig   = new Twig_Environment($loader, array(
        'autoescape' => $html ? 'html' : false,
    ));

    // Save twig globally
    $GLOBALS['raise-twig'] = $twig;

    return $twig;
}

/**
 * Send out emails
 *
 * @param array  $donation Donation
 */
function raise_send_emails(array $donation)
{
    // Send confirmation email
    raise_send_confirmation_email($donation);

    // Send notification email
    raise_send_notification_email($donation);
}

/**
 * Monoloinguify language labels on level
 *
 * @param array  $labels
 * @param int    $depth
 * @param string $language
 * @return array
 */
function raise_monolinguify(array $labels, $depth = 0, $language = null)
{
    if (!$depth--) {
        foreach (array_keys($labels) as $key) {
            if (is_array($labels[$key])) {
                $labels[$key] = raise_get_localized_value($labels[$key], $language);
            }
        }
    } else {
        foreach (array_keys($labels) as $key) {
            if (is_array($labels[$key])) {
                $labels[$key] = raise_monolinguify($labels[$key], $depth, $language);
            }
        }
    }

    return $labels;
}

/**
 * Load tax deduction settings
 *
 * @param string $form     Form name
 * @param string $language Language
 * @return array|null
 * @see raise_get_tax_deduction_settings_by_donation
 */
function raise_load_tax_deduction_settings($form, $language = null)
{
    // Get local settings
    $formSettings         = raise_load_settings($form);
    $taxDeductionSettings = raise_get($formSettings['payment']['labels']['tax_deduction'], []);

    return $taxDeductionSettings ? raise_monolinguify($taxDeductionSettings, 3, $language) : null;
}

/**
 * Get bank transfer token
 *
 * @param string $form        Form name
 * @param string $prefix      Constitutes a separate block
 * @param int    $length      Total length, without prefix and hyphens
 * @param int    $blockLength Blocks are separated by a hyphen
 * @param string $separator   Separates blocks
 * @return string
 */
function raise_get_banktransfer_reference($form, $prefix = '', $length = 8, $blockLength = 4, $separator = '-')
{
    $codeAlphabet = "ABCDEFGHJKLMNPQRTWXYZ"; // without I, O, V, U, S
    $codeAlphabet.= "0123456789";
    $max          = strlen($codeAlphabet);
    $token        = "";

    // Generate token
    for ($i = 0; $i < $length; $i++) {
        $token .= $codeAlphabet[rand(0, $max-1)];
    }

    // Chunk split token string
    $tokenArray = str_split($token, $blockLength);

    // Add prefix to token array
    if (!empty($prefix)) {
        // Load settings
        $formSettings = raise_load_settings($form);

        // Check if reference number prefix is defined
        if (
            ($predefinedPrefix = raise_get($formSettings['payment']['reference_number_prefix'][$prefix])) ||
            ($predefinedPrefix = raise_get($formSettings['payment']['reference_number_prefix']['default']))
        ) {
            $prefix = $predefinedPrefix;
        }

        array_unshift($tokenArray, strtoupper($prefix));
    }

    return join($separator, $tokenArray);
}

/**
 * Get PayPal API context
 *
 * @param array $donation
 * @return \PayPal\Rest\ApiContext
 * @throws \Exception
 */
function raise_get_paypal_api_context(array $donation)
{
    // Get PayPal account settings
    $settings = raise_get_payment_provider_account_settings('paypal', $donation);

    $apiContext = new \PayPal\Rest\ApiContext(
        new \PayPal\Auth\OAuthTokenCredential(
            $settings['client_id'],
            $settings['client_secret']
        )
    );

    $mode = raise_get($donation['mode']);
    if ($mode === 'live') {
        $apiContext->setConfig(array('mode' => 'live'));
    }

    return $apiContext;
}

/**
 * Localize array keys
 *
 * @param array $array
 * @return array
 */
function raise_localize_array_keys(array $array)
{
    $localizedKeys = array_map(function($key) {
        return __($key, "raise");
    }, array_keys($array));

    return array_combine($localizedKeys, array_values($array));
}

/**
 * Get donation account property
 *
 * @param array $formSettings
 * @param array $donation
 * @return string|null
 */
function raise_get_account(array $formSettings, array $donation)
{
    // Check if provider settings exist
    $providerKeys    = array_flip($GLOBALS['pp_key2pp_label']);
    $paymentProvider = $donation['payment_provider'];
    $providerKey     = raise_get($providerKeys[$paymentProvider]);

    if (!$providerKey || empty($formSettings['payment']['provider'][$providerKey])) {
        return null;
    }

    // Return account
    $settings = $formSettings['payment']['provider'][$providerKey];
    $mapper   = function ($item) { return raise_get($item['account']); };

    return raise_get_conditional_value($settings, $donation, $mapper);
}

/**
 * Clean up donation data, save local posts, send webhooks, send emails
 *
 * @param array $donation
 */
function raise_do_post_donation_actions(array $donation)
{
    // Add post donation instructions and deductible flag to donation
    $formSettings           = raise_load_settings($donation['form']);
    $taxReceiptSettings     = raise_get($formSettings['payment']['form_elements']['tax_receipt']);
    $mapper                 = function ($item) { return !raise_get($item['disabled'], false); };
    $donation['deductible'] = raise_get_conditional_value($taxReceiptSettings, $donation, $mapper);

    // Add account
    if ($account = raise_get_account($formSettings, $donation)) {
        $donation['account'] = $account;
    }

    // Clean up donation data
    $cleanDonation = raise_clean_up_donation_data($donation);

    // Save custom posts (if enabled)
    raise_save_custom_posts($cleanDonation);

    // Trigger web hooks
    raise_trigger_webhooks($cleanDonation);

    // Send emails
    raise_send_emails($cleanDonation);
}

/**
 * Merge settings recursively (except numeric arrays)
 *
 * @param array $array
 * @param array $array1
 * @return array
 */
function raise_array_replace_recursive($array, $array1)
{
    $recurse = function($array, $array1) use (&$recurse)
    {
        foreach ($array1 as $key => $value)
        {
            // Create new key in $array, if it is empty or not an array
            if (!isset($array[$key]) || (isset($array[$key]) && !is_array($array[$key]))) {
                $array[$key] = array();
            }

            // Overwrite the value in the base array
            if (is_array($value)) {
                if (raise_has_string_keys($value)) {
                    // Replace if string keys
                    $value = $recurse($array[$key], $value);
                } else {
                    // Prepend if numeric keys
                    $value = array_merge($value, $array[$key]);
                }
            }
            $array[$key] = $value;
        }

        return $array;
    };

    // Handle the arguments, merge one by one
    $args  = func_get_args();
    $array = $args[0];
    if (!is_array($array)) {
        return $array;
    }
    for ($i = 1; $i < count($args); $i++) {
        if (is_array($args[$i])) {
            $array = $recurse($array, $args[$i]);
        }
    }

    return $array;
  }

/**
 * Find smallest country flag sprite with all `country_flag` 
 * instances in settings with brute force search
 *
 * @return string|null `most`, `some`, `few` or null (if less than 2 flags)
 */
function raise_get_best_flag_sprite()
{
    $settings = get_option('raise_settings');

    // Check external settings as well
    if (function_exists('raise_config')) {
        $settings .= json_encode(raise_config());
    }

    preg_match_all('/"country_flag":"(\w\w)"/', $settings, $matches);
    $matches = raise_get($matches[1], []);

    if (count($matches) <= 1) {
        // Flags won't be shown to user since there's no choice
        return null;
    }

    // Find best sprite (default = `most`)
    $flagSprite         = 'most';
    $smallerFlagSprites = [
        'few'  => ['eu', 'ch', 'gb', 'us'],
        'some' => ['au', 'br', 'ca', 'ch', 'cl', 'cn', 'cz', 'dk', 'eu', 'gb', 'hk', 'hu', 'id', 'il', 'in', 'jp', 'kr', 'mx', 'my', 'no', 'nz', 'ph', 'pk', 'pl', 'ru', 'se', 'sg', 'th', 'tr', 'tw', 'us', 'za'],
    ];

    foreach ($smallerFlagSprites as $sprite => $flags) {
        $allFlagsCovered = array_reduce($matches, function ($carry, $item) use ($flags) {
            return $carry && in_array($item, $flags);
        }, true);

        if ($allFlagsCovered) {
            // We've found a smaller sprite
            $flagSprite = $sprite;
            break;
        }
    }

    return $flagSprite;
}

/**
 * Does the current form have a payment provider with monthly support?
 *
 * @param array $enabledProviders
 * @return bool
 */
function raise_monthly_frequency_supported(array $enabledProviders)
{
    return array_reduce($GLOBALS['monthlySupport'], function ($carry, $item) use ($enabledProviders) {
        return $carry || in_array($item, $enabledProviders);
    }, false);
}
