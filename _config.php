<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Stripe
 */

// Set your secret key: remember to change this to your live secret key in production
// See your keys here https://dashboard.stripe.com/account/apikeys
$stripe = array(
  "secret_key"      => "sk_test_BQokikJOvBiI2HlWgH4olfQ2",
  "publishable_key" => "pk_test_6pRNASCoBOKtIshFeQd4XMUh"
);

\Stripe\Stripe::setApiKey($stripe['secret_key']);
