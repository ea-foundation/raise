<?php
/**
 * Plugin Name: Sentience Donation Processor
 * Plugin URI: http://www.gbs-schweiz.org
 * Description: This plugin processes donations to GBS
 * Version: 0.0.1
 * Author: Naoki Peter
 * Author URI: http://www.0x1.ch
 * License: proprietary
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

add_action( 'init', 'create_post_type' );

function create_post_type() {
  register_post_type( 'gbs_donation',
    array(
      'labels' => array(
        'name' => __( 'Donations' ),
        'singular_name' => __( 'Donation' ),
        'add_new_item' => __( 'Add New Donation' ),
        'edit_item' => __( 'Edit Donation' ),
        'new_item' => __( 'New Donation' )
      ),
      'supports' => array('title', 'editor', 'custom-fields'),
      'public' => true,
      'has_archive' => true,
      'menu_icon' => 'dashicons-star-filled',
      'exclude_from_search' => 'true'
    )
  );
}

include_once("_functions.php");
include_once("form.php");
add_shortcode('donationForm','donationForm');


/*
  Additional Styles 
*/
function register_donation_styles() {
  wp_register_style( 'donation-plugin-css', plugins_url( 'sentience-donation-processor/css/scrollable-horizontal.css' ) );
  wp_enqueue_style( 'donation-plugin-css' );
}

add_action( 'wp_enqueue_scripts', 'register_donation_styles' );



/*
  Additional Scripts  
*/
function register_donation_scripts()
{ 
  wp_register_script( 'donation-plugin-jquerytools', plugins_url( 'sentience-donation-processor/js/jquery.tools.min.js' ) );
  wp_enqueue_script( 'donation-plugin-jquerytools' );
  wp_register_script( 'donation-plugin-form', plugins_url( 'sentience-donation-processor/js/form.js' ), array('donation-plugin-jquerytools') );
  wp_enqueue_script( 'donation-plugin-form' );
}

add_action( 'wp_enqueue_scripts', 'register_donation_scripts' );
