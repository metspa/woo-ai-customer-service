<?php
/**
 * Chat API Class
 *
 * Handles communication with Claude API
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_AI_Chat_API {

    /**
     * API key
     */
    private $api_key;

    /**
     * API endpoint URL
     */
    private $api_url = 'https://api.anthropic.com/v1/messages';

    /**
     * Model to use
     */
    private $model;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = $this->get_decrypted_api_key();
        $this->model = get_option('woo_ai_chat_model', 'claude-haiku-4-5-20251001');
    }

    /**
     * Send message to Claude API
     *
     * @param array $messages Conversation messages
     * @param string $customer_context_string Formatted customer context
     * @return array Response with success status and message
     */
    public function send_message($messages, $customer_context_string) {
        if (empty($this->api_key)) {
            return $this->handle_error('API key not configured');
        }

        $system_prompt = $this->build_system_prompt($customer_context_string);

        $payload = array(
            'model' => $this->model,
            'max_tokens' => (int) get_option('woo_ai_chat_max_tokens', 1024),
            'system' => $system_prompt,
            'messages' => $messages,
        );

        $response = wp_remote_post($this->api_url, array(
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body' => wp_json_encode($payload),
        ));

        if (is_wp_error($response)) {
            return $this->handle_error($response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200) {
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'API request failed';
            return $this->handle_error($error_message);
        }

        if (!isset($body['content'][0]['text'])) {
            return $this->handle_error('Invalid API response format');
        }

        return array(
            'success' => true,
            'message' => $body['content'][0]['text'],
        );
    }

    /**
     * Build system prompt with customer context
     *
     * @param string $customer_context Customer context string
     * @return string Complete system prompt
     */
    private function build_system_prompt($customer_context) {
        $business_name = get_option('woo_ai_chat_business_name', 'Organic Skincare');
        $support_email = get_option('woo_ai_chat_support_email', 'admin@organicskincare.com');
        $support_phone = get_option('woo_ai_chat_support_phone', '516-322-9380');
        $business_hours = get_option('woo_ai_chat_business_hours', 'Mon-Fri 9am-6pm EST');
        $custom_prompt = get_option('woo_ai_chat_system_prompt', '');

        $prompt = "You are a helpful and friendly customer service assistant for {$business_name}, an organic skincare e-commerce store.

{$customer_context}

DIRECT CONTACT INFORMATION (provide when customer needs human assistance):
- Email: {$support_email}
- Phone/Text: {$support_phone} (call or text anytime)
- Business Hours: {$business_hours}

YOUR CAPABILITIES:
- Answer questions about order status and tracking
- Provide tracking numbers and links when available
- Help with product inquiries (ingredients, usage, recommendations)
- Explain shipping timeframes and policies
- Assist with general questions about organic skincare
- Direct customers to contact support for complex issues

ORDER STATUS EXPLANATIONS:
- \"Pending\" = Order received, awaiting payment
- \"Processing\" = Payment received, order is being prepared for shipment
- \"On Hold\" = Awaiting payment confirmation or review
- \"Completed\" = Order has been shipped and/or delivered
- \"Shipped\" = Package is in transit
- \"Delivered\" = Package has arrived
- \"Cancelled\" = Order was cancelled
- \"Refunded\" = Payment has been returned
- \"Failed\" = Payment failed or was declined

GUIDELINES:
- Be warm, friendly, and professional
- Address the customer by their first name
- Reference their specific order details when relevant
- If tracking shows \"in transit,\" reassure them and provide tracking link
- For refunds, exchanges, or complex issues, provide the direct contact info
- Never make up order information - only share what's in the context
- If you don't have information, say so and offer to connect them with support
- Keep responses concise but helpful (2-3 paragraphs maximum)
- Use simple, clear language

ESCALATION TRIGGERS (always provide contact info for these):
- Refund requests
- Order cancellation requests
- Damaged or wrong items received
- Allergic reaction concerns
- Billing disputes
- Requests to speak with a human
- Complaints or negative feedback

RESPONSE FORMAT:
- Keep responses friendly and conversational
- When sharing order details, format them clearly
- Always end with an offer to help further or provide contact info if needed";

        if (!empty($custom_prompt)) {
            $prompt .= "\n\nADDITIONAL INSTRUCTIONS:\n{$custom_prompt}";
        }

        return $prompt;
    }

    /**
     * Handle API errors
     *
     * @param string $error_message Error message
     * @return array Error response
     */
    private function handle_error($error_message) {
        // Log error for admin
        error_log('WooCommerce AI Chat Error: ' . $error_message);

        $support_email = get_option('woo_ai_chat_support_email', 'admin@organicskincare.com');
        $support_phone = get_option('woo_ai_chat_support_phone', '516-322-9380');

        $fallback = get_option(
            'woo_ai_chat_fallback_message',
            "I'm having trouble connecting right now. Please contact us directly at {$support_email} or call/text {$support_phone} and we'll be happy to help!"
        );

        // Replace placeholders in fallback message
        $fallback = str_replace('{email}', $support_email, $fallback);
        $fallback = str_replace('{phone}', $support_phone, $fallback);

        return array(
            'success' => false,
            'message' => $fallback,
            'error' => $error_message,
        );
    }

    /**
     * Get decrypted API key
     *
     * @return string Decrypted API key
     */
    private function get_decrypted_api_key() {
        $encrypted = get_option('woo_ai_chat_api_key', '');
        if (empty($encrypted)) {
            return '';
        }

        // Decrypt the key
        return $this->decrypt($encrypted);
    }

    /**
     * Encrypt API key for storage
     * Simple base64 encoding - database is already secure
     *
     * @param string $value Value to encrypt
     * @return string Encrypted value
     */
    public static function encrypt($value) {
        if (empty($value)) {
            return '';
        }
        // Just base64 encode with a simple marker
        return 'V3_' . base64_encode($value);
    }

    /**
     * Decrypt API key
     *
     * @param string $value Encrypted value
     * @return string Decrypted value
     */
    private function decrypt($value) {
        if (empty($value)) {
            return '';
        }

        // V3 format (current)
        if (strpos($value, 'V3_') === 0) {
            return base64_decode(substr($value, 3));
        }

        // WOOAI format (previous)
        if (strpos($value, 'WOOAI_') === 0) {
            $decoded = base64_decode(substr($value, 6));
            return $decoded !== false ? strrev($decoded) : '';
        }

        // Try raw base64
        $decoded = base64_decode($value, true);
        if ($decoded !== false && strpos($decoded, 'sk-') === 0) {
            return $decoded;
        }

        // Raw key
        if (strpos($value, 'sk-') === 0) {
            return $value;
        }

        return '';
    }

    /**
     * Test API connection
     *
     * @return array Test result
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'API key not configured',
            );
        }

        $response = $this->send_message(
            array(array('role' => 'user', 'content' => 'Hi')),
            'Test connection - no customer context'
        );

        if ($response['success']) {
            return array(
                'success' => true,
                'message' => 'API connection successful',
            );
        }

        return array(
            'success' => false,
            'message' => 'API connection failed: ' . ($response['error'] ?? 'Unknown error'),
        );
    }
}
