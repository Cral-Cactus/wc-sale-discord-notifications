<?php
/**
* Plugin Name: WooCommerce Sale Notifications for Discord
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

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class WC_Discord_Sale_Notifications {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('woocommerce_thankyou', array($this, 'send_discord_notification'));
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Discord Notifications', 'wc-discord-notifications'),
            __('Discord Notifications', 'wc-discord-notifications'),
            'manage_options',
            'wc-discord-notifications',
            array($this, 'notification_settings_page')
        );
    }

    public function register_settings() {
        register_setting('wc_discord_notifications', 'discord_webhook_url');

        add_settings_section(
            'wc_discord_notifications_section',
            __('Discord Webhook Settings', 'wc-discord-notifications'),
            null,
            'wc_discord_notifications'
        );

        add_settings_field(
            'discord_webhook_url',
            __('Discord Webhook URL', 'wc-discord-notifications'),
            array($this, 'discord_webhook_url_callback'),
            'wc_discord_notifications',
            'wc_discord_notifications_section'
        );
    }

    public function discord_webhook_url_callback() {
        $webhook_url = get_option('discord_webhook_url');
        echo '<input type="text" name="discord_webhook_url" value="' . esc_attr($webhook_url) . '" size="50" />';
    }

    public function notification_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Discord Sale Notifications', 'wc-discord-notifications'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_discord_notifications');
                do_settings_sections('wc_discord_notifications');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function send_discord_notification($order_id) {
        $webhook_url = get_option('discord_webhook_url');
        if (!$webhook_url) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $order_data = $order->get_data();
        $order_total = $order_data['total'];
        $order_currency = $order_data['currency'];
        $order_date = $order_data['date_created']->date('Y-m-d H:i:s');
        $order_number = $order_data['id'];
        $order_items = $order->get_items();
        $items_list = '';

        foreach ($order_items as $item) {
            $items_list .= $item->get_name() . ', ';
        }
        $items_list = rtrim($items_list, ', ');

        $message = "🎉 New sale! \n\n**Order Number:** $order_number\n**Order Date:** $order_date\n**Order Total:** $order_total $order_currency\n**Items:** $items_list";

        $this->send_to_discord($webhook_url, $message);
    }

    private function send_to_discord($webhook_url, $message) {
        $data = json_encode(array("content" => $message));

        $args = array(
            'body'        => $data,
            'headers'     => array('Content-Type' => 'application/json'),
            'timeout'     => 60,
        );

        wp_remote_post($webhook_url, $args);
    }
}

// Initialize the plugin
new WC_Discord_Sale_Notifications();