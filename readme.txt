=== Subscribely Plans for WooCommerce ===
Contributors: zamzamcoders
Tags: woocommerce, subscriptions, recurring payments, subscription products, memberships
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sell WooCommerce products on recurring subscription plans, alongside or instead of one-time purchases.

== Description ==

Subscribely Plans for WooCommerce adds subscription plans to WooCommerce products. Each product can offer one or more plans with their own recurring price and billing schedule, and you choose whether customers can also buy the product once.

**Features:**

* Subscription plans per product: recurring price, billing every 1–6 days, weeks, months or years, and an optional fixed number of payments
* Sell as "Subscription only" or "One-time purchase or subscription"
* Plan picker on the product page, with the recurring price shown on shop and product pages
* Works with the classic and the block-based cart and checkout
* Customers must have an account to subscribe; guest checkout is turned off while the cart holds a subscription
* A subscription record is created for every subscribed product at checkout and starts when the order is paid
* WooCommerce → Subscriptions screen with status filters, search and sorting
* Subscriptions box on the order screen
* Compatible with High-Performance Order Storage (HPOS)

**Coming in later versions:** renewal orders and invoices, customer subscription management in My Account, and subscription emails.

== Installation ==

1. Upload the `subscribely-plans-for-woocommerce` folder to `/wp-content/plugins/`, or install the plugin from the Plugins screen.
2. Activate it. WooCommerce must be active.
3. Edit a simple product, open the **Subscription** tab, tick **Sell on subscription** and add at least one plan.

== Frequently Asked Questions ==

= Which product types are supported? =

Simple products, including virtual and downloadable ones.

= Are coupons applied to the recurring price? =

No. A coupon discounts the first order only; the recurring amount stored on the subscription is the plan price.

= Does it charge renewals automatically? =

Not yet. This version records subscriptions and their next payment date. Renewal processing comes in a later version.

== Changelog ==

= 1.0.0 =
* Initial release.
