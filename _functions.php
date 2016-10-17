<?php


/**
 * AJAX method
 */
function processDonation()
{
    try {
        // Do some cosmetics on form data
        $keys = array_keys($_POST);

        // Replace amount-other
        if (in_array('amount_other', $keys) && !empty($_POST['amount_other'])) {
          $_POST['amount'] = $_POST['amount_other'];
        }
        unset($_POST['amount_other']);

        // Convert amount to cents
        if (is_numeric($_POST['amount'])) {
          $_POST['amount'] = (int)($_POST['amount'] * 100);
        } else {
          throw new Exception('Invalid amount.');
        }

        // add payment-details
        /*if (in_array('payment', $keys) && $_POST['payment'] == "Lastschriftverfahren") {
          $_POST['payment-details'] = $_POST['payment-directdebit-frequency'] . ' | '  . $_POST['payment-directdebit-bankaccount'] . ' | ' . $_POST['payment-directdebit-method'];
        } else {
          $_POST['payment-details'] = '';
        }
        unset($_POST['payment-directdebit-frequency']);
        unset($_POST['payment-directdebit-bankaccount']);
        unset($_POST['payment-directdebit-method']);*/
        
        // Output
        if ($_POST['payment'] == "Stripe") {
            handleStripePayment($_POST);
        } else if ($_POST['payment'] == "Banktransfer") {
            handleBankTransferPayment($_POST);
        } else if ($_POST['payment'] == "Skrill") {
            //FIXME
            //echo gbs_skrillRedirect($_POST);
        } else {
            throw new Exception('Payment method is invalid.');
        }
        
        die(json_encode(array(
            'success' => true,
        )));
    } catch (\Exception $e) {
        die(json_encode(array(
            'success' => false,
            'error'   => "An error occured and your donation could not be processed (" .  $e->getMessage() . "). Please contact us.",
        )));
    }
}

/**
 * Process Stripe payment
 */
function handleStripePayment($post)
{
    // Create the charge on Stripe's servers - this will charge the user's card
    try {
        // Get the credit card details submitted by the form
        $form      = $post['form'];
        $mode      = $post['mode'];
        $language  = $post['language'];
        $token     = $post['stripeToken'];
        $publicKey = $post['stripePublicKey'];
        $amount    = $post['amount'];
        $currency  = $post['currency'];
        $email     = $post['email'];
        $frequency = $post['frequency'];

        // Find the secret key that goes with the public key
        $formSettings = $GLOBALS['easForms'][$form];
        $secretKey    = '';
        foreach ($formSettings as $key => $value) {
            if ($value === $publicKey) {
                $secretKeyKey = preg_replace('#public_key$#', 'secret_key', $key);
                $secretKey    = $formSettings[$secretKeyKey];
                break;
            }
        }

        //throw new Exception('Key: ' . $secretKeyKey . ', PK: ' . $publicKey . ', SK: ' . $secretKey);

        // Make sure we have the settings
        if (empty($secretKey)) {
            throw new \Exception("No form settings found for key $publicKey ($form : $mode)");
        }

        // Load secret key
        \Stripe\Stripe::setApiKey($secretKey);

        // Get customer settings
        $customerSettings = getStripeCustomerSettings($post);

        // Make customer
        $customer = \Stripe\Customer::create($customerSettings);

        // Make charge
        $charge = \Stripe\Charge::create(
            array(
                'customer'    => $customer->id,
                'amount'      => $amount, // !!! in cents !!!
                'currency'    => $currency,
                'description' => 'Donation',
            )
        );

        // Prepare hook
        $donation = array(
            'form'      => $form,
            'mode'      => $mode,
            'language'  => $language,
            'time'      => date('r'),
            'currency'  => $currency,
            'amount'    => money_format('%i', $amount / 100),
            'type'      => 'stripe',
            'email'     => $email,
            'frequency' => $frequency,
            'purpose'   => isset($post['purpose'])  ? $post['purpose'] : '',
            'name'      => isset($post['name'])     ? $post['name']    : '',
            'address'   => isset($post['address'])  ? $post['address'] : '',
            'zip'       => isset($post['zip'])      ? $post['zip']     : '',
            'city'      => isset($post['city'])     ? $post['city']    : '',
            'country'   => isset($post['country'])  ? getEnglishNameByCountryCode($post['country']) : '',
        );

        // Trigger logging web hook for Zapier
        triggerLoggingWebHooks($form, $donation);

        // Trigger mailing list web hook for Zapier
        if (isset($post['mailinglist']) && $post['mailinglist'] == 1) {
            $subscription = array(
                'email' => $email,
                'name'  => isset($post['name']) ? $post['name'] : '',
            );

            triggerMailingListWebHooks($form, $subscription);
        }

        // Send email
        sendThankYouEmail($email, $form, $language);
    } catch(\Stripe\Error\InvalidRequest $e) {
        // The card has been declined
        throw new Exception($e->getMessage() . ' ' . $e->getStripeParam() . " : $form : $mode : $email : $amount : $currency : $token");
    }
}

/**
 * Get customer settings (once/monthly)
 *
 * @param array post
 * @return array
 */
function getStripeCustomerSettings($post)
{
    $token     = $post['stripeToken'];
    $email     = $post['email'];
    $amount    = $post['amount'];
    $currency  = $post['currency'];
    $frequency = $post['frequency'];

    if ($frequency == 'monthly') {
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

        return array(
            'email'  => $email,
            'plan'   => $planId,
            'source' => $token,
        );
    } else {
        // frequency = 'once'
        return array(
            'email'  => $email,
            'source' => $token,
        );
    }
}

/**
 * Process bank transfer payment (simply log it)
 */
function handleBankTransferPayment($post)
{
    // Prepare hook
    $donation = array(
        'form'     => $post['form'],
        'mode'     => $post['mode'],
        'language' => $post['language'],
        'time'     => date('r'),
        'currency' => $post['currency'],
        'amount'   => money_format('%i', $post['amount'] / 100),
        'type'     => 'bank transfer',
        'email'    => $post['email'],
        'purpose'  => isset($post['purpose']) ? $post['purpose']  : '',
        'name'     => isset($post['name'])    ? $post['name']     : '',
        'address'  => isset($post['address']) ? $post['address'] : '',
        'zip'      => isset($post['zip'])     ? $post['zip']      : '',
        'city'     => isset($post['city'])    ? $post['city']     : '',
        'country'  => isset($post['country']) ? getEnglishNameByCountryCode($post['country']) : '',
    );

    // Trigger hook for Zapier
    triggerLoggingWebHooks($post['form'], $donation);

    // Triger mailing list web hook for Zapier
    if (isset($post['mailinglist']) && $post['mailinglist'] == 1) {
        $subscription = array(
            'email' => $post['email'],
            'name'  => isset($post['name']) ? $post['name'] : '',
        );

        triggerMailingListWebHooks($post['form'], $subscription);
    }

    // Send email
    sendThankYouEmail($post['email'], $post['form'], $post['language']);
}

/**
 * Send logging web hook to Zapier. See Settings > Webhooks
 */
function triggerLoggingWebHooks($form, $donation)
{
    // Trigger hooks for Zapier
    if (isset($GLOBALS['easForms'][$form]['web_hook.logging']) && is_array($GLOBALS['easForms'][$form]['web_hook.logging'])) {
        foreach ($GLOBALS['easForms'][$form]['web_hook.logging'] as $hook) {
            $suffix = preg_replace('/[^\w]+/', '_', trim($hook));
            do_action('eas_donation_logging_' . $suffix, $donation);
        }
    }
}

/**
 * Send mailing_list web hook to Zapier. See Settings > Webhooks
 */
function triggerMailingListWebHooks($form, $subscription)
{
    // Trigger hooks for Zapier
    if (isset($GLOBALS['easForms'][$form]['web_hook.mailing_list']) && is_array($GLOBALS['easForms'][$form]['web_hook.mailing_list'])) {
        foreach ($GLOBALS['easForms'][$form]['web_hook.mailing_list'] as $hook) {
            $suffix = preg_replace('/[^\w]+/', '_', trim($hook));
            do_action('eas_donation_mailinglist_' . $suffix, $subscription);
        }
    }
}


/**
 * AJAX method
 * Returns Paypal pay key for donation and stores
 * user input in session until user is forwarded back from Paypal
 */
function getPaypalPayKey()
{
    try {
        $form       = $_POST['form'];
        $mode       = $_POST['mode'];
        $language   = $_POST['language'];
        $email      = $_POST['email'];
        $amount     = $_POST['amount'];
        $currency   = $_POST['currency'];
        $taxReceipt = $_POST['tax_receipt'];
        $country    = isset($_POST['country']) ? $_POST['country'] : '';
        $returnUrl  = admin_url('admin-ajax.php');
        $reqId      = uniqid(); // Secret reference ID. Needed to prevent replay attack

        // Get best Paypal account for donation
        $paypalAccount = getBestPaypalAccount($form, $mode, $taxReceipt, $currency, $country);

        $qsConnector = strpos('?', $returnUrl) ? '&' : '?';
        $content = array(
            "actionType"      => "PAY",
            "returnUrl"       => $returnUrl . $qsConnector . "action=log&req=" . $reqId,
            "cancelUrl"       => $returnUrl . $qsConnector . "action=log",
            "requestEnvelope" => array(
                "errorLanguage" => "en_US",
                "detailLevel"   => "ReturnAll",
            ),
            "currencyCode"    => $currency,
            "receiverList"    => array(
                "receiver" => array(
                    array(
                        "email"  => $paypalAccount['email_id'],
                        "amount" => $amount,
                    )
                )
            )
        );

        $headers = array(
            'X-PAYPAL-SECURITY-USERID: '      . $paypalAccount['api_username'],
            'X-PAYPAL-SECURITY-PASSWORD: '    . $paypalAccount['api_password'],
            'X-PAYPAL-SECURITY-SIGNATURE: '   . $paypalAccount['api_signature'],
            'X-PAYPAL-DEVICE-IPADDRESS: '     . $_SERVER['REMOTE_ADDR'],
            'X-PAYPAL-REQUEST-DATA-FORMAT: '  . 'JSON',
            'X-PAYPAL-RESPONSE-DATA-FORMAT: ' . 'JSON',
            'X-PAYPAL-APPLICATION-ID: '       . 'APP-80W284485P519543T',
        );

        //die(json_encode($content));

        // Set Options for CURL
        $curl = curl_init($GLOBALS['paypalPayKeyEndpoint'][$mode]);
        curl_setopt($curl, CURLOPT_HEADER, false);
        // Return Response to Application
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        // Execute call via http-POST
        curl_setopt($curl, CURLOPT_POST, true);
        // Set POST-Body
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($content));
        // Set headers
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        // WARNING: This option should NOT be "false"
        // Otherwise the connection is not secured
        // You can turn it of if you're working on the test-system with no vital data
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        // Load list file with up-to-date certificate authorities
        //curl_setopt($curl, CURLOPT_CAINFO, 'ssl/server.crt');
        // CURL-Execute & catch response
        $jsonResponse = curl_exec($curl);
        // Get HTTP-Status, abort if Status != 200 
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status != 200) {
            die(json_encode(array(
                'success' => false,
                'error'   => "Error: Call to " . $GLOBALS['paypalPayKeyEndpoint'][$mode] . " failed with status $status, " .
                             "response " . $jsonResponse . ", curl_error " . curl_error($curl) . ", curl_errno " . 
                             curl_errno($curl) . ", HTTP-Status: " . $status,
            )));
        }
        // Close connection
        curl_close($curl);
        
        //Convert response into an array and extract the payKey
        $response = json_decode($jsonResponse, true);
        if ($response['responseEnvelope']['ack'] != 'Success') {
            die("Error: " . $response['error'][0]['message']);
        }

        // Put user data in session. This way we can avoid other people using it to spam our logs
        $_SESSION['eas-form']        = $form;
        $_SESSION['eas-mode']        = $mode;
        $_SESSION['eas-language']    = $language;
        $_SESSION['eas-req-id']      = $reqId;
        $_SESSION['eas-email']       = $email;
        $_SESSION['eas-currency']    = $currency;
        $_SESSION['eas-country']     = $country;
        $_SESSION['eas-amount']      = money_format('%i', $amount);
        $_SESSION['eas-tax-receipt'] = $taxReceipt;
        // Optional fields
        $_SESSION['eas-mailinglist'] = isset($_POST['mailinglist']) ? $_POST['mailinglist'] == 1 : false;
        $_SESSION['eas-purpose']     = isset($_POST['purpose'])     ? $_POST['purpose']          : '';
        $_SESSION['eas-name']        = isset($_POST['name'])        ? $_POST['name']             : '';
        $_SESSION['eas-address']     = isset($_POST['address'])     ? $_POST['address']          : '';
        $_SESSION['eas-zip']         = isset($_POST['zip'])         ? $_POST['zip']              : '';
        $_SESSION['eas-city']        = isset($_POST['city'])        ? $_POST['city']             : '';

        // Return pay key
        die(json_encode(array(
            'success' => true,
            'paykey'  => $response['payKey'],
        )));
    } catch (\Exception $e) {
        die(json_encode(array(
            'success' => false,
            'error'   => $e->getMessage(),
        )));
    }
}

/*
 * Get best Paypal account
 */
function getBestPaypalAccount($form, $mode, $taxReceiptNeeded, $currency, $country)
{
    // Make things lowercase
    $currency = strtolower($currency);
    $country  = strtolower($country);

    // Extract settings of the form we're talking about
    $formSettings = $GLOBALS['easForms'][$form];

    // Check all possible settings
    $hasCountrySetting  = isset($formSettings["payment.provider.paypal_$country.$mode.email_id"]);
    $hasCurrencySetting = isset($formSettings["payment.provider.paypal_$currency.$mode.email_id"]);
    $hasDefaultSetting  = isset($formSettings["payment.provider.paypal.$mode.email_id"]);
    
    // Check if there are settings for a country where the chosen currency is used.
    // This is only relevant if the donor does not need a donation receipt (always related 
    // to specific country) and if there are no currency specific settings
    $hasCountryOfCurrencySetting = false;
    $countryOfCurrency           = '';
    if (!$taxReceiptNeeded && !$hasCurrencySetting) {
        $countries = getCountriesByCurrency($currency);
        foreach ($countries as $country) {
            if (isset($formSettings["payment.provider.paypal_$country.$mode.email_id"])) {
                $hasCountryOfCurrencySetting = true;
                $countryOfCurrency = $country;
                break;
            }
        }
    }

    if ($taxReceiptNeeded && $hasCountrySetting) {
        // Use country specific key
        $paypalAccount = array(
            'email_id'      => $formSettings["payment.provider.paypal_$country.$mode.email_id"],
            'api_username'  => $formSettings["payment.provider.paypal_$country.$mode.api_username"],
            'api_password'  => $formSettings["payment.provider.paypal_$country.$mode.api_password"],
            'api_signature' => $formSettings["payment.provider.paypal_$country.$mode.api_signature"],
        );
    } else if ($hasCurrencySetting) {
        // Use currency specific key
        $paypalAccount = array(
            'email_id'      => $formSettings["payment.provider.paypal_$currency.$mode.email_id"],
            'api_username'  => $formSettings["payment.provider.paypal_$currency.$mode.api_username"],
            'api_password'  => $formSettings["payment.provider.paypal_$currency.$mode.api_password"],
            'api_signature' => $formSettings["payment.provider.paypal_$currency.$mode.api_signature"],
        );
    } else if ($hasCountryOfCurrencySetting) {
        // Use key of a country where the chosen currency is used
        $paypalAccount = array(
            'email_id'      => $formSettings["payment.provider.paypal_$countryOfCurrency.$mode.email_id"],
            'api_username'  => $formSettings["payment.provider.paypal_$countryOfCurrency.$mode.api_username"],
            'api_password'  => $formSettings["payment.provider.paypal_$countryOfCurrency.$mode.api_password"],
            'api_signature' => $formSettings["payment.provider.paypal_$countryOfCurrency.$mode.api_signature"],
        );
    } else if ($hasDefaultSetting) {
        // Use default key
        $paypalAccount = array(
            'email_id'      => $formSettings["payment.provider.paypal.$mode.email_id"],
            'api_username'  => $formSettings["payment.provider.paypal.$mode.api_username"],
            'api_password'  => $formSettings["payment.provider.paypal.$mode.api_password"],
            'api_signature' => $formSettings["payment.provider.paypal.$mode.api_signature"],
        );
    } else {
        throw new \Exception('No Paypal settings found');
    }

    return $paypalAccount;
}

/**
 * AJAX method
 * User is forwarded here after successful Paypal transaction.
 * Takes user data from session and sends them to the Google sheet.
 * Sends thank you email.
 */
function processPaypalLog()
{
    if (isset($_GET['req']) && $_GET['req'] == $_SESSION['eas-req-id']) {
        // Prepare hook
        $donation = array(
            "form"     => $_SESSION['eas-form'],
            "mode"     => $_SESSION['eas-mode'],
            "language" => $_SESSION['eas-language'],
            "time"     => date('r'),
            "currency" => $_SESSION['eas-currency'],
            "amount"   => money_format('%i', $_SESSION['eas-amount']),
            "type"     => "paypal",
            "purpose"  => $_SESSION['eas-purpose'],
            "email"    => $_SESSION['eas-email'],
            "name"     => $_SESSION['eas-name'],
            "address"  => $_SESSION['eas-address'],
            "zip"      => $_SESSION['eas-zip'],
            "city"     => $_SESSION['eas-city'],
            "country"  => getEnglishNameByCountryCode($_SESSION['eas-country']),
        );

        // Reset request ID to prevent replay attacks
        $_SESSION['eas-req-id'] = uniqid();

        // Trigger logging web hook for Zapier
        triggerLoggingWebHooks($_SESSION['eas-form'], $donation);

        // Trigger mailing list web hook for Zapier
        if ($_SESSION['eas-mailinglist']) {
            $subscription = array(
                'email' => $_SESSION['eas-email'],
                'name'  => $_SESSION['eas-name'],
            );

            triggerMailingListWebHooks($_SESSION['eas-form'], $subscription);
        }

        // Send email
        sendThankYouEmail($_SESSION['eas-email'], $_SESSION['eas-form'], $_SESSION['eas-language']);

        // Add method for showing confirmation
        $qsConnector = strpos('?', $_SERVER['eas-plugin-url']) ? '&' : '?';
        $script = "var mainWindow = (window == top) ? /* mobile */ opener : /* desktop */ parent; mainWindow.embeddedPPFlow.closeFlow(); mainWindow.showConfirmation('paypal'); close();";
    } else {
        // Add method for unlocking buttons for trying again
        $script = "var mainWindow = (window == top) ? /* mobile */ opener : /* desktop */ parent; mainWindow.embeddedPPFlow.closeFlow(); mainWindow.lockLastStep(false); close();";
    }

    // Make sure the contents can be displayed inside iFrame
    header_remove('X-Frame-Options');

    // Die and send script to close flow
    die('<!doctype html>
         <html lang="en"><head><meta charset="utf-8"><title>Closing flow...</title></head>
         <body><script>' . $script . '</script><body></html>');
}

/**
 * Filter for changing sender email address
 */
function easEmailAddress($original_email_address)
{
    $settings = $GLOBALS['currentEasFormSettings'];
    return isset($settings["finish.email.address"]) ? $settings["finish.email.address"] : $original_email_address;
}

/**
 * Filter for changing email sender
 */
function easEmailFrom($original_email_from)
{
    $settings = $GLOBALS['currentEasFormSettings'];
    return isset($settings["finish.email.sender"]) ? $settings["finish.email.sender"] : $original_email_from;
}

/**
 * Filter for changing email content type
 */
function easEmailContentType($original_content_type)
{
    return 'text/html';
}

/**
 * Email Message
 */
function sendThankYouEmail($email, $form, $language)
{
    $easForms = $GLOBALS['easForms'];

    if (isset($easForms[$form]["finish.email.contents.$language.subject"]) && isset($easForms[$form]["finish.email.contents.$language.text"])) {
        $subject = $easForms[$form]["finish.email.contents.$language.subject"];
        $message = $easForms[$form]["finish.email.contents.$language.text"];

        //throw new \Exception("$email : $form : $language : $subject : $message");

        // Add email hooks
        $GLOBALS['currentEasFormSettings'] = isset($GLOBALS['easForms'][$form]) ? $GLOBALS['easForms'][$form] : array();
        add_filter('wp_mail_from', 'easEmailAddress', 20, 1);
        add_filter('wp_mail_from_name', 'easEmailFrom', 20, 1);
        //add_filter('wp_mail_content_type', 'easEmailContentType', 20, 1);

        // Send email
        wp_mail($email, $subject, $message);

        // Remove email hooks
        remove_filter('wp_mail_from', 'easEmailAddress', 20);
        remove_filter('wp_mail_from_name', 'easEmailFrom', 20);
        //remove_filter('wp_mail_content_type', 'easEmailContentType', 20);
    }
}


/**
 * Flatten settings array
 */
function flattenSettings($settings, &$result, $parentKey = '')
{
    // Return scalar values, numeric arrays and special values
    if (!is_array($settings)
        || !hasStringKeys($settings)
        // IMPORTANT: Add parameters here that should be overwritten completely in non-default forms
        || preg_match('/payment\.purpose$/', $parentKey)
        || preg_match('/amount\.currency$/', $parentKey) 
        //|| preg_match('/amount\.button$/', $parentKey)
    ) {
        $result[$parentKey] = $settings;
        return;
    }
    
    // Do recursion on rest
    foreach ($settings as $key => $item) {
        $flattenedKey = !empty($parentKey) ? $parentKey . '.' . $key : $key;
        flattenSettings($item, $result, $flattenedKey);
    }
}

/**
 * Check if array has string keys
 */
function hasStringKeys(array $array) {
    return count(array_filter(array_keys($array), 'is_string')) > 0;
}

/**
 * Get user IP address
 */
function getUserIp()
{
    //return '8.8.8.8'; // US
    //return '84.227.243.215'; // CH

    $ipFields = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
    
    foreach ($ipFields as $ipField) {
        if (isset($_SERVER[$ipField])) {
            return $_SERVER[$ipField];
        }
    }

    return null;
}

/**
 * Get user country from freegeoip.net, e.g. as array('code' => 'CH', 'name' => 'Switzerland')
 */
function getUserCountry($userIp = null)
{
    if (!$userIp) {
        $userIp = getUserIp();
    }

    try {
        if (!empty($userIp)) {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, "http://freegeoip.net/json/" . $userIp);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($curl); 
            curl_close($curl);

            $response = json_decode($output, true);

            if (isset($response['country_name']) && isset($response['country_code'])) {
                return array(
                    'code' => $response['country_code'],
                    'name' => $response['country_name'],
                );
            } else {
                return array();
            }
        }
    } catch (\Exception $ex) {
        return array();
    }
}

/**
 * Get user currency
 */
function getUserCurrency($countryCode = null)
{
    if (!$countryCode) {
        $userCountry = getUserCountry();
        if (!$userCountry) {
            return null;
        }
        $countryCode = $userCountry['code'];
    }

    $mapping = $GLOBALS['country2currency'];

    return isset($mapping[$countryCode]) ? $mapping[$countryCode] : null;
}

/**
 * Get list of countries
 * Format array("country code" => array(0 => "translated name", 1 => "English name"))
 *
 * @param string[] Country list gets filtered, e.g. array('CH') will only return Switzerland
 */
function getSortedCountryList($countryCodeFilters = array())
{
    $countries = array(
        "AF" => __("Afghanistan", "eas-donation-processor"),
        "AX" => __("Åland Islands", "eas-donation-processor"),
        "AL" => __("Albania", "eas-donation-processor"),
        "DZ" => __("Algeria", "eas-donation-processor"),
        "AS" => __("American Samoa", "eas-donation-processor"),
        "AD" => __("Andorra", "eas-donation-processor"),
        "AO" => __("Angola", "eas-donation-processor"),
        "AI" => __("Anguilla", "eas-donation-processor"),
        "AQ" => __("Antarctica", "eas-donation-processor"),
        "AG" => __("Antigua and Barbuda", "eas-donation-processor"),
        "AR" => __("Argentina", "eas-donation-processor"),
        "AM" => __("Armenia", "eas-donation-processor"),
        "AW" => __("Aruba", "eas-donation-processor"),
        "AU" => __("Australia", "eas-donation-processor"),
        "AT" => __("Austria", "eas-donation-processor"),
        "AZ" => __("Azerbaijan", "eas-donation-processor"),
        "BS" => __("Bahamas", "eas-donation-processor"),
        "BH" => __("Bahrain", "eas-donation-processor"),
        "BD" => __("Bangladesh", "eas-donation-processor"),
        "BB" => __("Barbados", "eas-donation-processor"),
        "BY" => __("Belarus", "eas-donation-processor"),
        "BE" => __("Belgium", "eas-donation-processor"),
        "BZ" => __("Belize", "eas-donation-processor"),
        "BJ" => __("Benin", "eas-donation-processor"),
        "BM" => __("Bermuda", "eas-donation-processor"),
        "BT" => __("Bhutan", "eas-donation-processor"),
        "BO" => __("Bolivia, Plurinational State of", "eas-donation-processor"),
        "BQ" => __("Bonaire, Sint Eustatius and Saba", "eas-donation-processor"),
        "BA" => __("Bosnia and Herzegovina", "eas-donation-processor"),
        "BW" => __("Botswana", "eas-donation-processor"),
        "BV" => __("Bouvet Island", "eas-donation-processor"),
        "BR" => __("Brazil", "eas-donation-processor"),
        "IO" => __("British Indian Ocean Territory", "eas-donation-processor"),
        "BN" => __("Brunei Darussalam", "eas-donation-processor"),
        "BG" => __("Bulgaria", "eas-donation-processor"),
        "BF" => __("Burkina Faso", "eas-donation-processor"),
        "BI" => __("Burundi", "eas-donation-processor"),
        "KH" => __("Cambodia", "eas-donation-processor"),
        "CM" => __("Cameroon", "eas-donation-processor"),
        "CA" => __("Canada", "eas-donation-processor"),
        "CV" => __("Cape Verde", "eas-donation-processor"),
        "KY" => __("Cayman Islands", "eas-donation-processor"),
        "CF" => __("Central African Republic", "eas-donation-processor"),
        "TD" => __("Chad", "eas-donation-processor"),
        "CL" => __("Chile", "eas-donation-processor"),
        "CN" => __("China", "eas-donation-processor"),
        "CX" => __("Christmas Island", "eas-donation-processor"),
        "CC" => __("Cocos (Keeling) Islands", "eas-donation-processor"),
        "CO" => __("Colombia", "eas-donation-processor"),
        "KM" => __("Comoros", "eas-donation-processor"),
        "CG" => __("Congo, Republic of", "eas-donation-processor"),
        "CD" => __("Congo, Democratic Republic of the", "eas-donation-processor"),
        "CK" => __("Cook Islands", "eas-donation-processor"),
        "CR" => __("Costa Rica", "eas-donation-processor"),
        "CI" => __("Côte d'Ivoire", "eas-donation-processor"),
        "HR" => __("Croatia", "eas-donation-processor"),
        "CU" => __("Cuba", "eas-donation-processor"),
        "CW" => __("Curaçao", "eas-donation-processor"),
        "CY" => __("Cyprus", "eas-donation-processor"),
        "CZ" => __("Czech Republic", "eas-donation-processor"),
        "DK" => __("Denmark", "eas-donation-processor"),
        "DJ" => __("Djibouti", "eas-donation-processor"),
        "DM" => __("Dominica", "eas-donation-processor"),
        "DO" => __("Dominican Republic", "eas-donation-processor"),
        "EC" => __("Ecuador", "eas-donation-processor"),
        "EG" => __("Egypt", "eas-donation-processor"),
        "SV" => __("El Salvador", "eas-donation-processor"),
        "GQ" => __("Equatorial Guinea", "eas-donation-processor"),
        "ER" => __("Eritrea", "eas-donation-processor"),
        "EE" => __("Estonia", "eas-donation-processor"),
        "ET" => __("Ethiopia", "eas-donation-processor"),
        "FK" => __("Falkland Islands (Malvinas)", "eas-donation-processor"),
        "FO" => __("Faroe Islands", "eas-donation-processor"),
        "FJ" => __("Fiji", "eas-donation-processor"),
        "FI" => __("Finland", "eas-donation-processor"),
        "FR" => __("France", "eas-donation-processor"),
        "GF" => __("French Guiana", "eas-donation-processor"),
        "PF" => __("French Polynesia", "eas-donation-processor"),
        "TF" => __("French Southern Territories", "eas-donation-processor"),
        "GA" => __("Gabon", "eas-donation-processor"),
        "GM" => __("Gambia", "eas-donation-processor"),
        "GE" => __("Georgia", "eas-donation-processor"),
        "DE" => __("Germany", "eas-donation-processor"),
        "GH" => __("Ghana", "eas-donation-processor"),
        "GI" => __("Gibraltar", "eas-donation-processor"),
        "GR" => __("Greece", "eas-donation-processor"),
        "GL" => __("Greenland", "eas-donation-processor"),
        "GD" => __("Grenada", "eas-donation-processor"),
        "GP" => __("Guadeloupe", "eas-donation-processor"),
        "GU" => __("Guam", "eas-donation-processor"),
        "GT" => __("Guatemala", "eas-donation-processor"),
        "GG" => __("Guernsey", "eas-donation-processor"),
        "GN" => __("Guinea", "eas-donation-processor"),
        "GW" => __("Guinea-Bissau", "eas-donation-processor"),
        "GY" => __("Guyana", "eas-donation-processor"),
        "HT" => __("Haiti", "eas-donation-processor"),
        "HM" => __("Heard Island and McDonald Islands", "eas-donation-processor"),
        "VA" => __("Holy See (Vatican City State)", "eas-donation-processor"),
        "HN" => __("Honduras", "eas-donation-processor"),
        "HK" => __("Hong Kong", "eas-donation-processor"),
        "HU" => __("Hungary", "eas-donation-processor"),
        "IS" => __("Iceland", "eas-donation-processor"),
        "IN" => __("India", "eas-donation-processor"),
        "ID" => __("Indonesia", "eas-donation-processor"),
        "IR" => __("Iran, Islamic Republic of", "eas-donation-processor"),
        "IQ" => __("Iraq", "eas-donation-processor"),
        "IE" => __("Ireland", "eas-donation-processor"),
        "IM" => __("Isle of Man", "eas-donation-processor"),
        "IL" => __("Israel", "eas-donation-processor"),
        "IT" => __("Italy", "eas-donation-processor"),
        "JM" => __("Jamaica", "eas-donation-processor"),
        "JP" => __("Japan", "eas-donation-processor"),
        "JE" => __("Jersey", "eas-donation-processor"),
        "JO" => __("Jordan", "eas-donation-processor"),
        "KZ" => __("Kazakhstan", "eas-donation-processor"),
        "KE" => __("Kenya", "eas-donation-processor"),
        "KI" => __("Kiribati", "eas-donation-processor"),
        "KP" => __("Korea, Democratic People's Republic of", "eas-donation-processor"),
        "KR" => __("Korea, Republic of", "eas-donation-processor"),
        "KW" => __("Kuwait", "eas-donation-processor"),
        "KG" => __("Kyrgyzstan", "eas-donation-processor"),
        "LA" => __("Lao People's Democratic Republic", "eas-donation-processor"),
        "LV" => __("Latvia", "eas-donation-processor"),
        "LB" => __("Lebanon", "eas-donation-processor"),
        "LS" => __("Lesotho", "eas-donation-processor"),
        "LR" => __("Liberia", "eas-donation-processor"),
        "LY" => __("Libya", "eas-donation-processor"),
        "LI" => __("Liechtenstein", "eas-donation-processor"),
        "LT" => __("Lithuania", "eas-donation-processor"),
        "LU" => __("Luxembourg", "eas-donation-processor"),
        "MO" => __("Macao", "eas-donation-processor"),
        "MK" => __("Macedonia, Former Yugoslav Republic of", "eas-donation-processor"),
        "MG" => __("Madagascar", "eas-donation-processor"),
        "MW" => __("Malawi", "eas-donation-processor"),
        "MY" => __("Malaysia", "eas-donation-processor"),
        "MV" => __("Maldives", "eas-donation-processor"),
        "ML" => __("Mali", "eas-donation-processor"),
        "MT" => __("Malta", "eas-donation-processor"),
        "MH" => __("Marshall Islands", "eas-donation-processor"),
        "MQ" => __("Martinique", "eas-donation-processor"),
        "MR" => __("Mauritania", "eas-donation-processor"),
        "MU" => __("Mauritius", "eas-donation-processor"),
        "YT" => __("Mayotte", "eas-donation-processor"),
        "MX" => __("Mexico", "eas-donation-processor"),
        "FM" => __("Micronesia, Federated States of", "eas-donation-processor"),
        "MD" => __("Moldova, Republic of", "eas-donation-processor"),
        "MC" => __("Monaco", "eas-donation-processor"),
        "MN" => __("Mongolia", "eas-donation-processor"),
        "ME" => __("Montenegro", "eas-donation-processor"),
        "MS" => __("Montserrat", "eas-donation-processor"),
        "MA" => __("Morocco", "eas-donation-processor"),
        "MZ" => __("Mozambique", "eas-donation-processor"),
        "MM" => __("Myanmar", "eas-donation-processor"),
        "NA" => __("Namibia", "eas-donation-processor"),
        "NR" => __("Nauru", "eas-donation-processor"),
        "NP" => __("Nepal", "eas-donation-processor"),
        "NL" => __("Netherlands", "eas-donation-processor"),
        "NC" => __("New Caledonia", "eas-donation-processor"),
        "NZ" => __("New Zealand", "eas-donation-processor"),
        "NI" => __("Nicaragua", "eas-donation-processor"),
        "NE" => __("Niger", "eas-donation-processor"),
        "NG" => __("Nigeria", "eas-donation-processor"),
        "NU" => __("Niue", "eas-donation-processor"),
        "NF" => __("Norfolk Island", "eas-donation-processor"),
        "MP" => __("Northern Mariana Islands", "eas-donation-processor"),
        "NO" => __("Norway", "eas-donation-processor"),
        "OM" => __("Oman", "eas-donation-processor"),
        "PK" => __("Pakistan", "eas-donation-processor"),
        "PW" => __("Palau", "eas-donation-processor"),
        "PS" => __("Palestinian Territory, Occupied", "eas-donation-processor"),
        "PA" => __("Panama", "eas-donation-processor"),
        "PG" => __("Papua New Guinea", "eas-donation-processor"),
        "PY" => __("Paraguay", "eas-donation-processor"),
        "PE" => __("Peru", "eas-donation-processor"),
        "PH" => __("Philippines", "eas-donation-processor"),
        "PN" => __("Pitcairn", "eas-donation-processor"),
        "PL" => __("Poland", "eas-donation-processor"),
        "PT" => __("Portugal", "eas-donation-processor"),
        "PR" => __("Puerto Rico", "eas-donation-processor"),
        "QA" => __("Qatar", "eas-donation-processor"),
        "RE" => __("Réunion", "eas-donation-processor"),
        "RO" => __("Romania", "eas-donation-processor"),
        "RU" => __("Russian Federation", "eas-donation-processor"),
        "RW" => __("Rwanda", "eas-donation-processor"),
        "SH" => __("Saint Helena, Ascension and Tristan da Cunha", "eas-donation-processor"),
        "KN" => __("Saint Kitts and Nevis", "eas-donation-processor"),
        "LC" => __("Saint Lucia", "eas-donation-processor"),
        "PM" => __("Saint Pierre and Miquelon", "eas-donation-processor"),
        "VC" => __("Saint Vincent and the Grenadines", "eas-donation-processor"),
        "WS" => __("Samoa", "eas-donation-processor"),
        "SM" => __("San Marino", "eas-donation-processor"),
        "ST" => __("Sao Tome and Principe", "eas-donation-processor"),
        "SA" => __("Saudi Arabia", "eas-donation-processor"),
        "SN" => __("Senegal", "eas-donation-processor"),
        "RS" => __("Serbia", "eas-donation-processor"),
        "SC" => __("Seychelles", "eas-donation-processor"),
        "SL" => __("Sierra Leone", "eas-donation-processor"),
        "SG" => __("Singapore", "eas-donation-processor"),
        "SK" => __("Slovakia", "eas-donation-processor"),
        "SI" => __("Slovenia", "eas-donation-processor"),
        "SB" => __("Solomon Islands", "eas-donation-processor"),
        "SO" => __("Somalia", "eas-donation-processor"),
        "ZA" => __("South Africa", "eas-donation-processor"),
        "GS" => __("South Georgia and the South Sandwich Islands", "eas-donation-processor"),
        "SS" => __("South Sudan", "eas-donation-processor"),
        "ES" => __("Spain", "eas-donation-processor"),
        "LK" => __("Sri Lanka", "eas-donation-processor"),
        "SD" => __("Sudan", "eas-donation-processor"),
        "SR" => __("Suriname", "eas-donation-processor"),
        "SJ" => __("Svalbard and Jan Mayen", "eas-donation-processor"),
        "SZ" => __("Swaziland", "eas-donation-processor"),
        "SE" => __("Sweden", "eas-donation-processor"),
        "CH" => __("Switzerland", "eas-donation-processor"),
        "SY" => __("Syrian Arab Republic", "eas-donation-processor"),
        "TW" => __("Taiwan, Province of China", "eas-donation-processor"),
        "TJ" => __("Tajikistan", "eas-donation-processor"),
        "TZ" => __("Tanzania, United Republic of", "eas-donation-processor"),
        "TH" => __("Thailand", "eas-donation-processor"),
        "TL" => __("Timor-Leste", "eas-donation-processor"),
        "TG" => __("Togo", "eas-donation-processor"),
        "TK" => __("Tokelau", "eas-donation-processor"),
        "TO" => __("Tonga", "eas-donation-processor"),
        "TT" => __("Trinidad and Tobago", "eas-donation-processor"),
        "TN" => __("Tunisia", "eas-donation-processor"),
        "TR" => __("Turkey", "eas-donation-processor"),
        "TM" => __("Turkmenistan", "eas-donation-processor"),
        "TC" => __("Turks and Caicos Islands", "eas-donation-processor"),
        "TV" => __("Tuvalu", "eas-donation-processor"),
        "UG" => __("Uganda", "eas-donation-processor"),
        "UA" => __("Ukraine", "eas-donation-processor"),
        "AE" => __("United Arab Emirates", "eas-donation-processor"),
        "GB" => __("United Kingdom", "eas-donation-processor"),
        "US" => __("United States", "eas-donation-processor"),
        "UM" => __("United States Minor Outlying Islands", "eas-donation-processor"),
        "UY" => __("Uruguay", "eas-donation-processor"),
        "UZ" => __("Uzbekistan", "eas-donation-processor"),
        "VU" => __("Vanuatu", "eas-donation-processor"),
        "VE" => __("Venezuela, Bolivarian Republic of", "eas-donation-processor"),
        "VN" => __("Viet Nam", "eas-donation-processor"),
        "VG" => __("Virgin Islands, British", "eas-donation-processor"),
        "VI" => __("Virgin Islands, U.S.", "eas-donation-processor"),
        "WF" => __("Wallis and Futuna", "eas-donation-processor"),
        "EH" => __("Western Sahara", "eas-donation-processor"),
        "YE" => __("Yemen", "eas-donation-processor"),
        "ZM" => __("Zambia", "eas-donation-processor"),
        "ZW" => __("Zimbabwe", "eas-donation-processor"),
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
function getEnglishNameByCountryCode($countryCode)
{
    $countryCode = strtoupper($countryCode);
    return isset($GLOBALS['code2country'][$countryCode]) ? $GLOBALS['code2country'][$countryCode] : $countryCode;
}

/**
 * Get array with country codes where currency is used
 *
 * @param string $currency E.g. "CHF"
 * @return array E.g. array("LI", "CH")
 */
function getCountriesByCurrency($currency)
{
    $mapping = $GLOBALS['currency2country'];

    if (isset($mapping[$currency])) {
        return $mapping[$currency];
    } else {
        return array();
    }
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
 * @param array $form Settings array of the form
 * @return array 
 */
function getStripePublicKeys(array $form)
{
    $formStripeKeys = array();

    // Load Stripe sandbox/live default public keys
    $defaultStripeKeys = array();
    if (isset($form['payment.provider.stripe.sandbox.public_key'])) {
        $defaultStripeKeys['sandbox'] = $form['payment.provider.stripe.sandbox.public_key'];
    }
    if (isset($form['payment.provider.stripe.live.public_key'])) {
        $defaultStripeKeys['live'] = $form['payment.provider.stripe.live.public_key'];
    }
    $formStripeKeys['default'] = $defaultStripeKeys;

    // Load Stripe non-default settings (per country or per currency)
    $nonDefaultStripeKeys = array_map(function($key, $value) {
        if (preg_match('#^payment\.provider\.stripe_([^\.]+)\.(sandbox|live)\.public_key$#', $key, $matches)) {
            return array(
                'domain' => $matches[1], // e.g. ch, de, eur, usd, etc.
                'mode'   => $matches[2], // live or sandbox
                'key'    => $value,      // the Stripe public key
            );
        } else {
            return array();
        }
    }, array_keys($form), array_values($form));

    // Get rid of empty entries and then save everything to $formStripeKeys
    $nonDefaultStripeKeys = array_filter($nonDefaultStripeKeys, 'count');
    foreach ($nonDefaultStripeKeys as $val) {
        $formStripeKeys[$val['domain']][$val['mode']] = $val['key'];
    }

    return $formStripeKeys;
}

/**
 * Get Paypal accounts
 * 
 * E.g.
 * [
 *     'default' => ['sandbox', 'live'],
 *     'ch'      => ['sandbox', 'live'],
 *     'gb'      => ['sandbox', 'live'],
 *     'de'      => ['sandbox', 'live'],
 *     'chf'     => ['sandbox', 'live'],
 *     'eur'     => ['sandbox', 'live'],
 *     'usd'     => ['sandbox', 'live']
 * ]
 *
 * param array $form Settings array of the form
 * return array 
 */
/*function getPaypalAccounts(array $form)
{
    $formPaypalAccounts = array();

    // Load default Paypal account
    $defaultPaypalAccount = array();
    if (isset($form['payment.provider.paypal.sandbox.email_id'])) {
        $defaultPaypalAccount[] = 'sandbox';
    }
    if (isset($form['payment.provider.paypal.live.email_id'])) {
        $defaultPaypalAccount[] = 'live';
    }
    $formPaypalAccounts['default'] = $defaultPaypalAccount;

    // Load Paypal non-default accounts (per country or per currency)
    $nonDefaultPaypalAccounts = array_map(function($key, $value) {
        if (preg_match('#^payment\.provider\.paypal_([^\.]+)\.(sandbox|live)\.email_id$#', $key, $matches)) {
            return array(
                'domain' => $matches[1], // e.g. ch, de, eur, usd, etc.
                'mode'   => $matches[2], // live or sandbox
            );
        } else {
            return array();
        }
    }, array_keys($form), array_values($form));

    // Get rid of empty entries and then save everything to $formPaypalAccounts
    $nonDefaultPaypalAccounts = array_filter($nonDefaultPaypalAccounts, 'count');
    foreach ($nonDefaultPaypalAccounts as $val) {
        $formPaypalAccounts[$val['domain']][] = $val['mode'];
    }

    return $formPaypalAccounts;
}*/

/*

function gbs_skrillRedirect($post)
{
    ob_start();
?>
    <p>You will be redirected to Skrill in a few seconds... If not, click <a href="javascript:document.getElementById('skrillUSDRedirect').submit();">here</a>.</p>
    <form action="https://www.moneybookers.com/app/payment.pl" method="post" id="skrillUSDRedirect">
    <input type="hidden" name="pay_to_email" value="reg-skrill@gbs-schweiz.org">
    <input type="hidden" name="return_url" value="http://reg-charity.org">
    <input type="hidden" name="status_url" value="donate@gbs-schweiz.org">
    <input type="hidden" name="language" value="EN">
    <input type="hidden" name="amount" value="<?php echo $post['amount']; ?>">
    <input type="hidden" name="currency" value="<?php echo $post['currency']; ?>">
    <input type="hidden" name="pay_from_email" value="<?php $post['email']; ?>">
    <input type="hidden" name="firstname" value="<?php echo $post['firstname']; ?>">
    <input type="hidden" name="lastname" value="<?php echo $post['lastname']; ?>">
    <input type="hidden" name="confirmation_note" value="The world just got a bit brighter. Thanks for supporting our effective charities! If you haven't already, don't forget to go to reg-charity.org and become a REG member. And don't forget: You can reach us anytime at info@reg-charity.org"> <!-- This is somehow not working -->
    <input type="image" src="http://reg-charity.org/wp-content/uploads/2014/10/skrill-button.png" border="0" name="submit" alt="Pay by Skrill">
    </form>
    <script>
        var form = document.getElementById("skrillUSDRedirect");
        //setTimeout(function() { form.submit(); }, 1000);
    </script>
<?php
    $content = ob_get_clean();
    // remove line breaks
    $content = trim(preg_replace('/\s+/', ' ', $content));
    return $content;
}
*/
























