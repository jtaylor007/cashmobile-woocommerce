=== CashMobile Gateway for WooCommerce ===
Contributors: cashmobile
Tags: woocommerce, payment gateway, cashmobile, haiti, moncash
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments on your WooCommerce store through CashMobile.

== Description ==

Your buyer chooses CashMobile at the checkout, pays on a CashMobile page, and
comes back to your store. The order is marked as paid only once CashMobile
confirms the payment, server to server.

= What you need =

* A CashMobile merchant account with API access switched on
* A Client ID and a Secret ID, from the API page of your CashMobile dashboard
* A store currency that CashMobile accepts, and a CashMobile wallet in that
  same currency

The list of accepted currencies is published at
https://cashmobile.net/developer/currencies

= Getting started =

1. Install and activate the plugin.
2. Go to WooCommerce → Settings → Payments → CashMobile.
3. Leave the mode on **Sandbox** and paste your **sandbox** keys.
4. Place a test order. Sign in on the payment page with one of the test payers
   listed at https://cashmobile.net/developer/sandbox
5. Test a refused payment too, with the test payer meant for it, and check that
   your order does **not** become paid.
6. Switch the mode to **Live** and replace the keys with your live pair.

A key only works on the base URL of its own mode. Change the mode and the keys
together, or the gateway will refuse the request.

== Frequently Asked Questions ==

= The payment method does not show at the checkout =

The gateway hides itself until the address, the Client ID and the Secret ID are
all filled in. Check WooCommerce → Settings → Payments → CashMobile.

= A payment went through but the order is still pending =

Open WooCommerce → Status → Logs and read the `cashmobile` log. If it says the
status could not be read, the plugin deliberately left the order alone rather
than cancel an order that may have been paid. Confirm it in your CashMobile
dashboard, then mark the order as paid by hand.

= Does the plugin trust the return redirect? =

No, and this matters. The redirect back to your store is not signed and could be
forged. The plugin asks CashMobile directly whether the payment was made, and
completes the order only on that answer.

== Upgrade Notice ==

= 2.0.0 =
Important security fix. Earlier versions marked an order as completed on the
strength of the return redirect alone, without asking CashMobile whether the
payment had succeeded — so a refused payment completed the order and released
the goods. Upgrade before taking any further orders.

== Changelog ==

= 2.0.0 =
* Security: the order is completed only after a server-to-server check of the
  payment status. Previously the return redirect alone was enough, which meant a
  refused payment completed the order.
* Security: the return URL now carries a secret generated for that one order,
  instead of the API access token. A credential no longer travels through the
  browser, the referrer header or the store's log files.
* Security: credentials and API responses are no longer written to the log.
* Fix: `payment_complete()` is used instead of forcing the status to completed,
  so stock is reduced and a physical order goes to "processing" rather than
  straight to "shipped".
* Fix: a return that arrives twice no longer reduces stock twice.
* Fix: when the gateway cannot be reached, the order is left untouched rather
  than cancelled — the payment may well have been made.
* Change: the mode (Sandbox or Live) is chosen from a list, and the base URL is
  built from it. A mismatched key and URL is no longer possible by typo.
* Change: HTTP calls go through WordPress instead of a bundled copy of Guzzle.
  The plugin is 45 KB rather than 1.4 MB and no longer clashes with other
  plugins' dependencies.
* Change: the gateway refuses to switch on while its settings are incomplete.
* Added: declared compatible with WooCommerce High-Performance Order Storage.
