<?php
defined('ABSPATH') or die('No script kiddies please!');

/**
 * Paypal
 */
$GLOBALS['paypalPayKeyEndpoint'] = array(
    'live'    => 'https://svcs.paypal.com/AdaptivePayments/Pay',
    'sandbox' => 'https://svcs.sandbox.paypal.com/AdaptivePayments/Pay',
);

$GLOBALS['paypalPaymentEndpoint'] = array(
    'live'    => 'https://www.paypal.com/webapps/adaptivepayment/flow/pay',
    'sandbox' => 'https://www.sandbox.paypal.com/webapps/adaptivepayment/flow/pay',
);
