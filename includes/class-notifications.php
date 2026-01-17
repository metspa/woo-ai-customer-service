<?php
/**
 * Notifications Class
 *
 * Handles email notifications for chat events
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_AI_Chat_Notifications {

    /**
     * Check if notifications are enabled
     *
     * @return bool
     */
    public static function is_enabled() {
        return (bool) get_option('woo_ai_chat_notifications_enabled', false);
    }

    /**
     * Get notification email recipients
     *
     * @return array Email addresses
     */
    public static function get_recipients() {
        $emails_string = get_option('woo_ai_chat_notification_emails', '');

        if (empty($emails_string)) {
            // Fall back to admin email
            return array(get_option('admin_email'));
        }

        $emails = array_map('trim', explode(',', $emails_string));
        return array_filter($emails, 'is_email');
    }

    /**
     * Send notification for new conversation
     *
     * @param array $lead_data Customer lead data
     * @param string $session_id Session ID
     * @return bool Whether email was sent
     */
    public static function notify_new_conversation($lead_data, $session_id) {
        if (!self::is_enabled()) {
            return false;
        }

        if (!get_option('woo_ai_chat_notify_on_start', true)) {
            return false;
        }

        $recipients = self::get_recipients();
        if (empty($recipients)) {
            return false;
        }

        $business_name = get_option('woo_ai_chat_business_name', 'Your Store');
        $customer_name = $lead_data['first_name'] . ' ' . $lead_data['last_name'];
        $customer_email = $lead_data['email'];
        $customer_phone = !empty($lead_data['phone']) ? $lead_data['phone'] : 'Not provided';

        $subject = sprintf(
            '[%s] New Chat Started - %s',
            $business_name,
            $customer_name
        );

        $admin_url = admin_url('admin.php?page=woo-ai-chat-conversations');

        $message = self::get_email_template('new_conversation', array(
            'business_name' => $business_name,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'customer_phone' => $customer_phone,
            'session_id' => $session_id,
            'admin_url' => $admin_url,
            'timestamp' => current_time('F j, Y g:i a'),
        ));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $business_name . ' <' . get_option('admin_email') . '>',
        );

        // Send to all recipients
        $sent = false;
        foreach ($recipients as $recipient) {
            if (wp_mail($recipient, $subject, $message, $headers)) {
                $sent = true;
            }
        }

        return $sent;
    }

    /**
     * Send notification for new message
     *
     * @param array $lead_data Customer lead data
     * @param string $message Customer message
     * @param string $session_id Session ID
     * @return bool Whether email was sent
     */
    public static function notify_new_message($lead_data, $message, $session_id) {
        if (!self::is_enabled()) {
            return false;
        }

        if (!get_option('woo_ai_chat_notify_on_message', false)) {
            return false;
        }

        $recipients = self::get_recipients();
        if (empty($recipients)) {
            return false;
        }

        $business_name = get_option('woo_ai_chat_business_name', 'Your Store');
        $customer_name = $lead_data['first_name'] . ' ' . $lead_data['last_name'];
        $customer_email = $lead_data['email'];

        $subject = sprintf(
            '[%s] New Message from %s',
            $business_name,
            $customer_name
        );

        $admin_url = admin_url('admin.php?page=woo-ai-chat-conversations');

        $email_content = self::get_email_template('new_message', array(
            'business_name' => $business_name,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'message' => $message,
            'session_id' => $session_id,
            'admin_url' => $admin_url,
            'timestamp' => current_time('F j, Y g:i a'),
        ));

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $business_name . ' <' . get_option('admin_email') . '>',
        );

        // Send to all recipients
        $sent = false;
        foreach ($recipients as $recipient) {
            if (wp_mail($recipient, $subject, $email_content, $headers)) {
                $sent = true;
            }
        }

        return $sent;
    }

    /**
     * Get email template
     *
     * @param string $template Template name
     * @param array $vars Variables to replace
     * @return string Email HTML
     */
    private static function get_email_template($template, $vars) {
        $primary_color = get_option('woo_ai_chat_primary_color', '#2d5a27');

        $base_style = '
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: ' . esc_attr($primary_color) . '; color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-top: none; }
            .info-box { background: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #eee; }
            .info-row { margin-bottom: 12px; }
            .info-label { font-weight: 600; color: #666; display: inline-block; width: 100px; }
            .message-box { background: #fff; padding: 15px; border-left: 4px solid ' . esc_attr($primary_color) . '; margin: 20px 0; }
            .button { display: inline-block; background: ' . esc_attr($primary_color) . '; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 600; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 13px; }
        ';

        if ($template === 'new_conversation') {
            return '
            <!DOCTYPE html>
            <html>
            <head><style>' . $base_style . '</style></head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>New Chat Started</h1>
                    </div>
                    <div class="content">
                        <p>A new customer has started a chat conversation on <strong>' . esc_html($vars['business_name']) . '</strong>.</p>

                        <div class="info-box">
                            <div class="info-row">
                                <span class="info-label">Name:</span>
                                <strong>' . esc_html($vars['customer_name']) . '</strong>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <a href="mailto:' . esc_attr($vars['customer_email']) . '">' . esc_html($vars['customer_email']) . '</a>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Phone:</span>
                                ' . esc_html($vars['customer_phone']) . '
                            </div>
                            <div class="info-row">
                                <span class="info-label">Time:</span>
                                ' . esc_html($vars['timestamp']) . '
                            </div>
                        </div>

                        <p style="text-align: center;">
                            <a href="' . esc_url($vars['admin_url']) . '" class="button">View Conversations</a>
                        </p>
                    </div>
                    <div class="footer">
                        <p>This notification was sent by the AI Chat plugin on ' . esc_html($vars['business_name']) . '</p>
                    </div>
                </div>
            </body>
            </html>';
        }

        if ($template === 'new_message') {
            return '
            <!DOCTYPE html>
            <html>
            <head><style>' . $base_style . '</style></head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>New Chat Message</h1>
                    </div>
                    <div class="content">
                        <p><strong>' . esc_html($vars['customer_name']) . '</strong> sent a new message:</p>

                        <div class="message-box">
                            ' . nl2br(esc_html($vars['message'])) . '
                        </div>

                        <div class="info-box">
                            <div class="info-row">
                                <span class="info-label">Customer:</span>
                                ' . esc_html($vars['customer_name']) . '
                            </div>
                            <div class="info-row">
                                <span class="info-label">Email:</span>
                                <a href="mailto:' . esc_attr($vars['customer_email']) . '">' . esc_html($vars['customer_email']) . '</a>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Time:</span>
                                ' . esc_html($vars['timestamp']) . '
                            </div>
                        </div>

                        <p style="text-align: center;">
                            <a href="' . esc_url($vars['admin_url']) . '" class="button">View Conversations</a>
                        </p>
                    </div>
                    <div class="footer">
                        <p>This notification was sent by the AI Chat plugin on ' . esc_html($vars['business_name']) . '</p>
                    </div>
                </div>
            </body>
            </html>';
        }

        return '';
    }
}
