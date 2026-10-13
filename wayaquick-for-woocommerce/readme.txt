=== WayaQuick for WooCommerce ===
Contributors: wayaquick
Tags: wayaquick, woocommerce, payments, payment-gateway, nigeria
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 6.0
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept card, bank transfer, USSD and wallet payments on WooCommerce through the WayaQuick hosted checkout.

== Description ==

WayaQuick for WooCommerce lets your store collect payments through WayaQuick.
At checkout, the customer selects WayaQuick and is redirected to the secure WayaQuick hosted payment page at https://pay.wayaquick.com to complete the payment.

The plugin initiates transactions through the WayaQuick Merchant API, verifies signed webhook notifications, checks payment status when the customer returns, and updates the WooCommerce order accordingly.

Features:

* Card, bank transfer, USSD and WayaQuick wallet payments, depending on the payment methods enabled for the merchant.
* Hosted checkout, so card details are not collected by your WordPress site.
* HMAC-SHA256 signed webhook verification with a 5-minute replay window.
* Test and Production environments.
* Compatible with WooCommerce High-Performance Order Storage (HPOS).
* Supports the Classic WooCommerce Checkout.

Important: Native WooCommerce Cart and Checkout Block support is not included in version 2.0.0. If WayaQuick does not appear on a block-based checkout, use the Classic Checkout shortcode `[woocommerce_checkout]`.

== Requirements ==

* WordPress 5.8 or later.
* PHP 7.4 or later.
* WooCommerce 6.0 or later.
* A WayaQuick merchant account.
* Your WayaQuick Merchant ID.
* Your WayaQuick Public Key.
* Your WayaQuick Secret Key.
* Nigerian Naira (NGN) configured as the WooCommerce store currency for the current integration.

== Installation ==

1. Install **WayaQuick for WooCommerce** from Plugins > Add New, or upload the `wayaquick-for-woocommerce.zip` package through Plugins > Add New > Upload Plugin.
2. Activate the plugin through the Plugins menu.
3. Go to WooCommerce > Settings > Payments.
4. Enable WayaQuick and click **Manage**.
5. Enter your Merchant ID, Public Key, Secret Key and Webhook Secret, then choose Test or Production.
6. Save your changes.
7. Copy the Webhook URL shown on the settings screen into your WayaQuick dashboard under Settings > API Keys and Webhooks.

For Test transactions, use your test credentials. For Production transactions, use your production credentials.

== External services ==

This plugin connects to WayaQuick services to initiate and verify payments.

When a customer chooses WayaQuick, the plugin sends payment information to the WayaQuick Merchant API. This can include the merchant ID, customer first and last name, billing email, billing phone number, order amount, currency, transaction reference, WooCommerce order ID and return URL.

The customer is then redirected to the WayaQuick hosted checkout at https://pay.wayaquick.com to complete the payment. The plugin also queries WayaQuick for payment status and accepts signed webhook notifications from WayaQuick.

WayaQuick service information: https://www.wayaquick.com/
WayaQuick terms: https://www.wayaquick.com/terms

By enabling and using this payment method, merchants and customers send the payment-related data described above to WayaQuick as necessary to process and verify the transaction.

== Frequently Asked Questions ==

= What do I need to use the plugin? =

You need WordPress, WooCommerce, a WayaQuick merchant account, and your WayaQuick Merchant ID, Public Key and Secret Key.

= Where does the customer enter card or other payment details? =

The customer selects WayaQuick on the WooCommerce checkout page and is redirected to the WayaQuick hosted checkout to complete payment using the payment methods enabled for the merchant.

= Does the plugin expose my Secret Key to the browser? =

No. The Secret Key is used server-side when the WordPress site communicates with WayaQuick. The Public Key may be sent to the hosted checkout because it is not a secret credential.

= Why is WayaQuick not visible on my Checkout Block? =

Version 2.0.0 supports the Classic WooCommerce Checkout. If the gateway does not appear on a block-based checkout, edit the Checkout page and use the `[woocommerce_checkout]` shortcode.

= Can WayaQuick send webhooks to localhost? =

No. Your webhook endpoint must be publicly reachable. For local development, use a secure HTTPS tunnel such as ngrok or Cloudflare Tunnel.

== Changelog ==

= 2.0.0 =
* Rebuilt on the WayaQuick Merchant API v2.
* Added WayaQuick hosted checkout redirection.
* Added merchant Public Key support for hosted checkout initialization.
* Added signed webhook verification and replay protection.
* Added payment status reconciliation when the customer returns.
* Added Test and Production environment configuration.
* Added HPOS compatibility declaration.

