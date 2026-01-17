<?php
/**
 * Admin Settings Class
 *
 * Handles admin settings page and leads management
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_AI_Chat_Admin_Settings {

    /**
     * Lead capture instance
     */
    private $lead_capture;

    /**
     * Conversation logger instance
     */
    private $conversation_logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->lead_capture = new Woo_AI_Chat_Lead_Capture();
        $this->conversation_logger = new Woo_AI_Chat_Conversation_Logger();

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'handle_export'));
        add_action('admin_init', array($this, 'handle_delete_lead'));
        add_action('wp_ajax_woo_ai_chat_test_api', array($this, 'ajax_test_api'));
        add_action('wp_ajax_woo_ai_chat_save_api_key', array($this, 'ajax_save_api_key'));
        add_action('wp_ajax_woo_ai_chat_update_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_woo_ai_chat_delete_conversation', array($this, 'ajax_delete_conversation'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main settings page
        add_menu_page(
            __('AI Chat Settings', 'woo-ai-customer-service'),
            __('AI Customer Chat', 'woo-ai-customer-service'),
            'manage_options',
            'woo-ai-chat',
            array($this, 'render_settings_page'),
            'dashicons-format-chat',
            56
        );

        // Settings submenu (same as main)
        add_submenu_page(
            'woo-ai-chat',
            __('Settings', 'woo-ai-customer-service'),
            __('Settings', 'woo-ai-customer-service'),
            'manage_options',
            'woo-ai-chat',
            array($this, 'render_settings_page')
        );

        // Leads submenu
        add_submenu_page(
            'woo-ai-chat',
            __('Chat Leads', 'woo-ai-customer-service'),
            __('Leads', 'woo-ai-customer-service'),
            'manage_options',
            'woo-ai-chat-leads',
            array($this, 'render_leads_page')
        );

        // Conversations submenu
        add_submenu_page(
            'woo-ai-chat',
            __('Conversations', 'woo-ai-customer-service'),
            __('Conversations', 'woo-ai-customer-service'),
            'manage_options',
            'woo-ai-chat-conversations',
            array($this, 'render_conversations_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // API Settings
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_api_key', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_api_key'),
        ));
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_model');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_max_tokens', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
        ));

        // Business Settings
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_business_name');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_support_email', array(
            'sanitize_callback' => 'sanitize_email',
        ));
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_support_phone');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_business_hours');

        // Lead Capture Settings
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_lead_capture_enabled');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_phone_required');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_form_title');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_form_subtitle');

        // Chatbot Settings
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_system_prompt');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_welcome_message');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_fallback_message');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_max_messages', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
        ));

        // Widget Appearance
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_enabled');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_position');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_primary_color');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_secondary_color');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_widget_title');

        // Notification Settings
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_notifications_enabled');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_notification_emails', array(
            'sanitize_callback' => array($this, 'sanitize_notification_emails'),
        ));
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_notify_on_start');
        register_setting('woo_ai_chat_settings', 'woo_ai_chat_notify_on_message');
    }

    /**
     * Sanitize notification emails (comma-separated)
     */
    public function sanitize_notification_emails($value) {
        if (empty($value)) {
            return '';
        }

        $emails = array_map('trim', explode(',', $value));
        $valid_emails = array();

        foreach ($emails as $email) {
            if (is_email($email)) {
                $valid_emails[] = sanitize_email($email);
            }
        }

        return implode(', ', $valid_emails);
    }

    /**
     * Sanitize API key before saving
     */
    public function sanitize_api_key($value) {
        // If empty, KEEP the existing key (don't overwrite with nothing)
        if (empty($value)) {
            return get_option('woo_ai_chat_api_key', '');
        }

        // Trim whitespace
        $value = trim($value);

        // If still empty after trim, keep existing
        if (empty($value)) {
            return get_option('woo_ai_chat_api_key', '');
        }

        // If user submitted placeholder dots, keep existing key
        if (preg_match('/^[•\.]+$/', $value) || strpos($value, '•') !== false) {
            return get_option('woo_ai_chat_api_key', '');
        }

        // If already in our V3 format, keep it
        if (strpos($value, 'V3_') === 0) {
            return $value;
        }

        // If already in WOOAI format, keep it
        if (strpos($value, 'WOOAI_') === 0) {
            return $value;
        }

        // If it's a raw API key, encrypt it
        if (strpos($value, 'sk-') === 0) {
            return Woo_AI_Chat_API::encrypt($value);
        }

        // For anything else, keep existing key (don't save garbage)
        return get_option('woo_ai_chat_api_key', '');
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'woo-ai-chat') === false) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Add timestamp to bust cache
        $version = WOO_AI_CHAT_VERSION . '.' . time();

        wp_enqueue_style(
            'woo-ai-chat-admin',
            WOO_AI_CHAT_URL . 'assets/css/admin.css',
            array(),
            $version
        );

        wp_enqueue_script(
            'woo-ai-chat-admin',
            WOO_AI_CHAT_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            $version,
            true
        );

        wp_localize_script('woo-ai-chat-admin', 'wooAiChatAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woo_ai_chat_admin'),
        ));
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check for settings saved message
        if (isset($_GET['settings-updated'])) {
            add_settings_error('woo_ai_chat_messages', 'woo_ai_chat_message', __('Settings saved.', 'woo-ai-customer-service'), 'updated');
        }

        // Get current values
        $api_key = get_option('woo_ai_chat_api_key', '');
        $has_api_key = !empty($api_key);
        ?>
        <div class="wrap woo-ai-chat-settings">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('woo_ai_chat_messages'); ?>

            <form method="post" action="options.php">
                <?php settings_fields('woo_ai_chat_settings'); ?>

                <!-- API Settings -->
                <div class="woo-ai-chat-section">
                    <h2><?php _e('API Settings', 'woo-ai-customer-service'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_api_key"><?php _e('Anthropic API Key', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="woo_ai_chat_api_key" name="woo_ai_chat_api_key"
                                    value=""
                                    class="regular-text"
                                    placeholder="sk-ant-api03-..."
                                    autocomplete="off"
                                    style="font-family: monospace;">
                                <button type="button" id="save-api-key" class="button button-primary" style="margin-left: 10px;">
                                    <?php _e('Save API Key', 'woo-ai-customer-service'); ?>
                                </button>
                                <div id="api-key-status" style="margin-top: 10px;">
                                    <?php if ($has_api_key): ?>
                                        <span style="color: green; font-weight: bold;">✓ API key is saved</span>
                                    <?php else: ?>
                                        <span style="color: #999;">No API key saved yet</span>
                                    <?php endif; ?>
                                </div>
                                <p class="description" style="margin-top: 8px;">
                                    <?php _e('Get your API key from', 'woo-ai-customer-service'); ?>
                                    <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_model"><?php _e('Model', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <select id="woo_ai_chat_model" name="woo_ai_chat_model">
                                    <option value="claude-haiku-4-5-20251001" <?php selected(get_option('woo_ai_chat_model'), 'claude-haiku-4-5-20251001'); ?>>
                                        Claude Haiku (Fastest, Most Cost-Effective)
                                    </option>
                                    <option value="claude-sonnet-4-20250514" <?php selected(get_option('woo_ai_chat_model'), 'claude-sonnet-4-20250514'); ?>>
                                        Claude Sonnet (Balanced)
                                    </option>
                                </select>
                                <p class="description"><?php _e('Haiku is recommended for customer service (fast & affordable).', 'woo-ai-customer-service'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_max_tokens"><?php _e('Max Response Length', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="woo_ai_chat_max_tokens" name="woo_ai_chat_max_tokens"
                                    value="<?php echo esc_attr(get_option('woo_ai_chat_max_tokens', 1024)); ?>"
                                    min="256" max="4096" step="256">
                                <p class="description"><?php _e('Maximum tokens for AI responses. 1024 is recommended.', 'woo-ai-customer-service'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Connection Status', 'woo-ai-customer-service'); ?></th>
                            <td>
                                <p class="description" style="margin-bottom: 10px;"><?php _e('Click to verify your API key is working with Anthropic.', 'woo-ai-customer-service'); ?></p>
                                <button type="button" id="test-api-connection" class="button button-primary" style="font-size: 14px; padding: 6px 20px;">
                                    <?php _e('Test Connection', 'woo-ai-customer-service'); ?>
                                </button>
                                <div id="api-test-result"></div>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Business Information -->
                <div class="woo-ai-chat-section">
                    <h2><?php _e('Business Information', 'woo-ai-customer-service'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_business_name"><?php _e('Business Name', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="woo_ai_chat_business_name" name="woo_ai_chat_business_name"
                                    value="<?php echo esc_attr(get_option('woo_ai_chat_business_name', 'Organic Skincare')); ?>"
                                    class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_support_email"><?php _e('Support Email', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <input type="email" id="woo_ai_chat_support_email" name="woo_ai_chat_support_email"
                                    value="<?php echo esc_attr(get_option('woo_ai_chat_support_email', 'admin@organicskincare.com')); ?>"
                                    class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_support_phone"><?php _e('Support Phone', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="woo_ai_chat_support_phone" name="woo_ai_chat_support_phone"
                                    value="<?php echo esc_attr(get_option('woo_ai_chat_support_phone', '516-322-9380')); ?>"
                                    class="regular-text">
                                <p class="description"><?php _e('Customers can call or text this number.', 'woo-ai-customer-service'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_business_hours"><?php _e('Business Hours', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="woo_ai_chat_business_hours" name="woo_ai_chat_business_hours"
                                    value="<?php echo esc_attr(get_option('woo_ai_chat_business_hours', 'Mon-Fri 9am-6pm EST')); ?>"
                                    class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Lead Capture Settings -->
                <div class="woo-ai-chat-section">
                    <h2><?php _e('Lead Capture', 'woo-ai-customer-service'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Phone Number', 'woo-ai-customer-service'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="woo_ai_chat_phone_required" value="1"
                                        <?php checked(get_option('woo_ai_chat_phone_required', false)); ?>>
                                    <?php _e('Require phone number', 'woo-ai-customer-service'); ?>
                                </label>
                                <p class="description"><?php _e('If unchecked, phone will be optional.', 'woo-ai-customer-service'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_form_title"><?php _e('Form Title', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="woo_ai_chat_form_title" name="woo_ai_chat_form_title"
                                    value="<?php echo esc_attr(get_option('woo_ai_chat_form_title', "Let's get started!")); ?>"
                                    class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_form_subtitle"><?php _e('Form Subtitle', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="woo_ai_chat_form_subtitle" name="woo_ai_chat_form_subtitle"
                                    value="<?php echo esc_attr(get_option('woo_ai_chat_form_subtitle', 'Please enter your info so we can better assist you')); ?>"
                                    class="wide-text" style="width: 100%; max-width: 500px;">
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Chatbot Persona -->
                <div class="woo-ai-chat-section">
                    <h2><?php _e('Chatbot Messages', 'woo-ai-customer-service'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_welcome_message"><?php _e('Welcome Message', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <textarea id="woo_ai_chat_welcome_message" name="woo_ai_chat_welcome_message"
                                    rows="3" class="large-text"><?php echo esc_textarea(get_option('woo_ai_chat_welcome_message', "Hi {first_name}! Welcome to Organic Skincare. I'm here to help with your orders, product questions, or anything else. How can I assist you today?")); ?></textarea>
                                <p class="description"><?php _e('Available placeholders: {first_name}, {last_name}, {email}', 'woo-ai-customer-service'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_fallback_message"><?php _e('Fallback Message', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <textarea id="woo_ai_chat_fallback_message" name="woo_ai_chat_fallback_message"
                                    rows="3" class="large-text"><?php echo esc_textarea(get_option('woo_ai_chat_fallback_message', "I'm having trouble connecting right now. Please contact us directly at admin@organicskincare.com or call/text 516-322-9380 and we'll be happy to help!")); ?></textarea>
                                <p class="description"><?php _e('Shown when the AI cannot respond.', 'woo-ai-customer-service'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_system_prompt"><?php _e('Additional Instructions', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <textarea id="woo_ai_chat_system_prompt" name="woo_ai_chat_system_prompt"
                                    rows="5" class="large-text"><?php echo esc_textarea(get_option('woo_ai_chat_system_prompt', '')); ?></textarea>
                                <p class="description"><?php _e('Optional. Add custom instructions for the AI (e.g., specific product info, policies).', 'woo-ai-customer-service'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_max_messages"><?php _e('Max Messages Per Session', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="woo_ai_chat_max_messages" name="woo_ai_chat_max_messages"
                                    value="<?php echo esc_attr(get_option('woo_ai_chat_max_messages', 30)); ?>"
                                    min="5" max="100">
                                <p class="description"><?php _e('Rate limiting to prevent abuse.', 'woo-ai-customer-service'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Widget Appearance -->
                <div class="woo-ai-chat-section">
                    <h2><?php _e('Widget Appearance', 'woo-ai-customer-service'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Chat Widget', 'woo-ai-customer-service'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="woo_ai_chat_enabled" value="1"
                                        <?php checked(get_option('woo_ai_chat_enabled', true)); ?>>
                                    <?php _e('Show chat widget on frontend', 'woo-ai-customer-service'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_position"><?php _e('Widget Position', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <select id="woo_ai_chat_position" name="woo_ai_chat_position">
                                    <option value="bottom-right" <?php selected(get_option('woo_ai_chat_position'), 'bottom-right'); ?>>
                                        <?php _e('Bottom Right', 'woo-ai-customer-service'); ?>
                                    </option>
                                    <option value="bottom-left" <?php selected(get_option('woo_ai_chat_position'), 'bottom-left'); ?>>
                                        <?php _e('Bottom Left', 'woo-ai-customer-service'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_widget_title"><?php _e('Widget Title', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="woo_ai_chat_widget_title" name="woo_ai_chat_widget_title"
                                    value="<?php echo esc_attr(get_option('woo_ai_chat_widget_title', 'Chat with us')); ?>"
                                    class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_primary_color"><?php _e('Primary Color', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="woo_ai_chat_primary_color" name="woo_ai_chat_primary_color"
                                    value="<?php echo esc_attr(get_option('woo_ai_chat_primary_color', '#2d5a27')); ?>"
                                    class="woo-ai-chat-color-picker">
                                <p class="description"><?php _e('Button and header color.', 'woo-ai-customer-service'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_secondary_color"><?php _e('Secondary Color', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="woo_ai_chat_secondary_color" name="woo_ai_chat_secondary_color"
                                    value="<?php echo esc_attr(get_option('woo_ai_chat_secondary_color', '#ffffff')); ?>"
                                    class="woo-ai-chat-color-picker">
                                <p class="description"><?php _e('Text and icon color.', 'woo-ai-customer-service'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Email Notifications -->
                <div class="woo-ai-chat-section">
                    <h2><?php _e('Email Notifications', 'woo-ai-customer-service'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable Notifications', 'woo-ai-customer-service'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="woo_ai_chat_notifications_enabled" value="1"
                                        <?php checked(get_option('woo_ai_chat_notifications_enabled', false)); ?>>
                                    <?php _e('Send email notifications for chat activity', 'woo-ai-customer-service'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="woo_ai_chat_notification_emails"><?php _e('Notification Emails', 'woo-ai-customer-service'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="woo_ai_chat_notification_emails" name="woo_ai_chat_notification_emails"
                                    value="<?php echo esc_attr(get_option('woo_ai_chat_notification_emails', '')); ?>"
                                    class="large-text"
                                    placeholder="admin@example.com, support@example.com">
                                <p class="description">
                                    <?php _e('Comma-separated list of email addresses to receive notifications. Leave empty to use the admin email.', 'woo-ai-customer-service'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Notify When', 'woo-ai-customer-service'); ?></th>
                            <td>
                                <fieldset>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="woo_ai_chat_notify_on_start" value="1"
                                            <?php checked(get_option('woo_ai_chat_notify_on_start', true)); ?>>
                                        <?php _e('New conversation starts (customer submits lead form)', 'woo-ai-customer-service'); ?>
                                    </label>
                                    <label style="display: block;">
                                        <input type="checkbox" name="woo_ai_chat_notify_on_message" value="1"
                                            <?php checked(get_option('woo_ai_chat_notify_on_message', false)); ?>>
                                        <?php _e('Customer sends a message (can generate many emails)', 'woo-ai-customer-service'); ?>
                                    </label>
                                </fieldset>
                                <p class="description">
                                    <?php _e('Tip: Enable "new conversation" notifications to be alerted when customers start chatting.', 'woo-ai-customer-service'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render leads page
     */
    public function render_leads_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $leads = $this->lead_capture->get_all_leads();
        $total = $this->lead_capture->get_lead_count();
        ?>
        <div class="wrap">
            <h1>
                <?php _e('Chat Leads', 'woo-ai-customer-service'); ?>
                <span class="title-count theme-count"><?php echo esc_html($total); ?></span>
            </h1>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Lead deleted successfully.', 'woo-ai-customer-service'); ?></p>
                </div>
            <?php endif; ?>

            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-chat-leads&export=csv&_wpnonce=' . wp_create_nonce('export_leads'))); ?>" class="button">
                    <span class="dashicons dashicons-download" style="vertical-align: middle;"></span>
                    <?php _e('Export CSV', 'woo-ai-customer-service'); ?>
                </a>
            </p>

            <?php if (empty($leads)): ?>
                <div class="notice notice-info">
                    <p><?php _e('No leads captured yet. Leads will appear here when customers use the chat widget.', 'woo-ai-customer-service'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'woo-ai-customer-service'); ?></th>
                            <th><?php _e('Email', 'woo-ai-customer-service'); ?></th>
                            <th><?php _e('Phone', 'woo-ai-customer-service'); ?></th>
                            <th><?php _e('Source', 'woo-ai-customer-service'); ?></th>
                            <th><?php _e('First Contact', 'woo-ai-customer-service'); ?></th>
                            <th><?php _e('Last Contact', 'woo-ai-customer-service'); ?></th>
                            <th style="width: 80px;"><?php _e('Actions', 'woo-ai-customer-service'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($lead->first_name . ' ' . $lead->last_name); ?></strong>
                            </td>
                            <td>
                                <a href="mailto:<?php echo esc_attr($lead->email); ?>">
                                    <?php echo esc_html($lead->email); ?>
                                </a>
                            </td>
                            <td>
                                <?php if (!empty($lead->phone)): ?>
                                    <a href="tel:<?php echo esc_attr($lead->phone); ?>">
                                        <?php echo esc_html($lead->phone); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($lead->source); ?></td>
                            <td><?php echo esc_html(date('M j, Y g:i a', strtotime($lead->created_at))); ?></td>
                            <td><?php echo esc_html(date('M j, Y g:i a', strtotime($lead->last_contact))); ?></td>
                            <td>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=woo-ai-chat-leads&action=delete&lead_id=' . $lead->id), 'delete_lead_' . $lead->id)); ?>"
                                   class="button button-small"
                                   onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this lead?', 'woo-ai-customer-service'); ?>');">
                                    <?php _e('Delete', 'woo-ai-customer-service'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle CSV export
     */
    public function handle_export() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'woo-ai-chat-leads') {
            return;
        }

        if (!isset($_GET['export']) || $_GET['export'] !== 'csv') {
            return;
        }

        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'export_leads')) {
            wp_die(__('Security check failed.', 'woo-ai-customer-service'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export leads.', 'woo-ai-customer-service'));
        }

        $csv = $this->lead_capture->export_leads_csv();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="chat-leads-' . date('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $csv;
        exit;
    }

    /**
     * Handle delete lead action
     */
    public function handle_delete_lead() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'woo-ai-chat-leads') {
            return;
        }

        if (!isset($_GET['action']) || $_GET['action'] !== 'delete') {
            return;
        }

        if (!isset($_GET['lead_id'])) {
            return;
        }

        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'delete_lead_' . $_GET['lead_id'])) {
            wp_die(__('Security check failed.', 'woo-ai-customer-service'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete leads.', 'woo-ai-customer-service'));
        }

        $lead_id = absint($_GET['lead_id']);
        $this->lead_capture->delete_lead($lead_id);

        wp_redirect(admin_url('admin.php?page=woo-ai-chat-leads&deleted=1'));
        exit;
    }

    /**
     * AJAX handler to directly save API key (bypasses WordPress settings API)
     */
    public function ajax_save_api_key() {
        check_ajax_referer('woo_ai_chat_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'No API key provided'));
        }

        if (strpos($api_key, 'sk-') !== 0) {
            wp_send_json_error(array('message' => 'Invalid API key format. Must start with sk-'));
        }

        // Directly save to database with V3 encoding
        $encoded = 'V3_' . base64_encode($api_key);
        $result = update_option('woo_ai_chat_api_key', $encoded);

        if ($result) {
            wp_send_json_success(array('message' => 'API key saved successfully! Now click Test Connection.'));
        } else {
            // Check if it's already the same value
            $current = get_option('woo_ai_chat_api_key', '');
            if ($current === $encoded) {
                wp_send_json_success(array('message' => 'API key is already saved. Now click Test Connection.'));
            }
            wp_send_json_error(array('message' => 'Failed to save API key to database'));
        }
    }

    public function ajax_test_api() {
        check_ajax_referer('woo_ai_chat_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Debug: Check what's stored in the database
        $stored_key = get_option('woo_ai_chat_api_key', '');
        $key_info = '';
        if (empty($stored_key)) {
            $key_info = 'No key stored in database';
        } elseif (strpos($stored_key, 'V3_') === 0) {
            $key_info = 'Key format: V3 (current)';
        } elseif (strpos($stored_key, 'WOOAI_') === 0) {
            $key_info = 'Key format: WOOAI';
        } else {
            $key_info = 'Key format: legacy/unknown (first 10 chars: ' . substr($stored_key, 0, 10) . '...)';
        }

        $chat_api = new Woo_AI_Chat_API();
        $result = $chat_api->test_connection();

        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            // Add debug info to error message
            wp_send_json_error(array('message' => $result['message'] . ' [Debug: ' . $key_info . ']'));
        }
    }

    /**
     * AJAX handler for updating conversation status
     */
    public function ajax_update_status() {
        check_ajax_referer('woo_ai_chat_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $conversation_id = isset($_POST['conversation_id']) ? absint($_POST['conversation_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$conversation_id || !$status) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
        }

        $result = $this->conversation_logger->update_status($conversation_id, $status);

        if ($result) {
            wp_send_json_success(array(
                'message' => 'Status updated successfully',
                'status' => $status,
                'label' => Woo_AI_Chat_Conversation_Logger::$statuses[$status] ?? $status,
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to update status'));
        }
    }

    /**
     * AJAX handler for deleting conversation
     */
    public function ajax_delete_conversation() {
        check_ajax_referer('woo_ai_chat_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        $conversation_id = isset($_POST['conversation_id']) ? absint($_POST['conversation_id']) : 0;

        if (!$conversation_id) {
            wp_send_json_error(array('message' => 'Invalid conversation ID'));
        }

        $result = $this->conversation_logger->delete($conversation_id);

        if ($result) {
            wp_send_json_success(array('message' => 'Conversation deleted successfully'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete conversation'));
        }
    }

    /**
     * Render conversations page
     */
    public function render_conversations_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if viewing a specific conversation
        if (isset($_GET['view']) && is_numeric($_GET['view'])) {
            $this->render_single_conversation(absint($_GET['view']));
            return;
        }

        // Get filter parameters
        $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // Get conversations based on filters
        if (!empty($search_term)) {
            $conversations = $this->conversation_logger->search($search_term, $current_status);
        } else {
            $conversations = $this->conversation_logger->get_filtered($current_status);
        }

        $status_counts = $this->conversation_logger->get_status_counts();
        $statuses = Woo_AI_Chat_Conversation_Logger::$statuses;
        $stats = $this->conversation_logger->get_stats();
        ?>
        <div class="wrap woo-ai-chat-conversations-page">
            <h1><?php _e('Chat Conversations', 'woo-ai-customer-service'); ?></h1>

            <!-- Stats Overview -->
            <div class="woo-ai-chat-stats" style="display: flex; gap: 15px; margin: 20px 0; flex-wrap: wrap;">
                <div style="background: #fff; padding: 12px 20px; border: 1px solid #ccd0d4; border-radius: 4px; min-width: 100px;">
                    <div style="font-size: 24px; font-weight: 600; color: #2d5a27;"><?php echo esc_html($stats['today']); ?></div>
                    <div style="color: #666; font-size: 12px;"><?php _e('Today', 'woo-ai-customer-service'); ?></div>
                </div>
                <div style="background: #fff; padding: 12px 20px; border: 1px solid #ccd0d4; border-radius: 4px; min-width: 100px;">
                    <div style="font-size: 24px; font-weight: 600; color: #2d5a27;"><?php echo esc_html($stats['this_week']); ?></div>
                    <div style="color: #666; font-size: 12px;"><?php _e('This Week', 'woo-ai-customer-service'); ?></div>
                </div>
                <div style="background: #fff; padding: 12px 20px; border: 1px solid #ccd0d4; border-radius: 4px; min-width: 100px;">
                    <div style="font-size: 24px; font-weight: 600; color: #50575e;"><?php echo esc_html($stats['avg_messages']); ?></div>
                    <div style="color: #666; font-size: 12px;"><?php _e('Avg Messages', 'woo-ai-customer-service'); ?></div>
                </div>
            </div>

            <!-- Status Filter Tabs -->
            <ul class="subsubsub" style="margin-bottom: 10px;">
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-chat-conversations')); ?>"
                       class="<?php echo $current_status === 'all' ? 'current' : ''; ?>">
                        <?php _e('All', 'woo-ai-customer-service'); ?>
                        <span class="count">(<?php echo esc_html($status_counts['all']); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-chat-conversations&status=needs_attention')); ?>"
                       class="<?php echo $current_status === 'needs_attention' ? 'current' : ''; ?>"
                       style="color: #d63638;">
                        <?php _e('Needs Attention', 'woo-ai-customer-service'); ?>
                        <span class="count">(<?php echo esc_html($status_counts['needs_attention']); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-chat-conversations&status=waiting')); ?>"
                       class="<?php echo $current_status === 'waiting' ? 'current' : ''; ?>"
                       style="color: #dba617;">
                        <?php _e('Waiting for Reply', 'woo-ai-customer-service'); ?>
                        <span class="count">(<?php echo esc_html($status_counts['waiting']); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-chat-conversations&status=active')); ?>"
                       class="<?php echo $current_status === 'active' ? 'current' : ''; ?>">
                        <?php _e('Active', 'woo-ai-customer-service'); ?>
                        <span class="count">(<?php echo esc_html($status_counts['active']); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-chat-conversations&status=resolved')); ?>"
                       class="<?php echo $current_status === 'resolved' ? 'current' : ''; ?>"
                       style="color: #00a32a;">
                        <?php _e('Resolved', 'woo-ai-customer-service'); ?>
                        <span class="count">(<?php echo esc_html($status_counts['resolved']); ?>)</span>
                    </a>
                </li>
            </ul>

            <!-- Search Box -->
            <form method="get" style="float: right; margin-top: -30px;">
                <input type="hidden" name="page" value="woo-ai-chat-conversations">
                <?php if ($current_status !== 'all'): ?>
                    <input type="hidden" name="status" value="<?php echo esc_attr($current_status); ?>">
                <?php endif; ?>
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr($search_term); ?>"
                           placeholder="<?php esc_attr_e('Search conversations...', 'woo-ai-customer-service'); ?>">
                    <input type="submit" class="button" value="<?php esc_attr_e('Search', 'woo-ai-customer-service'); ?>">
                </p>
            </form>

            <div style="clear: both;"></div>

            <?php if (!empty($search_term)): ?>
                <p><?php printf(__('Search results for: %s', 'woo-ai-customer-service'), '<strong>' . esc_html($search_term) . '</strong>'); ?>
                   <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-chat-conversations' . ($current_status !== 'all' ? '&status=' . $current_status : ''))); ?>"><?php _e('Clear', 'woo-ai-customer-service'); ?></a>
                </p>
            <?php endif; ?>

            <?php if (empty($conversations)): ?>
                <div class="notice notice-info">
                    <p><?php _e('No conversations found.', 'woo-ai-customer-service'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 50px;"><?php _e('ID', 'woo-ai-customer-service'); ?></th>
                            <th><?php _e('Customer', 'woo-ai-customer-service'); ?></th>
                            <th><?php _e('Email', 'woo-ai-customer-service'); ?></th>
                            <th style="width: 130px;"><?php _e('Status', 'woo-ai-customer-service'); ?></th>
                            <th style="width: 70px;"><?php _e('Messages', 'woo-ai-customer-service'); ?></th>
                            <th><?php _e('Started', 'woo-ai-customer-service'); ?></th>
                            <th style="width: 150px;"><?php _e('Actions', 'woo-ai-customer-service'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conversations as $convo): ?>
                        <tr data-id="<?php echo esc_attr($convo->id); ?>">
                            <td><?php echo esc_html($convo->id); ?></td>
                            <td>
                                <strong><?php echo esc_html($convo->customer_name); ?></strong>
                            </td>
                            <td>
                                <a href="mailto:<?php echo esc_attr($convo->customer_email); ?>">
                                    <?php echo esc_html($convo->customer_email); ?>
                                </a>
                            </td>
                            <td>
                                <?php echo $this->get_status_badge($convo->status); ?>
                            </td>
                            <td><?php echo esc_html($convo->message_count); ?></td>
                            <td><?php echo esc_html(date('M j, g:i a', strtotime($convo->started_at))); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-chat-conversations&view=' . $convo->id)); ?>"
                                   class="button button-small button-primary">
                                    <?php _e('View', 'woo-ai-customer-service'); ?>
                                </a>
                                <button type="button" class="button button-small woo-ai-chat-delete-convo"
                                        data-id="<?php echo esc_attr($convo->id); ?>"
                                        title="<?php esc_attr_e('Delete', 'woo-ai-customer-service'); ?>">
                                    <span class="dashicons dashicons-trash" style="vertical-align: middle; font-size: 16px;"></span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get status badge HTML
     *
     * @param string $status Status key
     * @return string Badge HTML
     */
    private function get_status_badge($status) {
        $colors = array(
            'active' => '#2271b1',
            'needs_attention' => '#d63638',
            'waiting' => '#dba617',
            'resolved' => '#00a32a',
        );
        $labels = Woo_AI_Chat_Conversation_Logger::$statuses;

        $color = isset($colors[$status]) ? $colors[$status] : '#666';
        $label = isset($labels[$status]) ? $labels[$status] : ucfirst($status);

        return sprintf(
            '<span style="display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 500; background: %s; color: #fff;">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }

    /**
     * Render single conversation view
     *
     * @param int $conversation_id Conversation ID
     */
    private function render_single_conversation($conversation_id) {
        $conversation = $this->conversation_logger->get_by_id($conversation_id);

        if (!$conversation) {
            ?>
            <div class="wrap">
                <h1><?php _e('Conversation Not Found', 'woo-ai-customer-service'); ?></h1>
                <p><a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-chat-conversations')); ?>">&larr; <?php _e('Back to Conversations', 'woo-ai-customer-service'); ?></a></p>
            </div>
            <?php
            return;
        }

        $messages = json_decode($conversation->messages, true) ?: array();
        $statuses = Woo_AI_Chat_Conversation_Logger::$statuses;
        ?>
        <div class="wrap woo-ai-chat-single-conversation">
            <h1>
                <?php _e('Conversation', 'woo-ai-customer-service'); ?> #<?php echo esc_html($conversation->id); ?>
                <?php echo $this->get_status_badge($conversation->status); ?>
            </h1>

            <p><a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-chat-conversations')); ?>">&larr; <?php _e('Back to Conversations', 'woo-ai-customer-service'); ?></a></p>

            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <!-- Left Column: Customer Info & Actions -->
                <div style="flex: 0 0 300px;">
                    <!-- Conversation Info -->
                    <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; border-radius: 4px;">
                        <h3 style="margin-top: 0;"><?php _e('Customer', 'woo-ai-customer-service'); ?></h3>
                        <p style="font-size: 16px; font-weight: 600; margin-bottom: 5px;"><?php echo esc_html($conversation->customer_name); ?></p>
                        <p style="margin-bottom: 15px;">
                            <a href="mailto:<?php echo esc_attr($conversation->customer_email); ?>"><?php echo esc_html($conversation->customer_email); ?></a>
                        </p>

                        <hr style="border: none; border-top: 1px solid #ddd; margin: 15px 0;">

                        <p><strong><?php _e('Started:', 'woo-ai-customer-service'); ?></strong><br>
                           <?php echo esc_html(date('M j, Y g:i a', strtotime($conversation->started_at))); ?></p>

                        <?php if ($conversation->ended_at): ?>
                        <p><strong><?php _e('Last Activity:', 'woo-ai-customer-service'); ?></strong><br>
                           <?php echo esc_html(date('M j, Y g:i a', strtotime($conversation->ended_at))); ?></p>
                        <?php endif; ?>

                        <p><strong><?php _e('Messages:', 'woo-ai-customer-service'); ?></strong> <?php echo esc_html($conversation->message_count); ?></p>
                    </div>

                    <!-- Status Update -->
                    <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 20px; border-radius: 4px;">
                        <h3 style="margin-top: 0;"><?php _e('Update Status', 'woo-ai-customer-service'); ?></h3>
                        <div class="woo-ai-chat-status-buttons" data-id="<?php echo esc_attr($conversation->id); ?>">
                            <?php foreach ($statuses as $key => $label): ?>
                                <button type="button"
                                        class="button <?php echo $conversation->status === $key ? 'button-primary' : ''; ?> woo-ai-chat-status-btn"
                                        data-status="<?php echo esc_attr($key); ?>"
                                        style="display: block; width: 100%; margin-bottom: 8px; <?php echo $conversation->status === $key ? '' : ''; ?>">
                                    <?php echo esc_html($label); ?>
                                    <?php if ($conversation->status === $key): ?>
                                        <span class="dashicons dashicons-yes" style="font-size: 16px; height: 16px; width: 16px;"></span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <p id="status-update-result" style="margin-top: 10px;"></p>
                    </div>

                    <!-- Actions -->
                    <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px;">
                        <h3 style="margin-top: 0;"><?php _e('Actions', 'woo-ai-customer-service'); ?></h3>
                        <button type="button" class="button woo-ai-chat-delete-convo-single"
                                data-id="<?php echo esc_attr($conversation->id); ?>"
                                style="color: #d63638; border-color: #d63638;">
                            <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                            <?php _e('Delete Conversation', 'woo-ai-customer-service'); ?>
                        </button>
                    </div>
                </div>

                <!-- Right Column: Transcript -->
                <div style="flex: 1; min-width: 400px;">
                    <div style="background: #f0f0f1; border: 1px solid #ccd0d4; border-radius: 4px; overflow: hidden;">
                        <div style="background: #fff; padding: 15px 20px; border-bottom: 1px solid #ccd0d4;">
                            <h3 style="margin: 0;"><?php _e('Conversation Transcript', 'woo-ai-customer-service'); ?></h3>
                        </div>

                        <div style="padding: 20px; max-height: 600px; overflow-y: auto;">
                            <?php if (empty($messages)): ?>
                                <p style="color: #666; text-align: center;"><?php _e('No messages in this conversation.', 'woo-ai-customer-service'); ?></p>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): ?>
                                    <div style="margin-bottom: 15px; display: flex; <?php echo $msg['role'] === 'user' ? 'justify-content: flex-end;' : 'justify-content: flex-start;'; ?>">
                                        <div style="max-width: 80%; padding: 12px 15px; border-radius: 12px; <?php echo $msg['role'] === 'user' ? 'background: #2d5a27; color: #fff; border-bottom-right-radius: 4px;' : 'background: #fff; border: 1px solid #ddd; border-bottom-left-radius: 4px;'; ?>">
                                            <div style="font-size: 11px; <?php echo $msg['role'] === 'user' ? 'color: rgba(255,255,255,0.7);' : 'color: #666;'; ?> margin-bottom: 5px;">
                                                <strong><?php echo $msg['role'] === 'user' ? esc_html($conversation->customer_name) : __('AI Assistant', 'woo-ai-customer-service'); ?></strong>
                                                <?php if (!empty($msg['timestamp'])): ?>
                                                    &middot; <?php echo esc_html(date('g:i a', strtotime($msg['timestamp']))); ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($msg['image_url'])): ?>
                                                <div style="margin-bottom: 8px;">
                                                    <a href="<?php echo esc_url($msg['image_url']); ?>" target="_blank">
                                                        <img src="<?php echo esc_url($msg['image_url']); ?>"
                                                             style="max-width: 100%; max-height: 200px; border-radius: 8px; display: block;">
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                            <div style="white-space: pre-wrap; word-wrap: break-word;"><?php echo esc_html($msg['content']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Add dashboard widget
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'woo_ai_chat_dashboard_widget',
            __('AI Chat Overview', 'woo-ai-customer-service'),
            array($this, 'render_dashboard_widget')
        );
    }

    /**
     * Render dashboard widget
     */
    public function render_dashboard_widget() {
        $stats = $this->conversation_logger->get_stats();
        $lead_count = $this->lead_capture->get_lead_count();
        $has_api_key = !empty(get_option('woo_ai_chat_api_key', ''));
        ?>
        <div class="woo-ai-chat-dashboard-widget">
            <?php if (!$has_api_key): ?>
                <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px 12px; margin-bottom: 15px;">
                    <strong><?php _e('Setup Required', 'woo-ai-customer-service'); ?></strong><br>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-chat')); ?>">
                        <?php _e('Add your Anthropic API key to enable the chatbot.', 'woo-ai-customer-service'); ?>
                    </a>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 15px;">
                <div style="text-align: center; padding: 15px; background: #f0f6fc; border-radius: 4px;">
                    <div style="font-size: 28px; font-weight: 600; color: #2d5a27;"><?php echo esc_html($stats['today']); ?></div>
                    <div style="color: #666; font-size: 12px;"><?php _e('Chats Today', 'woo-ai-customer-service'); ?></div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f0f6fc; border-radius: 4px;">
                    <div style="font-size: 28px; font-weight: 600; color: #2d5a27;"><?php echo esc_html($stats['this_week']); ?></div>
                    <div style="color: #666; font-size: 12px;"><?php _e('This Week', 'woo-ai-customer-service'); ?></div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f6f7f7; border-radius: 4px;">
                    <div style="font-size: 28px; font-weight: 600; color: #50575e;"><?php echo esc_html($lead_count); ?></div>
                    <div style="color: #666; font-size: 12px;"><?php _e('Total Leads', 'woo-ai-customer-service'); ?></div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f6f7f7; border-radius: 4px;">
                    <div style="font-size: 28px; font-weight: 600; color: #50575e;"><?php echo esc_html($stats['avg_messages']); ?></div>
                    <div style="color: #666; font-size: 12px;"><?php _e('Avg Messages', 'woo-ai-customer-service'); ?></div>
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-chat-conversations')); ?>" class="button button-primary" style="flex: 1; text-align: center;">
                    <?php _e('View Conversations', 'woo-ai-customer-service'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=woo-ai-chat-leads')); ?>" class="button" style="flex: 1; text-align: center;">
                    <?php _e('View Leads', 'woo-ai-customer-service'); ?>
                </a>
            </div>
        </div>
        <?php
    }
}
