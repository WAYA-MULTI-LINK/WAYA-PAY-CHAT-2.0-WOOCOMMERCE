<?php
/**
 * Plugin Name: WayaPay WooCommerce Gateway
 * Description: Accept payments on WooCommerce using WayaPay.
 * Version: 1.0.0
 * Author: WayaPay
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'wayapay_init_gateway');

function wayapay_init_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-gateway-wayapay.php';

    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'WC_Gateway_WayaPay';
        return $gateways;
    });
}