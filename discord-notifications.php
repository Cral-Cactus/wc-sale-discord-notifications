<?php
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

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DiscordSaleNotifications {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'settings_page' ) );
        add_action( 'admin_init', array( $this, 'save_settings' ) );
        add_action( 'woocommerce_payment_complete', array( $this, 'send_notification' ) );
    }

    public function settings_page() {
        add_submenu_page( 'options-general.php', __('Discord Sale Notifications', 'wc-discord-sale-notifications'), __('Discord Sale Notifications', 'wc-discord-sale-notifications'), 'manage_options', 'wc-discord-sale-notifications', array( $this, 'notification_settings' ) );
    }

    public function notification_settings() {
        $settings = array(
            array(
                'id' => 'ppsndw_woo_webhook',
                'label' => __('Webhook URL', 'wc-discord-sale-notifications' ),
                'type' => 'text',
                'desc' => ''
            )
        );

        echo '<div class="wrap">';
        echo '<h2>' . __('Discord Sale Notifications', 'wc-discord-sale-notifications') . '</h2>';
        echo '<form method="post" action="options.php">';
        settings_fields( 'ppsndw_discord_woo' );
        do_settings_sections( 'ppsndw_discord_woo' );
        echo '<table class="form-table">';
        foreach( $settings as $setting ){
            echo '<tr valign="top">';
            echo '<th scope="row">' . $setting['label'] . '</th>';
            echo '<td>';
            if( $setting['type'] == 'checkbox' ){
                echo '<input type="checkbox" name="' . $setting['id'] . '" value="1" ' . checked( get_option( $setting['id'] ), 1, false ) . ' />';
            } else {
                echo '<input type="text" name="' . $setting['id'] . '" value="' . get_option( $setting['id'] ) . '" />';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function save_settings() {
        $settings = array(
            array(
                'id' => 'ppsndw_woo_webhook',
                'label' => __('Webhook URL', 'wc-discord-sale-notifications' ),
                'type' => 'text',
                'desc' => ''
            )
        );
    }
}