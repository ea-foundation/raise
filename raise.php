<?php
/**
 * Plugin Name: Raise
 * Plugin URI: https://github.com/ea-foundation/raise
 * GitHub Plugin URI: ea-foundation/raise
 * Description: The Free Donation Plugin for WordPress
 * Version: 2.2.4
 * Author: Naoki Peter
 * License: GPLv3 or later
 */

defined('ABSPATH') or exit;

// Set priority constant for email filters
define('RAISE_PRIORITY', 12838790321);

// Asset version
define('RAISE_ASSET_VERSION', '0.50');

// Load other files
require_once "vendor/autoload.php";
require_once "_globals.php";
require_once "_options.php";
require_once "bitpay/EncryptedWPOptionStorage.php";
require_once "functions.php";
require_once "updates.php";
require_once "form.php";

// Add shortcode for donation form
add_shortcode('raise_form','raise_form');

// Start session (needed for most payment providers)
add_action('wp_loaded', 'raise_start_session');
function raise_start_session()
{
    if (defined('RAISE_PHPUNIT_RUN')) {
        // Disable session for tests
        return;
    }

    $status = session_status();

    if (PHP_SESSION_DISABLED === $status) {
        echo "<pre>Error: Raise requires PHP Session to be enabled</pre>";
        return;
    }

    if (PHP_SESSION_NONE === $status) {
        session_start();
    }

    if (!preg_match('/admin-ajax\.php/', $_SERVER['REQUEST_URI'])) {
        $_SESSION['raise-plugin-url'] = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }
}

// Process donation (Bank Transfer only)
add_action("wp_ajax_nopriv_process_banktransfer", "raise_process_banktransfer");
add_action("wp_ajax_process_banktransfer", "raise_process_banktransfer");

// Log Stripe donation
add_action("wp_ajax_nopriv_stripe_log", "raise_finish_stripe_donation_flow");
add_action("wp_ajax_stripe_log", "raise_finish_stripe_donation_flow");

// Log Stripe donation
add_action("wp_ajax_nopriv_cancel_payment", "raise_cancel_payment");
add_action("wp_ajax_cancel_payment", "raise_cancel_payment");

// Prepare redirect (PayPal, Skrill, GoCardless, BitPay)
add_action("wp_ajax_nopriv_raise_redirect", "raise_prepare_redirect");
add_action("wp_ajax_raise_redirect", "raise_prepare_redirect");

// Prepare redirect HTML (Stripe)
add_action("wp_ajax_nopriv_raise_redirect_html", "raise_prepare_redirect_html");
add_action("wp_ajax_raise_redirect_html", "raise_prepare_redirect_html");

// Execute and log Paypal transaction
add_action("wp_ajax_nopriv_paypal_execute", "raise_execute_paypal_donation");
add_action("wp_ajax_paypal_execute", "raise_execute_paypal_donation");

// Process GoCardless donation
add_action("wp_ajax_nopriv_gocardless_debit", "raise_process_gocardless_donation");
add_action("wp_ajax_gocardless_debit", "raise_process_gocardless_donation");

// Log BitPay donation
add_action("wp_ajax_nopriv_bitpay_log", "raise_process_bitpay_log");
add_action("wp_ajax_bitpay_log", "raise_process_bitpay_log");

// Log Coinbase donation
add_action("wp_ajax_nopriv_coinbase_log", "raise_process_coinbase_log");
add_action("wp_ajax_coinbase_log", "raise_process_coinbase_log");

// Log Skrill donation
add_action("wp_ajax_nopriv_skrill_log", "raise_process_skrill_log");
add_action("wp_ajax_skrill_log", "raise_process_skrill_log");

// Add translations
add_action('plugins_loaded', 'raise_load_textdomain');
function raise_load_textdomain()
{
    load_plugin_textdomain('raise', false, dirname(plugin_basename(__FILE__)) . '/lang/');
}

// Add JSON settings editor
add_action('admin_enqueue_scripts', 'raise_json_settings_editor');
function raise_json_settings_editor()
{
    wp_enqueue_script('donation-jquery-ui', plugins_url('assets/js/jquery-ui.min.js', __FILE__), [], RAISE_ASSET_VERSION);
    wp_enqueue_script('donation-json-settings-editor', plugins_url('assets/js/jsoneditor.min.js', __FILE__), [], RAISE_ASSET_VERSION);
    wp_enqueue_style('donation-json-settings-editor-css', plugins_url('assets/css/jsoneditor.min.css', __FILE__), [], RAISE_ASSET_VERSION);
    wp_enqueue_style('donation-admin-css', plugins_url('assets/css/admin.css', __FILE__), [], RAISE_ASSET_VERSION);
    wp_enqueue_style('donation-jquery-ui-css', plugins_url('assets/css/jquery-ui.min.css', __FILE__), [], RAISE_ASSET_VERSION);
    wp_enqueue_media();
}

/*
 * Add styles and scripts
 */
add_action('wp_enqueue_scripts', 'raise_register_donation_styles');
function raise_register_donation_styles()
{
    // Register bootstrap and bootstrap combobox
    wp_register_style('bootstrap-scoped', plugins_url('assets/css/scoped-bootstrap.min.css', __FILE__), [], RAISE_ASSET_VERSION);
    wp_register_style('donation-combobox', plugins_url('assets/css/bootstrap-combobox.css', __FILE__), [], RAISE_ASSET_VERSION);

    // Enqueue country flag sprites if necessary
    if ($flagSprite = raise_get_best_flag_sprite()) {
        wp_enqueue_style('donation-plugin-flags', plugins_url('assets/css/flags-' . $flagSprite . '.css', __FILE__), [], RAISE_ASSET_VERSION);
    }

    // Enqueue all other styles
    wp_enqueue_style('donation-plugin', plugins_url('assets/css/form.css', __FILE__), [], RAISE_ASSET_VERSION);
    wp_enqueue_style('donation-button', plugins_url('assets/css/button.css.php', __FILE__), [], RAISE_ASSET_VERSION);
    wp_enqueue_style('slick', plugins_url('assets/css/slick.css', __FILE__), [], RAISE_ASSET_VERSION);

    // Register scripts (enqueue later)
    wp_register_script('donation-plugin-bootstrapjs', 'https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js', array('jquery'));
    wp_register_script('donation-plugin-jqueryformjs', 'https://malsup.github.io/jquery.form.js', array('jquery'));
    wp_register_script('donation-plugin-paypal', 'https://www.paypalobjects.com/api/checkout.js'); // The query string is actually supposed to be a separate attribute without value, see below
    wp_register_script('donation-plugin-jquery-slick', plugins_url('assets/js/slick.min.js', __FILE__));
    wp_register_script('donation-plugin-json-logic', plugins_url('assets/js/logic.js', __FILE__));
    wp_register_script('donation-plugin-combobox', plugins_url('assets/js/bootstrap-combobox.js', __FILE__), [], RAISE_ASSET_VERSION);
    wp_register_script('donation-plugin-form', plugins_url('assets/js/form.js', __FILE__), array('jquery'), RAISE_ASSET_VERSION);
}

/*
 * Enqueue bootstrap if necessary 
 */
add_action('wp_print_styles', 'raise_enqueue_bootstrap');
function raise_enqueue_bootstrap()
{
    // Check if we need to enqueue bootstrap
    if (!wp_style_is('bootstrap')) {
        wp_enqueue_style('bootstrap-scoped');
    }

    // Enqueue bootstrap combobox
    wp_enqueue_style('donation-combobox');
}

// Register donation log post type
add_action('init', 'raise_create_doantion_log_post_type');
function raise_create_doantion_log_post_type()
{
    register_post_type('raise_donation_log', array(
        'labels' => array(
            'name'          => __('Donation Logs', 'raise'),
            'singular_name' => __('Donation Log', 'raise'),
            'add_new_item'  => __('Add New Donation Log', 'raise'),
            'edit_item'     => __('Edit Donation Log', 'raise'),
            'new_item'      => __('New Donation Log', 'raise'),
        ),
        'supports'            => array('title', 'custom-fields'),
        'public'              => true,
        'has_archive'         => true,
        'menu_icon'           => 'dashicons-list-view',
        'exclude_from_search' => 'true',
    ));
}

// Add settings link to plugins page
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'raise_plugin_add_settings_link');
function raise_plugin_add_settings_link($links)
{
    $settings_link = '<a href="options-general.php?page=raise-donation-settings">' . __('Settings') . '</a>';
    array_push($links, $settings_link);
    return $links;
}

// Redefine locale for asynchronous POST calls on multi-domain sites
add_filter('locale', 'raise_redefine_locale', RAISE_PRIORITY);
function raise_redefine_locale($locale) {
    if (isset($_POST['locale'])) {
        $locale = $_POST['locale'];
    }
    return $locale;
}

/**
 * Endpoint for Stripe webhook
 */
add_action('rest_api_init', function () {
    register_rest_route('raise/v1', '/stripe/log', [
        'methods'  => 'POST',
        'callback' => 'raise_log_stripe_donation',
    ]);
});

/**
 * Returns current plugin version
 *
 * @return string Plugin version
 */
function raise_get_plugin_version() {
    if (!empty($GLOBALS['raisePluginVersion'])) {
        return $GLOBALS['raisePluginVersion'];
    }

    if (!function_exists('get_plugin_data')) {
        require_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

    // Set plugin version
    $pluginData                    = get_plugin_data(__FILE__, false, false);
    $GLOBALS['raisePluginVersion'] = $pluginData['Version'];

    return $GLOBALS['raisePluginVersion'];
}
