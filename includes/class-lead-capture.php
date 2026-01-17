<?php
/**
 * Lead Capture Class
 *
 * Handles capturing and managing lead information from chat widget
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_AI_Chat_Lead_Capture {

    /**
     * Database table name
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'ai_chat_leads';
    }

    /**
     * Save lead information before chat starts
     *
     * @param array $data Lead data (first_name, last_name, email, phone, session_id)
     * @return int|false Lead ID on success, false on failure
     */
    public function save_lead($data) {
        global $wpdb;

        // Check if lead exists by email
        $existing = $this->get_lead_by_email($data['email']);

        if ($existing) {
            // Update existing lead
            $wpdb->update(
                $this->table_name,
                array(
                    'first_name' => sanitize_text_field($data['first_name']),
                    'last_name' => sanitize_text_field($data['last_name']),
                    'phone' => sanitize_text_field($data['phone'] ?? ''),
                    'session_id' => sanitize_text_field($data['session_id']),
                    'last_contact' => current_time('mysql'),
                ),
                array('email' => $data['email']),
                array('%s', '%s', '%s', '%s', '%s'),
                array('%s')
            );
            return $existing->id;
        }

        // Insert new lead
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'first_name' => sanitize_text_field($data['first_name']),
                'last_name' => sanitize_text_field($data['last_name']),
                'email' => sanitize_email($data['email']),
                'phone' => sanitize_text_field($data['phone'] ?? ''),
                'user_id' => get_current_user_id() ?: null,
                'session_id' => sanitize_text_field($data['session_id']),
                'source' => 'chat_widget',
                'created_at' => current_time('mysql'),
                'last_contact' => current_time('mysql'),
            ),
            array('%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get lead by email
     *
     * @param string $email Email address
     * @return object|null Lead object or null
     */
    public function get_lead_by_email($email) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE email = %s",
                sanitize_email($email)
            )
        );
    }

    /**
     * Get lead by ID
     *
     * @param int $lead_id Lead ID
     * @return object|null Lead object or null
     */
    public function get_lead_by_id($lead_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d",
                $lead_id
            )
        );
    }

    /**
     * Get lead by session ID
     *
     * @param string $session_id Session ID
     * @return object|null Lead object or null
     */
    public function get_lead_by_session($session_id) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE session_id = %s ORDER BY last_contact DESC LIMIT 1",
                sanitize_text_field($session_id)
            )
        );
    }

    /**
     * Get all leads for admin view
     *
     * @param int $limit Number of leads to retrieve
     * @param int $offset Offset for pagination
     * @return array Array of lead objects
     */
    public function get_all_leads($limit = 50, $offset = 0) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    /**
     * Get lead count
     *
     * @return int Total number of leads
     */
    public function get_lead_count() {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }

    /**
     * Search leads
     *
     * @param string $search Search term
     * @param int $limit Number of results
     * @return array Array of lead objects
     */
    public function search_leads($search, $limit = 50) {
        global $wpdb;
        $search = '%' . $wpdb->esc_like($search) . '%';
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE first_name LIKE %s
                OR last_name LIKE %s
                OR email LIKE %s
                OR phone LIKE %s
                ORDER BY created_at DESC
                LIMIT %d",
                $search,
                $search,
                $search,
                $search,
                $limit
            )
        );
    }

    /**
     * Export leads as CSV
     *
     * @return string CSV content
     */
    public function export_leads_csv() {
        $leads = $this->get_all_leads(10000, 0);

        $csv = "First Name,Last Name,Email,Phone,Source,Created,Last Contact\n";
        foreach ($leads as $lead) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s"' . "\n",
                str_replace('"', '""', $lead->first_name),
                str_replace('"', '""', $lead->last_name),
                str_replace('"', '""', $lead->email),
                str_replace('"', '""', $lead->phone),
                str_replace('"', '""', $lead->source),
                $lead->created_at,
                $lead->last_contact
            );
        }

        return $csv;
    }

    /**
     * Add note to lead
     *
     * @param int $lead_id Lead ID
     * @param string $note Note content
     * @return bool Success status
     */
    public function add_note($lead_id, $note) {
        global $wpdb;
        $lead = $this->get_lead_by_id($lead_id);

        if (!$lead) {
            return false;
        }

        $timestamp = current_time('Y-m-d H:i');
        $new_note = $lead->notes ? $lead->notes . "\n\n" : '';
        $new_note .= "[$timestamp] " . sanitize_textarea_field($note);

        return $wpdb->update(
            $this->table_name,
            array('notes' => $new_note),
            array('id' => $lead_id),
            array('%s'),
            array('%d')
        ) !== false;
    }

    /**
     * Delete lead
     *
     * @param int $lead_id Lead ID
     * @return bool Success status
     */
    public function delete_lead($lead_id) {
        global $wpdb;
        return $wpdb->delete(
            $this->table_name,
            array('id' => $lead_id),
            array('%d')
        ) !== false;
    }

    /**
     * Get leads by date range
     *
     * @param string $start_date Start date (Y-m-d format)
     * @param string $end_date End date (Y-m-d format)
     * @return array Array of lead objects
     */
    public function get_leads_by_date_range($start_date, $end_date) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name}
                WHERE DATE(created_at) >= %s
                AND DATE(created_at) <= %s
                ORDER BY created_at DESC",
                $start_date,
                $end_date
            )
        );
    }

    /**
     * Update lead's last contact time
     *
     * @param int $lead_id Lead ID
     * @return bool Success status
     */
    public function update_last_contact($lead_id) {
        global $wpdb;
        return $wpdb->update(
            $this->table_name,
            array('last_contact' => current_time('mysql')),
            array('id' => $lead_id),
            array('%s'),
            array('%d')
        ) !== false;
    }
}
