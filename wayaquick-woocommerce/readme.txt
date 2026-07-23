=== WayaQuick WooCommerce Gateway ===
Contributors: wayaquick
Tags: woocommerce, payment gateway, wayaquick, nigeria, card, ussd
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
WC requires at least: 6.0
Stable tag: 2.0.0
License: GPLv2 or later

Accept card, bank transfer, USSD and wallet payments on WooCommerce through WayaQuick (Merchant API v2).

== Description ==

WayaQuick WooCommerce Gateway lets your store collect payments through WayaQuick. At checkout the
customer is redirected to the WayaQuick hosted payment page. The order is completed from a signed
webhook (the authoritative signal), with the status endpoint as a safety net when the customer
returns.

Features:

* Card, bank transfer, USSD and WayaQuick wallet
* Hosted, PCI-friendly checkout (no card data touches your server)
* HMAC-SHA256 signed webhooks with a 5-minute replay window
* Test and Production environments
* Compatible with WooCommerce High-Performance Order Storage (HPOS)

== Installation ==

1. Upload the `wayaquick-woocommerce` folder to `/wp-content/plugins/`, or install the zip via
   Plugins, Add New, Upload Plugin.
2. Activate the plugin through the Plugins menu.
3. Go to WooCommerce, Settings, Payments, and enable WayaQuick.
4. Enter your Merchant ID, Secret Key and Webhook Secret, then choose Test or Production.
5. Copy the Webhook URL shown on the settings screen into your WayaQuick dashboard under
   Settings, API Keys and Webhooks.

== Changelog ==

= 2.0.0 =
* Rebuilt on the WayaQuick Merchant API v2 (Bearer auth, /merchant-middleware/api/v2 paths).
* Signed webhook verification and status-endpoint reconciliation.
* HPOS compatibility.


== Frequently Asked Questions ==

= What Do I Need To Use The Plugin =

1.	You need to have Woocommerce plugin installed and activated on your WordPress site.
2.	You need to register an account [here](https://www.wayaquick.ng/)



