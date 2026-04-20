# Hidoom Gift Cards for WooCommerce

A WooCommerce plugin that turns any product in the **Gift Card** category into a digital gift card.

## What it does

1. Adds a **gift card form** (receiver name, receiver email, sender name, optional message) on product pages in the `gift-card` category.
2. Validates the form before add-to-cart and carries the values all the way through cart → checkout → order.
3. On successful payment (`processing` / `completed`), creates a **unique one-time-use WooCommerce coupon** whose code includes the receiver's name and whose value equals the product price.
4. Emails the receiver with the coupon code via the **Gmail API** (OAuth 2.0 refresh-token flow). Falls back to `wp_mail()` if Gmail is not configured.
5. Provides a dashboard page at **WooCommerce → Gift Cards** to enter the Gmail API credentials, email subject, coupon prefix, and expiry.

## Message template

> Congratulations **{receiver name}** you got gifted by **{coupon amount}** from **{sender name}** and the coupon is one-time purchase and it is **{coupon code}**

Every email ends with the signature: **هيدوم. هيدوم معاك**

## Setup

1. Upload the `woo-gift-card/` folder to `wp-content/plugins/` and activate it.
2. Activation will automatically create a product category with slug `gift-card`.
3. Create 5 (or any number of) Simple WooCommerce products — one per gift-card value (e.g. 10, 25, 50, 100, 200) — and assign each to the **Gift Card** category.
4. Go to **WooCommerce → Gift Cards** and enter your Gmail API credentials.

### Getting a Gmail API refresh token

1. Open https://console.cloud.google.com/ and create a project.
2. Enable the **Gmail API**.
3. Create an **OAuth 2.0 Client ID** of type "Web application".
4. Add `https://developers.google.com/oauthplayground` as an authorised redirect URI.
5. Visit https://developers.google.com/oauthplayground, click the gear icon, tick **Use your own OAuth credentials**, and paste your Client ID and Secret.
6. In step 1, enter the scope `https://www.googleapis.com/auth/gmail.send` and click **Authorize APIs**.
7. Sign in with the Gmail account that will send the gift cards.
8. In step 2, click **Exchange authorization code for tokens** — copy the **Refresh token**.
9. Paste the Client ID, Client Secret, Refresh Token, and From email into the plugin settings page.
10. Click **Send Test** to confirm sending works.

## How coupons are generated

Each coupon is:
- `{PREFIX}-{RECEIVER-NAME}-{5 RANDOM CHARS}` (e.g. `GIFT-AHMED-7H2KQ`)
- `fixed_cart` discount equal to the product price
- Limited to **1 use total** and **1 use per customer** (one-time purchase)
- Optional expiry (default 365 days; configurable, `0` = never)

## Files

```
woo-gift-card/
├── woo-gift-card.php                 # plugin bootstrap
├── includes/
│   ├── class-hidoom-gc-settings.php  # options storage
│   ├── class-hidoom-gc-form.php      # product page form + validation
│   ├── class-hidoom-gc-cart.php      # cart/order-item carry-through
│   ├── class-hidoom-gc-coupon.php    # coupon creator
│   ├── class-hidoom-gc-gmail.php     # Gmail API client
│   ├── class-hidoom-gc-order.php     # order hook → coupon + email
│   └── class-hidoom-gc-admin.php     # dashboard settings page
└── assets/css/gift-card.css
```

## Notes / limits

- The plugin triggers on `woocommerce_order_status_processing`, `woocommerce_order_status_completed`, and `woocommerce_payment_complete`, and is idempotent: `_hidoom_gc_processed=yes` on the order prevents double emailing.
- Requires WooCommerce.
- Gmail API access tokens are cached in `wp_options` under `hidoom_gc_gmail_access_token` and refreshed automatically before expiry.

هيدوم. هيدوم معاك
