=== WC Sale Discord Notifications ===
Contributors: cralcactus
Tags: woocommerce, discord, notifications, sales, webhooks, orders
Requires at least: 6.2
Tested up to: 6.8.2
WC requires at least: 8.5
WC tested up to: 10.1.2
Version: 3.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A powerful WooCommerce extension that sends order updates directly to your Discord server. Now with configurable message fields, status-specific webhooks/colors, and built-in duplicate protection.

== Description ==

This plugin sends a Discord notification for WooCommerce order events. It uses native WordPress/WooCommerce APIs and supports WooCommerce Custom Order Tables (v8+). You can choose which order statuses trigger notifications, customize which details are included, set different webhook URLs and embed colors per status, and optionally remove product images from the embed.

== Features ==

* Customizable message fields:
  * Order Status
  * Payment Info
  * Product Lines (names, qty, price)
  * Product Options (add-ons / custom fields)
  * Order Date
  * Billing Info
  * Transaction ID
* Option to disable product image in the embed
* Per-status webhook URL and embed color
* Duplicate-send protection via internal tracking
* Built using native WordPress/WooCommerce APIs
* Compatible with WooCommerce Custom Order Tables (v8+)

== Requirements ==

* WordPress 6.2 or higher (tested up to 6.8.2)
* WooCommerce 8.5 or higher (tested up to 10.1.2)

== Installation ==

1. Download this plugin or clone the repo into `/wp-content/plugins/wc-sale-discord-notifications`.
2. Activate the plugin via **Plugins → Installed Plugins**.
3. Go to **WooCommerce → Discord Notifications**.
4. Configure your settings.

== Configuration ==

1. **Webhook URL**  
   Enter your Discord Webhook URL (from your Discord server settings).

2. **Order Status Notifications**  
   Choose which order statuses should trigger notifications. You can also:
   * Add different webhook URLs per status
   * Choose unique embed colors

3. **Information to Include**  
   Select which fields should appear in the Discord embed (status, payment info, items, custom product fields, order date, billing info, transaction ID).

4. **Disable Product Image**  
   Toggle this to prevent the product image from appearing in the embed.

== Duplicate Protection ==

To prevent duplicate Discord messages (for example, if the thank-you page is refreshed), the plugin keeps track of sent events. Each entry logs `order_id|event_type` (e.g. `1655|new`). Before sending, the plugin checks whether that combination has already been sent and skips if so. This ensures each notification is only sent **once per order event**.

== Usage ==

1. After installing and activating the plugin, go to **WooCommerce → Discord Notifications**.
2. Paste your Discord Webhook URL and select which statuses should send notifications.
3. Choose which fields to include and whether to show product images.
4. Save your settings.

== Screenshots ==

1. Settings page under WooCommerce → Discord Notifications

== Changelog ==

= 3.0 =
* Updated plugin logo.

= 2.3 =
* Added support for custom product fields in Discord notifications.
* New "Custom Fields" toggle in settings—when enabled, product-level custom fields (from add-ons/APF) are included in order item details.

= 2.2 =
* Implemented per-status duplicate protection using order meta instead of a global flag.
* Removed redundant duplicate-check logic and double log writes.
* Added sanitization callbacks for all plugin options to improve data safety.
* Made Discord webhook POST asynchronous (`blocking => false`) with basic error handling.
* Improved status change hook to only trigger on selected statuses.
* Enhanced embed field building with formatted totals, safe hex color handling, and image fallback.
* Updated “Tested up to” and “WC tested up to” versions.

= 2.1 =
* Admin setting to choose what fields to include in Discord messages.
* Added protection against duplicate notifications using log tracking.
* Per-status webhook URL support.
* Full compatibility with WooCommerce 8+ (custom order tables).

= 2.0 =
* Added option to exclude product image from embeds.

= 1.9 =
* Added notifications for changes in order status.

= 1.8 and below =
* Initial features and webhook sending.

== Author ==

[Cral_Cactus](https://github.com/Cral-Cactus)

== Support ==

Found a bug or have a suggestion? Open an issue on the GitHub repo: https://github.com/Cral-Cactus/wc-sale-discord-notifications/issues