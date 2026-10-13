<?php
/**
 * Plugin Name: WayaQuick for WooCommerce
 * Description: Accept payments on WooCommerce using WayaQuick (Merchant API v2).
 * Plugin URI: https://www.wayaquick.com/web-integration
 * Version: 2.0.0
 * Author: WayaQuick
 * Author URI: https://www.wayaquick.com
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 * WC requires at least: 6.0
 * Text Domain: wayaquick-for-woocommerce
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
    echo esc_html__('WayaQuick for WooCommerce requires WooCommerce to be installed and active.', 'wayaquick-for-woocommerce');
    echo '</p></div>';
}

/**
 * Declare WooCommerce feature compatibility.
 */
add_action('before_woocommerce_init', 'wayaquick_wc_declare_compatibility');

function wayaquick_wc_declare_compatibility()
{
    if (class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        // The plugin uses wc_get_order()/wc_get_orders() and WooCommerce order metadata APIs.
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            WAYAQUICK_WC_FILE,
            true
        );

        // Native Checkout Block support is not implemented yet.
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            WAYAQUICK_WC_FILE,
            false
        );
    }
}
