<?php
/**
 * Plugin Name: EAS Donation Processor
 * Plugin URI: https://github.com/GBS-Schweiz/eas-donation-processor
 * Description: Process donations
 * Version: 0.2.3
 * Author: Naoki Peter
 * Author URI: http://0x1.ch
 * License: proprietary
 */

defined('ABSPATH') or die('No script kiddies please!');

// Set priority constant for email filters
define('EAS_PRIORITY', 12838790321);

// Asset version
define('EAS_ASSET_VERSION', '0.3');

// Load other files
require_once 'vendor/autoload.php';
require_once "_globals.php";
require_once "_options.php";
require_once "functions.php";
require_once "updates.php";
require_once "form.php";

// Check for new version of plugin
require 'plugin-update-checker/plugin-update-checker.php';
$className = PucFactory::getLatestClassVersion('PucGitHubChecker');
$myUpdateChecker = new $className(
    'https://github.com/GBS-Schweiz/eas-donation-processor',
    __FILE__,
    'master'
);
$myUpdateChecker->setAccessToken('93a8387a061d14040a5932e12ef31d90a1be419a'); // read only

// Add short code for donation form
add_shortcode('donationForm','donationForm');

// Start session (needed for PayPal)
add_action('init', 'eas_start_session', 1);
function eas_start_session() {
    if (!session_id()) {
        session_start();
    }
    if (!preg_match('/admin-ajax\.php/', $_SERVER['REQUEST_URI'])) {
        $_SESSION['eas-plugin-url'] = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }
}

// Process donation (Bank Transfer and Stripe)
add_action("wp_ajax_nopriv_donate", "eas_process_donation");
add_action("wp_ajax_donate", "eas_process_donation");
function eas_process_donation() {
    processDonation();
}

// Process PayPal donation. Generate and return pay key for redirect to PayPal
add_action("wp_ajax_nopriv_paypal_paykey", "eas_process_paypal_paykey");
add_action("wp_ajax_paypal_paykey", "eas_process_paypal_paykey");
function eas_process_paypal_paykey() {
    processPaypalDonation();
}

// Log Paypal transaction. User is redirected here after successful donation
add_action("wp_ajax_nopriv_log", "eas_process_paypal_log");
add_action("wp_ajax_log", "eas_process_paypal_log");
function eas_process_paypal_log() {
    processPaypalLog();
}

// Generate GoCardless signup URL
add_action("wp_ajax_nopriv_gocardless_url", "eas_process_gocardless_url");
add_action("wp_ajax_gocardless_url", "eas_process_gocardless_url");
function eas_process_gocardless_url() {
    prepareGoCardlessDonation();
}

// Process GoCardless donation
add_action("wp_ajax_nopriv_gocardless_debit", "eas_process_gocardless_debit");
add_action("wp_ajax_gocardless_debit", "eas_process_gocardless_debit");
function eas_process_gocardless_debit() {
    processGoCardlessDonation();
}

// Add translations
add_action('plugins_loaded', 'eas_load_textdomain');
function eas_load_textdomain() {
    load_plugin_textdomain('eas-donation-processor', false, dirname(plugin_basename(__FILE__)) . '/lang/');
}

// Add JSON settings editor
add_action('admin_enqueue_scripts', 'eas_json_settings_editor');
function eas_json_settings_editor() {
    wp_register_script('donation-json-settings-editor', plugins_url('eas-donation-processor/js/jsoneditor.min.js'), array(), EAS_ASSET_VERSION);
    wp_enqueue_script('donation-json-settings-editor');
    wp_register_style('donation-json-settings-editor-css', plugins_url('eas-donation-processor/js/jsoneditor.min.css'), array(), EAS_ASSET_VERSION);
    wp_enqueue_style('donation-json-settings-editor-css');
}

/*
 * Additional Styles 
 */
add_action('wp_enqueue_scripts', 'register_donation_styles');
function register_donation_styles() {
    wp_register_style('bootstrap', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css');
    wp_enqueue_style('bootstrap');
    wp_register_style('donation-plugin-css', plugins_url('eas-donation-processor/css/form.css'), array(), EAS_ASSET_VERSION);
    wp_enqueue_style('donation-plugin-css');
    wp_register_style('donation-combobox-css', plugins_url('eas-donation-processor/css/bootstrap-combobox.css'), array(), EAS_ASSET_VERSION);
    wp_enqueue_style('donation-combobox-css');
    wp_register_style('donation-plugin-flags', plugins_url('eas-donation-processor/css/flags-few.css'), array(), EAS_ASSET_VERSION);
    wp_enqueue_style('donation-plugin-flags');
}

/*
 * Additional Scripts  
 */
add_action('wp_enqueue_scripts', 'register_donation_scripts');
function register_donation_scripts()
{
    // Update settings if necessary
    updateSettings();

    // Load settings
    loadSettings();

    $easForms = $GLOBALS['easForms'];
    
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
    $stripeKeys = array();
    foreach ($easForms as $formName => $form) {
        $stripeKeys[$formName] = getStripePublicKeys($form);
    }

    wp_register_script('donation-plugin-bootstrapjs', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js', array('jquery'));
    wp_register_script('donation-plugin-jqueryformjs', '//malsup.github.io/jquery.form.js', array('jquery'));
    wp_register_script('donation-plugin-stripe', '//checkout.stripe.com/checkout.js');
    wp_register_script('donation-plugin-paypal', '//www.paypalobjects.com/js/external/dg.js');
    wp_register_script('donation-combobox', plugins_url('eas-donation-processor/js/bootstrap-combobox.js'), array(), EAS_ASSET_VERSION);
    wp_register_script('donation-plugin-form', plugins_url( 'eas-donation-processor/js/form.js' ), array('jquery', 'donation-plugin-stripe'), EAS_ASSET_VERSION);
    wp_localize_script('donation-plugin-form', 'wordpress_vars', array(
        'plugin_path'           => plugin_dir_url(__FILE__),
        'ajax_endpoint'         => admin_url('admin-ajax.php'),
        'amount_patterns'       => $amountPatterns,
        'stripe_public_keys'    => $stripeKeys,
        'organization'          => $GLOBALS['easOrganization'],
        'donate_button_once'    => __("Donate %currency-amount%", "eas-donation-processor"),
        'donate_button_monthly' => __("Donate %currency-amount% per month", "eas-donation-processor"),
        'donation'              => __("Donation", "eas-donation-processor"),
        'currency2country'      => $GLOBALS['currency2country'],
    ));
}

// Register matching campaign post type
add_action( 'init', 'create_campaign_post_type' );
function create_campaign_post_type() {
    register_post_type( 'eas_fundraiser',
        array(
            'labels' => array(
                'name'          => __("Fundraisers", "eas-donation-processor"),
                'singular_name' => __("Fundraiser", "eas-donation-processor"),
                'add_new_item'  => __("Add New Fundraiser", "eas-donation-processor"),
                'edit_item'     => __("Edit Fundraiser", "eas-donation-processor"),
                'new_item'      => __("New Fundraiser", "eas-donation-processor"),
            ),
            'supports'            => array('title', 'author'),
            'public'              => true,
            'has_archive'         => true,
            'menu_icon'           => 'dashicons-lightbulb',
            'exclude_from_search' => 'true',
        )
    );
}

// Register matching campaign donation post type
add_action( 'init', 'create_doantion_post_type' );
function create_doantion_post_type() {
    register_post_type( 'eas_donation',
        array(
            'labels' => array(
                'name'          => __("Fundraiser Donations", "eas-donation-processor"),
                'singular_name' => __("Fundraiser Donation", "eas-donation-processor"),
                'add_new_item'  => __("Add New Donation", "eas-donation-processor"),
                'edit_item'     => __("Edit Donation", "eas-donation-processor"),
                'new_item'      => __("New Donation", "eas-donation-processor"),
            ),
            'supports'            => array('title', 'custom-fields'),
            'public'              => true,
            'has_archive'         => true,
            'menu_icon'           => 'dashicons-heart',
            'exclude_from_search' => 'true',
        )
    );
}

/**
 * Returns current plugin version
 *
 * @return string Plugin version
 */
function getPluginVersion() {
    if (!empty($GLOBALS['easPluginVersion'])) {
        return $GLOBALS['easPluginVersion'];
    }

    if (!function_exists('get_plugin_data')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    // Set plugin version
    $pluginData                  = get_plugin_data(__FILE__, false, false);
    $GLOBALS['easPluginVersion'] = $pluginData['Version'];

    return $GLOBALS['easPluginVersion'];
}












