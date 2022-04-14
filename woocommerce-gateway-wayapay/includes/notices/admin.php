<?php 
/**
 * Admin Notices
 * 
 * All notices (error or success messages) 
 * that are shown in the admin area of this
 * plugin.
 */

/**
 * Display a notice if WooCommerce is not installed
 **/
function wayapay_missing_woocommerce_notice()
{
    echo '<div class="error">
        <p><strong>Wayapay Payment Gateway for WooCommerce</strong> requires <strong>"WooCommerce"</strong> to be installed and activated. Please click on the button below to continue</p>
        <p>'. sprintf( __( '%s', 'woo-wayapay' ), '<a href="' . admin_url( 'plugin-install.php?tab=plugin-information&plugin=woocommerce&TB_iframe=true&width=772&height=539' ) . '" class="button-primary thickbox open-plugin-details-modal">Install / Activate WooCommerce</a>' ) .'</p></div>';
}