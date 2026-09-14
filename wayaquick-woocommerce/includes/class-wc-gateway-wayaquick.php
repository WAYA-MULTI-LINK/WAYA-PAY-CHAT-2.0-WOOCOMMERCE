<?php
/**
 * WC_Gateway_WayaQuick
 *
 * The WooCommerce glue. Starts a payment on checkout, redirects the customer to
 * the WayaQuick hosted page, then finalises the order from TWO independent
 * signals:
 *   1. The signed webhook (authoritative, arrives server-to-server).
 *   2. The customer returning to our return URL (we confirm via the status
 *      endpoint, as a safety net if the webhook is slow).
 * Whichever lands first completes the order; the other becomes a no-op.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_WayaQuick extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'wayaquick';
        $this->method_title = __('WayaQuick', 'wayaquick');
        $this->method_description = __('Accept card, transfer, USSD and wallet payments through WayaQuick.', 'wayaquick');
        $this->has_fields = false;
        $this->supports = array('products');

        // Checkout logo. Only set it if the file is actually bundled, so a
        // missing asset shows no icon rather than a broken image.
        $icon_path = WAYAQUICK_WC_PATH . 'assets/images/wayalogo.png';
        if (file_exists($icon_path)) {
            $this->icon = plugins_url('assets/images/wayalogo.png', WAYAQUICK_WC_FILE);
        }

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        // Save settings from the admin screen.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // Public endpoints: /wc-api/wayaquick_webhook and /wc-api/wayaquick_return
        add_action('woocommerce_api_wayaquick_webhook', array($this, 'handle_webhook'));
        add_action('woocommerce_api_wayaquick_return', array($this, 'handle_return'));
    }

    /**
     * Render the checkout icon at a sane size, whatever the source image
     * dimensions are. WooCommerce themes vary wildly in how they style this.
     */
    public function get_icon()
    {
        if (empty($this->icon)) {
            return apply_filters('woocommerce_gateway_icon', '', $this->id);
        }

        $html = '<img src="' . esc_url($this->icon) . '"'
            . ' alt="' . esc_attr($this->get_title()) . '"'
            . ' style="max-height:24px;width:auto;vertical-align:middle;margin-left:6px;" />';

        return apply_filters('woocommerce_gateway_icon', $html, $this->id);
    }

    /**
     * Admin settings fields.
     */
    public function init_form_fields()
    {
        $webhook_url = function_exists('WC') ? WC()->api_request_url('wayaquick_webhook') : '';

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'wayaquick'),
                'type' => 'checkbox',
                'label' => __('Enable WayaQuick', 'wayaquick'),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'wayaquick'),
                'type' => 'text',
                'default' => __('WayaQuick', 'wayaquick'),
                'description' => __('What customers see as the payment method name at checkout.', 'wayaquick'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'wayaquick'),
                'type' => 'textarea',
                'default' => __('Pay securely with card, transfer, USSD or your WayaQuick wallet.', 'wayaquick'),
            ),
            'environment' => array(
                'title' => __('Environment', 'wayaquick'),
                'type' => 'select',
                'default' => 'test',
                'options' => array(
                    'test' => __('Test / Staging', 'wayaquick'),
                    'production' => __('Production', 'wayaquick'),
                ),
                'description' => __('Use Test while integrating, then switch to Production.', 'wayaquick'),
                'desc_tip' => true,
            ),
            'merchant_id' => array(
                'title' => __('Merchant ID', 'wayaquick'),
                'type' => 'text',
                'description' => __('Your MER_... Merchant ID from the dashboard.', 'wayaquick'),
                'desc_tip' => true,
            ),
            'public_key' => array(
                'title' => __('Public Key', 'wayaquick'),
                'type' => 'text',
                'description' => __('Your WayaQuick merchant public key.', 'wayaquick'),
                'desc_tip' => true,
            ),
            'secret_key' => array(
                'title' => __('Secret Key', 'wayaquick'),
                'type' => 'password',
                'description' => __('WAYASECK_TEST_... on test, WAYASECK_... on live.', 'wayaquick'),
                'desc_tip' => true,
            ),
            'webhook_secret' => array(
                'title' => __('Webhook Secret', 'wayaquick'),
                'type' => 'password',
                'description' => __('The merchant secret used to sign webhooks. Required to accept payment confirmations.', 'wayaquick'),
                'desc_tip' => true,
            ),
            'webhook_url' => array(
                'title' => __('Webhook URL', 'wayaquick'),
                'type' => 'title',
                'description' => sprintf(
                    /* translators: %s: the webhook URL to paste into the dashboard */
                    __('Add this URL in your WayaQuick dashboard under Settings, API Keys and Webhooks: %s', 'wayaquick'),
                    '<br/><code>' . esc_url($webhook_url) . '</code>'
                ),
            ),
            'base_url' => array(
                'title' => __('API Base URL (advanced)', 'wayaquick'),
                'type' => 'text',
                'description' => __('Leave blank to use the default for the selected environment. Override only if support gives you a different host.', 'wayaquick'),
                'default' => '',
                'desc_tip' => true,
            ),
        );
    }

    /**
     * Resolve the API base URL from the environment, or an explicit override.
     */
    private function get_base_url()
    {
        $override = trim((string) $this->get_option('base_url'));
        if ($override !== '') {
            return $override;
        }

        if ($this->get_option('environment') === 'production') {
            return 'https://services.wayapay.ng/merchant-middleware';
        }

        return 'https://services.staging.wayapay.ng/merchant-middleware';
    }

    private function get_client()
    {
        return new WayaQuick_Client(
            $this->get_option('merchant_id'),
            $this->get_option('secret_key'),
            $this->get_base_url()
        );
    }

    /**
     * Start the payment. Called when the customer clicks "Place order".
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(__('Unable to process payment: order not found.', 'wayaquick'), 'error');
            return array('result' => 'failure');
        }

        $reference = WayaQuick_Client::generate_reference('WC' . $order->get_id());

        // The customer lands here after paying. Carry the order id and our ref.
        $return_url = add_query_arg(
            array(
                'order_id' => $order->get_id(),
                'ref' => rawurlencode($reference),
            ),
            WC()->api_request_url('wayaquick_return')
        );

        $payload = array(
            'firstName' => $order->get_billing_first_name(),
            'lastName' => $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'amount' => number_format((float) $order->get_total(), 2, '.', ''),
            'currency' => $order->get_currency(),
            'transactionId' => $reference,
            'description' => sprintf(__('Order #%s', 'wayaquick'), $order->get_order_number()),
            'redirectLink' => $return_url,
            'meta' => array(
                'orderId' => (string) $order->get_id(),
            ),
        );

        try {
            $data = $this->get_client()->initiate_collection($payload);
        } catch (WayaQuick_Exception $e) {
            wc_add_notice(__('Payment could not be started. Please try again.', 'wayaquick'), 'error');
            $order->add_order_note('WayaQuick initiate failed: ' . $e->getMessage());
            return array('result' => 'failure');
        }

        $checkout_url = isset($data['checkOutUrl'])
    ? $data['checkOutUrl']
    : '';

$public_key = trim((string) $this->get_option('public_key'));

if ($checkout_url === '') {
    wc_add_notice(
        __('Payment could not be started: no checkout URL returned.', 'wayaquick'),
        'error'
    );

    $order->add_order_note(
        'WayaQuick initiate returned no checkOutUrl.'
    );

    return array('result' => 'failure');
}

if ($public_key === '') {
    wc_add_notice(
        __('WayaQuick public key is not configured.', 'wayaquick'),
        'error'
    );

    return array('result' => 'failure');
}

$checkout_url = add_query_arg(
    'PUBLIC_KEY',
    $public_key,
    $checkout_url
);
        $gateway_ref = isset($data['transactionId']) ? $data['transactionId'] : '';

        if ($checkout_url === '') {
            wc_add_notice(__('Payment could not be started: no checkout URL returned.', 'wayaquick'), 'error');
            $order->add_order_note('WayaQuick initiate returned no checkOutUrl.');
            return array('result' => 'failure');
        }

        // Store both references so webhook and return handlers can find this order.
        $order->update_meta_data('_wayaquick_reference', $reference);
        if ($gateway_ref !== '') {
            $order->update_meta_data('_wayaquick_gateway_ref', $gateway_ref);
        }
        $order->update_status('pending', __('Awaiting WayaQuick payment.', 'wayaquick'));
        $order->save();

        return array(
            'result' => 'success',
            'redirect' => $checkout_url,
        );
    }

    /**
     * Customer returns from the hosted page. Confirm via the status endpoint.
     */
    public function handle_return()
    {
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
        $order = $order_id ? wc_get_order($order_id) : false;

        if (!$order) {
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }

        // The webhook may already have completed the order.
        if ($order->is_paid()) {
            wp_safe_redirect($this->get_return_url($order));
            exit;
        }

        $gateway_ref = $order->get_meta('_wayaquick_gateway_ref');
        $status = '';

        if (!empty($gateway_ref)) {
            try {
                $data = $this->get_client()->get_collection_status($gateway_ref);
                $status = isset($data['status']) ? strtoupper($data['status']) : '';
            } catch (WayaQuick_Exception $e) {
                $order->add_order_note('WayaQuick status check failed: ' . $e->getMessage());
            }
        }

        if ($status === 'SUCCESSFUL') {
            $this->mark_order_paid($order, $gateway_ref);
            wp_safe_redirect($this->get_return_url($order));
            exit;
        }

        $failed = array('FAILED', 'DECLINED', 'REJECTED', 'ABANDONED', 'EXPIRED', 'CANCELLED');
        if (in_array($status, $failed, true)) {
            $order->update_status('failed', __('WayaQuick reported the payment as not completed.', 'wayaquick'));
            wc_add_notice(__('Your payment was not completed. Please try again.', 'wayaquick'), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        // Still in flight. Send them to the order-received page; the webhook finalises.
        wc_add_notice(__('Your payment is being confirmed. Your order will update shortly.', 'wayaquick'), 'notice');
        wp_safe_redirect($this->get_return_url($order));
        exit;
    }

    /**
     * Signed server-to-server webhook. The authoritative completion signal.
     */
    public function handle_webhook()
    {
        $raw_body = file_get_contents('php://input');
        $timestamp = isset($_SERVER['HTTP_X_WAYA_TIMESTAMP']) ? $_SERVER['HTTP_X_WAYA_TIMESTAMP'] : '';
        $signature = isset($_SERVER['HTTP_X_WAYA_SIGNATURE']) ? $_SERVER['HTTP_X_WAYA_SIGNATURE'] : '';
        $secret = $this->get_option('webhook_secret');

        try {
            $event = WayaQuick_Webhook::construct_event($raw_body, $timestamp, $signature, $secret);
        } catch (WayaQuick_Exception $e) {
            status_header(401);
            echo 'unauthorized';
            exit;
        }

        $gateway_ref = isset($event['OrderId']) ? $event['OrderId'] : '';
        $status = isset($event['Status']) ? strtoupper($event['Status']) : '';

        $order = $this->find_order_by_gateway_ref($gateway_ref);

        if ($order) {
            if ($status === 'SUCCESSFUL') {
                $this->mark_order_paid($order, $gateway_ref);
            } elseif ($status === 'FAILED') {
                if (!$order->is_paid()) {
                    $order->update_status('failed', __('WayaQuick webhook: payment failed.', 'wayaquick'));
                }
            }
            // PARTIAL: leave the order alone and reconcile manually.
        }

        // Acknowledge fast so WayaQuick does not retry needlessly.
        status_header(200);
        echo 'ok';
        exit;
    }

    /**
     * Complete an order exactly once. payment_complete() is idempotent-safe here
     * because we guard on is_paid().
     */
    private function mark_order_paid($order, $transaction_id)
    {
        if ($order->is_paid()) {
            return;
        }

        $order->payment_complete($transaction_id);
        $order->add_order_note(sprintf(__('WayaQuick payment confirmed (ref %s).', 'wayaquick'), $transaction_id));
    }

    /**
     * Find the order carrying a given gateway reference (webhook OrderId / refNo).
     */
    private function find_order_by_gateway_ref($ref)
    {
        if (empty($ref)) {
            return false;
        }

        // The webhook OrderId may echo either the gateway reference or the
        // reference we sent, so match on both.
        $orders = wc_get_orders(array(
            'limit' => 1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_wayaquick_gateway_ref',
                    'value' => $ref,
                ),
                array(
                    'key' => '_wayaquick_reference',
                    'value' => $ref,
                ),
            ),
        ));

        if (!empty($orders)) {
            return $orders[0];
        }

        return false;
    }
}