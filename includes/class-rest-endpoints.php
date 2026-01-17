<?php
/**
 * REST Endpoints Class
 *
 * Handles REST API endpoints for the chat widget
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_AI_Chat_REST_Endpoints {

    /**
     * Chat API instance
     */
    private $chat_api;

    /**
     * Customer context instance
     */
    private $customer_context;

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
        $this->chat_api = new Woo_AI_Chat_API();
        $this->customer_context = new Woo_AI_Chat_Customer_Context();
        $this->lead_capture = new Woo_AI_Chat_Lead_Capture();
        $this->conversation_logger = new Woo_AI_Chat_Conversation_Logger();

        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        $namespace = 'woo-ai-chat/v1';

        // Start session with lead capture
        register_rest_route($namespace, '/session', array(
            'methods' => 'POST',
            'callback' => array($this, 'start_session'),
            'permission_callback' => '__return_true',
            'args' => array(
                'first_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'last_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'email' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                ),
                'phone' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));

        // Send message
        register_rest_route($namespace, '/message', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_message'),
            'permission_callback' => array($this, 'verify_session'),
            'args' => array(
                'session_id' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'message' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'image_url' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                ),
            ),
        ));

        // Upload image
        register_rest_route($namespace, '/upload', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_upload'),
            'permission_callback' => array($this, 'verify_session_for_upload'),
        ));
    }

    /**
     * Start chat session - captures lead info
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function start_session($request) {
        $params = $request->get_json_params();

        // Validate required fields
        $required = array('first_name', 'last_name', 'email');
        foreach ($required as $field) {
            if (empty($params[$field])) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => "Missing required field: {$field}",
                ), 400);
            }
        }

        // Validate email
        if (!is_email($params['email'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Please enter a valid email address',
            ), 400);
        }

        // Check if phone is required
        $phone_required = get_option('woo_ai_chat_phone_required', false);
        if ($phone_required && empty($params['phone'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Phone number is required',
            ), 400);
        }

        // Generate session ID
        $session_id = wp_generate_uuid4();

        // Save lead
        $lead_data = array(
            'first_name' => sanitize_text_field($params['first_name']),
            'last_name' => sanitize_text_field($params['last_name']),
            'email' => sanitize_email($params['email']),
            'phone' => sanitize_text_field($params['phone'] ?? ''),
            'session_id' => $session_id,
        );

        $lead_id = $this->lead_capture->save_lead($lead_data);

        if (!$lead_id) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Failed to save lead information',
            ), 500);
        }

        // Build customer context
        $context = $this->customer_context->build_context($lead_data, $session_id);

        // Start conversation logging
        $customer_name = $lead_data['first_name'] . ' ' . $lead_data['last_name'];
        $this->conversation_logger->start_conversation(
            $session_id,
            $lead_id,
            $lead_data['email'],
            $customer_name
        );

        // Send notification email for new conversation
        Woo_AI_Chat_Notifications::notify_new_conversation($lead_data, $session_id);

        // Store session data in transient (2 hour expiry)
        $session_data = array(
            'lead_id' => $lead_id,
            'lead_data' => $lead_data,
            'context' => $context,
            'messages' => array(),
            'started' => time(),
            'message_count' => 0,
        );
        set_transient('woo_ai_chat_' . $session_id, $session_data, 2 * HOUR_IN_SECONDS);

        // Generate welcome message
        $welcome = get_option(
            'woo_ai_chat_welcome_message',
            "Hi {first_name}! Welcome to Organic Skincare. I'm here to help with your orders, product questions, or anything else. How can I assist you today?"
        );
        $welcome = str_replace('{first_name}', $lead_data['first_name'], $welcome);
        $welcome = str_replace('{last_name}', $lead_data['last_name'], $welcome);
        $welcome = str_replace('{email}', $lead_data['email'], $welcome);

        // Add order context to welcome if they have orders
        if (!empty($context['orders'])) {
            $order_count = count($context['orders']);
            $latest_order = $context['orders'][0];
            $welcome .= "\n\nI can see you have {$order_count} recent order(s). Your most recent order #{$latest_order['order_number']} is currently: {$latest_order['status']}.";
        }

        return new WP_REST_Response(array(
            'success' => true,
            'session_id' => $session_id,
            'welcome_message' => $welcome,
            'nonce' => wp_create_nonce('woo_ai_chat_' . $session_id),
            'has_orders' => !empty($context['orders']),
        ));
    }

    /**
     * Handle incoming chat message
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function handle_message($request) {
        $params = $request->get_json_params();
        $session_id = sanitize_text_field($params['session_id'] ?? '');
        $user_message = sanitize_text_field($params['message'] ?? '');
        $image_url = isset($params['image_url']) ? esc_url_raw($params['image_url']) : '';

        if (empty($session_id) || empty($user_message)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Missing session_id or message',
            ), 400);
        }

        // Get session data
        $session = get_transient('woo_ai_chat_' . $session_id);
        if (!$session) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Session expired. Please refresh the page and start a new chat.',
                'expired' => true,
            ), 400);
        }

        // Rate limiting - max 30 user messages per session
        $max_messages = (int) get_option('woo_ai_chat_max_messages', 30);
        if ($session['message_count'] >= $max_messages) {
            $support_email = get_option('woo_ai_chat_support_email', 'admin@organicskincare.com');
            $support_phone = get_option('woo_ai_chat_support_phone', '516-322-9380');
            return new WP_REST_Response(array(
                'success' => false,
                'error' => "Message limit reached. Please contact us directly at {$support_email} or call {$support_phone}.",
                'rate_limited' => true,
            ), 429);
        }

        // Message length limit
        if (strlen($user_message) > 2000) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Message too long. Please keep messages under 2000 characters.',
            ), 400);
        }

        // Build message content for Claude (include image description if present)
        $message_for_claude = $user_message;
        if (!empty($image_url)) {
            $message_for_claude .= "\n\n[Customer attached an image: {$image_url}]";
        }

        // Add user message to history
        $session['messages'][] = array(
            'role' => 'user',
            'content' => $message_for_claude,
        );
        $session['message_count']++;

        // Build context string
        $context_string = $this->customer_context->format_for_claude($session['context']);

        // Send to Claude
        $response = $this->chat_api->send_message($session['messages'], $context_string);

        // Add assistant response to history
        $session['messages'][] = array(
            'role' => 'assistant',
            'content' => $response['message'],
        );

        // Log messages to conversation (with image if present)
        $this->conversation_logger->add_message($session_id, 'user', $user_message, $image_url);
        $this->conversation_logger->add_message($session_id, 'assistant', $response['message']);

        // Send notification email for new message (if enabled)
        Woo_AI_Chat_Notifications::notify_new_message($session['lead_data'], $user_message, $session_id);

        // Update last contact time for lead
        $this->lead_capture->update_last_contact($session['lead_id']);

        // Update session with refreshed expiry
        set_transient('woo_ai_chat_' . $session_id, $session, 2 * HOUR_IN_SECONDS);

        return new WP_REST_Response(array(
            'success' => $response['success'],
            'message' => $response['message'],
            'messages_remaining' => $max_messages - $session['message_count'],
        ));
    }

    /**
     * Verify session and nonce
     *
     * @param WP_REST_Request $request Request object
     * @return bool Whether request is authorized
     */
    public function verify_session($request) {
        $params = $request->get_json_params();
        $session_id = sanitize_text_field($params['session_id'] ?? '');

        if (empty($session_id)) {
            return false;
        }

        // Verify session exists
        $session = get_transient('woo_ai_chat_' . $session_id);
        if (!$session) {
            return false;
        }

        // Verify nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'woo_ai_chat_' . $session_id)) {
            return false;
        }

        return true;
    }

    /**
     * Verify session for upload (from form data)
     *
     * @param WP_REST_Request $request Request object
     * @return bool Whether request is authorized
     */
    public function verify_session_for_upload($request) {
        $session_id = sanitize_text_field($request->get_param('session_id') ?? '');

        if (empty($session_id)) {
            return false;
        }

        // Verify session exists
        $session = get_transient('woo_ai_chat_' . $session_id);
        if (!$session) {
            return false;
        }

        // Verify nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'woo_ai_chat_' . $session_id)) {
            return false;
        }

        return true;
    }

    /**
     * Handle image upload
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response Response
     */
    public function handle_upload($request) {
        $session_id = sanitize_text_field($request->get_param('session_id') ?? '');

        // Check if files were uploaded
        $files = $request->get_file_params();
        if (empty($files['image'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'No image file provided',
            ), 400);
        }

        $file = $files['image'];

        // Validate file type
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'Invalid file type. Please upload a JPG, PNG, GIF, or WebP image.',
            ), 400);
        }

        // Validate file size (max 5MB)
        $max_size = 5 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => 'File too large. Maximum size is 5MB.',
            ), 400);
        }

        // Include WordPress upload functions
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Generate unique filename
        $filename = 'chat-' . $session_id . '-' . time() . '-' . sanitize_file_name($file['name']);

        // Prepare the file for upload
        $upload_overrides = array(
            'test_form' => false,
            'unique_filename_callback' => function($dir, $name, $ext) use ($filename) {
                return $filename;
            }
        );

        // Move uploaded file to uploads directory
        $movefile = wp_handle_upload($file, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            return new WP_REST_Response(array(
                'success' => true,
                'url' => $movefile['url'],
                'filename' => basename($movefile['file']),
            ));
        } else {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $movefile['error'] ?? 'Upload failed',
            ), 500);
        }
    }
}
