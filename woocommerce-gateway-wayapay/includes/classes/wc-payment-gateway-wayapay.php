<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_WayaPay extends WC_Payment_Gateway
{
    private string $merchant_id;
    private string $api_secret_key;
    private string $environment;
    private string $base_url;
    private string $payment_link;

    public function __construct()
    {
        $this->id = 'wayapay';
        $this->method_title = 'WayaPay';
        $this->method_description = 'Accept payments using WayaPay.';
        $this->has_fields = false;

        $this->supports = [
            'products'
        ];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        $this->merchant_id = $this->get_option('merchant_id');
        $this->api_secret_key = $this->get_option('api_secret_key');
        $this->environment = $this->get_option('environment');

        $is_prod = in_array(strtolower($this->environment), ['production', 'prod']);

        $this->base_url = $is_prod
            ? 'https://services.wayapay.ng'
            : 'https://services.staging.wayapay.ng';

        $this->payment_link = $is_prod
            ? 'https://pay.wayapay.ng/?_tranId='
            : 'https://pay.staging.wayapay.ng/?_tranId=';

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [$this, 'process_admin_options']
        );

        add_action(
            'woocommerce_api_wayapay_callback',
            [$this, 'handle_callback']
        );
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable WayaPay Payment Gateway',
                'default' => 'no'
            ],

            'title' => [
                'title' => 'Title',
                'type' => 'text',
                'default' => 'WayaPay',
                'description' => 'This is the payment method title customers will see during checkout.'
            ],

            'description' => [
                'title' => 'Description',
                'type' => 'textarea',
                'default' => 'Pay securely using WayaPay.',
            ],

            'merchant_id' => [
                'title' => 'Merchant ID',
                'type' => 'text',
                'description' => 'Your WayaPay Merchant ID.',
            ],

            'api_secret_key' => [
                'title' => 'API Secret Key',
                'type' => 'password',
                'description' => 'Your WayaPay API Secret Key.',
            ],

            'environment' => [
                'title' => 'Environment',
                'type' => 'select',
                'default' => 'test',
                'options' => [
                    'test' => 'Test / Staging',
                    'production' => 'Production'
                ]
            ],
        ];
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice('Unable to process payment. Invalid order.', 'error');
            return;
        }

        $payment_ref = 'WC-' . $order->get_id() . '-' . time();

        $payload = [
            'currency' => $order->get_currency(),
            'amount' => (float) $order->get_total(),
            'callBackUrl' => home_url('/wc-api/wayapay_callback'),
            'idempotencyKey' => wp_generate_uuid4(),
            'paymentRef' => $payment_ref,
            'metadata' => [
                'firstName' => $order->get_billing_first_name(),
                'lastName' => $order->get_billing_last_name(),
                'phoneNumber' => $order->get_billing_phone(),
                'emailAddress' => $order->get_billing_email(),
                'orderId' => $order->get_id(),
                'cancelUrl' => $order->get_cancel_order_url()
            ]
        ];

        $response = $this->send_request(
            'POST',
            '/payment-collect/initiate',
            $payload
        );

        if (
            empty($response['status']) &&
            empty($response['data'])
        ) {
            wc_add_notice('Unable to initialize WayaPay payment.', 'error');
            return;
        }

        $order->update_meta_data('_wayapay_payment_ref', $payment_ref);
        $order->save();

        $transaction_id = $response['data']['transactionId']
            ?? $response['data']['tranId']
            ?? $response['data']['id']
            ?? null;

        if (!$transaction_id) {
            wc_add_notice('Payment initialized, but checkout reference was not returned.', 'error');
            return;
        }

        return [
            'result' => 'success',
            'redirect' => $this->payment_link . $transaction_id
        ];
    }

    public function handle_callback()
    {
        $transaction_ref = sanitize_text_field($_GET['ref'] ?? $_GET['transactionRef'] ?? '');

        if (empty($transaction_ref)) {
            wp_die('Invalid payment callback.');
        }

        $response = $this->send_request(
            'GET',
            '/payment/transaction?ref=' . urlencode($transaction_ref)
        );

        $data = $response['data'] ?? [];

        $payment_ref = $data['paymentRef'] ?? $data['reference'] ?? null;
        $payment_status = strtolower($data['status'] ?? '');

        if (!$payment_ref) {
            wp_die('Unable to verify payment reference.');
        }

        $order_id = $this->extract_order_id_from_payment_ref($payment_ref);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_die('Order not found.');
        }

        if (in_array($payment_status, ['success', 'successful', 'completed', 'paid'])) {
            $order->payment_complete($transaction_ref);
            $order->add_order_note('WayaPay payment completed successfully.');

            WC()->cart->empty_cart();

            wp_redirect($this->get_return_url($order));
            exit;
        }

        $order->update_status('failed', 'WayaPay payment verification failed.');

        wp_redirect($order->get_cancel_order_url());
        exit;
    }

    private function send_request(string $method, string $endpoint, array $body = []): array
    {
        $args = [
            'method' => $method,
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'Merchant-ID' => $this->merchant_id,
                'API-Secret-Key' => $this->api_secret_key,
            ],
        ];

        if (!empty($body)) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($this->base_url . $endpoint, $args);

        if (is_wp_error($response)) {
            return [
                'status' => false,
                'message' => $response->get_error_message()
            ];
        }

        $body = wp_remote_retrieve_body($response);

        return json_decode($body, true) ?: [];
    }

    private function extract_order_id_from_payment_ref(string $payment_ref): ?int
    {
        if (preg_match('/WC-(\d+)-/', $payment_ref, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}