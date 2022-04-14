<?php 

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Payment_Gateway_Wayapay
 * 
 * @package WooCommerce/Payments
 */
class WC_Payment_Gateway_Wayapay extends WC_Payment_Gateway
{
    /** Internal ID of the payment gateway */
    const GATEWAY_ID = 'wayapay';

    /** API request endpoint for test transactions */
    const TEST_REQUEST_ENDPOINT = 'https://services.staging.wayapay.ng/payment-gateway/api/v1/request/transaction';
    
    /** API verification endpoint for test transactions */
    const TEST_VERIFICATION_ENDPOINT = 'https://services.staging.wayapay.ng/payment-gateway/api/v1/transaction/query/';

    /** TEST Redirect URL */
    const TEST_REDIRECT_URL = 'https://pay.staging.wayapay.ng/';

    /**
     * Constructor
     */
    public function __construct() {
        // The Gateway's Unique ID
        $this->id = self::GATEWAY_ID;

        // Gateway Icon
        $this->icon = ''; // TODO

        // Gateway title
        $this->method_title = __( 'WayaPay Payments', 'woocommerce-gateway-wayapay' );

        // Description of the payment gateway
        $this->method_description = __( 'One platform that lets you sell wherever your customers are — online, in-person, anywhere in the world. Accept Credit/Debit Cards, USSD, Bank Account, Wallets, PayAttitude and more.', 'woocommerce-gateway-wayapay' );

        // Supports
        $this->supports = [ 'products' ];

        // Define the settings field (admin)
        $this->init_form_fields();

        // Load the settings (admin)
        $this->init_settings();

        // Get setting values
        $this->enabled              = $this->get_option( 'enabled' );
        $this->title                = $this->get_option( 'title' );
        $this->description          = $this->get_option( 'description' );
        $this->payment_mode         = $this->get_option( 'payment_mode' );
        $this->merchant_id          = $this->get_option( 'merchant_id' );
        $this->test_public_key      = $this->get_option( 'test_public_key' );
        $this->live_public_key      = $this->get_option( 'live_public_key' );

        // Hooks
        add_action( 
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array(
                $this,
                'process_admin_options'
            ) 
        );

    }

    /**
     * Init_form_fields
     *
     * Define all the settings (form fields) that should
     * be available for this gateway.
     *
     **/
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled'               => [
                'title'         => __( 'Enable/Disable', 'woocommerce-gateway-wayapay' ),
                'label'         => __( 'Enable WayaPay', 'woocommerce-gateway-wayapay' ),
                'type'          => 'checkbox',
                'description'   => __( 'Enable WayaPay as a payment option in the checkout page.', 'woocommerce-gateway-wayapay' ),
                'default'       => 'no',
                'desc_tip'      => true,
            ],
            'title'                 => [
                'title'         => __( 'Title', 'woocommerce-gateway-wayapay' ),
                'type'          => 'text',
                'description'   => __( 'This controls the title (name/identifier) of the payment method which the user sees during checkout.', 'woocommerce-gateway-wayapay' ),
                'default'       => 'WayaPay',
            ],
            'description'           => [
                'title'         => __( 'Description', 'woocommerce-gateway-wayapay' ),
                'type'          => 'textarea',
                'description'   => __( 'This controls the payment method description which the user sees during checkout.', 'woocommerce-gateway-wayapay' ),
                'default'       => 'Credit/Debit Cards, USSD, Bank Account, Wallets, PayAttitude and more.',
            ],
            'payment_mode'          => [
                'title'         => __( 'Payment Mode', 'woocommerce-gateway-wayapay' ),
                'type'          => 'select',
                'description'   => __( 'Choose a payment mode: Test or Live.', 'woocommerce-gateway-wayapay' ),
                'default'       => 'live',
                'options'       => [
                    'live'      => __( 'Live Mode: receive actual payments.', 'woocommerce-gateway-wayapay' ),
                    'test'      => __( 'Test Mode: for testing purposes only.', 'woocommerce-gateway-wayapay' ),
                ]
            ],
            'merchant_id'            => [
                'title'         => __( 'WayaPay Merchant ID', 'woocommerce-gateway-wayapay' ),
                'type'          => 'text',
                'description'   => sprintf( __( '<a href="%1$s" target="_blank">Login</a> or <a href="%2$s" target="_blank">Register</a> for a WayaPay account and get your merchant ID.', 'woocommerce-gateway-wayapay' ), 'https://wayapay.ng/login', 'https://wayapay.ng/register' ),
                'default'       => '',
            ],
            'test_public_key'       => [
                'title'         => __( 'WayaPay Test Public Key', 'woocommerce-gateway-wayapay' ),
                'type'          => 'text',
                'description'   => sprintf( __( '<a href="%1$s" target="_blank">Login</a> or <a href="%2$s" target="_blank">Register</a> for a WayaPay account and get your Test Public Key.', 'woocommerce-gateway-wayapay' ), 'https://wayapay.ng/login', 'https://wayapay.ng/register' ),
                'default'       => '',
            ],
            'live_public_key'       => [
                'title'         => __( 'WayaPay Live Public Key', 'woocommerce-gateway-wayapay' ),
                'type'          => 'text',
                'description'   => sprintf( __( '<a href="%1$s" target="_blank">Login</a> or <a href="%2$s" target="_blank">Register</a> for a WayaPay account and get your Live Public Key.', 'woocommerce-gateway-wayapay' ), 'https://wayapay.ng/login', 'https://wayapay.ng/register' ),
                'default'       => '',
            ],
            
        ];
    }

    /**
     * Process Payment
     *
     * Process the order using the redirect method
     * as the only available method for now.
     *
     * @param Int $var Order ID
     * @return Mixed
     **/
    public function process_payment( $order_id )
    {
        global $woocommerce;
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
            'wayaPublicKey' => ( $this->payment_mode == 'test' ) ? $this->test_public_key : $this->live_public_key
        ];

        // Set args for remote request
        $args = [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode( $wayapay_params )
        ];

        // Run the remote POST request
        $request = wp_remote_post( self::TEST_REQUEST_ENDPOINT, $args );

        // Check the response from the request
        if ( ! is_wp_error( $request ) ) { 
            
            // Decode the response body to be used for either success or error responses.
            $wayapay_response = json_decode( wp_remote_retrieve_body( $request ) );
            
            if ( 200 === wp_remote_retrieve_response_code( $request ) ) {

                // Return the response
                return [
                    'result' => 'success',
                    'redirect' => self::TEST_REDIRECT_URL . '?_tranId=' . $wayapay_response->data->tranId
                ];
            }
            else {
                // Request failed, show error
                wc_add_notice( 
                    __( 'Payment Failed. Reason: ', 'woocommerce-gateway-wayapay' ) .
                    $wayapay_response->message,
                    'error'  
                );
        
                return;
            }
        }
        else {
            // Request failed, show error
            wc_add_notice( 
                __( 'Payment Failed. Reason: Unable to connect to payment gateway.', 'woocommerce-gateway-wayapay' ),
                'error'
            );

            return;
        }
    }

    /**
     * Verify Payment
     *
     * Verify the payment made and return either a
     * success or failed message as fetched from the
     * verification endpoint.
     *
     * @return type
     * @throws conditon
     **/
    public function verify_payment()
    {
        # code...
    }

    //TODO: admin notices
}
