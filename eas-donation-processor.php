<?php
/**
 * Plugin Name: EAS Donation Processor
 * Plugin URI: http://www.ea-stiftung.org
 * Description: This plugin processes donations to EAS
 * Version: 0.0.1
 * Author: Naoki Peter
 * Author URI: http://www.0x1.ch
 * License: proprietary
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once('vendor/autoload.php');
require_once("_config.php");
require_once("_functions.php");
require_once("form.php");

// Set up ajax calls for processing donation
add_action("wp_ajax_nopriv_donate", "eas_process_donation");
add_action("wp_ajax_donate", "eas_process_donation");

function eas_process_donation() {
    processDonation();
}


/*add_action('eas_log_donation', 'eas_test', 10, 1);

function eas_test($donation) {
    //TODO echo json_encode($donation);
}*/

add_filter("hookpress_actions", "eas_webhooks");

// Add hook for saving stuff with hook press and zapier to Google spreadsheets
function eas_webhooks($hookpress_actions) {
    $hookpress_actions['eas_log_donation'] = array('donation');
    return $hookpress_actions;
}

add_shortcode('donationForm','donationForm');


/*
 * Additional Styles 
 */
function register_donation_styles() {
  wp_register_style( 'donation-plugin-bootstrap', 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css');
  wp_enqueue_style( 'donation-plugin-bootstrap' );
  wp_register_style( 'donation-plugin-css', plugins_url( 'eas-donation-processor/css/scrollable-horizontal.css' ) );
  wp_enqueue_style( 'donation-plugin-css' );
}

add_action( 'wp_enqueue_scripts', 'register_donation_styles' );



/*
 * Additional Scripts  
 */
function register_donation_scripts()
{
  wp_register_script( 'donation-plugin-bootstrapjs', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js', array('jquery') );
  wp_enqueue_script( 'donation-plugin-bootstrapjs' );
  wp_register_script( 'donation-plugin-jqueryformjs', '//malsup.github.io/jquery.form.js', array('jquery') );
  wp_enqueue_script( 'donation-plugin-jqueryformjs' );
  wp_register_script( 'donation-plugin-stripe', '//checkout.stripe.com/checkout.js' );
  wp_enqueue_script( 'donation-plugin-stripe' );
  wp_register_script( 'donation-plugin-form', plugins_url( 'eas-donation-processor/js/form.js' ), array('jquery', 'donation-plugin-stripe') );
  wp_localize_script( 'donation-plugin-form', 'wordpress_vars', array('plugin_path' => plugin_dir_url(__FILE__)));
  wp_enqueue_script( 'donation-plugin-form' );
}

add_action( 'wp_enqueue_scripts', 'register_donation_scripts' );

















