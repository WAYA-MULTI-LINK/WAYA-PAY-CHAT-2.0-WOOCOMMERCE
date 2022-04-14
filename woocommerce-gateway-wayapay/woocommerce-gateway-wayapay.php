<?php 
/**
 * Plugin Name: WooCommerce WayaPay Gateway
 * Plugin URI: https://wayapay.ng
 * Description: One platform that lets you sell wherever your customers are — online, in-person, anywhere in the world.
 * Version: 1.0.0
 * Author: WayaPay, Samuel Asor
 * Author URI: https://sammyskills.github.io
 * Developer: Samuel Asor
 * Developer URI: https://sammyskills.github.io
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * WC requires at least: 5.0.0
 * WC tested up to: 6.3
 * Text Domain: woocommerce-gateway-wayapay
 **/

//  Prevent direct access 
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin specific constants
define( 'WC_WAYAPAY_PLUGIN_FILE', __FILE__ );
define( 'WC_WAYAPAY_PLUGIN_DIR', plugin_dir_path( WC_WAYAPAY_PLUGIN_FILE ) );

/**
 * WayaPay Gateway Init
 *
 * Initialize the WooCommerce WayaPay Payment
 * Gateway for use
 *
 **/
function woocommerce_gateway_wayapay_init()
{
    // Add the notices file(s)
    require_once( WC_WAYAPAY_PLUGIN_DIR . 'includes/notices/admin.php' );
    
    // WooCommerce MUST be installed and active to use this plugin
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'wayapay_missing_woocommerce_notice' );
        return;
    }

    // Add the gateway class file
    require_once( WC_WAYAPAY_PLUGIN_DIR . 'includes/classes/wc-payment-gateway-wayapay.php' );
    
    // The wc gateway filter
    add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_wayapay_gateway', 99 );
}
add_action( 'plugins_loaded', 'woocommerce_gateway_wayapay_init', 99 );

/**
 * WooCommerce Add WayaPay Gateway
 *
 * Add WayaPay to the list of WooCommerce 
 * Payment Gateways / Methods.
 *
 * @param Array $methods Existing payment methods/gateways
 * @return Array
 **/
function woocommerce_add_wayapay_gateway( $methods )
{
    // Add the class
    $methods[] = 'WC_Payment_Gateway_Wayapay';

    // Return all methods
    return $methods;
}

/**
 * WayaPay Plugin Action Links
 *
 * Add a settings link to the plugin entry page
 * in the plugins page.
 *
 * @param Array $links Existing links
 * @return Array
 **/
function wayapay_plugin_action_links( $links )
{
    // Set of parameters to build the URL to the gateway's settings page.
    $settings_url_params = [
        'page'    => 'wc-settings',
        'tab'     => 'checkout',
        'section' => 'wayapay',
    ];

    // Settings url
    $settings_url = admin_url( add_query_arg( $settings_url_params, 'admin.php' ) );
    
    // settings array
    $settings_array = [
        'settings' => '<a href="' . esc_url( $settings_url ) . '" title="' . __( 'WooCommerce WayaPay Gateway - Settings Page', 'woocommerce-gateway-wayapay' ) . '">' . __( 'Settings', 'woocommerce-gateway-wayapay' ) . '</a>'
    ];

    return array_merge($settings_array, $links);
    
}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'wayapay_plugin_action_links' );