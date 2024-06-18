/*
* Plugin Name: Woocommerce Sale Notifications for Discord
* Plugin URI: https://github.com/Cral-Cactus/woocommerce-discord-sale-notifications
* Description: Sends a notification to a Discord channel when a sale is made on Woocommerce.
* Version: 1.0
* Author: Cral_Cactus
* Author URI: https://github.com/Cral-Cactus
* Requires Plugins: woocommerce
* Requires at least: 6.2
* WC requires at least: 8.5
* WC tested up to: 8.9
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load the plugin
add_action( 'plugins_loaded', 'discord_logs_init' );

function discord_logs_init() {
    // Check if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    // Load the plugin
    require_once 'includes/class-discord-logs.php';
    $discord_logs = new Discord_Logs();
    $discord_logs->init();
}

// Add settings link on plugin page
function discord_logs_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=integration&section=discord_logs">Settings</a>';
    array_push( $links, $settings_link );
    return $links;
}