<?php
/**
 * Plugin Name: WC Sale Discord Notifications
 * Plugin URI: https://github.com/Cral-Cactus/wc-sale-discord-notifications
 * Description: Sends a notification to a Discord channel when a sale is made or order status is changed on WooCommerce.
 * Version: 3.2.0
 * Author: Cral_Cactus
 * Author URI: https://github.com/Cral-Cactus
 * Requires Plugins: woocommerce
 * Requires at least: 6.2
 * Tested up to: 6.9.4
 * WC requires at least: 8.5
 * WC tested up to: 10.6.1
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

    const OPTION_GROUP = 'wc_sale_discord_notifications';
    const PAGE_SLUG = 'wc-sale-discord-notifications';
    const DISCORD_FIELD_MAX = 1024;
    const DISCORD_EMBED_MAX = 6000;

    /**
     * Default Discord embed field order (keys must match sanitize / builder).
     *
     * @return array<int, string>
     */
    private static function default_embed_field_order(): array {
        return array(
            'order_id',
            'status',
            'payment',
            'product',
            'product_meta',
            'creation_date',
            'billing',
            'customer_type',
            'shipping',
            'transaction_id',
            'order_notes',
        );
    }

    /**
     * Sanitize saved field order; append any missing keys in default order.
     *
     * @param array<int, mixed> $value Raw POST values.
     * @return array<int, string>
     */
    public static function sanitize_embed_field_order_value(array $value): array {
        $default = self::default_embed_field_order();
        $seen = array();
        $out = array();
        foreach ($value as $raw) {
            $k = sanitize_text_field((string) $raw);
            if (in_array($k, $default, true) && !in_array($k, $seen, true)) {
                $out[] = $k;
                $seen[] = $k;
            }
        }
        foreach ($default as $k) {
            if (!in_array($k, $seen, true)) {
                $out[] = $k;
            }
        }
        return $out;
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_color_picker'));
        add_action('woocommerce_thankyou', array($this, 'send_discord_notification'));
        add_action('woocommerce_order_status_changed', array($this, 'send_discord_notification_on_status_change'), 10, 4);
        add_action('woocommerce_order_status_pending', array($this, 'send_discord_notification_on_pending'), 10, 2);
        add_action('woocommerce_checkout_order_processed', array($this, 'send_discord_notification_on_checkout_processed'), 10, 3);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
    }

    public function add_settings_page() {
        $capability = apply_filters('wc_sale_discord_notifications_capability', 'manage_woocommerce');
        add_submenu_page(
            'woocommerce',
            __('Discord Notifications', 'wc-sale-discord-notifications'),
            __('Discord Notifications', 'wc-sale-discord-notifications'),
            $capability,
            self::PAGE_SLUG,
            array($this, 'notification_settings_page')
        );
    }

    public function register_settings() {
        register_setting(self::OPTION_GROUP, 'wc_sale_discord_webhook_url', [
            'sanitize_callback' => function ($value) {
                return is_string($value) ? esc_url_raw(trim($value)) : '';
            },
        ]);
        register_setting(self::OPTION_GROUP, 'wc_sale_discord_order_statuses', [
            'sanitize_callback' => function ($value) {
                if (!is_array($value)) {
                    return [];
                }
                return array_map('sanitize_text_field', $value);
            },
        ]);
        register_setting(self::OPTION_GROUP, 'wc_sale_discord_status_webhooks', [
            'sanitize_callback' => function ($value) {
                if (!is_array($value)) {
                    return [];
                }
                $sanitized = [];
                foreach ($value as $k => $v) {
                    $sanitized[sanitize_text_field($k)] = esc_url_raw(trim((string) $v));
                }
                return $sanitized;
            },
        ]);
        register_setting(self::OPTION_GROUP, 'wc_sale_discord_status_colors', [
            'sanitize_callback' => function ($value) {
                if (!is_array($value)) {
                    return [];
                }
                $sanitized = [];
                foreach ($value as $k => $v) {
                    $sanitized[sanitize_text_field($k)] = sanitize_hex_color($v) ?: '#ffffff';
                }
                return $sanitized;
            },
        ]);
        register_setting(self::OPTION_GROUP, 'wc_sale_discord_disable_image', [
            'sanitize_callback' => function ($value) {
                return !empty($value) ? 1 : 0;
            },
        ]);
        register_setting(self::OPTION_GROUP, 'wc_sale_discord_info_fields', [
            'sanitize_callback' => function ($value) {
                $allowed = array('status', 'payment', 'product', 'product_meta', 'creation_date', 'billing', 'customer_type', 'shipping', 'transaction_id', 'order_notes');
                $value = is_array($value) ? $value : array();
                return array_values(array_intersect($value, $allowed));
            },
        ]);
        register_setting(self::OPTION_GROUP, 'wc_sale_discord_field_order', [
            'sanitize_callback' => static function ($value) {
                return self::sanitize_embed_field_order_value(is_array($value) ? $value : array());
            },
        ]);
        register_setting(self::OPTION_GROUP, 'wc_sale_discord_force_blocking', [
            'sanitize_callback' => function ($value) {
                return !empty($value) ? 1 : 0;
            },
        ]);
        register_setting(self::OPTION_GROUP, 'wc_sale_discord_notify_initiating_payment', [
            'sanitize_callback' => function ($value) {
                return !empty($value) ? 1 : 0;
            },
        ]);
        register_setting(self::OPTION_GROUP, 'wc_sale_discord_order_notes_customer_only', [
            'sanitize_callback' => function ($value) {
                return !empty($value) ? 1 : 0;
            },
        ]);

        add_settings_section(
            self::OPTION_GROUP . '_section',
            __('Discord Webhook Settings', 'wc-sale-discord-notifications'),
            null,
            self::OPTION_GROUP
        );

        add_settings_field(
            'wc_sale_discord_webhook_url',
            __('Discord Webhook URL', 'wc-sale-discord-notifications'),
            array($this, 'discord_webhook_url_callback'),
            self::OPTION_GROUP,
            self::OPTION_GROUP . '_section'
        );

        add_settings_field(
            'wc_sale_discord_order_statuses',
            __('Order Status Notifications', 'wc-sale-discord-notifications'),
            array($this, 'discord_order_statuses_callback'),
            self::OPTION_GROUP,
            self::OPTION_GROUP . '_section'
        );

        add_settings_field(
            'wc_sale_discord_disable_image',
            __('Disable Product Image in Embed', 'wc-sale-discord-notifications'),
            function() {
                $disable_image = get_option('wc_sale_discord_disable_image');
                echo '<input type="hidden" name="wc_sale_discord_disable_image" value="0" />';
                echo '<input type="checkbox" name="wc_sale_discord_disable_image" value="1"' . checked(1, $disable_image, false) . '/>';
            },
            self::OPTION_GROUP,
            self::OPTION_GROUP . '_section'
        );

        add_settings_field(
            'wc_sale_discord_notify_initiating_payment',
            __('Send notification for Initiating payments', 'wc-sale-discord-notifications'),
            function() {
                $enabled = (int) get_option('wc_sale_discord_notify_initiating_payment', 0);
                echo '<input type="hidden" name="wc_sale_discord_notify_initiating_payment" value="0" />';
                echo '<input type="checkbox" name="wc_sale_discord_notify_initiating_payment" value="1"' . checked(1, $enabled, false) . '/>';
                echo '<p class="description">' . esc_html__('Notify when a customer starts checkout (pending) before payment is completed. Uses a separate embed title from completed orders.', 'wc-sale-discord-notifications') . '</p>';
            },
            self::OPTION_GROUP,
            self::OPTION_GROUP . '_section'
        );

        add_settings_field(
            'wc_sale_discord_info_fields',
            __('Embed Fields', 'wc-sale-discord-notifications'),
            array($this, 'info_fields_callback'),
            self::OPTION_GROUP,
            self::OPTION_GROUP . '_section'
        );

        add_settings_field(
            'wc_sale_discord_field_order',
            __('Embed field order', 'wc-sale-discord-notifications'),
            array($this, 'field_order_callback'),
            self::OPTION_GROUP,
            self::OPTION_GROUP . '_section'
        );

        add_settings_field(
            'wc_sale_discord_order_notes_customer_only',
            __('Customer notes only', 'wc-sale-discord-notifications'),
            function () {
                $customer_only = (int) get_option('wc_sale_discord_order_notes_customer_only', 0);
                echo '<input type="hidden" name="wc_sale_discord_order_notes_customer_only" value="0" />';
                echo '<input type="checkbox" name="wc_sale_discord_order_notes_customer_only" value="1"' . checked(1, $customer_only, false) . '/>';
                echo '<p class="description">' . esc_html__('When Order Notes is included, show only customer notes (exclude internal/admin notes).', 'wc-sale-discord-notifications') . '</p>';
            },
            self::OPTION_GROUP,
            self::OPTION_GROUP . '_section'
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
            self::OPTION_GROUP . '_section'
        );
    }

    public function info_fields_callback() {
        $info_fields = get_option('wc_sale_discord_info_fields', array());
        if (!is_array($info_fields)) {
            $info_fields = array();
        }
        $available = array(
            'status'        => __('Status', 'wc-sale-discord-notifications'),
            'payment'       => __('Payment', 'wc-sale-discord-notifications'),
            'product'       => __('Product', 'wc-sale-discord-notifications'),
            'product_meta'  => __('Product Meta', 'wc-sale-discord-notifications'),
            'creation_date' => __('Creation Date', 'wc-sale-discord-notifications'),
            'billing'       => __('Billing Information', 'wc-sale-discord-notifications'),
            'customer_type' => __('New or returning customer', 'wc-sale-discord-notifications'),
            'shipping'      => __('Shipping method', 'wc-sale-discord-notifications'),
            'transaction_id' => __('Transaction ID', 'wc-sale-discord-notifications'),
            'order_notes'   => __('Order Notes', 'wc-sale-discord-notifications'),
        );
        echo '<p class="description">' . esc_html__('Choose which fields to include in the Discord embed. Leave empty to include all.', 'wc-sale-discord-notifications') . '</p>';
        foreach ($available as $key => $label) {
            $checked = in_array($key, $info_fields) ? 'checked' : '';
            echo '<p style="margin: 5px 0;">';
            echo '<label><input type="checkbox" name="wc_sale_discord_info_fields[]" value="' . esc_attr($key) . '" ' . esc_attr($checked) . '> ' . esc_html($label) . '</label>';
            echo '</p>';
        }
    }

    /**
     * Labels for embed field order UI (includes Order ID).
     *
     * @return array<string, string>
     */
    private function get_embed_field_labels(): array {
        return array(
            'order_id'       => __('Order ID', 'wc-sale-discord-notifications'),
            'status'         => __('Status', 'wc-sale-discord-notifications'),
            'payment'        => __('Payment', 'wc-sale-discord-notifications'),
            'product'        => __('Product', 'wc-sale-discord-notifications'),
            'product_meta'   => __('Product Meta', 'wc-sale-discord-notifications'),
            'creation_date'  => __('Creation Date', 'wc-sale-discord-notifications'),
            'billing'        => __('Billing Information', 'wc-sale-discord-notifications'),
            'customer_type'  => __('New or returning customer', 'wc-sale-discord-notifications'),
            'shipping'       => __('Shipping method', 'wc-sale-discord-notifications'),
            'transaction_id' => __('Transaction ID', 'wc-sale-discord-notifications'),
            'order_notes'    => __('Order Notes', 'wc-sale-discord-notifications'),
        );
    }

    public function field_order_callback() {
        $saved = get_option('wc_sale_discord_field_order', array());
        $order = (!is_array($saved) || empty($saved)) ? self::default_embed_field_order() : self::sanitize_embed_field_order_value($saved);
        $labels = $this->get_embed_field_labels();
        echo '<p class="description">' . esc_html__('Drag rows to set the order of fields in the Discord embed. Fields turned off under Embed Fields above are still omitted when sending.', 'wc-sale-discord-notifications') . '</p>';
        echo '<ul id="wc-sale-discord-field-order" class="wc-sale-discord-sortable" style="list-style:none;margin:0;padding:0;max-width:28rem;">';
        foreach ($order as $key) {
            if (!isset($labels[$key])) {
                continue;
            }
            echo '<li style="margin:4px 0;padding:0;border:1px solid #c3c4c7;background:#fff;border-radius:4px;">';
            echo '<span class="wc-sale-discord-field-order-handle" style="display:block;padding:8px 12px;cursor:grab;">';
            echo '<input type="hidden" name="wc_sale_discord_field_order[]" value="' . esc_attr($key) . '" />';
            echo esc_html($labels[$key]);
            echo '</span></li>';
        }
        echo '</ul>';
    }

    public function discord_webhook_url_callback() {
        $webhook_url = get_option('wc_sale_discord_webhook_url');
        echo '<input type="text" name="wc_sale_discord_webhook_url" value="' . esc_attr($webhook_url) . '" size="50" />';
    }

    public function discord_order_statuses_callback() {
        if (!function_exists('wc_get_order_statuses')) {
            echo '<p class="description">' . esc_html__('WooCommerce must be active to display order statuses.', 'wc-sale-discord-notifications') . '</p>';
            return;
        }
        $order_statuses = wc_get_order_statuses();
        if (empty($order_statuses)) {
            echo '<p class="description">' . esc_html__('No order statuses available.', 'wc-sale-discord-notifications') . '</p>';
            return;
        }
        $selected_statuses = get_option('wc_sale_discord_order_statuses', []);
        if (!is_array($selected_statuses)) {
            $selected_statuses = [];
        }
        $status_webhooks = get_option('wc_sale_discord_status_webhooks', []);
        $status_colors = get_option('wc_sale_discord_status_colors', []);
        if (!is_array($status_webhooks)) {
            $status_webhooks = [];
        }
        if (!is_array($status_colors)) {
            $status_colors = [];
        }

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
        if (strpos($hook_suffix, self::PAGE_SLUG) === false) {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script(
            'wc_sale-color-picker-script',
            plugins_url('color-picker.js', __FILE__),
            array('wp-color-picker'),
            '1.0.0',
            true
        );
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script(
            'wc-sale-discord-field-order',
            plugins_url('field-order.js', __FILE__),
            array('jquery', 'jquery-ui-sortable'),
            '1.0.0',
            true
        );
        wp_register_style('wc-sale-discord-admin', false);
        wp_enqueue_style('wc-sale-discord-admin');
        wp_add_inline_style(
            'wc-sale-discord-admin',
            '#wc-sale-discord-field-order .ui-sortable-placeholder{visibility:visible!important;min-height:2.5rem;background:#f0f6fc;border:1px dashed #2271b1;border-radius:4px;}'
        );
    }

    public function notification_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Discord Sale Notifications', 'wc-sale-discord-notifications'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::OPTION_GROUP);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Send notification when customer lands on thank you page (pending orders only).
     */
    public function send_discord_notification($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        // Only fire for pending when customer lands on thank you page (manual/slow payments)
        if ($order->get_status() === 'pending') {
            if (get_option('wc_sale_discord_notify_initiating_payment')) {
                $this->maybe_send_initiating_notification($order_id, $order);
            } else {
                $this->send_discord_notification_common($order_id, 'new');
            }
        }
    }

    /**
     * Send notification when order status changes.
     *
     * @param int    $order_id   Order ID.
     * @param string $old_status Previous status.
     * @param string $new_status New status.
     * @param WC_Order|null $order Order object.
     */
    public function send_discord_notification_on_status_change($order_id, $old_status, $new_status, $order) {
        $order_obj = is_object($order) ? $order : wc_get_order($order_id);

        // Pending + initiating option enabled: send 'initiating' (bypass selected_statuses)
        if ($new_status === 'pending' && get_option('wc_sale_discord_notify_initiating_payment')) {
            $this->maybe_send_initiating_notification($order_id, $order_obj);
            return;
        }

        $selected = get_option('wc_sale_discord_order_statuses', array());
        $selected = is_array($selected) ? $selected : array();

        $target = 'wc-' . $new_status;
        if (!in_array($target, $selected, true)) {
            return;
        }

        // If we recently sent a 'new' for this target status, skip the immediate 'update'
        $recent_new_sent = $order_obj ? $order_obj->get_meta('_discord_sent_' . $target . '_new') : '';
        if ($recent_new_sent) {
            $sent_ts = strtotime($recent_new_sent);
            if ($sent_ts && (current_time('timestamp') - $sent_ts) < 120) {
                return;
            }
        }

        // First notification for a completed status becomes 'new'. Exclude _initiating so pending→processing sends "New Order".
        $any_sent = false;
        if ($order_obj) {
            $meta_data = $order_obj->get_meta_data();
            foreach ($meta_data as $meta) {
                $key = is_object($meta) && method_exists($meta, 'get_key') ? $meta->get_key() : (isset($meta->key) ? $meta->key : '');
                if (strpos($key, '_discord_sent_') === 0 && strpos($key, '_initiating') === false) {
                    $any_sent = true;
                    break;
                }
            }
        }

        $type = $any_sent ? 'update' : 'new';
        $this->send_discord_notification_common($order_id, $type);
    }

    /**
     * Handle orders created directly with pending status (admin, API) when woocommerce_order_status_changed may not fire.
     */
    public function send_discord_notification_on_pending($order_id, $order = null) {
        $this->maybe_send_initiating_notification($order_id, $order);
    }

    /**
     * Backup trigger: fires when checkout order is processed. Catches pending orders when status hooks
     * may not fire (e.g. block checkout, some payment gateways).
     */
    public function send_discord_notification_on_checkout_processed($order_id, $posted_data, $order) {
        $this->maybe_send_initiating_notification($order_id, $order);
    }

    /**
     * Send initiating payment notification if conditions are met. Deduplicated via 120s meta check.
     */
    private function maybe_send_initiating_notification($order_id, $order = null) {
        if (!get_option('wc_sale_discord_notify_initiating_payment')) {
            return;
        }
        $order_obj = is_object($order) ? $order : wc_get_order($order_id);
        if (!$order_obj || $order_obj->get_status() !== 'pending') {
            return;
        }
        $recent_initiating_sent = $order_obj->get_meta('_discord_sent_wc-pending_initiating');
        if ($recent_initiating_sent) {
            $sent_ts = strtotime($recent_initiating_sent);
            if ($sent_ts && (current_time('timestamp') - $sent_ts) < 120) {
                return;
            }
        }
        $order_obj->update_meta_data('_discord_sent_wc-pending_initiating', current_time('mysql'));
        $order_obj->save();
        $this->send_discord_notification_common($order_id, 'initiating');
    }

    /**
     * Build embed and send to Discord webhook.
     *
     * @param int    $order_id Order ID.
     * @param string $type     Notification type: 'new', 'update', or 'initiating'.
     */
    private function send_discord_notification_common($order_id, $type) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $order_status_key = 'wc-' . $order->get_status();
        $status_webhooks = get_option('wc_sale_discord_status_webhooks', []);
        $status_colors = get_option('wc_sale_discord_status_colors', []);
        if (!is_array($status_webhooks)) {
            $status_webhooks = [];
        }
        if (!is_array($status_colors)) {
            $status_colors = [];
        }

        $is_initiating = ($type === 'initiating');

        if (!$is_initiating) {
            $selected_statuses = get_option('wc_sale_discord_order_statuses', []);
            if (!is_array($selected_statuses)) {
                $selected_statuses = [];
            }
            if (!in_array($order_status_key, $selected_statuses)) {
                return;
            }
        }

        $webhook_url = !empty($status_webhooks[$order_status_key]) ? $status_webhooks[$order_status_key] : get_option('wc_sale_discord_webhook_url');
        $embed_color = !empty($status_colors[$order_status_key]) ? hexdec(substr($status_colors[$order_status_key], 1)) : hexdec(substr('#ffdc00', 1));

        if (!$webhook_url) {
            return;
        }

        // 120s deduplication for new/update: skip if same event was sent recently (thank-you refresh, repeated hooks)
        if (in_array($type, array('new', 'update'), true)) {
            $meta_key = '_discord_sent_' . $order_status_key . '_' . $type;
            $recent_sent = $order->get_meta($meta_key);
            if ($recent_sent) {
                $sent_ts = strtotime($recent_sent);
                if ($sent_ts && (current_time('timestamp') - $sent_ts) < 120) {
                    return;
                }
            }
        }

        $order_data = $order->get_data();
        $order_id = $order_data['id'];
        $order_status = ucwords(wc_get_order_status_name($order->get_status()));
        $order_total = html_entity_decode(strip_tags($order->get_formatted_order_total()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $order_currency = $order_data['currency'];
        $order_date = $order_data['date_created'] ?? null;
        $order_timestamp = ($order_date && is_object($order_date) && method_exists($order_date, 'getTimestamp')) ? $order_date->getTimestamp() : time();
        $payment_method = !empty($order_data['payment_method_title']) ? $order_data['payment_method_title'] : $order->get_payment_method();
        $transaction_id = !empty($order_data['transaction_id']) ? $order_data['transaction_id'] : $order->get_transaction_id();
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

            $product_name = wp_strip_all_tags($item->get_name());
            $product_quantity = $item->get_quantity();
            $product_total = html_entity_decode(strip_tags(wc_price((float) $item->get_total(), array('currency' => $order_currency))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $items_list .= "{$product_quantity}x {$product_name} - {$product_total}\n";
        }
        $items_list = $this->truncate_discord_field(rtrim($items_list, "\n"));

        $order_edit_url = admin_url('post.php?post=' . $order_id . '&action=edit');

        $info_fields = get_option('wc_sale_discord_info_fields', array());
        if (!is_array($info_fields)) {
            $info_fields = array();
        }
        $use_info_fields = !empty($info_fields);
        $field_included = function ($key) use ($use_info_fields, $info_fields) {
            return !$use_info_fields || in_array($key, $info_fields, true);
        };

        $embed_title = $this->get_embed_title($order, $type);

        $product_meta_list = '';
        if ($field_included('product_meta')) {
            $product_meta_list = $this->get_product_meta_for_embed($order_items);
        }
        $shipping_value = $this->get_shipping_for_embed($order, $order_currency);
        $customer_type_label = '';
        if ($field_included('customer_type')) {
            $customer_type_label = $this->get_customer_type_label_for_embed($order);
        }
        $customer_only_notes = (bool) get_option('wc_sale_discord_order_notes_customer_only', false);
        $order_notes_text = '';
        if ($field_included('order_notes')) {
            $order_notes_text = $this->get_order_notes_for_embed($order_id, $order, $customer_only_notes);
        }

        $embed_fields = array();
        foreach ($this->get_embed_field_order_for_send() as $field_key) {
            switch ($field_key) {
                case 'order_id':
                    $embed_fields[] = array(
                        'internal_id' => 'order_id',
                        'name'        => __('Order ID', 'wc-sale-discord-notifications'),
                        'value'       => "[#{$order_id}]({$order_edit_url})",
                        'inline'      => false,
                    );
                    break;
                case 'status':
                    if (!$field_included('status')) {
                        break;
                    }
                    $embed_fields[] = array(
                        'internal_id' => 'status',
                        'name'        => __('Status', 'wc-sale-discord-notifications'),
                        'value'       => $order_status,
                        'inline'      => false,
                    );
                    break;
                case 'payment':
                    if (!$field_included('payment')) {
                        break;
                    }
                    $embed_fields[] = array(
                        'internal_id' => 'payment',
                        'name'        => __('Payment', 'wc-sale-discord-notifications'),
                        'value'       => "{$order_total} - {$payment_method}",
                        'inline'      => false,
                    );
                    break;
                case 'product':
                    if (!$field_included('product')) {
                        break;
                    }
                    $embed_fields[] = array(
                        'internal_id' => 'product',
                        'name'        => __('Product', 'wc-sale-discord-notifications'),
                        'value'       => $items_list ?: '-',
                        'inline'      => false,
                    );
                    break;
                case 'product_meta':
                    if (!$field_included('product_meta') || $product_meta_list === '') {
                        break;
                    }
                    $embed_fields[] = array(
                        'internal_id' => 'product_meta',
                        'name'        => __('Product Meta', 'wc-sale-discord-notifications'),
                        'value'       => $this->truncate_discord_field($product_meta_list),
                        'inline'      => false,
                    );
                    break;
                case 'creation_date':
                    if (!$field_included('creation_date')) {
                        break;
                    }
                    $embed_fields[] = array(
                        'internal_id' => 'creation_date',
                        'name'        => __('Creation Date', 'wc-sale-discord-notifications'),
                        'value'       => "<t:{$order_timestamp}:d> (<t:{$order_timestamp}:R>)",
                        'inline'      => false,
                    );
                    break;
                case 'billing':
                    if (!$field_included('billing')) {
                        break;
                    }
                    $billing_value = "**Name** » {$billing_first_name} {$billing_last_name}\n**Email** » {$billing_email}";
                    if (!empty($billing_discord)) {
                        $billing_value .= "\n**Discord** » {$billing_discord}";
                    }
                    $embed_fields[] = array(
                        'internal_id' => 'billing',
                        'name'        => __('Billing Information', 'wc-sale-discord-notifications'),
                        'value'       => $this->truncate_discord_field($billing_value),
                        'inline'      => true,
                    );
                    break;
                case 'customer_type':
                    if (!$field_included('customer_type')) {
                        break;
                    }
                    $embed_fields[] = array(
                        'internal_id' => 'customer_type',
                        'name'        => __('New or returning customer', 'wc-sale-discord-notifications'),
                        'value'       => $customer_type_label,
                        'inline'      => true,
                    );
                    break;
                case 'shipping':
                    if (!$field_included('shipping')) {
                        break;
                    }
                    $embed_fields[] = array(
                        'internal_id' => 'shipping',
                        'name'        => __('Shipping', 'wc-sale-discord-notifications'),
                        'value'       => $this->truncate_discord_field($shipping_value !== '' ? $shipping_value : '-'),
                        'inline'      => false,
                    );
                    break;
                case 'transaction_id':
                    if (!$field_included('transaction_id') || empty($transaction_id)) {
                        break;
                    }
                    $embed_fields[] = array(
                        'internal_id' => 'transaction_id',
                        'name'        => __('Transaction ID', 'wc-sale-discord-notifications'),
                        'value'       => $this->truncate_discord_field($transaction_id),
                        'inline'      => false,
                    );
                    break;
                case 'order_notes':
                    if (!$field_included('order_notes') || $order_notes_text === '') {
                        break;
                    }
                    $embed_fields[] = array(
                        'internal_id' => 'order_notes',
                        'name'        => __('Order Notes', 'wc-sale-discord-notifications'),
                        'value'       => $this->truncate_discord_field($order_notes_text),
                        'inline'      => false,
                    );
                    break;
            }
        }

        $embed = array(
            'title'  => $embed_title,
            'fields' => $embed_fields,
            'color'  => $embed_color,
        );

        if ($first_product_image && !get_option('wc_sale_discord_disable_image')) {
            $embed['image'] = array('url' => $first_product_image);
        }

        $embed = $this->trim_embed_to_limit($embed);
        $this->send_to_discord($webhook_url, $embed);

        // Track sent notifications for double-notification failsafe
        if (in_array($type, array('new', 'update'), true) && $order) {
            $order->update_meta_data('_discord_sent_' . $order_status_key . '_' . $type, current_time('mysql'));
            $order->save();
        }
    }

    /**
     * Resolved embed field order for outgoing notifications (saved option or default, filterable).
     *
     * @return array<int, string>
     */
    private function get_embed_field_order_for_send(): array {
        $saved = get_option('wc_sale_discord_field_order', array());
        if (!is_array($saved) || empty($saved)) {
            $order = self::default_embed_field_order();
        } else {
            $order = self::sanitize_embed_field_order_value($saved);
        }
        return apply_filters('wc_sale_discord_field_order', $order);
    }

    /**
     * Get embed title based on notification type and order (subscription-aware when WC Subscriptions is active).
     *
     * @param WC_Order $order Order object.
     * @param string   $type  Notification type: 'new', 'update', or 'initiating'.
     * @return string Embed title.
     */
    private function get_embed_title($order, string $type): string {
        if ($type === 'initiating') {
            $title = '⏳ Initiating payment';
        } elseif (function_exists('wcs_order_contains_subscription')) {
            if (wcs_order_contains_subscription($order, 'renewal')) {
                $title = '🔄 Subscription Renewal';
            } elseif (wcs_order_contains_subscription($order, 'parent') && $type === 'new') {
                $title = '📦 New subscription';
            } else {
                $title = ($type === 'new') ? '🎉 New Order!' : '🪄 Order Update!';
            }
        } else {
            $title = ($type === 'new') ? '🎉 New Order!' : '🪄 Order Update!';
        }
        return apply_filters('wc_sale_discord_embed_title', $title, $order, $type);
    }

    /**
     * Get chosen shipping method(s) and cost for Discord embed.
     *
     * @param WC_Order $order          Order object.
     * @param string   $order_currency Order currency code.
     * @return string One line per shipping package, or empty if none.
     */
    private function get_shipping_for_embed($order, string $order_currency): string {
        $lines = array();
        foreach ($order->get_items('shipping') as $item) {
            if (!is_object($item) || !method_exists($item, 'get_name')) {
                continue;
            }
            $name = wp_strip_all_tags($item->get_name());
            if ($name === '' && method_exists($item, 'get_method_title')) {
                $name = wp_strip_all_tags((string) $item->get_method_title());
            }
            $total = (float) $item->get_total() + (float) $item->get_total_tax();
            $price = html_entity_decode(
                strip_tags(wc_price($total, array('currency' => $order_currency))),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            $line = $name;
            if ($price !== '') {
                $line .= ($line !== '' ? ' - ' : '') . $price;
            }
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        if (!empty($lines)) {
            return implode("\n", array_filter($lines));
        }
        $method = $order->get_shipping_method();
        if ($method !== '') {
            $total = (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();
            $price = html_entity_decode(
                strip_tags(wc_price($total, array('currency' => $order_currency))),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            return trim(wp_strip_all_tags($method) . ($price !== '' ? " - {$price}" : ''));
        }
        return '';
    }

    /**
     * Whether the purchaser has prior qualifying orders (excluding this order).
     *
     * @param WC_Order $order Current order.
     * @return string Localized "New customer", "Returning customer", or "Unknown".
     */
    private function get_customer_type_label_for_embed($order): string {
        if (!function_exists('wc_get_orders')) {
            return __('Unknown', 'wc-sale-discord-notifications');
        }
        $current_id = $order->get_id();
        $customer_id = (int) $order->get_customer_id();
        $email = (string) $order->get_billing_email();
        if ($customer_id < 1 && $email === '') {
            return __('Unknown', 'wc-sale-discord-notifications');
        }
        $statuses = apply_filters(
            'wc_sale_discord_returning_customer_order_statuses',
            array('completed', 'processing', 'on-hold')
        );
        $args = array(
            'limit'   => 1,
            'return'  => 'ids',
            'status'  => $statuses,
            'exclude' => array($current_id),
        );
        if ($customer_id > 0) {
            $args['customer_id'] = $customer_id;
        } else {
            $args['billing_email'] = $email;
        }
        $prior = wc_get_orders($args);
        if (is_wp_error($prior)) {
            return __('Unknown', 'wc-sale-discord-notifications');
        }
        $is_returning = !empty($prior);
        $label = $is_returning
            ? __('Returning customer', 'wc-sale-discord-notifications')
            : __('New customer', 'wc-sale-discord-notifications');
        return apply_filters('wc_sale_discord_customer_type_label', $label, $order, $is_returning);
    }

    /**
     * Get formatted order notes for Discord embed.
     * Discord embed field values have a 1024 character limit.
     * Prefers WC_Order::get_customer_order_notes() when customer_only and available (WC 9.2+).
     *
     * @param int        $order_id     Order ID.
     * @param WC_Order   $order        Order object (for get_customer_order_notes when available).
     * @param bool       $customer_only If true, only include customer notes.
     * @return string Formatted order notes or empty string.
     */
    private function get_order_notes_for_embed(int $order_id, $order, bool $customer_only = false): string {
        $notes = [];

        if ($customer_only && $order && method_exists($order, 'get_customer_order_notes')) {
            $notes = $order->get_customer_order_notes();
        } elseif (function_exists('wc_get_order_notes')) {
            $args = [
                'order_id' => $order_id,
                'limit'    => 20,
                'orderby'  => 'date_created',
                'order'    => 'DESC',
            ];
            if ($customer_only) {
                $args['type'] = 'customer';
            }
            $notes = wc_get_order_notes($args);
        }

        if (empty($notes)) {
            return '';
        }

        $lines = [];
        $max_length = 950; // Leave room for truncation message
        $current_length = 0;

        foreach ($notes as $note) {
            $type = (is_object($note) && method_exists($note, 'get_type')) ? $note->get_type() : (isset($note->type) ? $note->type : 'internal');
            $type_label = ('customer' === $type) ? __('Customer', 'wc-sale-discord-notifications') : __('Internal', 'wc-sale-discord-notifications');
            $content = (is_object($note) && method_exists($note, 'get_content')) ? $note->get_content() : (isset($note->content) ? $note->content : '');
            $content = wp_strip_all_tags($content);

            $date_obj = (is_object($note) && method_exists($note, 'get_date_created')) ? $note->get_date_created() : (isset($note->date_created) ? $note->date_created : null);
            $date = $date_obj && method_exists($date_obj, 'format') ? $date_obj->format('Y-m-d H:i') : '';

            if (empty($content)) {
                continue;
            }

            $line = "**{$type_label}** ({$date}): {$content}";
            if ($current_length + strlen($line) + 2 > $max_length) {
                $lines[] = '…';
                break;
            }
            $lines[] = $line;
            $current_length += strlen($line) + 2;
        }

        return implode("\n", $lines);
    }

    /**
     * Trim embed to fit Discord's 6000 character limit. Truncates longest field values first.
     *
     * @param array $embed Embed array (title, fields, color, image).
     * @return array Trimmed embed.
     */
    private function trim_embed_to_limit(array $embed): array {
        $json = wp_json_encode($embed);
        if ($json === false || strlen($json) <= self::DISCORD_EMBED_MAX) {
            return $embed;
        }

        $trimmable_ids = array('order_notes', 'product_meta', 'product', 'billing', 'shipping', 'transaction_id');
        $embed_copy = $embed;
        $attempt = 0;
        $max_attempts = 50;

        while (strlen(wp_json_encode($embed_copy)) > self::DISCORD_EMBED_MAX && $attempt < $max_attempts) {
            $longest_idx = null;
            $longest_len = 0;

            foreach ($embed_copy['fields'] as $idx => $field) {
                $val = $field['value'] ?? '';
                $fid = isset($field['internal_id']) ? (string) $field['internal_id'] : '';
                if (strlen($val) > $longest_len && in_array($fid, $trimmable_ids, true)) {
                    $longest_idx = $idx;
                    $longest_len = strlen($val);
                }
            }

            if ($longest_idx === null) {
                if (isset($embed_copy['image']) && strlen(wp_json_encode($embed_copy)) > self::DISCORD_EMBED_MAX) {
                    unset($embed_copy['image']);
                }
                while (strlen(wp_json_encode($embed_copy)) > self::DISCORD_EMBED_MAX && count($embed_copy['fields']) > 1) {
                    array_pop($embed_copy['fields']);
                }
                break;
            }

            $val = $embed_copy['fields'][$longest_idx]['value'];
            $new_len = max(50, (int) (strlen($val) * 0.75));
            $truncated = $this->truncate_discord_field($val, $new_len);

            if (strlen($truncated) >= strlen($val)) {
                array_splice($embed_copy['fields'], $longest_idx, 1);
            } else {
                $embed_copy['fields'][$longest_idx]['value'] = $truncated;
            }
            $attempt++;
        }

        return $embed_copy;
    }

    /**
     * Truncate string to Discord embed field limit (1024 chars).
     *
     * @param string $str Value to truncate.
     * @param int $max Max length (default DISCORD_FIELD_MAX).
     * @return string Truncated string.
     */
    private function truncate_discord_field(string $str, int $max = self::DISCORD_FIELD_MAX): string {
        $len = function_exists('mb_strlen') ? mb_strlen($str, 'UTF-8') : strlen($str);
        if ($len <= $max) {
            return $str;
        }
        $cut = function_exists('mb_substr') ? mb_substr($str, 0, $max - 3, 'UTF-8') : substr($str, 0, $max - 3);
        return $cut . '...';
    }

    /**
     * Get formatted product/item meta for Discord embed.
     *
     * @param array<int, WC_Order_Item> $order_items Order items from WC_Order::get_items().
     * @return string Formatted product meta or empty string.
     */
    private function get_product_meta_for_embed(array $order_items): string {
        $lines = [];
        foreach ($order_items as $item) {
            if (!method_exists($item, 'get_formatted_meta_data')) {
                continue;
            }
            $meta_data = $item->get_formatted_meta_data('_', true);
            if (empty($meta_data)) {
                continue;
            }
            $item_name = wp_strip_all_tags($item->get_name());
            foreach ($meta_data as $meta) {
                $key = isset($meta->display_key) ? $meta->display_key : '';
                $val = isset($meta->display_value) ? wp_strip_all_tags($meta->display_value) : '';
                if ($key !== '') {
                    $lines[] = "**{$item_name}**: {$key} » {$val}";
                }
            }
        }
        return implode("\n", $lines);
    }

    /**
     * POST embed payload to Discord webhook URL.
     *
     * @param string $webhook_url Discord webhook URL.
     * @param array  $embed       Embed array (title, fields, color, image).
     */
    private function send_to_discord($webhook_url, $embed) {
        if (!empty($embed['fields']) && is_array($embed['fields'])) {
            foreach ($embed['fields'] as $idx => $field) {
                if (is_array($field) && array_key_exists('internal_id', $field)) {
                    unset($embed['fields'][$idx]['internal_id']);
                }
            }
        }
        $data = wp_json_encode(['embeds' => [$embed]]);

        $args = [
            'body'    => $data,
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 10,
            'blocking' => false,
        ];
        if (get_option('wc_sale_discord_force_blocking')) {
            $args['blocking'] = true;
            $args['timeout']  = 60;
        }

        $response = wp_remote_post($webhook_url, $args);
        if (is_wp_error($response)) {
            if (function_exists('wc_get_logger')) {
                wc_get_logger()->error(
                    'Discord webhook failed: ' . $response->get_error_message(),
                    array('source' => 'wc-sale-discord-notifications')
                );
            }
        } elseif (wp_remote_retrieve_response_code($response) >= 400) {
            if (function_exists('wc_get_logger')) {
                $body = wp_remote_retrieve_body($response);
                wc_get_logger()->error(
                    'Discord webhook returned ' . wp_remote_retrieve_response_code($response) . ': ' . substr($body, 0, 500),
                    array('source' => 'wc-sale-discord-notifications')
                );
            }
        }
    }

    public function plugin_action_links($links) {
        $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">' . esc_html__('Settings', 'wc-sale-discord-notifications') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

new Sale_Discord_Notifications_Woo();