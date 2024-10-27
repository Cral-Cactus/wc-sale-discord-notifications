=== WC Sale Discord Notifications ===
Contributors: cralcactus
Tags: woocommerce, discord, notifications, sales
Requires at least: 6.2
Tested up to: 6.6.2
Version: 1.9
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This plugin notifies a Discord channel of WooCommerce sales. It supports various order statuses, configurable webhook URLs, and message colors.

== Description ==

This plugin sends a notification to a Discord channel whenever a sale is made on WooCommerce. It is highly customizable, allowing notifications for different order statuses and the ability to configure the webhook URL and message colors.

== Features ==

* Sends a Discord notification when a sale is made on WooCommerce.
* Customizable order statuses for notifications.
* Configure different webhook URLs for different order statuses.
* Color-coded notifications based on order status.
* Optionally exclude product images from embeds.

== Requirements ==

* WordPress 6.2 or higher (tested up to 6.6.2)
* WooCommerce 8.5 or higher (tested up to 9.3.3)

== Installation ==

1. Download the plugin from the [GitHub repository](https://github.com/Cral-Cactus/wc-sale-discord-notifications).
2. Upload the plugin files to the `/wp-content/plugins/wc-sale-discord-notifications` directory, or install the plugin through the WordPress plugins screen directly.
3. Activate the plugin through the 'Plugins' screen in WordPress.
4. Navigate to WooCommerce > Discord Notifications to configure the plugin.

== Configuration ==

1. **Webhook URL**: Enter the Discord Webhook URL where notifications will be sent.
2. **Order Status Notifications**: Select the order statuses for which you want to send notifications. You can also specify different webhook URLs and colors for each status.
3. **Disable Product Image in Embed**: Check this option if you wish to omit product images from the embed.

== Usage ==

1. After installing and activating the plugin, go to WooCommerce > Discord Notifications.
2. Configure your Discord Webhook URL and select the order statuses you want to receive notifications for.
3. Save your settings.

Whenever an order is placed, a notification will be sent to the specified Discord channel with details about the order.

== Contributing ==

1. Fork the repository on GitHub.
2. Create a new branch for your feature or bug fix.
3. Commit your changes and push the branch to GitHub.
4. Open a pull request to the main branch.

== Screenshots ==

1. Picture of the plugin dashboard

== Changelog ==

= 2.0 =
* Order status notifications fix and added option to exclude product images from embeds.

= 1.9 =
* Added notifications for changes in order status.

= 1.8 =
* Major changes.

= 1.7 =
* Version update.

= 1.6 =
* Initial release.

== Author ==

[Cral_Cactus](https://github.com/Cral-Cactus)

== Support ==

If you have any questions or need help, feel free to open an issue on the [GitHub repository](https://github.com/Cral-Cactus/wc-sale-discord-notifications).