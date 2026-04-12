# WC Sale Discord Notifications

[![GitHub release](https://img.shields.io/github/release/Cral-Cactus/wc-sale-discord-notifications.svg)](https://github.com/Cral-Cactus/wc-sale-discord-notifications/releases)
[![GitHub issues](https://img.shields.io/github/issues/Cral-Cactus/wc-sale-discord-notifications.svg)](https://github.com/Cral-Cactus/wc-sale-discord-notifications/issues/)

> A powerful WooCommerce extension that sends order updates directly to your Discord server. Configurable message fields, status-specific webhooks, and built-in duplicate protection via order meta.

---

## ✨ Features

- ✅ Customizable message fields:
  - Order Status
  - Payment Info
  - Product Lines (names, qty, price)
  - Product Options (add-ons / custom fields)
  - Order Date
  - Billing Info
  - New or returning customer
  - Shipping method (freight)
  - Transaction ID
  - Order Notes (customer and/or internal)
- ⏳ Initiating payment notification – get notified when a customer begins the payment process (pending)
- 📝 Customer notes only – optionally exclude internal/admin notes from Order Notes
- 🖼️ Optionally disable product image in embed
- 🎯 Custom webhook & embed color per order status
- 🔒 Duplicate protection via order meta (120s deduplication)
- 📏 Automatic embed size trimming (Discord 6000 char limit)
- ⚙️ Built using native WordPress/WooCommerce APIs
- 🧠 Compatible with WooCommerce Custom Order Tables (HPOS)
- 📦 Subscription-aware titles when WooCommerce Subscriptions is active (New subscription, Subscription Renewal)
- 🚚 Optional shipping method field (chosen freight and price) in the embed
- ↕️ **Embed field order** – drag-and-drop in settings to arrange fields in Discord (including Order ID)
- 👤 Optional **New or returning customer** field (based on prior qualifying orders)

---

## 🧰 Requirements

- WordPress 6.2 or higher (tested up to 6.9.4)
- WooCommerce 8.5 or higher (tested up to 10.6.1)
- PHP 8.0 or higher

---

## 🔧 Installation

1. Download this plugin or clone the repo into `/wp-content/plugins/wc-sale-discord-notifications`
2. Activate the plugin via **Plugins > Installed Plugins**
3. Navigate to **WooCommerce > Discord Notifications**
4. Configure your settings

---

## ⚙️ Configuration

1. **Webhook URL**  
   Enter your Discord Webhook URL (from your Discord server settings).

2. **Order Status Notifications**  
   Choose which order statuses should trigger notifications. You can also:
   - Add different webhook URLs per status
   - Choose unique embed colors

3. **Embed Fields**  
   Select which fields should appear in the Discord embed (status, payment, product, product meta, creation date, billing, new/returning customer, shipping method, transaction ID, order notes).

4. **Embed field order**  
   Drag rows to set the order fields appear in Discord. Fields disabled under Embed Fields are still omitted when sending.

5. **Customer notes only**  
   When Order Notes is included, show only customer notes (exclude internal/admin notes).

6. **Disable Product Image**  
   Toggle to prevent product image from appearing in the embed.

7. **Send notification for Initiating payments**  
   When enabled, sends a distinct "⏳ Initiating payment" notification for pending orders. When payment completes, sends "🎉 New Order!" for processing.

8. **Debug: Force blocking HTTP requests**  
   Enable for troubleshooting (may slow down checkout).

---

## 🔒 Duplicate Protection

To prevent duplicate Discord messages (e.g. when a user refreshes the thank-you page), the plugin stores sent-event metadata on each order (`_discord_sent_*`). Initiating, new, and update notifications all use 120-second time-based deduplication. Before sending, the plugin checks whether that event was already sent within the last 120 seconds and skips if so.

This ensures each notification is only sent **once per order event**.

---

## 🧪 Development

Built and maintained by:

**Author:**
- [Cral_Cactus](https://github.com/Cral-Cactus)

**Contributors:**
- [Dex (ComFoo)](https://github.com/Dextiz)

Pull requests welcome!

---

## 📜 Changelog

### 3.2.0
- Optional embed field: shipping method (chosen freight) with tax-inclusive price per shipping line
- **Embed field order** – drag-and-drop in settings to arrange Discord embed fields (filter: `wc_sale_discord_field_order`)
- Stable `internal_id` on each field so embed trimming works with translated field titles
- Optional embed field **New or returning customer** (prior orders with `completed` / `processing` / `on-hold`, excluding current order). Filters: `wc_sale_discord_returning_customer_order_statuses`, `wc_sale_discord_customer_type_label`

### 3.1.2
- Subscription-aware embed titles when WooCommerce Subscriptions is active (New subscription, Subscription Renewal)
- HTML entity decoding for price display so currency symbols render correctly in Discord (e.g. kr, €, £)
- Filter `wc_sale_discord_embed_title` for customizing embed titles

### 3.1.1
- Customer notes only toggle – exclude internal/admin notes when Order Notes is included
- Automatic embed size trimming to fit Discord’s 6000 character limit
- UTF-8 safe truncation for field values (mb_substr when available)
- Webhook error logging via WooCommerce logger
- Prefer `WC_Order::get_customer_order_notes()` when available (WC 9.2+)
- Code quality: docblocks, type hints, removed redundant logic

### 3.1
- Notification for Initiating Payment (when order is placed with pending status, before payment completes)
- Embedded fields for Order Notes
- When Initiating Payments is enabled: sends "⏳ Initiating payment" for pending orders, then "🎉 New Order!" when payment completes (processing)

### 3.0
- Updated plugin logo

### 2.3
- Add support for custom product fields in Discord notifications
- New toggle "Custom Fields" in settings (when enabled, product-level custom fields from addons/APF are included in order item details)

### 2.2
- Implemented per-status duplicate protection using order meta instead of global flag
- Removed redundant duplicate-check logic and double log writes
- Added sanitization callbacks for all plugin options to improve data safety
- Made Discord webhook POST asynchronous (blocking => false) with basic error handling
- Improved status change hook to only trigger on selected statuses
- Enhanced embed field building with formatted totals, safe hex color handling, and image fallback
- Updated "Tested up to" and "WC tested up to" versions

### 2.1
- Admin setting: Choose what fields to include in Discord messages
- Added protection against duplicate notifications using order meta
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
