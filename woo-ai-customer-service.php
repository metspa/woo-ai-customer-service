<?php
/**
 * Plugin Name: WooCommerce AI Customer Service
 * Plugin URI: https://organicskincare.com
 * Description: AI-powered customer service chatbot with lead capture using Claude API
 * Version: 1.0.0
 * Author: Organic Skincare
 * Author URI: https://organicskincare.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-ai-customer-service
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WOO_AI_CHAT_VERSION', '1.0.0');
define('WOO_AI_CHAT_PATH', plugin_dir_path(__FILE__));
define('WOO_AI_CHAT_URL', plugin_dir_url(__FILE__));
define('WOO_AI_CHAT_BASENAME', plugin_basename(__FILE__));

/**
 * Check if WooCommerce is active
 */
function woo_ai_chat_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'woo_ai_chat_woocommerce_notice');
        return false;
    }
    return true;
}

/**
 * Admin notice for missing WooCommerce
 */
function woo_ai_chat_woocommerce_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('WooCommerce AI Customer Service requires WooCommerce to be installed and active.', 'woo-ai-customer-service'); ?></p>
    </div>
    <?php
}

/**
 * Create leads database table on activation
 */
function woo_ai_chat_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_chat_leads';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(50),
        user_id bigint(20) DEFAULT NULL,
        session_id varchar(100) NOT NULL,
        source varchar(50) DEFAULT 'chat_widget',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        last_contact datetime DEFAULT CURRENT_TIMESTAMP,
        notes text,
        PRIMARY KEY (id),
        KEY email (email),
        KEY user_id (user_id),
        KEY session_id (session_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Create conversations table
    $conversations_table = $wpdb->prefix . 'ai_chat_conversations';
    $sql2 = "CREATE TABLE IF NOT EXISTS $conversations_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        session_id varchar(100) NOT NULL,
        lead_id bigint(20) DEFAULT NULL,
        customer_email varchar(255) NOT NULL,
        customer_name varchar(200) NOT NULL,
        messages longtext NOT NULL,
        message_count int(11) DEFAULT 0,
        started_at datetime DEFAULT CURRENT_TIMESTAMP,
        ended_at datetime DEFAULT NULL,
        status varchar(20) DEFAULT 'active',
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY lead_id (lead_id),
        KEY customer_email (customer_email),
        KEY started_at (started_at)
    ) $charset_collate;";
    dbDelta($sql2);

    // Set default options
    $defaults = array(
        'woo_ai_chat_model' => 'claude-haiku-4-5-20251001',
        'woo_ai_chat_max_tokens' => 1024,
        'woo_ai_chat_business_name' => 'Organic Skincare',
        'woo_ai_chat_support_email' => 'admin@organicskincare.com',
        'woo_ai_chat_support_phone' => '516-322-9380',
        'woo_ai_chat_business_hours' => 'Mon-Fri 9am-6pm EST',
        'woo_ai_chat_position' => 'bottom-right',
        'woo_ai_chat_primary_color' => '#2d5a27',
        'woo_ai_chat_secondary_color' => '#ffffff',
        'woo_ai_chat_widget_title' => 'Chat with us',
        'woo_ai_chat_form_title' => "Let's get started!",
        'woo_ai_chat_form_subtitle' => 'Please enter your info so we can better assist you',
        'woo_ai_chat_phone_required' => false,
        'woo_ai_chat_lead_capture_enabled' => true,
        'woo_ai_chat_welcome_message' => "Hi {first_name}! Welcome to Organic Skincare. I'm here to help with your orders, product questions, or anything else. How can I assist you today?",
        'woo_ai_chat_fallback_message' => "I'm having trouble connecting right now. Please contact us directly at admin@organicskincare.com or call/text 516-322-9380 and we'll be happy to help!",
        'woo_ai_chat_notifications_enabled' => false,
        'woo_ai_chat_notification_emails' => '',
        'woo_ai_chat_notify_on_start' => true,
        'woo_ai_chat_notify_on_message' => false,
    );

    foreach ($defaults as $option => $value) {
        if (get_option($option) === false) {
            add_option($option, $value);
        }
    }

    // Flush rewrite rules for REST API
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'woo_ai_chat_activate');

/**
 * Clean up on deactivation
 */
function woo_ai_chat_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'woo_ai_chat_deactivate');

/**
 * Initialize the plugin
 */
function woo_ai_chat_init() {
    // Check for WooCommerce
    if (!woo_ai_chat_check_woocommerce()) {
        return;
    }

    // Load class files
    require_once WOO_AI_CHAT_PATH . 'includes/class-lead-capture.php';
    require_once WOO_AI_CHAT_PATH . 'includes/class-order-tracking.php';
    require_once WOO_AI_CHAT_PATH . 'includes/class-customer-context.php';
    require_once WOO_AI_CHAT_PATH . 'includes/class-chat-api.php';
    require_once WOO_AI_CHAT_PATH . 'includes/class-conversation-logger.php';
    require_once WOO_AI_CHAT_PATH . 'includes/class-notifications.php';
    require_once WOO_AI_CHAT_PATH . 'includes/class-rest-endpoints.php';
    require_once WOO_AI_CHAT_PATH . 'includes/class-chat-widget.php';
    require_once WOO_AI_CHAT_PATH . 'includes/class-admin-settings.php';

    // Initialize components
    new Woo_AI_Chat_REST_Endpoints();
    new Woo_AI_Chat_Widget();

    if (is_admin()) {
        new Woo_AI_Chat_Admin_Settings();
    }
}
add_action('plugins_loaded', 'woo_ai_chat_init');

/**
 * Declare WooCommerce feature compatibility (HPOS, Blocks, etc.)
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        // High-Performance Order Storage (HPOS) compatibility
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        // Cart and Checkout Blocks compatibility
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

/**
 * Add settings link to plugins page
 */
function woo_ai_chat_plugin_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=woo-ai-chat') . '">' . __('Settings', 'woo-ai-customer-service') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . WOO_AI_CHAT_BASENAME, 'woo_ai_chat_plugin_links');
