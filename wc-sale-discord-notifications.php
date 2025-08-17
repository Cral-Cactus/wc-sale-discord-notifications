<?php
/**
 * Plugin Name: WC Sale Discord Notifications
 * Plugin URI: https://github.com/Cral-Cactus/wc-sale-discord-notifications
 * Description: Sends a notification to a Discord channel when a sale is made or order status changes on WooCommerce. Includes configurable message content, per-status webhooks, and optional display of product add-ons.
 * Version: 2.3.0
 * Author: Cral_Cactus + Custom Mod by Dex (product build)
 * Author URI: https://github.com/Cral-Cactus + https://github.com/Dextiz
 * Requires Plugins: woocommerce
 * Requires at least: 6.2
 * Tested up to: 6.8.1
 * WC requires at least: 8.5
 * WC tested up to: 9.9.5
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wc-sale-discord-notifications
 */

if (!defined('ABSPATH')) exit;

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

class Sale_Discord_Notifications_Woo
{
    const OPTION_GROUP = 'wc_sale_discord_notifications';
    const PAGE_SLUG    = 'wc-sale-discord-notifications';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_color_picker'));

        add_action('woocommerce_thankyou', array($this, 'send_discord_notification'));
        add_action('woocommerce_order_status_changed', array($this, 'send_discord_notification_on_status_change'), 10, 4);

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('wc-sale-discord-notifications', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function add_settings_page()
    {
        add_submenu_page(
            'woocommerce',
            __('Discord Notifications', 'wc-sale-discord-notifications'),
            __('Discord Notifications', 'wc-sale-discord-notifications'),
            'manage_woocommerce',
            self::PAGE_SLUG,
            array($this, 'notification_settings_page')
        );
    }

    public function register_settings()
    {
        register_setting(self::OPTION_GROUP, 'wc_sale_discord_webhook_url', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
            'show_in_rest'      => false,
        ));

        register_setting(self::OPTION_GROUP, 'wc_sale_discord_order_statuses', array(
            'type'              => 'array',
            'sanitize_callback' => function ($val) {
                $val = is_array($val) ? $val : array();
                $val = array_map('sanitize_text_field', $val);
                return array_values(array_filter($val));
            },
            'default'           => array(),
        ));

        register_setting(self::OPTION_GROUP, 'wc_sale_discord_status_webhooks', array(
            'type'              => 'array',
            'sanitize_callback' => function ($val) {
                $out = array();
                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        $out[sanitize_text_field($k)] = esc_url_raw($v);
                    }
                }
                return $out;
            },
            'default'           => array(),
        ));

        register_setting(self::OPTION_GROUP, 'wc_sale_discord_status_colors', array(
            'type'              => 'array',
            'sanitize_callback' => function ($val) {
                $out = array();
                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        $color = sanitize_hex_color($v);
                        $out[sanitize_text_field($k)] = $color ? $color : '#ffffff';
                    }
                }
                return $out;
            },
            'default'           => array(),
        ));

        register_setting(self::OPTION_GROUP, 'wc_sale_discord_disable_image', array(
            'type'              => 'boolean',
            'sanitize_callback' => function ($v) {
                return ($v === 'on' || $v === '1' || $v === 1 || $v === true || $v === 'true') ? 1 : 0;
            },
            'default'           => 0,
        ));

        // Allow choosing which info fields to include in the embed
        register_setting(self::OPTION_GROUP, 'wc_sale_discord_info_fields', array(
            'type'              => 'array',
            'sanitize_callback' => function ($val) {
                $allowed = array('status', 'payment', 'product', 'product_meta', 'creation_date', 'billing', 'transaction_id');
                $val = is_array($val) ? $val : array();
                $val = array_values(array_intersect($val, $allowed));
                return $val;
            },
            'default'           => array(),
        ));

        // Debug toggle to force blocking requests
        register_setting(self::OPTION_GROUP, 'wc_sale_discord_force_blocking', array(
            'type'              => 'boolean',
            'sanitize_callback' => function ($v) {
                return ($v === 'on' || $v === '1' || $v === 1 || $v === true || $v === 'true') ? 1 : 0;
            },
            'default'           => 0,
        ));

        add_settings_section(
            'wc_sale_discord_notifications_section',
            __('Discord Webhook Settings', 'wc-sale-discord-notifications'),
            null,
            self::OPTION_GROUP
        );

        add_settings_field(
            'wc_sale_discord_webhook_url',
            __('Default Discord Webhook URL', 'wc-sale-discord-notifications'),
            array($this, 'discord_webhook_url_callback'),
            self::OPTION_GROUP,
            'wc_sale_discord_notifications_section'
        );

        add_settings_field(
            'wc_sale_discord_info_fields',
            __('Information to include', 'wc-sale-discord-notifications'),
            array($this, 'discord_info_fields_callback'),
            self::OPTION_GROUP,
            'wc_sale_discord_notifications_section'
        );

        add_settings_field(
            'wc_sale_discord_order_statuses',
            __('Order status notifications', 'wc-sale-discord-notifications'),
            array($this, 'discord_order_statuses_callback'),
            self::OPTION_GROUP,
            'wc_sale_discord_notifications_section'
        );

        add_settings_field(
            'wc_sale_discord_disable_image',
            __('Disable product image in embed', 'wc-sale-discord-notifications'),
            function () {
                $disable_image = (int) get_option('wc_sale_discord_disable_image', 0);
                echo '<input type="hidden" name="wc_sale_discord_disable_image" value="0" />';
                echo '<label><input type="checkbox" name="wc_sale_discord_disable_image" value="1" ' . checked(1, $disable_image, false) . '> ' . esc_html__('Disable image', 'wc-sale-discord-notifications') . '</label>';
            },
            self::OPTION_GROUP,
            'wc_sale_discord_notifications_section'
        );

        add_settings_field(
            'wc_sale_discord_force_blocking',
            __('Debug: Force blocking HTTP requests', 'wc-sale-discord-notifications'),
            function () {
                $force = (int) get_option('wc_sale_discord_force_blocking', 0);
                echo '<input type="hidden" name="wc_sale_discord_force_blocking" value="0" />';
                echo '<label><input type="checkbox" name="wc_sale_discord_force_blocking" value="1" ' . checked(1, $force, false) . '> ' . esc_html__('Enable for troubleshooting. May slow down checkout.', 'wc-sale-discord-notifications') . '</label>';
            },
            self::OPTION_GROUP,
            'wc_sale_discord_notifications_section'
        );
    }

    public function enqueue_color_picker($hook_suffix)
    {
        if ($hook_suffix !== 'woocommerce_page_' . self::PAGE_SLUG) {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        $js = "
            (function($){
                $(function(){
                    $('.discord-embed-color-picker').wpColorPicker();
                });
            })(jQuery);
        ";
        wp_add_inline_script('wp-color-picker', $js);
    }

    public function discord_webhook_url_callback()
    {
        $webhook_url = get_option('wc_sale_discord_webhook_url', '');
        echo '<input type="url" name="wc_sale_discord_webhook_url" value="' . esc_attr($webhook_url) . '" size="60" placeholder="' . esc_attr__('https://discord.com/api/webhooks/...', 'wc-sale-discord-notifications') . '"/>';
    }

    public function discord_info_fields_callback()
    {
        $options = get_option('wc_sale_discord_info_fields', array());
        $fields  = array(
            'status'         => __('Status', 'wc-sale-discord-notifications'),
            'payment'        => __('Payment', 'wc-sale-discord-notifications'),
            'product'        => __('Product lines (name, qty, price)', 'wc-sale-discord-notifications'),
            'product_meta'   => __('Product options (add-ons / custom fields)', 'wc-sale-discord-notifications'),
            'creation_date'  => __('Creation Date', 'wc-sale-discord-notifications'),
            'billing'        => __('Billing Information', 'wc-sale-discord-notifications'),
            'transaction_id' => __('Transaction ID', 'wc-sale-discord-notifications'),
        );
        foreach ($fields as $key => $label) {
            echo '<label style="display:block;margin:2px 0;"><input type="checkbox" name="wc_sale_discord_info_fields[]" value="' . esc_attr($key) . '" ' . checked(in_array($key, (array) $options, true), true, false) . '> ' . esc_html($label) . '</label>';
        }
    }

    public function discord_order_statuses_callback()
    {
        $order_statuses    = wc_get_order_statuses();
        $selected_statuses = get_option('wc_sale_discord_order_statuses', array());
        $selected_statuses = is_array($selected_statuses) ? $selected_statuses : (array) maybe_unserialize($selected_statuses);

        $status_webhooks = get_option('wc_sale_discord_status_webhooks', array());
        $status_colors   = get_option('wc_sale_discord_status_colors', array());

        $default_colors = array(
            'wc-pending'    => '#ffdc00',
            'wc-processing' => '#00e5ed',
            'wc-on-hold'    => '#FFA500',
            'wc-completed'  => '#00d660',
            'wc-cancelled'  => '#d60000',
            'wc-refunded'   => '#6800e0',
            'wc-failed'     => '#111111',
        );

        foreach ($order_statuses as $status => $label) {
            $is_checked = in_array($status, $selected_statuses, true);
            $webhook    = isset($status_webhooks[$status]) ? $status_webhooks[$status] : '';
            $color      = isset($status_colors[$status]) ? $status_colors[$status] : (isset($default_colors[$status]) ? $default_colors[$status] : '#ffffff');

            echo '<p style="margin-bottom: 10px;">';
            echo '<label style="margin-right: 10px;">';
            echo '<input type="checkbox" name="wc_sale_discord_order_statuses[]" value="' . esc_attr($status) . '" ' . checked($is_checked, true, false) . '> ' . esc_html($label);
            echo '</label> ';
            echo '<input type="url" class="webhook-input" style="margin-right: 10px" name="wc_sale_discord_status_webhooks[' . esc_attr($status) . ']" value="' . esc_attr($webhook) . '" placeholder="' . esc_attr__('Webhook URL (optional)', 'wc-sale-discord-notifications') . '" size="50"> ';
            echo '<input type="text" name="wc_sale_discord_status_colors[' . esc_attr($status) . ']" value="' . esc_attr($color) . '" class="discord-embed-color-picker" />';
            echo '</p>';
        }
    }

    public function notification_settings_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Discord Sale Notifications', 'wc-sale-discord-notifications') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::OPTION_GROUP);
        do_settings_sections(self::OPTION_GROUP);
        submit_button();
        echo '</form></div>';
    }

    public function send_discord_notification($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) return;

        // Only fire 'new' via thankyou for pending (manual/slow payments)
        if ($order->get_status() === 'pending') {
            $this->send_discord_notification_common($order_id, 'new');
        }
    }

    public function send_discord_notification_on_status_change($order_id, $old_status, $new_status, $order)
    {
        $selected = get_option('wc_sale_discord_order_statuses', array());
        $selected = is_array($selected) ? $selected : (array) maybe_unserialize($selected);

        $target = 'wc-' . $new_status;
        if (!in_array($target, $selected, true)) {
            return;
        }

        // If we recently sent a 'new' for this target status, skip the immediate 'update'
        $recent_new_sent = get_post_meta($order_id, '_discord_sent_' . $target . '_new', true);
        if ($recent_new_sent) {
            $sent_ts = strtotime($recent_new_sent);
            if ($sent_ts && (current_time('timestamp') - $sent_ts) < 120) {
                return;
            }
        }

        // First notification for this order becomes 'new'
        $any_sent = false;
        $all_meta = get_post_meta($order_id);
        foreach ($all_meta as $key => $val) {
            if (strpos($key, '_discord_sent_') === 0) {
                $any_sent = true;
                break;
            }
        }

        $type = $any_sent ? 'update' : 'new';
        $this->send_discord_notification_common($order_id, $type);
    }

    private function send_discord_notification_common($order_id, $type)
    {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $selected_statuses = get_option('wc_sale_discord_order_statuses', array());
        $selected_statuses = is_array($selected_statuses) ? $selected_statuses : (array) maybe_unserialize($selected_statuses);

        $status_webhooks = get_option('wc_sale_discord_status_webhooks', array());
        $status_colors   = get_option('wc_sale_discord_status_colors', array());
        $enabled_fields  = get_option('wc_sale_discord_info_fields', array());

        $order_status = 'wc-' . $order->get_status();
        if (!in_array($order_status, $selected_statuses, true)) return;

        // Duplicate protection per status & type
        $status_meta_key = '_discord_sent_' . $order_status . '_' . $type;
        if (get_post_meta($order_id, $status_meta_key, true)) {
            return;
        }

        $webhook_url = !empty($status_webhooks[$order_status]) ? $status_webhooks[$order_status] : get_option('wc_sale_discord_webhook_url');
        if (!$webhook_url) return;

        // Color
        $hex         = !empty($status_colors[$order_status]) ? $status_colors[$order_status] : '#ffffff';
        $hex         = sanitize_hex_color($hex) ?: '#ffffff';
        $embed_color = hexdec(ltrim($hex, '#'));

        // Order data
        $order_data      = $order->get_data();
        $order_currency  = $order_data['currency'];
        $order_date      = $order_data['date_created'];
        $order_timestamp = $order_date ? $order_date->getTimestamp() : time();

        $payment_method = !empty($order_data['payment_method_title']) ? $order_data['payment_method_title'] : $order->get_payment_method();
        $transaction_id = !empty($order_data['transaction_id']) ? $order_data['transaction_id'] : $order->get_transaction_id();

        $billing_first_name = $order_data['billing']['first_name'];
        $billing_last_name  = $order_data['billing']['last_name'];
        $billing_email      = $order_data['billing']['email'];
        $billing_discord    = $order->get_meta('_billing_discord');

        $order_items         = $order->get_items();
        $item_lines          = array();
        $first_product_image = '';

        $include_product      = in_array('product', (array) $enabled_fields, true);
        $include_product_meta = in_array('product_meta', (array) $enabled_fields, true);

        foreach ($order_items as $item) {
            $product = $item->get_product();
            if ($first_product_image === '' && $product) {
                $img_id = $product->get_image_id();
                if ($img_id) {
                    $first_product_image = wp_get_attachment_url($img_id);
                }
            }

            // Base line: qty x name - total
            $product_name  = $item->get_name();
            $product_qty   = $item->get_quantity();
            $product_total = $item->get_total();
            $line_total    = html_entity_decode(wp_strip_all_tags(wc_price($product_total, array('currency' => $order_currency))));
            $line          = "{$product_qty}x {$product_name} - {$line_total}";

            // Append meta/add-ons if enabled (uses WC formatted meta; hides underscore/private meta)
            if ($include_product_meta) {
                $meta_data = $item->get_formatted_meta_data(); // default hides keys starting with '_'
                if (!empty($meta_data)) {
                    foreach ($meta_data as $meta) {
                        $k = wp_strip_all_tags($meta->display_key);
                        $v = is_scalar($meta->display_value) ? (string) $meta->display_value : wp_strip_all_tags(wc_clean(wp_json_encode($meta->display_value)));
                        $v = wp_strip_all_tags($v);
                        if ($k !== '' && $v !== '') {
                            $line .= "\n   • {$k}: {$v}";
                        }
                    }
                }
            }

            $item_lines[] = $line;
        }

        // Build Product field value (if requested)
        $embed_fields = array();
        $order_edit_url    = admin_url('post.php?post=' . absint($order_id) . '&action=edit');
        $embed_title       = ($type === 'new') ? '🎉 New Order' : '🪄 Order Update';
        $order_status_name = wc_get_order_status_name($order->get_status());

        $embed_fields[] = array('name' => 'Order ID', 'value' => "[#{$order_id}]({$order_edit_url})", 'inline' => false);

        if (in_array('status', (array) $enabled_fields, true)) {
            $embed_fields[] = array('name' => 'Status', 'value' => $order_status_name, 'inline' => false);
        }

        if (in_array('payment', (array) $enabled_fields, true)) {
            $order_total_fmt = html_entity_decode(wp_strip_all_tags($order->get_formatted_order_total()));
            $embed_fields[]  = array('name' => 'Payment', 'value' => "{$order_total_fmt} — {$payment_method}", 'inline' => false);
        }

        if ($include_product) {
            $items_list = implode("\n", $item_lines);
            $items_list = $this->truncate_field($items_list, 1000); // Discord embed field value limit is 1024 chars
            $embed_fields[] = array('name' => 'Product', 'value' => ($items_list !== '' ? $items_list : '-'), 'inline' => false);
        }

        if (in_array('creation_date', (array) $enabled_fields, true)) {
            $embed_fields[] = array('name' => 'Creation Date', 'value' => "<t:{$order_timestamp}:d> (<t:{$order_timestamp}:R>)", 'inline' => false);
        }

        if (in_array('billing', (array) $enabled_fields, true)) {
            $billing_info = "**Name** » {$billing_first_name} {$billing_last_name}\n**Email** » {$billing_email}";
            if (!empty($billing_discord)) {
                $billing_info .= "\n**Discord** » {$billing_discord}";
            }
            $billing_info = $this->truncate_field($billing_info, 1000);
            $embed_fields[] = array('name' => 'Billing Information', 'value' => $billing_info, 'inline' => true);
        }

        if (in_array('transaction_id', (array) $enabled_fields, true) && !empty($transaction_id)) {
            $embed_fields[] = array('name' => 'Transaction ID', 'value' => $this->truncate_field($transaction_id, 1000), 'inline' => false);
        }

        $embed = array(
            'title'  => $embed_title,
            'fields' => $embed_fields,
            'color'  => $embed_color,
        );

        if ($first_product_image && !get_option('wc_sale_discord_disable_image')) {
            $embed['image'] = array('url' => $first_product_image);
        }

        $this->send_to_discord($webhook_url, $embed, $order_id, $order_status, $type);
    }

    private function truncate_field($text, $limit = 1000)
    {
        $text = (string) $text;
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return mb_substr($text, 0, $limit - 6) . " [...]";
    }

    private function send_to_discord($webhook_url, $embed, $order_id = null, $order_status = '', $type = '')
    {
        $data = wp_json_encode(array('embeds' => array($embed)));
        $args = array(
            'body'     => $data,
            'headers'  => array('Content-Type' => 'application/json'),
            'timeout'  => 20,
            'blocking' => false,
        );

        $force_blocking = (int) get_option('wc_sale_discord_force_blocking', 0);
        if ($force_blocking) {
            $args['blocking'] = true;
            $args['timeout']  = max(30, (int) $args['timeout']);
        }

        $args = apply_filters('wc_sale_discord_http_args', $args, $order_id, $order_status, $type, $webhook_url);

        $response = wp_remote_post($webhook_url, $args);

        if (!empty($args['blocking'])) {
            if (is_wp_error($response)) {
                error_log('[WC Discord] HTTP error: ' . $response->get_error_message());
            } else {
                $code = wp_remote_retrieve_response_code($response);
                if ($code < 200 || $code >= 300) {
                    error_log('[WC Discord] Non-2xx response: ' . $code . ' Body: ' . wp_remote_retrieve_body($response));
                }
            }
        } else {
            if (!empty($order_id)) {
                error_log(sprintf('[WC Discord] Sent async: order=%d status=%s type=%s', (int) $order_id, $order_status, $type));
            }
        }

        if (!empty($order_id) && !empty($order_status) && !empty($type)) {
            $status_meta_key = '_discord_sent_' . $order_status . '_' . $type;
            update_post_meta($order_id, $status_meta_key, current_time('mysql'));
        }
    }

    public function plugin_action_links($links)
    {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">' . esc_html__('Settings', 'wc-sale-discord-notifications') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

new Sale_Discord_Notifications_Woo();
