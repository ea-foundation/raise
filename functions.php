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
    'tax_receipt',
    'time',
    'type', //TODO Legacy. Remove in next major release.
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
    $amountPatterns      = array();
    $currencies          = raise_get($formSettings['amount']['currency'], array());
    foreach ($currencies as $currency => $currencySettings) {
        $amountPatterns[strtoupper($currency)] = raise_get($currencySettings['pattern'], '%amount%');
    }

    // Get enabled payment providers
    $enabledProviders = raise_enabled_payment_providers($formSettings, $mode);

    // Get Stripe public keys
    $stripeKeys = in_array('stripe', $enabledProviders) ? raise_get_stripe_public_keys($formSettings, $mode) : array();

    // Get tax deduction labels
    $taxDeductionLabels = raise_load_tax_deduction_settings($form);

    // Get bank accounts and localize their labels
    $bankAccounts = array_map('raise_localize_array_keys', raise_get($formSettings['payment']['provider']['banktransfer']['accounts'], array()));

    // Localize script
    wp_localize_script('donation-plugin-form', 'wordpress_vars', array(
        'logo'                  => $logo,
        'ajax_endpoint'         => admin_url('admin-ajax.php'),
        'amount_patterns'       => $amountPatterns,
        'stripe_public_keys'    => $stripeKeys,
        'tax_deduction_labels'  => $taxDeductionLabels,
        'bank_accounts'         => $bankAccounts,
        'organization'          => $GLOBALS['raiseOrganization'],
        'currency2country'      => $GLOBALS['currency2country'],
        'donate_button_once'    => __("Donate %currency-amount%", "raise"),
        'donate_button_monthly' => __("Donate %currency-amount% per month", "raise"),
        'donation'              => __("Donation", "raise"),
        'cookie_warning'        => __("Please enable cookies before you proceed with your donation.", "raise"),
    ));

    // Enqueue previously registered scripts and styles (to prevent them loading on every page load)
    wp_enqueue_script('donation-plugin-bootstrapjs');
    wp_enqueue_script('donation-plugin-jqueryformjs');
    if (in_array('stripe', $enabledProviders)) {
        wp_enqueue_script('donation-plugin-stripe');
    }
    if (in_array('paypal', $enabledProviders)) {
        wp_enqueue_script('donation-plugin-paypal');
    }
    wp_enqueue_script('donation-plugin-form');
    wp_enqueue_script('donation-combobox');

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
        throw new \Exception("Circular form definition. See Settings > Donation Plugin");
    }

    if (!isset($formsSettings[$form])) {
        throw new \Exception("No settings found for form '$form'. See Settings > Donation Plugin");
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

    // Extract default settings (always needed)
    $providers = array_keys(array_filter($providerSettings, function ($settings, $provider) use ($mode) {
        return strpos($provider, '_') === false &&
               is_array($settings) &&
               raise_payment_provider_settings_complete($provider, raise_get($settings[$mode], array()));
    }, ARRAY_FILTER_USE_BOTH));

    return $providers;
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
                $text   = '<span class="payment-method-name sr-only">' . __('credit card', 'raise') . '</span>';
                $images = array(
                    array(
                        'path' => plugins_url('images/visa.png', __FILE__),
                        'alt'  => 'Visa',
                    ),
                    array(
                        'path' => plugins_url('images/mastercard.png', __FILE__),
                        'alt'  => 'Mastercard',
                    ),
                    array(
                        'path' => plugins_url('images/americanexpress.png', __FILE__),
                        'alt'  => 'American Express',
                    ),
                );
                break;
            case 'paypal':
                $value  = 'PayPal';
                $text   = '<span class="payment-method-name sr-only">PayPal</span>';
                $images = array(
                    array(
                        'path' => plugins_url('images/paypal.png', __FILE__),
                        'alt'  => 'PayPal',
                    ),
                );
                break;
            case 'bitpay':
                $value  = 'BitPay';
                $text   = '<span class="payment-method-name sr-only">Bitcoin</span>';
                $images = array(
                    array(
                        'path'  => plugins_url('images/bitcoin.png', __FILE__),
                        'alt'   => 'Bitcoin',
                        'width' => 23,
                    ),
                );
                break;
            case 'skrill':
                $value  = 'Skrill';
                $text   = '<span class="payment-method-name sr-only">Skrill</span>';
                $images = array(
                    array(
                        'path'  => plugins_url('images/skrill.png', __FILE__),
                        'alt'   => 'Skrill',
                    ),
                );
                break;
            case 'gocardless':
                $value  = 'GoCardless';
                $text   = '<a href="#" onClick="jQuery(\'#payment-gocardless\').click(); return false" data-toggle="tooltip" data-container="body" data-placement="top" title="' . __('Available for Eurozone, UK, and Sweden', 'raise') . '" style="text-decoration: none; color: inherit;"><span class="payment-method-name">' . __('direct debit', 'raise') . '</span></a>';
                $images = array();
                break;
            case 'banktransfer':
                $value  = 'Bank Transfer';
                $text   = '<span class="payment-method-name">' . __('bank transfer', 'raise') . '</span>';
                $images = array();
                break;
            default:
                // Do nothing
        }

        // Print radio box
        $id          = str_replace(' ', '', strtolower($value));
        $checkedAttr = $checked ? 'checked' : '';
        $checked     = false;
        $result .= '<label for="payment-' . $id . '" class="radio-inline">';
        $result .= '<input type="radio" name="payment_provider" value="' . $value . '" id="payment-' . $id . '" ' . $checkedAttr . '> ';
        foreach ($images as $image) {
            $width  = raise_get($image['width'], 38);
            $height = raise_get($image['height'], 23);
            $result .= '<img src="' . $image['path'] . '" alt="' . $image['alt'] . '" width="' . $width . '" height="' . $height . '"> ';
        }
        $result .= $text;
        $result .= '</label>' . "\n";
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

    // Convert amount to cents
    if (is_numeric($post['amount'])) {
        $post['amountInt'] = (int)($post['amount'] * 100);
        $post['amount']    = money_format('%i', $post['amountInt'] / 100);
    } else {
        throw new \Exception('Invalid amount.');
    }

    return array(
        'form'                 => $post['form'],
        'mode'                 => $post['mode'],
        'url'                  => $_SERVER['HTTP_REFERER'],
        'language'             => $post['language'],
        'time'                 => date('c'),
        'currency'             => $post['currency'],
        'amount'               => $post['amount'],
        'frequency'            => $post['frequency'],
        'payment_provider'     => $post['payment_provider'],
        'email'                => $post['email'],
        'name'                 => $post['name'],
        'purpose'              => raise_get($post['purpose'], ''),
        'address'              => raise_get($post['address'], ''),
        'zip'                  => raise_get($post['zip'], ''),
        'city'                 => raise_get($post['city'], ''),
        'country'              => raise_get($post['country'], ''),
        'comment'              => raise_get($post['comment'], ''),
        'account'              => raise_get($post['account'], ''),
        'g-recaptcha-response' => raise_get($post['g-recaptcha-response'], ''),
        'anonymous'            => (bool) raise_get($post['anonymous'], false),
        'mailinglist'          => (bool) raise_get($post['mailinglist'], false),
        'tax_receipt'          => (bool) raise_get($post['tax_receipt'], false),
    );
}

/**
 * AJAX endpoint that creates redirect response (PayPal, Skrill, GoCardless, BitPay)
 *
 * @return string JSON response
 */
function raise_prepare_redirect()
{
    try {
        // Trim the data
        $post = array_map('trim', $_POST);

        // Replace amount_other
        if (!empty($post['amount_other'])) {
            $post['amount'] = $post['amount_other'];
        }
        unset($post['amount_other']);

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
            default:
                throw new \Exception('Payment method ' . $post['payment_provider'] . ' is invalid');
        }

        // Return response
        die(json_encode($response));
    } catch (\Exception $e) {
        die(json_encode(array(
            'success' => false,
            'error'   => "An error occured and your donation could not be processed (" .  $e->getMessage() . "). Please contact us.",
        )));
    }
}

/**
 * AJAX endpoint that deals with submitted donation data (Bank Tarnsfer and Stripe)
 *
 * @return string JSON response
 */
function raise_process_donation()
{
    try {
        // Get donation
        $donation = raise_get_donation_from_post();

        // Output
        if ($donation['payment_provider'] == "Stripe") {
            // Make sure we have the Stripe token
            if (empty($_POST['stripeToken']) || empty($_POST['stripePublicKey'])) {
                throw new \Exception("No Stripe token sent");
            }

            // Handle payment
            raise_handle_stripe_payment($donation, $_POST['stripeToken'], $_POST['stripePublicKey']);

            // Prepare response
            $response = array('success' => true);
        } else if ($donation['payment_provider'] == "Bank Transfer") {
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
        } else {
            throw new \Exception('Payment method is invalid');
        }

        die(json_encode($response));
    } catch (\Exception $e) {
        die(json_encode(array(
            'success' => false,
            'error'   => "An error occured and your donation could not be processed (" .  $e->getMessage() . "). Please contact us.",
        )));
    }
}

/**
 * Process Stripe payment
 *
 * @param array $donation Donation data from donation form
 * @param string $token
 * @param string $publicKey
 * @throws \Exception On error from Stripe API
 */
function raise_handle_stripe_payment($donation, $token, $publicKey)
{
    // Create the charge on Stripe's servers - this will charge the user's card
    try {
        // Get Stripe settings
        $formSettings = raise_load_settings($donation['form']);
        $settings     = raise_get_best_payment_provider_settings(
            $formSettings,
            'stripe',
            $donation['mode'],
            $donation['tax_receipt'],
            $donation['currency'],
            raise_get($donation['country'])
        );

        if ($settings['public_key'] != $publicKey) {
            throw new \Exception("Key mismatch");
        }

        // Load secret key
        \Stripe\Stripe::setApiKey($settings['secret_key']);

        // Make customer
        $customer = \Stripe\Customer::create(array(
            'source'      => $token,
            'email'       => $donation['email'],
            'description' => $donation['name'],
        ));

        // Make charge/subscription
        $amountInt = (int)($donation['amount'] * 100);
        if ($donation['frequency'] == 'monthly') {
            // Get plan
            $plan = raise_get_stripe_plan($amountInt, $donation['currency']);

            // Subscribe customer to plan
            $subscription = \Stripe\Subscription::create(array(
                'customer' => $customer->id,
                'plan'     => $plan,
                'metadata' => array(
                    'url'     => $_SERVER['HTTP_REFERER'],
                    'purpose' => $donation['purpose'],
                ),
            ));

            // Add vendor reference ID
            $donation['vendor_subscription_id'] = $subscription->id;
        } else {
            // Make one-time charge
            $charge = \Stripe\Charge::create(array(
                'customer'    => $customer->id,
                'amount'      => $amountInt, // !!! in cents !!!
                'currency'    => $donation['currency'],
                'description' => 'Donation from ' . $donation['name'],
                'metadata'    => array(
                    'url'     => $_SERVER['HTTP_REFERER'],
                    'purpose' => $donation['purpose'],
                ),
            ));

            // Add vendor transaction ID
            $donation['vendor_transaction_id'] = $charge->id;
        }

        // Add customer ID
        $donation['vendor_customer_id'] = $customer->id;

        // Do post donation actions
        raise_do_post_donation_actions($donation);
    } catch (\Stripe\Error\InvalidRequest $e) {
        // The card has been declined
        throw new \Exception($e->getMessage() . " " . $e->getStripeParam()); // . " : $form : $mode : $email : $amount : $currency");
    } catch (\Exception $e) {
        throw new \Exception($e->getMessage()); // . " : $form : $mode : $email : $amount : $currency");
    }
}

/**
 * Get monthly Stripe plan
 *
 * @param int $amount Plan amount in cents
 * @param int $currency Plan currency
 * @return array
 */
function raise_get_stripe_plan($amount, $currency)
{
    $planId = 'donation-month-' . $currency . '-' . money_format('%i', $amount / 100);

    try {
        // Try fetching an existing plan
        $plan = \Stripe\Plan::retrieve($planId);
    } catch (\Exception $e) {
        // Create a new plan
        $params = array(
            'amount'   => $amount,
            'interval' => 'month',
            'name'     => 'Monthly donation of ' . $currency . ' ' . money_format('%i', $amount / 100),
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
    // Logging
    raise_trigger_logging_webhooks($donation);

    // Mailing list
    if ($donation['mailinglist'] == 'yes') {
        raise_trigger_mailinglist_webhooks($donation);
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
            //TODO Remove extra array layer around $donation in next major release
            raise_send_webhook($hook, ['donation' => $donation]);
        }
    }
}

/**
 * Remove unncecessary field from webhook data
 *
 * @param array $donation
 * @return array
 */
function raise_clean_up_donation_data(array $donation)
{
    // Transform boolean values to yes/no string
    $donation = array_map(function($val) {
        return is_bool($val) ? ($val ? 'yes' : 'no') : $val;
    }, $donation);

    // Translate country code to English
    if (!empty($donation['country'])) {
        $donation['country_code'] = $donation['country'];
        $donation['country']      = raise_get_english_name_by_country_code($donation['country']);
    }

    // Add referrer from query string if present
    $parts = parse_url(raise_get($donation['url']));
    parse_str(raise_get($parts['query'], ''), $query);
    $donation['referrer'] = raise_get($query['referrer']);

    //TODO Legacy property. Remove in next major release.
    $donation['type'] = raise_get($donation['payment_provider'], '');

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
            //TODO Remove extra array layer around $subscription in next major release
            raise_send_webhook($hook, ['subscription' => $subscription]);
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
        'referer'    => get_bloginfo('url'),
    );
    
    wp_remote_post($url, $args);
}

/**
 * Get GoCardless client
 *
 * @param string $form Form name
 * @param string $mode Form mode (live/sandbox)
 * @param bool   $taxReceiptNeeded
 * @param string $currency
 * @param string $country
 * @return \GoCardlessPro\Client
 */
function raise_get_gocardless_client($form, $mode, $taxReceiptNeeded, $currency, $country)
{
    // Get access token
    $formSettings = raise_load_settings($form);
    $settings     = raise_get_best_payment_provider_settings(
        $formSettings,
        "gocardless",
        $mode,
        $taxReceiptNeeded,
        $currency,
        $country
    );

    return new \GoCardlessPro\Client([
        'access_token' => $settings['access_token'],
        'environment'  => $mode == 'live' ? \GoCardlessPro\Environment::LIVE : \GoCardlessPro\Environment::SANDBOX,
    ]);
}

/**
 * Get best payment settings for the donor
 *
 * @param array  $formSettings
 * @param string $provider
 * @param string $mode
 * @param bool   $taxReceiptNeeded
 * @param string $currency
 * @param string $country
 * @return array
 * @throws \Exception
 */
function raise_get_best_payment_provider_settings(
    $formSettings,
    $provider,
    $mode,
    $taxReceiptNeeded,
    $currency,
    $country
) {
    // Make things lowercase
    $provider = strtolower($provider);
    $currency = strtolower($currency);
    $country  = strtolower($country);

    // Extract settings of the form we're talking about
    $countryCompulsory = raise_get($formSettings['payment']['extra_fields']['country'], false);

    // Check all possible settings
    $providers = $formSettings['payment']['provider'];
    if (empty($providers[$provider][$mode])) {
        throw new \Exception("No default settings found for $provider in $mode mode");
    }
    $hasCountrySetting  = raise_payment_provider_settings_complete($provider, raise_get($providers[$provider . '_' . $country][$mode], array()));
    $hasCurrencySetting = raise_payment_provider_settings_complete($provider, raise_get($providers[$provider . '_' . $currency][$mode], array()));
    $hasDefaultSetting  = raise_payment_provider_settings_complete($provider, raise_get($providers[$provider][$mode], array()));

    // Check if there are settings for a country where the chosen currency is used.
    // This is only relevant if the donor does not need a donation receipt (always related
    // to specific country) and if there are no currency specific settings
    $hasCountryOfCurrencySetting = false;
    $countryOfCurrency           = '';
    if (!$countryCompulsory && !$taxReceiptNeeded && !$hasCurrencySetting) {
        $countries = array_map('strtolower', raise_get_countries_by_currency($currency));
        foreach ($countries as $coc) {
            if (isset($providers[$provider . '_' . $coc][$mode])) {
                // Make sure we have all the properties
                $hasCountryOfCurrencySetting = raise_payment_provider_settings_complete($provider, raise_get($providers[$provider . '_' . $coc][$mode], array()));

                // If so, stop
                if ($hasCountryOfCurrencySetting) {
                    $countryOfCurrency = $coc;
                    break;
                }
            }
        }
    }

    if ($hasCountrySetting && ($taxReceiptNeeded || $countryCompulsory)) {
        // Use country specific settings
        return $providers[$provider . '_' . $country][$mode];
    } else if ($hasCurrencySetting) {
        // Use currency specific settings
        return $providers[$provider . '_' . $currency][$mode];
    } else if ($hasCountryOfCurrencySetting) {
        // Use settings of a country where the chosen currency is used
        return $providers[$provider . '_' . $countryOfCurrency][$mode];
    } else if ($hasDefaultSetting) {
        // Use default settings
        return $providers[$provider][$mode];
    } else {
        $requiredProperties = raise_get_payment_provider_properties($provider);
        $advice             = $requiredProperties ? " Required properties: " . implode(', ', $requiredProperties) : "";

        throw new \Exception("No valid settings found for $provider." . $advice);
    }
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
 * @param array $post
 * @return array
 */
function raise_prepare_gocardless_donation(array $post)
{
    try {
        // Make GoCardless redirect flow
        $reqId        = uniqid(); // Secret request ID. Needed to prevent replay attack
        $returnUrl    = raise_get_ajax_endpoint() . '?action=gocardless_debit&req=' . $reqId;
        $monthly      = $post['frequency'] == 'monthly' ? ", " . __("monthly", "raise") : "";
        $client       = raise_get_gocardless_client(
            $post['form'],
            $post['mode'],
            raise_get($post['tax_receipt'], false),
            $post['currency'],
            $post['country']
        );
        $redirectFlow = $client->redirectFlows()->create([
            "params" => [
                "description"          => __("Donation", "raise") . " (" . $post['currency'] . " " . money_format('%i', $post['amount']) . $monthly . ")",
                "session_token"        => $reqId,
                "success_redirect_url" => $returnUrl,
            ]
        ]);

        // Save flow ID to session
        $_SESSION['raise-gocardless-flow-id'] = $redirectFlow->id;

        // Save rest to session
        raise_set_donation_data_to_session($post, $reqId);

        // Return redirect URL
        return array(
            'success' => true,
            'url'     => $redirectFlow->redirect_url,
        );
    } catch (\Exception $ex) {
        return array(
            'success' => false,
            'error'   => "An error occured and your donation could not be processed (" .  $ex->getMessage() . "). Please contact us.",
        );
    }
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
        $form       = $donation['form'];
        $mode       = $donation['mode'];
        $taxReceipt = $donation['tax_receipt'];
        $currency   = $donation['currency'];
        $country    = $donation['country'];
        $reqId      = $donation['reqId'];
        $client     = raise_get_gocardless_client($form, $mode, $taxReceipt, $currency, $country);

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
        $language  = $donation['language'];
        $url       = $donation['url'];
        $amount    = $donation['amount'];
        $amountInt = floor($amount * 100);
        $name      = $donation['name'];
        $email     = $donation['email'];
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
        if ($frequency == 'monthly') {
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
         <html lang="en"><head><meta charset="utf-8"><title>Closing flow...</title></head>
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
 * Get BitPay object
 *
 * @param string $form
 * @param string $mode
 * @param bool   $taxReceipt
 * @param string $currency
 * @param string $country
 * @return \Bitpay\Bitpay
 */
function raise_get_bitpay_dependency_injector($form, $mode, $taxReceipt, $currency, $country)
{
    // Get BitPay pairing code
    $formSettings = raise_load_settings($form);
    $settings     = raise_get_best_payment_provider_settings(
        $formSettings,
        "bitpay",
        $mode,
        $taxReceipt,
        $currency,
        $country
    );
    $pairingCode = $settings['pairing_code'];

    // Get key IDs
    list($privateKeyId, $publicKeyId, $tokenId) = raise_get_bitpay_key_ids($pairingCode);

    // Get BitPay client
    $bitpay = new \Bitpay\Bitpay(array(
        'bitpay' => array(
            'network'              => $mode == 'live' ? 'livenet' : 'testnet',
            'public_key'           => $publicKeyId,
            'private_key'          => $privateKeyId,
            'key_storage'          => 'Raise\Bitpay\EncryptedWPOptionStorage',
            'key_storage_password' => $pairingCode, // Abuse pairing code for this
        )
    ));

    return $bitpay;
}

/**
 * Get BitPay token
 *
 * @param \Bitpay\Bitpay $bitpay
 * @param string $label
 * @return \Bitpay\Token
 */
function raise_generate_bitpay_token(\Bitpay\Bitpay $bitpay, $label = '')
{
    // Get BitPay pairing code as well as key/token IDs
    $pairingCode = $bitpay->getContainer()->getParameter('bitpay.key_storage_password');
    list($privateKeyId, $publicKeyId, $tokenId) = raise_get_bitpay_key_ids($pairingCode);
    
    // Generate keys
    $privateKey = \Bitpay\PrivateKey::create($privateKeyId)
        ->generate();
    $publicKey  = \Bitpay\PublicKey::create($publicKeyId)
        ->setPrivateKey($privateKey)
        ->generate();

    // Save keys (abuse pairing code as encryption password)
    $keyStorage = $bitpay->get('key_manager');
    $keyStorage->persist($privateKey);
    $keyStorage->persist($publicKey);

    // Get token
    // @var \Bitpay\SinKey
    $sin   = \Bitpay\SinKey::create()->setPublicKey($publicKey)->generate();
    $token = $bitpay->get('client')->createToken(array(
        'pairingCode' => $pairingCode,
        'label'       => $label,
        'id'          => (string) $sin,
    ));

    // Save token
    update_option($tokenId, $token->getToken());

    return $token;
}

/**
 * Get BitPay client
 *
 * @param string $form
 * @param string $mode
 * @param bool   $taxReceipt
 * @param string $currency
 * @param string $country
 * @return \Bitpay\Client\Client
 */
function raise_get_bitpay_client($form, $mode, $taxReceipt, $currency, $country)
{
    // Get BitPay dependency injector
    $bitpay = raise_get_bitpay_dependency_injector($form, $mode, $taxReceipt, $currency, $country);

    // Get BitPay pairing code as well as key/token IDs
    $pairingCode = $bitpay->getContainer()->getParameter('bitpay.key_storage_password');
    list($privateKeyId, $publicKeyId, $tokenId) = raise_get_bitpay_key_ids($pairingCode);

    // Generate token if first time
    if (!get_option($publicKeyId) || !get_option($privateKeyId) || !($tokenString = get_option($tokenId))) {
        $urlParts = parse_url(home_url());
        $label    = $urlParts['host'];
        $token    = raise_generate_bitpay_token($bitpay, $label);
    } else {
        $token = new \Bitpay\Token();
        $token->setToken($tokenString);
    }

    $client = $bitpay->get('client');
    $client->setToken($token);

    return $client;
}

/**
 * Returns the Skrill URL. It stores
 * user input in session until user is forwarded back from Skrill
 *
 * @param array $post
 * @return array
 */
function raise_prepare_skrill_donation(array $post)
{
    try {
        // Save request ID to session
        $reqId = uniqid(); // Secret request ID. Needed to prevent replay attack

        // Put user data in session
        raise_set_donation_data_to_session($post, $reqId);

        // Get Skrill URL
        $url = raise_get_skrill_url($reqId, $post);

        // Return URL
        return array(
            'success' => true,
            'url'     => $url,
        );
    } catch (\Exception $e) {
        return array(
            'success' => false,
            'error'   => "An error occured and your donation could not be processed (" .  $e->getMessage() . "). Please contact us.",
        );
    }
}

/**
 * Get Skrill URL
 *
 * @param string $reqId
 * @param array  $post
 * @return string
 */
function raise_get_skrill_url($reqId, $post)
{
    // Get best Skrill account settings
    $formSettings = raise_load_settings($post['form']);
    $settings     = raise_get_best_payment_provider_settings(
        $formSettings,
        "skrill",
        $post['mode'],
        raise_get($post['tax_receipt'], false),
        $post['currency'],
        $post['country']
    );

    // Prepare parameter array
    $params = array(
        'pay_to_email'      => $settings['merchant_account'],
        'pay_from_email'    => $post['email'],
        'amount'            => $post['amount'],
        'currency'          => $post['currency'],
        'return_url'        => raise_get_ajax_endpoint() . '?action=skrill_log&req=' . $reqId,
        'return_url_target' => 3, // _self
        'logo_url'          => preg_replace("/^http:/i", "https:", get_option('raise_logo', plugin_dir_url(__FILE__) . 'images/logo.png')),
        'language'          => strtoupper($post['language']),
        'transaction_id'    => $reqId,
        'payment_methods'   => "WLT", // Skrill comes first
        'prepare_only'      => 1, // Return URL instead of form HTML
    );

    // Add parameters for monthly donations
    if ($post['frequency'] == 'monthly') {
        $recStartDate = new \DateTime('+1 month');
        $params['rec_amount']     = $post['amount'];
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
    if ($post['mode'] == 'sandbox') {
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
 * @param array $post
 * @return array
 */
function raise_prepare_bitpay_donation(array $post)
{
    try {
        $form       = $post['form'];
        $mode       = $post['mode'];
        $language   = $post['language'];
        $email      = $post['email'];
        $name       = $post['name'];
        $amount     = $post['amount'];
        $currency   = $post['currency'];
        $taxReceipt = raise_get($post['tax_receipt'], false);
        $country    = $post['country'];
        $frequency  = $post['frequency'];
        $reqId      = uniqid(); // Secret request ID. Needed to prevent replay attack
        $returnUrl  = raise_get_ajax_endpoint() . '?action=bitpay_log&req=' . $reqId;
        //$returnUrl       = raise_get_ajax_endpoint() . '?action=bitpay_confirm';

        // Get BitPay object and token
        $client = raise_get_bitpay_client($form, $mode, $taxReceipt, $currency, $country);

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
            $request  = $client->getRequest();
            $response = $client->getResponse();
            $message  = (string) $request.PHP_EOL.PHP_EOL.PHP_EOL;
            $message .= (string) $response.PHP_EOL.PHP_EOL;
            throw new \Exception($message);
        }

        // Save invoice ID to session
        $_SESSION['raise-vendor-transaction-id']  = $invoice->getId();

        // Save user data to session
        raise_set_donation_data_to_session($post, $reqId);

        // Return pay key
        return array(
            'success' => true,
            'url'     => $invoice->getUrl(),
        );
    } catch (\Exception $e) {
        return array(
            'success' => false,
            'error'   => "An error occured and your donation could not be processed (" .  $e->getMessage() . "). Please contact us.",
        );
    }
}

/**
 * Verify session and reset request ID
 *
 * @throws \Exception
 */
function raise_verify_session()
{
    if (!isset($_GET['req']) || $_GET['req'] != $_SESSION['raise-req-id']) {
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

/**
 * AJAX endpoint for handling donation logging for Skrill.
 * User is forwarded here after successful Skrill transaction.
 * Takes user data from session and triggers the web hooks.
 *
 * @return string HTML with script that terminates the Skrill flow and shows the thank you step
 */
function raise_process_skrill_log()
{
    try {
        // Verify session and purge reqId
        raise_verify_session();

        // Get donation from session
        $donation = raise_get_donation_from_session();

        // Do post donation actions
        raise_do_post_donation_actions($donation);
    } catch (\Exception $e) {
        // No need to say anything. Just show confirmation.
    }

    die('<!doctype html>
         <html lang="en"><head><meta charset="utf-8"><title>Closing flow...</title></head>
         <body><script>parent.showConfirmation("skrill"); parent.hideModal();</script></body></html>');
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
        $donation   = raise_get_donation_from_session();
        $form       = $donation['form'];
        $mode       = $donation['mode'];
        $taxReceipt = $donation['tax_receipt'];
        $currency   = $donation['currency'];
        $country    = $donation['country'];

        // Add vendor transaction ID (BitPay invoice ID)
        $donation['vendor_transaction_id'] = $_SESSION['raise-vendor-transaction-id'];

        // Make sure the payment is paid
        $client      = raise_get_bitpay_client($form, $mode, $taxReceipt, $currency, $country);
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
         <html lang="en"><head><meta charset="utf-8"><title>Closing flow...</title></head>
         <body><script>var mainWindow = (window == top) ? /* mobile */ opener : /* desktop */ parent; mainWindow.showConfirmation("bitpay"); mainWindow.hideModal();</script></body></html>');
}

/**
 * Get donation data from session
 *
 * @return array
 */
function raise_get_donation_from_session()
{
    return array(
        "time"             => date('c'), // new
        "form"             => $_SESSION['raise-form'],
        "mode"             => $_SESSION['raise-mode'],
        "language"         => $_SESSION['raise-language'],
        "url"              => $_SESSION['raise-url'],
        "reqId"            => $_SESSION['raise-req-id'],
        "email"            => $_SESSION['raise-email'],
        "name"             => $_SESSION['raise-name'],
        "currency"         => $_SESSION['raise-currency'],
        "country"          => $_SESSION['raise-country'],
        "amount"           => $_SESSION['raise-amount'],
        "frequency"        => $_SESSION['raise-frequency'],
        "tax_receipt"      => $_SESSION['raise-tax-receipt'],
        "payment_provider" => $_SESSION['raise-payment-provider'],
        "purpose"          => $_SESSION['raise-purpose'],
        "address"          => $_SESSION['raise-address'],
        "zip"              => $_SESSION['raise-zip'],
        "city"             => $_SESSION['raise-city'],
        "mailinglist"      => $_SESSION['raise-mailinglist'],
        "comment"          => $_SESSION['raise-comment'],
        "account"          => $_SESSION['raise-account'],
        "anonymous"        => $_SESSION['raise-anonymous'],
    );
}

/**
 * Set donation data to session
 *
 * @param array  $post  Form post
 * @param string $reqId Request ID (against replay attack)
 */
function raise_set_donation_data_to_session(array $post, $reqId = null)
{
    // Required fields
    $_SESSION['raise-form']             = $post['form'];
    $_SESSION['raise-mode']             = $post['mode'];
    $_SESSION['raise-language']         = $post['language'];
    $_SESSION['raise-url']              = $_SERVER['HTTP_REFERER'];
    $_SESSION['raise-req-id']           = $reqId;
    $_SESSION['raise-email']            = $post['email'];
    $_SESSION['raise-name']             = $post['name'];
    $_SESSION['raise-currency']         = $post['currency'];
    $_SESSION['raise-country']          = $post['country'];
    $_SESSION['raise-amount']           = money_format('%i', $post['amount']);
    $_SESSION['raise-frequency']        = $post['frequency'];
    $_SESSION['raise-payment-provider'] = $post['payment_provider'];

    // Optional fields
    $_SESSION['raise-purpose']     = raise_get($post['purpose'], '');
    $_SESSION['raise-address']     = raise_get($post['address'], '');
    $_SESSION['raise-zip']         = raise_get($post['zip'], '');
    $_SESSION['raise-city']        = raise_get($post['city'], '');
    $_SESSION['raise-comment']     = raise_get($post['comment'], '');
    $_SESSION['raise-account']     = raise_get($post['account'], '');
    $_SESSION['raise-tax-receipt'] = (bool) raise_get($post['tax_receipt'], false);
    $_SESSION['raise-mailinglist'] = (bool) raise_get($post['mailinglist'], false);
    $_SESSION['raise-anonymous']   = (bool) raise_get($post['anonymous'], false);
}

/**
 * Make PayPal payment (= one-time payment)
 *
 * @param array $post
 * @return PayPal\Api\Payment
 */
function raise_create_paypal_payment(array $post)
{
    // Make payer
    $payer = new \PayPal\Api\Payer();
    $payer->setPaymentMethod("paypal");

    // Make amount
    $amount = new \PayPal\Api\Amount();
    $amount->setCurrency($post['currency'])
        ->setTotal($post['amount']);

    // Make transaction
    $transaction = new \PayPal\Api\Transaction();
    $transaction->setAmount($amount)
        ->setDescription($post['name'] . ' (' . $post['email'] . ')')
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
    $apiContext = raise_get_paypal_api_context(
        $post['form'],
        $post['mode'],
        raise_get($post['tax_receipt'], false),
        $post['currency'],
        $post['country']
    );

    return $payment->create($apiContext);
}

/**
 * Make PayPal billing agreement (= recurring payment)
 *
 * @param array $post
 * @return \PayPal\Api\Agreement
 */
function raise_create_paypal_billing_agreement(array $post)
{
    // Make new plan
    $plan = new \PayPal\Api\Plan();
    $plan->setName('Monthly Donation')
        ->setDescription('Monthly donation of ' . $post['currency'] . ' ' . $post['amount'])
        ->setType('INFINITE');

    // Make payment definition
    $paymentDefinition = new \PayPal\Api\PaymentDefinition();
    $paymentDefinition->setName('Regular Payments')
        ->setType('REGULAR')
        ->setFrequency('Month')
        ->setFrequencyInterval('1')
        ->setCycles('0')
        ->setAmount(new \PayPal\Api\Currency(array('value' => $post['amount'], 'currency' => $post['currency'])));

    // Make merchant preferences
    $returnUrl           = raise_get_ajax_endpoint() . '?action=paypal_execute';
    $merchantPreferences = new \PayPal\Api\MerchantPreferences();
    $merchantPreferences->setReturnUrl($returnUrl)
        ->setCancelUrl($returnUrl)
        ->setAutoBillAmount("yes")
        ->setInitialFailAmountAction("CONTINUE")
        ->setMaxFailAttempts("0");

    // Put things together and create
    $apiContext = raise_get_paypal_api_context(
        $post['form'],
        $post['mode'],
        raise_get($post['tax_receipt'], false),
        $post['currency'],
        $post['country']
    );
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
    $agreement->setName(__("Monthly Donation", "raise") . ': ' . $post['currency'] . ' ' . $post['amount'])
        ->setDescription(__("Monthly Donation", "raise") . ': ' . $post['currency'] . ' ' . $post['amount'])
        ->setStartDate($startDate->format('c'))
        ->setPlan($plan)
        ->setPayer($payer);

    return $agreement->create($apiContext);
}

/**
 * Returns Paypal pay key for donation. It stores
 * user input in session until user is forwarded back from Paypal
 *
 * @param array $post
 * @return array
 */
function raise_prepare_paypal_donation(array $post)
{
    try {
        if ($post['frequency'] == 'monthly') {
            $billingAgreement = raise_create_paypal_billing_agreement($post);

            // Save doantion to session
            raise_set_donation_data_to_session($post);

            // Parse approval link
            $approvalLinkParts = parse_url($billingAgreement->getApprovalLink());
            parse_str($approvalLinkParts['query'], $query);

            return array(
                'success' => true,
                'token'   => $query['token'],
            );
        } else {
            $payment = raise_create_paypal_payment($post);

            // Save doantion to session
            raise_set_donation_data_to_session($post);

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
        return array(
            'success' => false,
            'error'   => "An error occured and your donation could not be processed (" .  $ex->getMessage() . "). Please contact us.",
        );
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
        $apiContext = raise_get_paypal_api_context(
            $donation['form'],
            $donation['mode'],
            $donation['tax_receipt'],
            $donation['currency'],
            $donation['country']
        );

        if (!empty($_POST['paymentID']) && !empty($_POST['payerID'])) {
            // Execute payment (one-time)
            $paymentId = $_POST['paymentID'];
            $payment   = \PayPal\Api\Payment::get($paymentId, $apiContext);
            $execution = new \PayPal\Api\PaymentExecution();
            $execution->setPayerId($_POST['payerID']);
            $payment->execute($execution, $apiContext);
        } else if (!empty($_POST['token'])) {
            // Execute billing agreement (monthly)
            $agreement = new \PayPal\Api\Agreement();
            $agreement->execute($_POST['token'], $apiContext);
        } else {
            throw new \Exception("An error occured. Payment aborted.");
        }

        // Do post donation actions
        raise_do_post_donation_actions($donation);

        // Send response
        die(json_encode(array('success' => true)));
    } catch (\Exception $ex) {
        die(json_encode(array(
            'success' => false,
            'error'   => $ex->getMessage(),
        )));
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
        'post_type'  => 'raise_donation_log',
        'meta_key'   => 'form',
        'meta_value' => $form,
        'offset'     => $logMax,
        'orderby'    => 'ID',
        'order'      => 'DESC',
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
    $name      = $donation['anonymous'] == 'yes' ? 'Anonymous' : $donation['name'];
    $currency  = $donation['currency'];
    $amount    = $donation['amount'];
    $frequency = $donation['frequency'];
    $comment   = $donation['comment'];

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
    $freq    = !empty($donation['frequency']) && $donation['frequency'] == 'monthly' ? ' (monthly)' : '';
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

        // Add tax dedcution labels to donation
        $donation += raise_get_tax_deduction_settings_by_donation($donation);

        // Get email subject and text and pass it through twig
        $twig    = raise_get_twig($text, $subject, $html);
        $subject = $twig->render('finish.email.subject', $donation);
        $text    = $twig->render('finish.email.text', $donation);

        // Repalce %bank_account_formatted% in success_text with macro
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
 * Get user country from freegeoip.net, e.g. as ['code' => 'CH', 'name' => 'Switzerland']
 *
 * @param string $userIp
 * @param array  $default
 * @return array
 */
function raise_get_user_country($userIp = null, array $default = array())
{
    if (!$userIp) {
        $userIp = $_SERVER['REMOTE_ADDR'];
    }

    try {
        if (!empty($userIp)) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, "http://freegeoip.net/json/" . $userIp);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($curl);
            curl_close($curl);

            $response = json_decode($output, true);

            if (empty($response['country_name']) || empty($response['country_code'])) {
                throw new \Exception('Invalid response');
            }

            return array(
                'code' => $response['country_code'],
                'name' => $response['country_name'],
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
    $initialCountry = raise_get($formSettings['payment']['country']['initial'], 'geoip');

    if (empty($initialCountry) || $initialCountry == 'geoip') {
        // Do GeoIP call
        $fallbackCode = raise_get($formSettings['payment']['country']['fallback'], '');
        $fallbackName = raise_get($GLOBALS['code2country'][$fallbackCode]);
        $fallback     = !empty($fallbackName) ? array(
            'code' => $fallbackCode,
            'name' => $fallbackName,
        ) : array();

        return raise_get_user_country(null, $fallback);
    } else {
        // Return predefined country
        return isset($GLOBALS['code2country'][$initialCountry]) ? array(
            'code' => $initialCountry,
            'name' => $GLOBALS['code2country'][$initialCountry],
        ) : array();
    }
}

/**
 * Get user currency
 *
 * @param string $countryCode E.g. 'CH'
 * @return string|null
 */
function raise_get_user_currency($countryCode = null)
{
    if (!$countryCode) {
        $userCountry = raise_get_user_country();
        if (!$userCountry) {
            return null;
        }
        $countryCode = $userCountry['code'];
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
 * Get Stripe public keys for the form
 *
 * E.g.
 * [
 *     'default' => ['sandbox' => 'default_sandbox_key', 'live' => 'default_live_key'],
 *     'ch'      => ['sandbox' => 'ch_sandbox_key',  'live' => 'ch_live_key'],
 *     'gb'      => ['sandbox' => 'gb_sandbox_key',  'live' => 'gb_live_key'],
 *     'de'      => ['sandbox' => 'de_sandbox_key',  'live' => 'de_live_key'],
 *     'chf'     => ['sandbox' => 'chf_sandbox_key', 'live' => 'chf_live_key'],
 *     'eur'     => ['sandbox' => 'eur_sandbox_key', 'live' => 'eur_live_key'],
 *     'usd'     => ['sandbox' => 'usd_sandbox_key', 'live' => 'usd_live_key']
 * ]
 *
 * @param array $formSettings
 * @param string $mode sandbox/live
 * @return array
 */
function raise_get_stripe_public_keys(array $formSettings, $mode)
{
    // Get all enabled Stripe accounts with a public key for the given mode
    $stripeAccounts = array_filter(
        raise_get($formSettings['payment']['provider'], array()),
        function ($val, $key) use ($mode) {
            return preg_match('#^stripe#', $key) && !empty($val[$mode]['public_key']) && !empty($val[$mode]['secret_key']);
        },
        ARRAY_FILTER_USE_BOTH
    );

    // Get rid of `stripe_` and rename `stripe` to `default`
    $keys = array_map(function($key) {
        return $key == 'stripe' ? 'default' : substr($key, 7);
    }, array_keys($stripeAccounts));

    // Only leave public key
    $vals = array_map(function($val) use ($mode) {
        return $val[$mode]['public_key'];
    }, array_values($stripeAccounts));

    return array_combine($keys, $vals);
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
            $segments = explode('_', get_locale(), 2);
            $language = reset($segments);
        }
        return raise_get($setting[$language], reset($setting));
    }

    return null;
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
 * @param string $text    Email text
 * @param string $subject Email subject
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
 * @param array $labels
 * @param int   $depth
 * @return array
 */
function raise_monolinguify(array $labels, $depth = 0)
{
    if (!$depth--) {
        foreach (array_keys($labels) as $key) {
            if (is_array($labels[$key])) {
                $labels[$key] = raise_get_localized_value($labels[$key]);
            }
        }
    } else {
        foreach (array_keys($labels) as $key) {
            if (is_array($labels[$key])) {
                $labels[$key] = raise_monolinguify($labels[$key], $depth);
            }
        }
    }

    return $labels;
}

/**
 * Load tax deduction settings for donation
 *
 * @param array $donation
 * @return array
 * @see raise_load_tax_deduction_settings
 */
function raise_get_tax_deduction_settings_by_donation(array $donation)
{
    $settings = array();

    if ($taxDeductionSettings = raise_load_tax_deduction_settings($donation['form'])) {
        $countries = !empty($donation['country']) ? ['default', strtolower($donation['country'])]                    : ['default'];
        $types     = !empty($donation['payment_provider'])    ? ['default', str_replace(" ", "", strtolower($donation['payment_provider']))] : ['default']; // Payment provider
        $purposes  = !empty($donation['purpose']) ? ['default', $donation['purpose']]                                : ['default'];

        // Find best labels, more specific settings override more general settings
        foreach ($countries as $country) {
            foreach ($types as $type) {
                foreach ($purposes as $purpose) {
                    if (isset($taxDeductionSettings[$country][$type][$purpose])) {
                        $settings = array_merge($settings, $taxDeductionSettings[$country][$type][$purpose]);
                    }
                }
            }
        }

        // Monlinguify settings
        $settings = raise_monolinguify($settings);

        // Get %bank_account_formatted% and insert reference number (if present)
        $form         = raise_get($donation['form'], '');
        $formSettings = raise_load_settings($form);
        if ($donation['payment_provider'] == 'Bank Transfer' &&
            $account = raise_localize_array_keys(raise_get($formSettings['payment']['provider']['banktransfer']['accounts'][$donation['account']], array()))
        ) {
            // Insert %reference_number%
            if ($reference = raise_get($donation['reference'])) {
                $settings['bank_account'] = array_map(function ($val) use ($reference) {
                    return str_replace('%reference_number%', $reference, $val);
                }, $account);
            } else {
                $settings['bank_account'] = $account;
            }
        }
    }

    return $settings;
}

/**
 * Load tax deduction settings
 *
 * @param string $form Form name
 * @return array|null
 * @see raise_get_tax_deduction_settings_by_donation
 */
function raise_load_tax_deduction_settings($form)
{
    // Get local settings
    $formSettings         = raise_load_settings($form);
    $taxDeductionSettings = raise_get($formSettings['payment']['labels']['tax_deduction'], []);

    return $taxDeductionSettings ? raise_monolinguify($taxDeductionSettings, 3) : null;
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
 * @param string $form
 * @param string $mode
 * @param bool   $taxReceipt
 * @param string $currency
 * @param string $country
 * @return \PayPal\Rest\ApiContext
 * @throws \Exception
 */
function raise_get_paypal_api_context($form, $mode, $taxReceipt, $currency, $country)
{
    // Get best settings
    $formSettings = raise_load_settings($form);
    $settings     = raise_get_best_payment_provider_settings(
        $formSettings,
        "paypal",
        $mode,
        $taxReceipt,
        $currency,
        $country
    );

    $apiContext = new \PayPal\Rest\ApiContext(
        new \PayPal\Auth\OAuthTokenCredential(
            $settings['client_id'],
            $settings['client_secret']
        )
    );

    if ($mode == 'live') {
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
 * Clean up donation data, save local posts, send webhooks, send emails
 *
 * @param array $donation
 */
function raise_do_post_donation_actions($donation)
{
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
            if (is_array($value) && raise_has_string_keys($value)) {
                $value = $recurse($array[$key], $value);
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
