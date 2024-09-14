<?php
/**
 * Plugin Name: WC Sale Discord Notifications
 * Plugin URI: https://github.com/Cral-Cactus/wc-sale-discord-notifications
 * Description: Sends a notification to a Discord channel when a sale is made or order status is changed on WooCommerce.
 * Version: 1.9
 * Author: Cral_Cactus
 * Author URI: https://github.com/Cral-Cactus
 * Requires Plugins: woocommerce
 * Requires at least: 6.2
 * Tested up to: 6.6.1
 * WC requires at least: 8.5
 * WC tested up to: 9.1
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('before_woocommerce_init', function(){
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

class Sale_Discord_Notifications_Woo {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_color_picker'));
        add_action('woocommerce_thankyou', array($this, 'send_discord_notification'));
        add_action('woocommerce_order_status_changed', array($this, 'send_discord_notification_on_status_change'), 10, 4); // Action for order status change
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
    }

    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __('Discord Notifications', 'wc-sale-discord-notifications'),
            __('Discord Notifications', 'wc-sale-discord-notifications'),
            'manage_options',
            'wc-sale-discord-notifications',
            array($this, 'notification_settings_page')
        );
    }

    public function register_settings() {
        register_setting('wc_sale_discord_notifications', 'wc_sale_discord_webhook_url');
        register_setting('wc_sale_discord_notifications', 'wc_sale_discord_order_statuses');
        register_setting('wc_sale_discord_notifications', 'wc_sale_discord_status_webhooks');
        register_setting('wc_sale_discord_notifications', 'wc_sale_discord_status_colors');

        add_settings_section(
            'wc_sale_discord_notifications_section',
            __('Discord Webhook Settings', 'wc-sale-discord-notifications'),
            null,
            'wc_sale_discord_notifications'
        );

        add_settings_field(
            'wc_sale_discord_webhook_url',
            __('Discord Webhook URL', 'wc-sale-discord-notifications'),
            array($this, 'discord_webhook_url_callback'),
            'wc_sale_discord_notifications',
            'wc_sale_discord_notifications_section'
        );

        add_settings_field(
            'wc_sale_discord_order_statuses',
            __('Order Status Notifications', 'wc-sale-discord-notifications'),
            array($this, 'discord_order_statuses_callback'),
            'wc_sale_discord_notifications',
            'wc_sale_discord_notifications_section'
        );
    }

    public function discord_webhook_url_callback() {
        $webhook_url = get_option('wc_sale_discord_webhook_url');
        echo '<input type="text" name="wc_sale_discord_webhook_url" value="' . esc_attr($webhook_url) . '" size="50" />';
    }

    public function discord_order_statuses_callback() {
        $order_statuses = wc_get_order_statuses();
        $selected_statuses = get_option('wc_sale_discord_order_statuses', []);
        $selected_statuses = maybe_unserialize($selected_statuses);
        if (!is_array($selected_statuses)) {
            $selected_statuses = [];
        }
        $status_webhooks = get_option('wc_sale_discord_status_webhooks', []);
        $status_colors = get_option('wc_sale_discord_status_colors', []);

        $default_colors = array(
            'wc-pending' => '#ffdc00',
            'wc-processing' => '#00e5ed',
            'wc-on-hold' => '#FFA500',
            'wc-completed' => '#00d660',
            'wc-cancelled' => '#d60000',
            'wc-refunded' => '#6800e0',
            'wc-failed' => '#111111'
        );

        foreach ($order_statuses as $status => $label) {
            $checked = in_array($status, $selected_statuses) ? 'checked' : '';
            $webhook = isset($status_webhooks[$status]) ? esc_attr($status_webhooks[$status]) : '';
            $color = isset($status_colors[$status]) ? esc_attr($status_colors[$status]) : (isset($default_colors[$status]) ? $default_colors[$status] : '#ffffff');

            echo '<p style="margin-bottom: 10px;">';
            echo '<label style="margin-right: 10px;">';
            echo '<input type="checkbox" name="wc_sale_discord_order_statuses[]" value="' . esc_attr($status) . '" ' . esc_attr($checked) . '>';
            echo ' ' . esc_html($label);
            echo '</label>';
            echo '<input type="text" class="webhook-input" style="margin-right: 10px" name="wc_sale_discord_status_webhooks[' . esc_attr($status) . ']" value="' . esc_attr($webhook) . '" placeholder="Webhook URL (optional)" size="50">';
            echo '<input type="text" name="wc_sale_discord_status_colors[' . esc_attr($status) . ']" value="' . esc_attr($color) . '" class="discord-embed-color-picker" />';
            echo '</p>';
        }
    }

    public function enqueue_color_picker($hook_suffix) {
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script(
            'wc_sale-color-picker-script',
            plugins_url('color-picker.js', __FILE__),
            array('wp-color-picker'),
            '1.0.0',
            true
        );
    }

    public function notification_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Discord Sale Notifications', 'wc-sale-discord-notifications'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_sale_discord_notifications');
                do_settings_sections('wc_sale_discord_notifications');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function send_discord_notification($order_id) {
        $this->send_discord_notification_common($order_id, 'new');
    }

    public function send_discord_notification_on_status_change($order_id, $old_status, $new_status, $order) {
        $this->send_discord_notification_common($order_id, 'update');
    }

    private function send_discord_notification_common($order_id, $type) {
        $selected_statuses = get_option('wc_sale_discord_order_statuses', []);
        $selected_statuses = maybe_unserialize($selected_statuses);
        if (!is_array($selected_statuses)) {
            $selected_statuses = [];
        }
        $status_webhooks = get_option('wc_sale_discord_status_webhooks', []);
        $status_colors = get_option('wc_sale_discord_status_colors', []);
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $order_status = 'wc-' . $order->get_status();

        if (!in_array($order_status, $selected_statuses)) {
            return;
        }

        $webhook_url = !empty($status_webhooks[$order_status]) ? $status_webhooks[$order_status] : get_option('wc_sale_discord_webhook_url');
        $embed_color = !empty($status_colors[$order_status]) ? hexdec(substr($status_colors[$order_status], 1)) : hexdec(substr('#ffffff', 1));

        if (!$webhook_url) {
            return;
        }

        $order_data = $order->get_data();
        $order_id = $order_data['id'];
        $order_status = wc_get_order_status_name($order->get_status());
        $order_total = $order_data['total'];
        $order_currency = $order_data['currency'];
        $order_date = $order_data['date_created'];
        $order_timestamp = $order_date->getTimestamp();
        $payment_method = $order_data['payment_method_title'];
        $transaction_id = $order_data['transaction_id'];
        $billing_first_name = $order_data['billing']['first_name'];
        $billing_last_name = $order_data['billing']['last_name'];
        $billing_email = $order_data['billing']['email'];
        
        $billing_discord = $order->get_meta('_billing_discord');

        $order_items = $order->get_items();
        $items_list = '';
        $first_product_image = '';

        foreach ($order_items as $item) {
            $product = $item->get_product();
            if ($first_product_image == '' && $product) {
                $first_product_image = wp_get_attachment_url($product->get_image_id());
            }

            $product_name = $item->get_name();
            $product_quantity = $item->get_quantity();
            $product_total = $item->get_total();
            $items_list .= "{$product_quantity}x {$product_name} - {$product_total} {$order_currency}\n";
        }
        $items_list = rtrim($items_list, "\n");

        $order_edit_url = admin_url('post.php?post=' . $order_id . '&action=edit');

        $embed_title = ($type === 'new') ? '🎉 New Order!' : '🪄 Order Update!';

        $embed = [
            'title' => $embed_title,
            'fields' => [
                ['name' => 'Order ID', 'value' => "[#{$order_id}]({$order_edit_url})", 'inline' => false],
                ['name' => 'Status', 'value' => $order_status, 'inline' => false],
                ['name' => 'Payment', 'value' => "{$order_total} {$order_currency} - {$payment_method}", 'inline' => false],
                ['name' => 'Product', 'value' => $items_list, 'inline' => false],
                ['name' => 'Creation Date', 'value' => "<t:{$order_timestamp}:d> (<t:{$order_timestamp}:R>)", 'inline' => false],
                ['name' => 'Billing Information', 'value' => "**Name** » {$billing_first_name} {$billing_last_name}\n**Email** » {$billing_email}", 'inline' => true]
            ],
            'color' => $embed_color
        ];
		
        if (!empty($billing_discord)) {
            $embed['fields'][count($embed['fields']) - 1]['value'] .= "\n**Discord** » {$billing_discord}";
        }

        if (!empty($transaction_id)) {
            $embed['fields'][] = ['name' => 'Transaction ID', 'value' => $transaction_id, 'inline' => false];
        }

        if ($first_product_image) {
            $embed['image'] = ['url' => $first_product_image];
        }

        $this->send_to_discord($webhook_url, $embed);
    }

    private function send_to_discord($webhook_url, $embed) {
        $data = wp_json_encode(['embeds' => [$embed]]);

        $args = [
            'body' => $data,
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 60,
        ];

        wp_remote_post($webhook_url, $args);
    }

    public function plugin_action_links($links) {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=wc-sale-discord-notifications')) . '">' . esc_html__('Settings', 'wc-sale-discord-notifications') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

new Sale_Discord_Notifications_Woo();

if (!function_exists('wc_sale_is_wc_order')) {
    /**
     * Check if the post is a WooCommerce order.
     *
     * @param int $post_id Post id.
     *
     * @return bool True if the post is a WooCommerce order, false otherwise.
     */
    function wc_sale_is_wc_order($post_id = 0) {
        $bool = false;
        if ('shop_order' === \Automattic\WooCommerce\Utilities\OrderUtil::get_order_type($post_id)) {
            $bool = true;
        }
        return $bool;
    }
}
?>