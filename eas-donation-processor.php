<?php
/**
 * Plugin Name: EAS Donation Processor
 * Plugin URI: http://www.ea-stiftung.org
 * Description: This plugin processes donations to EAS
 * Version: 0.1.1
 * Author: Naoki Peter
 * Author URI: http://www.0x1.ch
 * License: proprietary
 */

defined('ABSPATH') or die('No script kiddies please!');

require_once('vendor/autoload.php');
require_once("_globals.php");
require_once("_options.php");
require_once("_functions.php");
require_once("form.php");

// check for new version of plugin
require 'plugin-update-checker/plugin-update-checker.php';
$className = PucFactory::getLatestClassVersion('PucGitHubChecker');
$myUpdateChecker = new $className(
    'https://github.com/GBS-Schweiz/eas-donation-processor',
    __FILE__,
    'master'
);
$myUpdateChecker->setAccessToken('93a8387a061d14040a5932e12ef31d90a1be419a'); // read only

// Load parameters
$easSettingString = get_option('settings');
$easSettings      = json_decode($easSettingString, true);
$easOrganization  = isset($easSettings['organization']) ? $easSettings['organization'] : '';

// Load settings of default form, if any
$flattenedDefaultSettings = array();
if (isset($easSettings['forms']['default'])) {
    flattenSettings($easSettings['forms']['default'], $flattenedDefaultSettings);
}
$easForms = array('default' => $flattenedDefaultSettings);

// Get custom form settings
foreach ($easSettings['forms'] as $name => $extraSettings) {
    if ($name != 'default') {
        $flattenedExtraSettings = array();
        flattenSettings($extraSettings, $flattenedExtraSettings);
        $easForms[$name] = array_merge($easForms['default'], $flattenedExtraSettings);
    }
}

// Add easForms to GLOBALS
$GLOBALS['easForms'] = $easForms;


// Start session
add_action('init', 'eas_start_session', 1);
function eas_start_session() {
    if (!session_id()) {
        session_start();
    }
    if (!preg_match('/admin-ajax\.php/', $_SERVER['REQUEST_URI'])) { // && !isset($_SESSION['eas-plugin-url'])
        $_SESSION['eas-plugin-url'] = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }
}

// Set up ajax calls for processing donation
add_action("wp_ajax_nopriv_donate", "eas_process_donation");
add_action("wp_ajax_donate", "eas_process_donation");
function eas_process_donation() {
    processDonation();
}

// Get Paypal payKey for donation
add_action("wp_ajax_nopriv_paypal_paykey", "eas_process_paypal_paykey");
add_action("wp_ajax_paypal_paykey", "eas_process_paypal_paykey");
function eas_process_paypal_paykey() {
    getPaypalPayKey($_POST);
}

// Log Paypal transaction
add_action("wp_ajax_nopriv_log", "eas_process_paypal_log");
add_action("wp_ajax_log", "eas_process_paypal_log");
function eas_process_paypal_log() {
    processPaypalLog();
}


// Add hook that tells the HookPress plugin about our Zapier hooks
function eas_webhooks($hookpress_actions) {
    $easForms = $GLOBALS['easForms'];

    foreach ($easForms as $formName => $formSettings) {
        // Logging
        if (isset($formSettings['web_hook.logging']) && is_array($formSettings['web_hook.logging'])) {
            foreach ($formSettings['web_hook.logging'] as $hook) {
                $suffix = preg_replace('/[^\w]+/', '_', trim($hook));
                $hookpress_actions['eas_donation_logging_' . $suffix] = array('donation');
            }
        }

        // Mailing lists
        if (isset($formSettings['web_hook.mailing_list']) && is_array($formSettings['web_hook.mailing_list'])) {
            foreach ($formSettings['web_hook.mailing_list'] as $hook) {
                $suffix = preg_replace('/[^\w]+/', '_', trim($hook));
                $hookpress_actions['eas_donation_mailinglist_' . $suffix] = array('subscription');
            }
        }
    }
    
    return $hookpress_actions;
}
add_filter("hookpress_actions", "eas_webhooks");

// Add short code for donation form
add_shortcode('donationForm','donationForm');

// Add translations
add_action('plugins_loaded', 'eas_load_textdomain');
function eas_load_textdomain() {
    load_plugin_textdomain('eas-donation-processor', false, dirname(plugin_basename(__FILE__)) . '/lang/');
}

// Add JSON settings editor
add_action('admin_enqueue_scripts', 'eas_json_settings_editor');
function eas_json_settings_editor() {
    wp_register_script('donation-json-settings-editor', plugins_url('eas-donation-processor/js/jsoneditor.min.js'));
    wp_enqueue_script('donation-json-settings-editor');
    wp_register_style('donation-json-settings-editor-css', plugins_url('eas-donation-processor/js/jsoneditor.min.css'));
    wp_enqueue_style('donation-json-settings-editor-css');
}

/*
 * Additional Styles 
 */
function register_donation_styles() {
    wp_register_style('bootstrap', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css');
    wp_enqueue_style('bootstrap');
    wp_register_style('donation-plugin-css', plugins_url('eas-donation-processor/css/form.css'));
    wp_enqueue_style('donation-plugin-css');
    wp_register_style('donation-combobox-css', plugins_url('eas-donation-processor/css/bootstrap-combobox.css'));
    wp_enqueue_style('donation-combobox-css');
    wp_register_style('donation-plugin-flags', plugins_url('eas-donation-processor/css/flags.css'));
    wp_enqueue_style('donation-plugin-flags');
}

add_action('wp_enqueue_scripts', 'register_donation_styles');


/*
 * Additional Scripts  
 */
function register_donation_scripts()
{
    global $easForms, $easOrganization;
    
    // Amount patterns
    $amountPatterns = array();
    foreach ($easForms as $formName => $form) {
        if (isset($form['amount.currency']) && is_array($form['amount.currency'])) {
            $patterns = array();
            foreach ($form['amount.currency'] as $currency => $currencySettings) {
                $patterns[strtoupper($currency)] = $currencySettings['pattern'];
            }
            $amountPatterns[$formName] = $patterns;
        }
    }

    // Get Stripe public keys + Paypal accounts
    $stripeKeys     = array();
    foreach ($easForms as $formName => $form) {
        $stripeKeys[$formName]     = getStripePublicKeys($form);
    }

    wp_register_script('donation-plugin-bootstrapjs', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js', array('jquery'));
    wp_register_script('donation-plugin-jqueryformjs', '//malsup.github.io/jquery.form.js', array('jquery'));
    wp_register_script('donation-plugin-stripe', '//checkout.stripe.com/checkout.js');
    wp_register_script('donation-plugin-paypal', '//www.paypalobjects.com/js/external/dg.js');
    wp_register_script('donation-plugin-form', plugins_url( 'eas-donation-processor/js/form.js' ), array('jquery', 'donation-plugin-stripe'));
    wp_localize_script('donation-plugin-form', 'wordpress_vars', array(
        'plugin_path'        => plugin_dir_url(__FILE__),
        'ajax_endpoint'      => admin_url('admin-ajax.php'),
        'amount_patterns'    => $amountPatterns,
        'stripe_public_keys' => $stripeKeys,
        'organization'       => $easOrganization,
        'donate_button_text' => __("Donate %currency-amount%", "eas-donation-processor"),
        'donation'           => __("Donation", "eas-donation-processor"),
        'currency2country'   => $GLOBALS['currency2country'],
    ));
    wp_register_script('donation-combobox', plugins_url('eas-donation-processor/js/bootstrap-combobox.js'));
}

add_action('wp_enqueue_scripts', 'register_donation_scripts');

















