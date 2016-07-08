<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once('_parameters.php');

/**
 * Contact
 */
$GLOBALS['contactEmail'] = $contactEmail;
$GLOBALS['contactName']  = $contactName;


/**
 * Stripe
 */
$GLOBALS['stripeSecretKey'] = $sandboxMode ? $stripeSandboxSecretKey : $stripeLiveSecretKey;
$GLOBALS['stripePublicKey'] = $sandboxMode ? $stripeSandboxPublicKey : $stripeLivePublicKey;
$stripe = array(
    "secret_key"      => $GLOBALS['stripeSecretKey'] ?: "sk_test_BQokikJOvBiI2HlWgH4olfQ2", // test account
    "publishable_key" => $GLOBALS['stripePublicKey'] ?: "pk_test_6pRNASCoBOKtIshFeQd4XMUh", // test account
);

\Stripe\Stripe::setApiKey($stripe['secret_key']);



/**
 * Paypal
 */
$GLOBALS['paypalId']  = $sandboxMode ? $paypalSandboxId : $paypalLiveId;
$GLOBALS['paypalUrl'] = $sandboxMode ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';




