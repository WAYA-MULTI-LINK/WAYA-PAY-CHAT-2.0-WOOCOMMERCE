<?php
/**
 * WayaQuick_Webhook
 *
 * Verifies inbound webhooks, mirroring client.webhooks() in the SDKs.
 * Signature scheme (from the integration spec):
 *
 *   Base64( HMAC-SHA256( "<X-Waya-Timestamp>" + "." + "<raw body>", secret ) )
 *
 * The timestamp is milliseconds since epoch and is rejected outside a
 * 5-minute replay window. Always feed this the RAW request body: the
 * signature is computed over the exact bytes sent.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WayaQuick_Webhook
{
    const TIMESTAMP_HEADER = 'X-Waya-Timestamp';
    const SIGNATURE_HEADER = 'X-Waya-Signature';

    // Replay window, in seconds.
    const DEFAULT_TOLERANCE = 300;

    /**
     * Signature-only + replay check. Returns a boolean, never throws.
     */
    public static function verify_signature($raw_body, $timestamp, $signature, $secret, $tolerance = self::DEFAULT_TOLERANCE)
    {
        if (empty($timestamp) || empty($signature) || empty($secret)) {
            return false;
        }

        // Timestamp arrives in milliseconds.
        $now_ms = (int) round(microtime(true) * 1000);
        $ts_ms = (int) $timestamp;

        if (abs($now_ms - $ts_ms) > ($tolerance * 1000)) {
            return false;
        }

        $signed_payload = $timestamp . '.' . $raw_body;
        $expected = base64_encode(hash_hmac('sha256', $signed_payload, $secret, true));

        return hash_equals($expected, $signature);
    }

    /**
     * Verify, then decode. Returns the event array, or throws on anything it
     * cannot trust (unsigned, forged, stale, or non-JSON).
     */
    public static function construct_event($raw_body, $timestamp, $signature, $secret, $tolerance = self::DEFAULT_TOLERANCE)
    {
        if (!self::verify_signature($raw_body, $timestamp, $signature, $secret, $tolerance)) {
            throw new WayaQuick_Exception('Webhook signature verification failed.');
        }

        $event = json_decode($raw_body, true);

        if (!is_array($event)) {
            throw new WayaQuick_Exception('Webhook body is not valid JSON.');
        }

        return $event;
    }
}
