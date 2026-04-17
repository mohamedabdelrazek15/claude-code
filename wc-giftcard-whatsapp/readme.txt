=== WooCommerce Gift Card → WhatsApp ===
Contributors: yourstore
Tags: woocommerce, gift card, whatsapp, coupon, store credit
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.3
Stable tag: 1.0.0

Turn any WooCommerce product into a gift card. After the buyer pays online,
the recipient receives a WhatsApp message containing a personal coupon that
works as store credit (balance-tracked across multiple orders).

== Description ==

Features:

* Flag any simple product as a "gift card" from the product data panel.
* Buyer enters **Recipient name**, **Recipient WhatsApp number**,
  **Sender name**, and an optional **Personal message** directly on the
  product page.
* **Cash on Delivery is automatically hidden** at checkout when a gift card
  is in the cart — the buyer must pay online.
* On payment completion, a unique coupon code is generated (e.g.
  `GC-1043-A7B2`).
* The coupon is a **balance-tracked fixed_cart coupon** — it can be used
  across multiple orders until the balance reaches zero. Cancellations and
  refunds restore the balance.
* The coupon is delivered to the recipient via the **WhatsApp Cloud API**
  using a pre-approved template.
* Failed sends are retried automatically (up to 3 times) via Action
  Scheduler, and there is an admin **Resend WhatsApp gift card** order
  action.
* Built-in test mode to route all outbound messages to a test number.

== Installation ==

1. Upload the `wc-giftcard-whatsapp` folder to `wp-content/plugins/` and
   activate it.
2. Go to **WooCommerce → Settings → Gift Cards** and fill in:
   - Meta Access Token
   - Phone Number ID
   - Template name (default: `gift_card_notification`)
   - Template language (default: `en_US`)
3. Edit one of your gift-card products → *General* tab → tick **This is a
   gift card** → save.

== Meta WhatsApp template ==

You must create and get approval for a template in Meta Business Manager
before messages can be sent. Suggested template:

Name: `gift_card_notification`
Category: Marketing (or Utility)
Language: `en_US` (or whatever you configure)
Body:

> Hi {{1}}, you've received a gift card worth {{2}} from {{3}}. Use code
> *{{4}}* at checkout on our store. {{5}}

Variables sent by the plugin, in order:

1. Recipient name
2. Amount (formatted)
3. Sender name
4. Coupon code
5. Personal message (a single space if the buyer left it empty)

== Frequently Asked Questions ==

= Can the same coupon be used more than once? =

Yes. It acts like store credit: the coupon remains valid until its balance
is fully spent. When the balance reaches zero the coupon is expired.

= What happens if a redeeming order is refunded? =

The used amount is added back to the gift card balance and the coupon's
expiry is cleared.

= Does it work with Cash on Delivery? =

No — this plugin intentionally hides COD for carts that contain a gift
card, because the recipient message must only be sent after confirmed
payment.

== Changelog ==

= 1.0.0 =
* Initial release.
