<?php
/**
 * WayaQuick_Client
 *
 * A thin client for the WayaQuick Merchant API v2, mirroring the official SDKs:
 * every method returns the unwrapped `data` payload, and throws
 * WayaQuick_Exception on any failure. The gateway only needs collections here,
 * but the shape is the same as the Java/Go/PHP/Node clients.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Raised for any WayaQuick failure: transport error, non-2xx, or a
 * success:false envelope.
 */
class WayaQuick_Exception extends Exception
{
    private $status_code;
    private $api_code;

    public function __construct($message, $status_code = 0, $api_code = null)
    {
        parent::__construct($message);
        $this->status_code = $status_code;
        $this->api_code = $api_code;
    }

    public function get_status_code()
    {
        return $this->status_code;
    }

    public function get_api_code()
    {
        return $this->api_code;
    }
}

class WayaQuick_Client
{
    private $merchant_id;
    private $secret_key;
    private $base_url;

    public function __construct($merchant_id, $secret_key, $base_url)
    {
        $this->merchant_id = $merchant_id;
        $this->secret_key = $secret_key;
        $this->base_url = rtrim($base_url, '/');
    }

    /**
     * Create a payment and return the checkout details.
     * POST /api/v2/payment-collect/initiate
     */
    public function initiate_collection($payload)
    {
        return $this->request('POST', '/api/v2/payment-collect/initiate', $payload);
    }

    /**
     * Look up a payment by its refNo (the gateway transactionId / webhook OrderId).
     * GET /api/v2/payment-collect/status/{refNo}
     */
    public function get_collection_status($ref_no)
    {
        return $this->request('GET', '/api/v2/payment-collect/status/' . rawurlencode($ref_no));
    }

    /**
     * Timestamped, collision-resistant reference, e.g. WC12-1748160000000-A1B2C3D4.
     * Reuse the same value on retries so it stays idempotent.
     */
    public static function generate_reference($prefix)
    {
        $ts = (int) round(microtime(true) * 1000);
        $rand = strtoupper(bin2hex(random_bytes(4)));
        return $prefix . '-' . $ts . '-' . $rand;
    }

    /**
     * Shared transport: auth headers, JSON encoding, and envelope unwrapping.
     */
    private function request($method, $path, $body = null)
    {
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->secret_key,
                'X-Merchant-Id' => $this->merchant_id,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
        );

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($this->base_url . $path, $args);

        if (is_wp_error($response)) {
            throw new WayaQuick_Exception($response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        if (!is_array($json)) {
            throw new WayaQuick_Exception('Unexpected response from WayaQuick (HTTP ' . $status . ').', $status);
        }

        // Envelope: { success, code, message, data, timestamp }. "00" is the success code.
        $ok = !empty($json['success']) && (!isset($json['code']) || $json['code'] === '00');

        if (!$ok) {
            $message = isset($json['message']) ? $json['message'] : 'WayaQuick request failed.';
            $api_code = isset($json['code']) ? $json['code'] : null;
            throw new WayaQuick_Exception($message, $status, $api_code);
        }

        return isset($json['data']) ? $json['data'] : array();
    }
}
