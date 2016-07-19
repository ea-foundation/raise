<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once('_parameters.php');
require_once("_functions.php");

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
$GLOBALS['paypalPayKeyEndpoint']  = $sandboxMode ? 'https://svcs.sandbox.paypal.com/AdaptivePayments/Pay' : 'https://svcs.paypal.com/AdaptivePayments/Pay';
$GLOBALS['paypalPaymentEndpoint'] = $sandboxMode ? 'https://www.sandbox.paypal.com/webapps/adaptivepayment/flow/pay' : 'https://www.paypal.com/webapps/adaptivepayment/flow/pay';
$GLOBALS['paypalEmailId']         = $sandboxMode ? $paypalSandboxEmailId      : $paypalLiveEmailId;
$GLOBALS['paypalApiUsername']     = $sandboxMode ? $paypalSandboxApiUsername  : $paypalLiveApiUsername;
$GLOBALS['paypalApiPassword']     = $sandboxMode ? $paypalSandboxApiPassword  : $paypalLiveApiPassword;
$GLOBALS['paypalApiSignature']    = $sandboxMode ? $paypalSandboxApiSignature : $paypalLiveApiSignature;
