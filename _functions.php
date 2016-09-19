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
        $form     = $post['form'];
        $mode     = $post['mode'];
        $language = $post['language'];
        $token    = $post['stripeToken'];
        $amount   = $post['amount'];
        $currency = $post['currency'];
        $email    = $post['email'];

        // Make sure we have the settings
        if (empty($GLOBALS['easForms'][$form]["payment.provider.stripe.$mode.secret_key"])) {
            throw new \Exception("Form settings not found : $form : $mode");
        }

        // Load secret key
        \Stripe\Stripe::setApiKey($GLOBALS['easForms'][$form]["payment.provider.stripe.$mode.secret_key"]);

        // Make customer
        $customer = \Stripe\Customer::create(array(
            'email'  => $email,
            'source' => $token,
        ));

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
            'form'     => $form,
            'mode'     => $mode,
            'language' => $language,
            'time'     => date('r'),
            'currency' => $currency,
            'amount'   => money_format('%i', $amount / 100),
            'type'     => 'stripe',
            'email'    => $email,
            'purpose'  => isset($post['purpose'])  ? $post['purpose'] : '',
            'name'     => isset($post['name'])     ? $post['name']    : '',
            'address'  => isset($post['address'])  ? $post['address'] : '',
            'zip'      => isset($post['zip'])      ? $post['zip']     : '',
            'city'     => isset($post['city'])     ? $post['city']    : '',
            'country'  => isset($post['country'])  ? $post['country'] : '',
        );

        // Trigger hook for Zapier
        triggerHook($form, $donation);

        // Send email
        sendThankYouEmail($email, $form, $language);
    } catch(\Stripe\Error\InvalidRequest $e) {
        // The card has been declined
        throw new Exception($e->getMessage() . ' ' . $e->getStripeParam() . " : $form : $mode : $email : $amount : $currency : $token");
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
        'purpose'  => isset($post['purpose'])  ? $post['purpose']  : '',
        'name'     => isset($post['name'])     ? $post['name']     : '',
        'address' => isset($post['address']) ? $post['address'] : '',
        'zip'      => isset($post['zip'])      ? $post['zip']      : '',
        'city'     => isset($post['city'])     ? $post['city']     : '',
        'country'  => isset($post['country'])  ? $post['country']  : '',
    );

    // Trigger hook for Zapier
    triggerHook($post['form'], $donation);

    // Send email
    sendThankYouEmail($post['email'], $post['form'], $post['language']);
}

/**
 * Send web hook to Zapier. See Settings > Webhooks
 */
function triggerHook($form, $donation)
{
    // Trigger hook for Zapier
    if (isset($GLOBALS['easForms'][$form]['logging.web_hook'])) {
        $suffix = preg_replace('/[^\w]+/', '_', trim($GLOBALS['easForms'][$form]['logging.web_hook']));
        do_action('eas_log_donation_' . $suffix, $donation);
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
        $form      = $_POST['form'];
        $mode      = $_POST['mode'];
        $language  = $_POST['language'];
        $email     = $_POST['email'];
        $amount    = $_POST['amount'];
        $currency  = $_POST['currency'];
        $returnUrl = admin_url('admin-ajax.php');
        $reqId = uniqid(); // Secret reference ID. Needed to prevent replay attack

        // Make sure we have all the settings
        if (empty($GLOBALS['easForms'][$form]["payment.provider.paypal.$mode.email_id"]) ||
            empty($GLOBALS['easForms'][$form]["payment.provider.paypal.$mode.api_username"]) ||
            empty($GLOBALS['easForms'][$form]["payment.provider.paypal.$mode.api_password"]) ||
            empty($GLOBALS['easForms'][$form]["payment.provider.paypal.$mode.api_signature"])
        ) {
            throw new \Exception("Form settings not found : $form : $mode");
        }

        // Extract settings of the form we're talking about
        $formSettings = $GLOBALS['easForms'][$form];

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
                        "email"  => $formSettings["payment.provider.paypal.$mode.email_id"],
                        "amount" => $amount,
                    )
                )
            )
        );

        $headers = array(
            'X-PAYPAL-SECURITY-USERID: '      . $formSettings["payment.provider.paypal.$mode.api_username"],
            'X-PAYPAL-SECURITY-PASSWORD: '    . $formSettings["payment.provider.paypal.$mode.api_password"],
            'X-PAYPAL-SECURITY-SIGNATURE: '   . $formSettings["payment.provider.paypal.$mode.api_signature"],
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
        $_SESSION['eas-form']     = $form;
        $_SESSION['eas-mode']     = $mode;
        $_SESSION['eas-language'] = $language;
        $_SESSION['eas-req-id']   = $reqId;
        $_SESSION['eas-email']    = $email;
        $_SESSION['eas-currency'] = $currency;
        $_SESSION['eas-amount']   = money_format('%i', $amount);
        // Optional fields
        $_SESSION['eas-purpose']  = isset($_POST['purpose'])  ? $_POST['purpose']  : '';
        $_SESSION['eas-name']     = isset($_POST['name'])     ? $_POST['name']     : '';
        $_SESSION['eas-address'] = isset($_POST['address']) ? $_POST['address'] : '';
        $_SESSION['eas-zip']      = isset($_POST['zip'])      ? $_POST['zip']      : '';
        $_SESSION['eas-city']     = isset($_POST['city'])     ? $_POST['city']     : '';
        $_SESSION['eas-country']  = isset($_POST['country'])  ? $_POST['country']  : '';

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
            "country"  => $_SESSION['eas-country'],
        );

        // Reset request ID to prevent replay attacks
        $_SESSION['eas-req-id'] = uniqid();

        // Trigger hook for Zapier
        triggerHook($_SESSION['eas-form'], $donation);

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
    if (!is_array($settings) || 
        preg_match('/payment\.purpose$/', $parentKey) ||
        preg_match('/amount\.currency$/', $parentKey) ||
        preg_match('/amount\.button$/', $parentKey)
    ) {
        $result[$parentKey] = $settings;
        return;
    }
    
    foreach ($settings as $key => $item) {
        $flattenedKey = !empty($parentKey) ? $parentKey . '.' . $key : $key;
        flattenSettings($item, $result, $flattenedKey);
    }
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

    /*echo '<pre>IP: ';
    var_dump($userIp);
    echo '</pre>';*/

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

    /*echo '<pre>Country code: ';
    var_dump($countryCode);
    echo '</pre>';*/

    $countryCurrencies = array(
        "NZ" => "NZD",
        "CK" => "NZD",
        "NU" => "NZD",
        "PN" => "NZD",
        "TK" => "NZD",
        "AU" => "AUD",
        "CX" => "AUD",
        "CC" => "AUD",
        "HM" => "AUD",
        "KI" => "AUD",
        "NR" => "AUD",
        "NF" => "AUD",
        "TV" => "AUD",
        "AS" => "EUR",
        "AD" => "EUR",
        "AT" => "EUR",
        "BE" => "EUR",
        "FI" => "EUR",
        "FR" => "EUR",
        "GF" => "EUR",
        "TF" => "EUR",
        "DE" => "EUR",
        "GR" => "EUR",
        "GP" => "EUR",
        "IE" => "EUR",
        "IT" => "EUR",
        "LU" => "EUR",
        "MQ" => "EUR",
        "YT" => "EUR",
        "MC" => "EUR",
        "NL" => "EUR",
        "PT" => "EUR",
        "RE" => "EUR",
        "WS" => "EUR",
        "SM" => "EUR",
        "SI" => "EUR",
        "ES" => "EUR",
        "VA" => "EUR",
        "GS" => "GBP",
        "GB" => "GBP",
        "JE" => "GBP",
        "IO" => "USD",
        "GU" => "USD",
        "MH" => "USD",
        "FM" => "USD",
        "MP" => "USD",
        "PW" => "USD",
        "PR" => "USD",
        "TC" => "USD",
        "US" => "USD",
        "UM" => "USD",
        "VG" => "USD",
        "VI" => "USD",
        "HK" => "HKD",
        "CA" => "CAD",
        "JP" => "JPY",
        "AF" => "AFN",
        "AL" => "ALL",
        "DZ" => "DZD",
        "AI" => "XCD",
        "AG" => "XCD",
        "DM" => "XCD",
        "GD" => "XCD",
        "MS" => "XCD",
        "KN" => "XCD",
        "LC" => "XCD",
        "VC" => "XCD",
        "AR" => "ARS",
        "AM" => "AMD",
        "AW" => "ANG",
        "AN" => "ANG",
        "AZ" => "AZN",
        "BS" => "BSD",
        "BH" => "BHD",
        "BD" => "BDT",
        "BB" => "BBD",
        "BY" => "BYR",
        "BZ" => "BZD",
        "BJ" => "XOF",
        "BF" => "XOF",
        "GW" => "XOF",
        "CI" => "XOF",
        "ML" => "XOF",
        "NE" => "XOF",
        "SN" => "XOF",
        "TG" => "XOF",
        "BM" => "BMD",
        "BT" => "INR",
        "IN" => "INR",
        "BO" => "BOB",
        "BW" => "BWP",
        "BV" => "NOK",
        "NO" => "NOK",
        "SJ" => "NOK",
        "BR" => "BRL",
        "BN" => "BND",
        "BG" => "BGN",
        "BI" => "BIF",
        "KH" => "KHR",
        "CM" => "XAF",
        "CF" => "XAF",
        "TD" => "XAF",
        "CG" => "XAF",
        "GQ" => "XAF",
        "GA" => "XAF",
        "CV" => "CVE",
        "KY" => "KYD",
        "CL" => "CLP",
        "CN" => "CNY",
        "CO" => "COP",
        "KM" => "KMF",
        "CD" => "CDF",
        "CR" => "CRC",
        "HR" => "HRK",
        "CU" => "CUP",
        "CY" => "CYP",
        "CZ" => "CZK",
        "DK" => "DKK",
        "FO" => "DKK",
        "GL" => "DKK",
        "DJ" => "DJF",
        "DO" => "DOP",
        "TP" => "IDR",
        "ID" => "IDR",
        "EC" => "ECS",
        "EG" => "EGP",
        "SV" => "SVC",
        "ER" => "ETB",
        "ET" => "ETB",
        "EE" => "EEK",
        "FK" => "FKP",
        "FJ" => "FJD",
        "PF" => "XPF",
        "NC" => "XPF",
        "WF" => "XPF",
        "GM" => "GMD",
        "GE" => "GEL",
        "GI" => "GIP",
        "GT" => "GTQ",
        "GN" => "GNF",
        "GY" => "GYD",
        "HT" => "HTG",
        "HN" => "HNL",
        "HU" => "HUF",
        "IS" => "ISK",
        "IR" => "IRR",
        "IQ" => "IQD",
        "IL" => "ILS",
        "JM" => "JMD",
        "JO" => "JOD",
        "KZ" => "KZT",
        "KE" => "KES",
        "KP" => "KPW",
        "KR" => "KRW",
        "KW" => "KWD",
        "KG" => "KGS",
        "LA" => "LAK",
        "LV" => "LVL",
        "LB" => "LBP",
        "LS" => "LSL",
        "LR" => "LRD",
        "LY" => "LYD",
        "LI" => "CHF",
        "CH" => "CHF",
        "LT" => "LTL",
        "MO" => "MOP",
        "MK" => "MKD",
        "MG" => "MGA",
        "MW" => "MWK",
        "MY" => "MYR",
        "MV" => "MVR",
        "MT" => "EUR",
        "MR" => "MRO",
        "MU" => "MUR",
        "MX" => "MXN",
        "MD" => "MDL",
        "MN" => "MNT",
        "MA" => "MAD",
        "EH" => "MAD",
        "MZ" => "MZN",
        "MM" => "MMK",
        "NA" => "NAD",
        "NP" => "NPR",
        "NI" => "NIO",
        "NG" => "NGN",
        "OM" => "OMR",
        "PK" => "PKR",
        "PA" => "PAB",
        "PG" => "PGK",
        "PY" => "PYG",
        "PE" => "PEN",
        "PH" => "PHP",
        "PL" => "PLN",
        "QA" => "QAR",
        "RO" => "RON",
        "RU" => "RUB",
        "RW" => "RWF",
        "ST" => "STD",
        "SA" => "SAR",
        "SC" => "SCR",
        "SL" => "SLL",
        "SG" => "SGD",
        "SK" => "SKK",
        "SB" => "SBD",
        "SO" => "SOS",
        "ZA" => "ZAR",
        "LK" => "LKR",
        "SD" => "SDG",
        "SR" => "SRD",
        "SZ" => "SZL",
        "SE" => "SEK",
        "SY" => "SYP",
        "TW" => "TWD",
        "TJ" => "TJS",
        "TZ" => "TZS",
        "TH" => "THB",
        "TO" => "TOP",
        "TT" => "TTD",
        "TN" => "TND",
        "TR" => "TRY",
        "TM" => "TMT",
        "UG" => "UGX",
        "UA" => "UAH",
        "AE" => "AED",
        "UY" => "UYU",
        "UZ" => "UZS",
        "VU" => "VUV",
        "VE" => "VEF",
        "VN" => "VND",
        "YE" => "YER",
        "ZM" => "ZMK",
        "ZW" => "ZWD",
        "AX" => "EUR",
        "AO" => "AOA",
        "AQ" => "AQD",
        "BA" => "BAM",
        "CD" => "CDF",
        "GH" => "GHS",
        "GG" => "GGP",
        "IM" => "GBP",
        "LA" => "LAK",
        "MO" => "MOP",
        "ME" => "EUR",
        "PS" => "JOD",
        "BL" => "EUR",
        "SH" => "GBP",
        "MF" => "ANG",
        "PM" => "EUR",
        "RS" => "RSD",
        "USAF" => "USD",
    );

    return isset($countryCurrencies[$countryCode]) ? $countryCurrencies[$countryCode] : null;
}

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
























