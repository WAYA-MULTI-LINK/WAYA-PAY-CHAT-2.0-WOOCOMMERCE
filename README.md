# WayaQuick WooCommerce Payment Gateway

WayaQuick WooCommerce Payment Gateway allows merchants to accept secure online payments on their WooCommerce stores through the WayaQuick hosted checkout.

Customers select **WayaQuick** during WooCommerce checkout and are securely redirected to the WayaQuick hosted payment page to complete their payment using the payment methods enabled for the merchant.

## Description

WayaQuick is a payment platform that enables businesses to accept and manage digital payments.

This plugin integrates WayaQuick with WooCommerce and allows a WooCommerce store to initiate payments through the WayaQuick Merchant API.

After selecting WayaQuick during checkout, the customer is redirected to:

`https://pay.wayaquick.com`

The hosted checkout then presents the payment methods available for that merchant.

Depending on the merchant's WayaQuick configuration, payment options may include:

- Visa
- Mastercard
- Verve
- Bank Transfer
- USSD
- Other payment channels enabled by WayaQuick

For more information about WayaQuick, visit:

https://wayaquick.com

---

## How It Works

The payment flow is:

1. A customer adds products to their WooCommerce cart.
2. The customer proceeds to checkout.
3. The customer selects **WayaQuick** as the payment method.
4. WooCommerce creates the order.
5. The WayaQuick plugin securely initiates the transaction using the WayaQuick Merchant API.
6. WayaQuick returns a hosted checkout URL.
7. The customer is redirected to `pay.wayaquick.com`.
8. The customer selects their preferred payment method and completes payment.
9. WayaQuick redirects the customer back to the merchant website.
10. The payment status is verified and the WooCommerce order is updated accordingly.
11. WayaQuick webhooks can also notify the store when the transaction status changes.

---

## Requirements

Before installing the plugin, make sure you have:

- WordPress installed.
- WooCommerce installed and activated.
- A WayaQuick merchant account.
- Your WayaQuick Merchant ID.
- Your WayaQuick Public Key.
- Your WayaQuick Secret Key.
- PHP 8.0 or later recommended.
- NGN configured as your WooCommerce store currency.

> Currently, this plugin supports payments in Nigerian Naira (NGN).

---

## Installation

### Manual Installation

Until the plugin is published in the official WordPress Plugin Directory, install it manually.

1. Download `wayaquick-woocommerce.zip`.

2. Log in to your WordPress Admin Dashboard.

3. Navigate to:

   `Plugins → Add Plugin`

4. Click **Upload Plugin**.

5. Select:

   `wayaquick-woocommerce.zip`

6. Click **Install Now**.

7. After installation completes, click **Activate Plugin**.

8. Make sure WooCommerce is also installed and activated.

---

## Configure WayaQuick

After activating the plugin, navigate to:

`WooCommerce → Settings → Payments`

Find **WayaQuick** and click **Manage**.

Configure the following settings.

### Enable / Disable

Enable WayaQuick as a WooCommerce payment method.

### Title

Controls the payment method name displayed to customers during checkout.

Recommended:

`WayaQuick`

### Description

Controls the description displayed under WayaQuick during checkout.

Example:

`Pay securely with card, transfer, USSD and other available WayaQuick payment methods.`

### Environment

Select the environment the plugin should use.

Available environments:

- Test / Staging
- Production / Live

Use **Test / Staging** while developing or testing the integration.

Switch to **Production / Live** only when you are ready to accept real payments.

### Merchant ID

Enter your WayaQuick Merchant ID.

Example:

`MER_XXXXXXXX`

### Public Key

Enter the WayaQuick public key associated with the selected environment.

Use your test public key when using the Test / Staging environment and your production public key when using the Production environment.

The public key is passed to the WayaQuick hosted checkout so that payment operations such as card encryption can be completed correctly.

### Secret Key

Enter your WayaQuick merchant secret key.

For Test / Staging, use your:

`merchantSecretTestKey`

For Production / Live, use your:

`merchantProductionSecretKey`

> Your secret key must remain private and must never be exposed in frontend JavaScript, URLs, or client-side storage.

### Webhook Secret

WayaQuick webhook signatures are verified using the merchant secret for the corresponding environment.

For Test / Staging, use your:

`merchantSecretTestKey`

For Production / Live, use your:

`merchantProductionSecretKey`

The webhook signature is verified using HMAC-SHA256 before the plugin acts on a webhook event.

### API Base URL

The API Base URL field is intended for advanced configuration.

Unless instructed otherwise by WayaQuick, leave this field at its default value.

After entering your credentials, click:

**Save changes**

---

## WooCommerce Checkout Compatibility

The current version of the plugin integrates with the **Classic WooCommerce Checkout**.

If your WooCommerce store is using the newer Checkout Block and WayaQuick does not appear under payment methods, edit your Checkout page and use the WooCommerce checkout shortcode:

To configure this:

Go to Pages → Checkout.
Edit the Checkout page.
Remove the WooCommerce Checkout Block.
Add a Shortcode block.

Enter:

[woocommerce_checkout]

Save or publish the page.

Native WooCommerce Checkout Block support is planned for a future version.

Testing Payments

Before accepting live payments, configure the plugin to use the Test / Staging environment.

Create a test WooCommerce product and proceed through checkout.

At checkout you should see:

WayaQuick

After selecting WayaQuick and placing the order, the customer should be redirected to a URL similar to:

https://pay.wayaquick.com/?_tranId=TRANSACTION_REFERENCE&merchantPublicKey=YOUR_PUBLIC_KEY

The hosted WayaQuick checkout will then display the payment methods enabled for the merchant.

Use WayaQuick test credentials and supported test payment details when testing.

Webhooks

WayaQuick can send payment status updates to the WooCommerce store through webhooks.

Webhook events may include:

SUCCESSFUL
PARTIAL
FAILED

The plugin verifies webhook requests using the following signature mechanism:

Base64(
    HMAC-SHA256(
        "<X-Waya-Timestamp>.<raw request body>",
        merchant_secret
    )
)

The secret used depends on the transaction environment:

Test

merchantSecretTestKey

Production

merchantProductionSecretKey

The plugin should only update or fulfil an order after a valid webhook has been verified.

Local Development

WayaQuick cannot send webhooks directly to:

localhost

If you are testing the plugin using LocalWP or another local WordPress environment, expose the webhook endpoint using a secure public HTTPS tunnel such as:

Cloudflare Tunnel
ngrok

Your public webhook URL must be reachable by WayaQuick servers.

Payment Statuses
SUCCESSFUL

The customer successfully completed payment.

The WooCommerce order can be marked as paid and processed.

PARTIAL

The customer paid less than the expected amount.

This primarily applies to supported bank-transfer flows.

The order should not be fulfilled until the full payment has been confirmed.

FAILED

The payment failed, was declined, or was abandoned.

The WooCommerce order should not be fulfilled.

Security

The plugin follows several important security principles:

Merchant secret keys are used server-side only.
Secret keys must never be exposed through URLs.
Secret keys must never be stored in browser session storage or local storage.
WayaQuick webhook signatures are verified before payment events are processed.
Webhook verification uses the raw HTTP request body.
Payment status should be verified before fulfilling WooCommerce orders.
Duplicate webhook events should not result in duplicate order fulfilment.
Frequently Asked Questions
What do I need to use the plugin?

You need:

A WordPress website.
WooCommerce installed and activated.
A WayaQuick merchant account.
Your WayaQuick Merchant ID.
Your WayaQuick Public Key.
Your WayaQuick Secret Key.
Where do customers enter their card or select USSD or bank transfer?

Customers do not enter payment details directly into the WooCommerce plugin.

They select WayaQuick during WooCommerce checkout and are redirected to the secure WayaQuick hosted checkout at:

https://pay.wayaquick.com

Available payment methods are displayed there.

Is my WayaQuick secret key sent to the customer's browser?

No.

The secret key is used by the WordPress/WooCommerce server when communicating with WayaQuick APIs.

It must never be exposed to the browser.

Why does the hosted checkout need the Public Key?

The merchant public key identifies the merchant during client-side operations performed by the WayaQuick hosted checkout, including card encryption.

Unlike the secret key, the public key can safely be provided to the hosted payment page.

Why does WayaQuick not appear on my checkout page?

The current plugin supports the Classic WooCommerce Checkout.

If your store uses the WooCommerce Checkout Block, replace it with:

[woocommerce_checkout]

Native WooCommerce Checkout Block support will be added in a future release.

Can webhooks work when WordPress is running on localhost?

No.

WayaQuick servers cannot access URLs such as:

http://localhost:10004

Use a publicly accessible HTTPS URL or a development tunnel such as Cloudflare Tunnel or ngrok.

Which currency is currently supported?

The current plugin integration supports:

NGN — Nigerian Naira

Development

The plugin files should be packaged with the following structure:

wayaquick-woocommerce/
├── wayaquick-woocommerce.php
├── includes/
├── assets/
├── readme.txt
└── ...

When distributing the plugin, ZIP the wayaquick-woocommerce directory itself.

The final installation package should be:

wayaquick-woocommerce.zip

Do not ZIP the entire Git repository around the plugin directory.

Changelog
Current Version
Rebranded the WooCommerce payment gateway from WayaPay to WayaQuick.
Added WayaQuick Merchant API integration.
Added Test / Staging and Production environment configuration.
Added Merchant ID configuration.
Added merchant Public Key configuration.
Added Secret Key configuration.
Added webhook signature verification.
Added support for environment-specific webhook secrets.
Added hosted checkout redirection through pay.wayaquick.com.
Added merchant public key to hosted checkout initialization.
Added WooCommerce order payment-status handling.
Added WayaQuick transaction status verification.
Added support for SUCCESSFUL, PARTIAL, and FAILED payment states.
Improved error handling and WooCommerce order notes.
Added NGN currency validation.
Added hosted payment callback handling.
1.0.0
Initial WooCommerce payment gateway release.
Support

For WayaQuick account, API credential, or integration assistance, contact the WayaQuick support team or visit:

https://wayaquick.com