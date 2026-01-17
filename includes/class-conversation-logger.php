<?php
/**
 * Conversation Logger Class
 *
 * Handles logging and retrieval of chat conversations
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_AI_Chat_Conversation_Logger {

    /**
     * Database table name
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ai_chat_conversations';
    }

    /**
     * Valid conversation statuses
     */
    public static $statuses = array(
        'active' => 'Active',
        'needs_attention' => 'Needs Attention',
        'waiting' => 'Waiting for Reply',
        'resolved' => 'Resolved',
    );

    /**
     * Create conversations table
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ai_chat_conversations';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
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
            KEY started_at (started_at),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Start a new conversation
     *
     * @param string $session_id Session ID
     * @param int $lead_id Lead ID
     * @param string $email Customer email
     * @param string $name Customer name
     * @return int|false Conversation ID or false
     */
    public function start_conversation($session_id, $lead_id, $email, $name) {
        global $wpdb;

        $result = $wpdb->insert(
            $this->table_name,
            array(
                'session_id' => sanitize_text_field($session_id),
                'lead_id' => absint($lead_id),
                'customer_email' => sanitize_email($email),
                'customer_name' => sanitize_text_field($name),
                'messages' => wp_json_encode(array()),
                'message_count' => 0,
                'started_at' => current_time('mysql'),
                'status' => 'active',
            ),
            array('%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Add message to conversation
     *
     * @param string $session_id Session ID
     * @param string $role Message role (user or assistant)
     * @param string $content Message content
     * @param string $image_url Optional image URL
     * @return bool Success
     */
    public function add_message($session_id, $role, $content, $image_url = '') {
        global $wpdb;

        $conversation = $this->get_by_session($session_id);
        if (!$conversation) {
            return false;
        }

        $messages = json_decode($conversation->messages, true) ?: array();
        $message_data = array(
            'role' => sanitize_text_field($role),
            'content' => sanitize_text_field($content),
            'timestamp' => current_time('mysql'),
        );

        // Add image URL if present
        if (!empty($image_url)) {
            $message_data['image_url'] = esc_url_raw($image_url);
        }

        $messages[] = $message_data;

        return $wpdb->update(
            $this->table_name,
            array(
                'messages' => wp_json_encode($messages),
                'message_count' => count($messages),
                'ended_at' => current_time('mysql'),
            ),
            array('session_id' => $session_id),
            array('%s', '%d', '%s'),
            array('%s')
        ) !== false;
    }

    /**
     * Get conversation by session ID
     *
     * @param string $session_id Session ID
     * @return object|null Conversation or null
     */
    public function get_by_session($session_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE session_id = %s ORDER BY id DESC LIMIT 1",
                sanitize_text_field($session_id)
            )
        );
    }

    /**
     * Get conversation by ID
     *
     * @param int $id Conversation ID
     * @return object|null Conversation or null
     */
    public function get_by_id($id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                absint($id)
            )
        );
    }

    /**
     * Get all conversations
     *
     * @param int $limit Number of conversations
     * @param int $offset Offset for pagination
     * @return array Conversations
     */
    public function get_all($limit = 50, $offset = 0) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY started_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    /**
     * Get conversation count
     *
     * @return int Total conversations
     */
    public function get_count() {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }

    /**
     * Get conversations by customer email
     *
     * @param string $email Customer email
     * @param int $limit Number of conversations
     * @return array Conversations
     */
    public function get_by_email($email, $limit = 10) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE customer_email = %s ORDER BY started_at DESC LIMIT %d",
                sanitize_email($email),
                $limit
            )
        );
    }

    /**
     * Delete conversation
     *
     * @param int $id Conversation ID
     * @return bool Success
     */
    public function delete($id) {
        global $wpdb;
        return $wpdb->delete(
            $this->table_name,
            array('id' => absint($id)),
            array('%d')
        ) !== false;
    }

    /**
     * Get stats for dashboard
     *
     * @return array Stats
     */
    public function get_stats() {
        global $wpdb;

        $total = $this->get_count();

        $today = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE DATE(started_at) = %s",
                current_time('Y-m-d')
            )
        );

        $this_week = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE started_at >= %s",
                date('Y-m-d', strtotime('-7 days'))
            )
        );

        $avg_messages = $wpdb->get_var(
            "SELECT AVG(message_count) FROM {$this->table_name} WHERE message_count > 0"
        );

        return array(
            'total' => (int) $total,
            'today' => (int) $today,
            'this_week' => (int) $this_week,
            'avg_messages' => round((float) $avg_messages, 1),
        );
    }

    /**
     * Mark conversation as ended
     *
     * @param string $session_id Session ID
     * @return bool Success
     */
    public function end_conversation($session_id) {
        global $wpdb;
        return $wpdb->update(
            $this->table_name,
            array(
                'status' => 'resolved',
                'ended_at' => current_time('mysql'),
            ),
            array('session_id' => $session_id),
            array('%s', '%s'),
            array('%s')
        ) !== false;
    }

    /**
     * Update conversation status
     *
     * @param int $id Conversation ID
     * @param string $status New status
     * @return bool Success
     */
    public function update_status($id, $status) {
        global $wpdb;

        // Validate status
        if (!array_key_exists($status, self::$statuses)) {
            return false;
        }

        $data = array('status' => $status);

        // If marking as resolved, set ended_at
        if ($status === 'resolved') {
            $data['ended_at'] = current_time('mysql');
        }

        return $wpdb->update(
            $this->table_name,
            $data,
            array('id' => absint($id)),
            array('%s', '%s'),
            array('%d')
        ) !== false;
    }

    /**
     * Get conversations filtered by status
     *
     * @param string $status Status to filter by (or 'all')
     * @param int $limit Number of conversations
     * @param int $offset Offset for pagination
     * @return array Conversations
     */
    public function get_filtered($status = 'all', $limit = 50, $offset = 0) {
        global $wpdb;

        if ($status === 'all' || empty($status)) {
            return $this->get_all($limit, $offset);
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE status = %s ORDER BY started_at DESC LIMIT %d OFFSET %d",
                $status,
                $limit,
                $offset
            )
        );
    }

    /**
     * Get count by status
     *
     * @param string $status Status to count (or 'all')
     * @return int Count
     */
    public function get_count_by_status($status = 'all') {
        global $wpdb;

        if ($status === 'all' || empty($status)) {
            return $this->get_count();
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
                $status
            )
        );
    }

    /**
     * Get status counts for dashboard
     *
     * @return array Status counts
     */
    public function get_status_counts() {
        global $wpdb;

        $counts = array();
        foreach (array_keys(self::$statuses) as $status) {
            $counts[$status] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} WHERE status = %s",
                    $status
                )
            );
        }
        $counts['all'] = array_sum($counts);

        return $counts;
    }

    /**
     * Search conversations
     *
     * @param string $search Search term
     * @param string $status Status filter
     * @param int $limit Number of results
     * @return array Conversations
     */
    public function search($search, $status = 'all', $limit = 50) {
        global $wpdb;
        $search_term = '%' . $wpdb->esc_like($search) . '%';

        $sql = "SELECT * FROM {$this->table_name} WHERE (customer_name LIKE %s OR customer_email LIKE %s OR messages LIKE %s)";
        $params = array($search_term, $search_term, $search_term);

        if ($status !== 'all' && !empty($status)) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        $sql .= " ORDER BY started_at DESC LIMIT %d";
        $params[] = $limit;

        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }
}
