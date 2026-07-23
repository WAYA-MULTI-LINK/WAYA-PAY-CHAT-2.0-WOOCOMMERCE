<?php
/**
 * Plugin Name: WayaQuick WooCommerce Gateway
 * Description: Accept payments on WooCommerce using WayaQuick (Merchant API v2).
 * Version: 2.0.0
 * Author: WayaQuick
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * Text Domain: wayaquick
 */

// Block direct access. WordPress defines ABSPATH; a direct hit will not.
if (!defined('ABSPATH')) {
    exit;
}

define('WAYAQUICK_WC_VERSION', '2.0.0');
define('WAYAQUICK_WC_FILE', __FILE__);
define('WAYAQUICK_WC_PATH', plugin_dir_path(__FILE__));

/**
 * Boot the gateway once all plugins are loaded, so WooCommerce is available.
 */
add_action('plugins_loaded', 'wayaquick_wc_init', 11);

function wayaquick_wc_init()
{
    // If WooCommerce is not active, warn the admin and stop.
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'wayaquick_wc_missing_wc_notice');
        return;
    }

    require_once WAYAQUICK_WC_PATH . 'includes/class-wayaquick-client.php';
    require_once WAYAQUICK_WC_PATH . 'includes/class-wayaquick-webhook.php';
    require_once WAYAQUICK_WC_PATH . 'includes/class-wc-gateway-wayaquick.php';

    add_filter('woocommerce_payment_gateways', 'wayaquick_wc_register_gateway');
}

/**
 * Register the gateway class with WooCommerce.
 */
function wayaquick_wc_register_gateway($gateways)
{
    $gateways[] = 'WC_Gateway_WayaQuick';
    return $gateways;
}

/**
 * Admin notice shown when WooCommerce is missing.
 */
function wayaquick_wc_missing_wc_notice()
{
    echo '<div class="notice notice-error"><p>';
    echo esc_html__('WayaQuick WooCommerce Gateway requires WooCommerce to be installed and active.', 'wayaquick');
    echo '</p></div>';
}

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 */
add_action('before_woocommerce_init', 'wayaquick_wc_declare_hpos');

function wayaquick_wc_declare_hpos()
{
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            WAYAQUICK_WC_FILE,
            true
        );
    }
}
