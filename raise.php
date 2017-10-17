<?php
/**
 * Plugin Name: Raise
 * Plugin URI: https://github.com/ea-foundation/raise
 * GitHub Plugin URI: ea-foundation/raise
 * Description: The Free Donation Plugin for WordPress
 * Version: 1.0.6
 * Author: Naoki Peter
 * License: GPLv3 or later
 */

defined('ABSPATH') or exit;

// Set priority constant for email filters
define('RAISE_PRIORITY', 12838790321);

// Asset version
define('RAISE_ASSET_VERSION', '0.41');

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

// Add legacy shortcode for donation form
//TODO Remove in next major release
add_shortcode('donationForm','raise_form');

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

// Process donation (Bank Transfer and Stripe)
add_action("wp_ajax_nopriv_raise_donate", "raise_process_donation");
add_action("wp_ajax_raise_donate", "raise_process_donation");

// Prepare redirect (PayPal, Skrill, GoCardless, BitPay)
add_action("wp_ajax_nopriv_raise_redirect", "raise_prepare_redirect");
add_action("wp_ajax_raise_redirect", "raise_prepare_redirect");

// Execute and log Paypal transaction
add_action("wp_ajax_nopriv_paypal_execute", "raise_execute_paypal_donation");
add_action("wp_ajax_paypal_execute", "raise_execute_paypal_donation");

// Process GoCardless donation
add_action("wp_ajax_nopriv_gocardless_debit", "raise_process_gocardless_donation");
add_action("wp_ajax_gocardless_debit", "raise_process_gocardless_donation");

// Log BitPay donation
add_action("wp_ajax_nopriv_bitpay_log", "raise_process_bitpay_log");
add_action("wp_ajax_bitpay_log", "raise_process_bitpay_log");

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
    wp_register_script('donation-jquery-ui', plugins_url('raise/js/jquery-ui.min.js'), array(), RAISE_ASSET_VERSION);
    wp_enqueue_script('donation-jquery-ui');
    wp_register_script('donation-json-settings-editor', plugins_url('raise/js/jsoneditor.min.js'), array(), RAISE_ASSET_VERSION);
    wp_enqueue_script('donation-json-settings-editor');
    wp_register_style('donation-json-settings-editor-css', plugins_url('raise/js/jsoneditor.min.css'), array(), RAISE_ASSET_VERSION);
    wp_enqueue_style('donation-json-settings-editor-css');
    wp_register_style('donation-admin-css', plugins_url('raise/css/admin.css'), array(), RAISE_ASSET_VERSION);
    wp_enqueue_style('donation-admin-css');
    wp_register_style('donation-jquery-ui-css', plugins_url('raise/css/jquery-ui.min.css'), array(), RAISE_ASSET_VERSION);
    wp_enqueue_style('donation-jquery-ui-css');
    wp_enqueue_media();
}

/*
 * Add styles and scripts
 */
add_action('wp_enqueue_scripts', 'raise_register_donation_styles');
function raise_register_donation_styles()
{
    // Register bootstrap and bootstrap combobox
    wp_register_style('bootstrap-scoped', plugins_url('raise/css/scoped-bootstrap.min.css'), array(), RAISE_ASSET_VERSION);
    wp_register_style('donation-combobox', plugins_url('raise/css/bootstrap-combobox.css'), array(), RAISE_ASSET_VERSION);

    // Enqueue country flag sprites if necessary
    if ($flagSprite = raise_get_best_flag_sprite()) {
        wp_register_style('donation-plugin-flags', plugins_url('raise/css/flags-' . $flagSprite . '.css'), array(), RAISE_ASSET_VERSION);
        wp_enqueue_style('donation-plugin-flags');
    }

    // Enqueue all other styles
    wp_register_style('donation-plugin', plugins_url('raise/css/form.css'), array(), RAISE_ASSET_VERSION);
    wp_enqueue_style('donation-plugin');
    wp_register_style('donation-button', plugins_url('raise/css/button.css.php'), array(), RAISE_ASSET_VERSION);
    wp_enqueue_style('donation-button');

    // Register scripts (enqueue later)
    wp_register_script('donation-plugin-bootstrapjs', '//maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js', array('jquery'));
    wp_register_script('donation-plugin-jqueryformjs', '//malsup.github.io/jquery.form.js', array('jquery'));
    wp_register_script('donation-plugin-stripe', '//checkout.stripe.com/checkout.js');
    wp_register_script('donation-plugin-paypal', '//www.paypalobjects.com/api/checkout.js?data-version-4'); // The query string is actually supposed to be a separate attribute without value, see below
    wp_register_script('donation-combobox', plugins_url('raise/js/bootstrap-combobox.js'), array(), RAISE_ASSET_VERSION);
    wp_register_script('donation-plugin-form', plugins_url('raise/js/form.js'), array('jquery', 'donation-plugin-stripe'), RAISE_ASSET_VERSION);
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

// Set attribute data-version-4 for PayPal Checkout.js
// Enable when data-version-5 is out
/*add_filter('clean_url', 'unclean_url', 10, 3);
function unclean_url($good_protocol_url, $original_url, $_context){
    if (false !== strpos($original_url, '?data-version-4')){
        remove_filter('clean_url', 'unclean_url', 10, 3);
        $url_parts = parse_url($good_protocol_url);
        return '//' . $url_parts['host'] . $url_parts['path'] . "' data-version-4 charset='UTF-8";
    }
    return $good_protocol_url;
}*/

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
