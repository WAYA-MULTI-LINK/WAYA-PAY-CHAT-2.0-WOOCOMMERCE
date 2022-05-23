<?php
/**
 * Plugin Name: WayaPay Payment Gateway
 * Plugin URI: https://wayapay.ng
 * Description: One platform that lets you sell wherever your customers are — online, in-person, anywhere in the world.
 * Version: 1.0.0
 * Author: WayaPay, Ajibola Fasasi
 * Author URI: https://sammyskills.github.io
 * Developer: Ajibola Fasasi
 * Developer URI: https://sammyskills.github.io
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * WC requires at least: 5.0.0
 * WC tested up to: 6.3
 * Text Domain: woocommerce-gateway-wayapay
**/

if(! defined ("ABSPATH")){
    die;
}

add_action('plugins_loaded', 'wc_wayapay_init');

function wc_wayapay_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class WC_Gateway_WayaPay extends WC_Payment_Gateway
    {
            public function __construct()
            {
                global $woocommerce;

                $this->id = 'wayapay';
                $this->icon = apply_filters('woocommerce_wayapay_icon', plugins_url('assets/images/wayapay-payment-options.png', __FILE__));
                $this->method_title = __('WayaPay', 'woocommerce');
                $this->method_description = __('WayaPay redirects customers to WayaPay to enter their payment informations', 'woocommerce');
              
                // Load the form fields.
                $this->init_form_fields();

                // Load the settings.
                $this->init_settings();

                // Define user set variables                
                $this->title              = $this->get_option( 'wayapay_title' );
                $this->description        = $this->get_option( 'wayapay_description' ); 
                $this->merchant_id        = $this->get_option( 'wayapay_merchant_id' );               
                $this->publickey          = $this->get_option( 'wayapay_publickey' ); 
                $this->mode               = $this->get_option( 'wayapay_mode' ); 
                $this->enabled            = $this->get_option( 'wayapay_enabled' );
                $this->payment_page       = $this->get_option( 'wayapay_payment_page' );
                $this->wayapay_url = null;
                $this->wayapayVerify_url = null;

                if ( ! function_exists( 'write_log' ) ) {
                /**
                * Write log to log file
                *
                * @param string|array|object $log
                */
                function write_log( $log ) {
                    if ( true === WP_DEBUG ) {
                        if ( is_array( $log ) || is_object( $log ) ) {
                                error_log( print_r( $log, true ) );
                        }
                        else if(is_bool($log)) {
                            error_log( ($log == true)? 'true': 'false' );
                        }
                        else {
                            error_log( $log );
                        }
                    }
                }
            }

            // Hooks           
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                &$this,
                'process_admin_options'
            ));
           
            add_action('admin_notices', array( $this, 'admin_notices'));

            // Payment listener/API hook.WC_Gateway_WayaPay
            add_action( 'woocommerce_api_wc_gateway_wayapay', array( $this, 'verify_wayapay_transaction' ) );

            //Filters
            add_filter('woocommerce_currencies', array(
            $this,
            'add_ngn_currency'
            ));
            add_filter('woocommerce_currency_symbol', array(
            $this,
            'add_ngn_currency_symbol'
            ), 10, 2);           

        }


        function add_ngn_currency($currencies)
        {
            $currencies['NGN'] = __('Nigerian Naira (NGN)', 'woocommerce');
            return $currencies;
        }

        function add_ngn_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'NGN':
                $currency_symbol = '₦';
                break;
            }
            return $currency_symbol;
        }

        function is_valid_for_use()
        {
            $return = true;

            if (!in_array(get_option('woocommerce_currency'), array(
                'NGN'
                ))) {
                    $return = false;
            }   
            return $return;
        }

        function add_query_vars_filter( $vars ){
            $vars[] = "transId";
            return $vars;
        }       

        function admin_options()
        {           
            echo '<h3>' . esc_html(__('WayaPay Payment Gateway', 'woocommerce')) . '</h3>';
            echo '<p>' . __('<br><img src="' . esc_url(plugins_url('assets/images/wayalogo.png', __FILE__)) . '" >', 'woocommerce') . '</p>';
            echo '<table class="form-table">';

            if ($this->is_valid_for_use()) {
                $this->generate_settings_html();
            } else {
                echo '<div class="inline error"><p><strong>' . esc_html( __('Gateway Disabled', 'woocommerce')). '</strong>: ' . esc_html(__('WayaPay does not support your store currency.', 'woocommerce')) . '</p></div>';
            }
            echo '</table>';
        }

        function init_form_fields()
        {
            $this->form_fields = array(
                'wayapay_title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the payment method title which the user sees during checkout.', 'woocommerce'),
                    'default' => __('Debit/Credit Cards', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'wayapay_description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('This controls the payment method description which the user sees during checkout.', 'woocommerce'),
                    'default' => __('Make payment using your debit and credit cards', 'woocommerce'),
                    'desc_tip' => true,
                ),
                'wayapay_enabled' => array(
                    'title' => __('Enable/Disable', 'woocommerce'), //Enable wayapay as a payment option on the checkout page.
                    'label' => __('Enable WayaPay', 'woocommerce'),
                    'description' => __('Enable WayaPay as a payment option on the checkout page.', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable', 'woocommerce'),
                    'default' => 'no'
                ),
                'wayapay_merchant_id' => array(
                    'title' => __('Merchant Id', 'woocommerce'),
                    'type' => 'text',
                    'description' => 'Login to wayapay.ng to get your merchant id',
                    'desc_tip' => true
                ),
                'wayapay_publickey' => array(
                    'title' => __('Public Key', 'woocommerce'),
                    'type' => 'text',
                    'description' => 'Login to wayapay.ng to get your public key',
                    'desc_tip' => true
                ),
                'wayapay_payment_page' => array(
                    'title' => __('Payment Option', 'woocommerce' ),
                    'type' => 'select',
                    'description' => __('Redirect will redirect the customer to WayaPay to make payment.', 'woocommerce' ),
                    'desc_tip' => true,
                    'options' => array(
                    'redirect' => __( 'Redirect', 'woocommerce' ),
                    ),
                ),
                'wayapay_mode' => array(
                    'title' => __('Environment', 'woocommerce'),
                    'type' => 'select',
                    'description' => __('Select Test or Live modes.', 'woothemes'),
                    'desc_tip' => true,
                    'placeholder' => '',
                    'options' => array(
                    'test' => "Test",
                    'live' => "Live"
                    )
                )
            );
        }

        function payment_fields()
        {
            // Description of payment method from settings
            if ($this->description) {
            ?>
            <p><?php
            echo esc_html($this->description);
            ?></p>

            <?php
            }
            ?>

            <?php
        }

        function admin_notices() {

            if ( $this->enabled == 'no' ) {
                return;
            }

           // Check required fields.
            if (!($this->publickey && $this->mode)) {
                echo '<div class="inline error"><p><strong>' . esc_html(__('Gateway Disabled', 'woocommerce')) . '</strong>: ' . esc_html(__('Please enter your public key.', 'woocommerce')) . '</p></div>';
                return;
            }
            
            if (!($this->merchant_id)) {
                echo '<div class="inline error"><p><strong>' . esc_html(__('Gateway Disabled', 'woocommerce')) . '</strong>: ' . esc_html(__('Please enter your public key.', 'woocommerce')) . '</p></div>';
                return;
            }
        }

        function process_payment($order_id){
            if ('redirect' === $this->payment_page) {
                return $this->process_redirect_payment($order_id);
            }
            else{
                wc_add_notice( __('cannot process payment', 'woocommerce'), 'error' );
            }
        }       

        function process_redirect_payment($order_id)
        {        
            $order = new WC_Order( $order_id );
            $customer_email = $order->get_billing_email();
            $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $customer_phone = $order->get_billing_phone();
            $currency_code = 566;
            $amount = $order->get_total();

            // WayaPay params
            $wayapay_params = [
                'amount'        => $amount,
                'description'   => 'Order from ' . $customer_name,
                'currency'      => $currency_code,
                'fee'           => 1,
                'customer'      => [
                    'name'          => $customer_name,
                    'email'         => $customer_email,
                    'phoneNumber'   => str_replace(['(', ')', '-', '+'], ['', '', '', ''], $customer_phone)
                ],
                'merchantId'    => $this->merchant_id,
                'wayaPublicKey' => $this->publickey
            ];

            // Set args for remote request
            $args = [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode( $wayapay_params )
            ];            
           

            if('live' === $this->mode){
                $this->wayapay_url = 'https://services.staging.wayapay.ng/payment-gateway/api/v1/request/transaction';
            }else {
                $this->wayapay_url = 'https://services.staging.wayapay.ng/payment-gateway/api/v1/request/transaction';
            }    
           
            $response = wp_remote_post($this->wayapay_url, $args);
          
            // Check the response from the request
            if ( ! is_wp_error( $response ) ) { 
              
                // Decode the response body to be used for either success or error responses.
                $wayapay_response = json_decode( wp_remote_retrieve_body( $response ) );
               
                if ( 200 === wp_remote_retrieve_response_code( $response) ) {

                    // Return the response                   
                    return [
                        'result' => 'success',
                        'redirect' => 'https://pay.staging.wayapay.ng/' . '?_tranId=' . $wayapay_response->data->tranId
                    ];
                }
                else {
                    wc_add_notice( __('payment failed from wayapay', 'woocommerce'), 'error' );
                    return;
                }
            }
            else {
                wc_add_notice( __('Unable to process payment try again', 'woocommerce'), 'error' );
                return;
            }
        }       
    }

    function woocommerce_add_wayapay_gateway($methods)
    {
        $methods[] = 'WC_Gateway_WayaPay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_wayapay_gateway');
}

?>