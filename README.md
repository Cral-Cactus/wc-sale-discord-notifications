# WC Sale Discord Notifications

[![GitHub release](https://img.shields.io/github/release/Cral-Cactus/wc-sale-discord-notifications.svg)](https://github.com/Cral-Cactus/wc-sale-discord-notifications/releases)
[![GitHub issues](https://img.shields.io/github/issues/Cral-Cactus/wc-sale-discord-notifications.svg)](https://github.com/Cral-Cactus/wc-sale-discord-notifications/issues/)

> A powerful WooCommerce extension that sends order updates directly to your Discord server. Now with configurable message fields, status-specific webhooks, and built-in duplicate protection via logging.

---

## ✨ Features

- ✅ Customizable message fields:
  - Order Status
  - Payment Info
  - Product List
  - Order Date
  - Billing Info
  - Transaction ID
- 🖼️ Optionally disable product image in embed
- 🎯 Custom webhook & embed color per order status
- 🔒 Prevent duplicate Discord notifications using internal log tracking
- ⚙️ Built using native WordPress/WooCommerce APIs
- 🧠 Compatible with WooCommerce Custom Order Tables (v8+)

---

## 🧰 Requirements

- WordPress 6.2 or higher (tested up to 6.6.2)
- WooCommerce 8.5 or higher (tested up to 9.3.3)

---

## 🔧 Installation

1. Download this plugin or clone the repo into `/wp-content/plugins/wc-sale-discord-notifications`
2. Activate the plugin via **Plugins > Installed Plugins**
3. Navigate to **WooCommerce > Discord Notifications**
4. Configure your settings

---

## ⚙️ Configuration

1. **Webhook URL**  
   Enter your Discord Webhook URL (from your Discord server settings)

2. **Order Status Notifications**  
   Choose which order statuses should trigger notifications. You can also:
   - Add different webhook URLs per status
   - Choose unique embed colors

3. **Information to Include**  
   Select which fields should appear in the Discord embed.

4. **Disable Product Image**  
   Toggle to prevent product image from appearing in the embed.

---

## 🔒 Duplicate Protection

To prevent duplicate Discord messages (e.g. when a user refreshes the thank-you page), a local file is used: discord_notification_log.txt

Each entry logs `order_id|event_type`, e.g. `1655 |new`.  
Before sending a message, the plugin checks if this log already contains that line.  
If it does, it skips sending.

This ensures each notification is only sent **once per order event**.

---

## 🧪 Development

Built and maintained by:

- [Cral_Cactus](https://github.com/Cral-Cactus)
- [Dex (ComFoo)](https://github.com/Dextiz)

Pull requests welcome!

---

## 📜 Changelog

### 2.1
- Admin setting: Choose what fields to include in Discord messages
- Added protection against duplicate notifications using log file
- Per-status webhook URL
- Fully compatible with WooCommerce 8+ (custom order tables)

### 2.0
- Added support for excluding product image

### 1.9
- Added notifications for changes in order status

### 1.8 and below
- Initial features and webhook sending

---

## 💬 Support

Found a bug? Have a suggestion?  
Open an issue on the [GitHub repo](https://github.com/Cral-Cactus/wc-sale-discord-notifications/issues).


