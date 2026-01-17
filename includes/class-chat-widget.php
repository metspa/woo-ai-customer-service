<?php
/**
 * Chat Widget Class
 *
 * Handles frontend chat widget display and assets
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_AI_Chat_Widget {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'render_widget'));
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // Only load on frontend, not admin
        if (is_admin()) {
            return;
        }

        // Check if chat is enabled
        if (!$this->is_chat_enabled()) {
            return;
        }

        wp_enqueue_style(
            'woo-ai-chat-widget',
            WOO_AI_CHAT_URL . 'assets/css/chat-widget.css',
            array(),
            WOO_AI_CHAT_VERSION
        );

        wp_enqueue_script(
            'woo-ai-chat-widget',
            WOO_AI_CHAT_URL . 'assets/js/chat-widget.js',
            array(),
            WOO_AI_CHAT_VERSION,
            true
        );

        // Pass config to JavaScript
        wp_localize_script('woo-ai-chat-widget', 'wooAiChatConfig', array(
            'restUrl' => rest_url('woo-ai-chat/v1'),
            'position' => get_option('woo_ai_chat_position', 'bottom-right'),
            'primaryColor' => get_option('woo_ai_chat_primary_color', '#2d5a27'),
            'secondaryColor' => get_option('woo_ai_chat_secondary_color', '#ffffff'),
            'widgetTitle' => get_option('woo_ai_chat_widget_title', 'Chat with us'),
            'formTitle' => get_option('woo_ai_chat_form_title', "Let's get started!"),
            'formSubtitle' => get_option('woo_ai_chat_form_subtitle', 'Please enter your info so we can better assist you'),
            'phoneRequired' => (bool) get_option('woo_ai_chat_phone_required', false),
            'supportEmail' => get_option('woo_ai_chat_support_email', 'admin@organicskincare.com'),
            'supportPhone' => get_option('woo_ai_chat_support_phone', '516-322-9380'),
            'businessName' => get_option('woo_ai_chat_business_name', 'Organic Skincare'),
        ));
    }

    /**
     * Render chat widget HTML
     */
    public function render_widget() {
        // Only render on frontend
        if (is_admin()) {
            return;
        }

        // Check if chat is enabled
        if (!$this->is_chat_enabled()) {
            return;
        }

        // Load template
        $template = WOO_AI_CHAT_PATH . 'templates/chat-widget.php';
        if (file_exists($template)) {
            include $template;
        }
    }

    /**
     * Check if chat widget is enabled
     *
     * @return bool Whether chat is enabled
     */
    private function is_chat_enabled() {
        // Check if API key is configured
        $api_key = get_option('woo_ai_chat_api_key', '');
        if (empty($api_key)) {
            return false;
        }

        // Check if widget is enabled in settings
        $enabled = get_option('woo_ai_chat_enabled', true);
        if (!$enabled) {
            return false;
        }

        // Allow filtering
        return apply_filters('woo_ai_chat_widget_enabled', true);
    }
}
