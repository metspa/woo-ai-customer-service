<?php
/**
 * Customer Context Class
 *
 * Gathers and formats WooCommerce customer data for Claude API
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_AI_Chat_Customer_Context {

    /**
     * Order tracking instance
     */
    private $order_tracking;

    /**
     * Lead capture instance
     */
    private $lead_capture;

    /**
     * Constructor
     */
    public function __construct() {
        $this->order_tracking = new Woo_AI_Chat_Order_Tracking();
        $this->lead_capture = new Woo_AI_Chat_Lead_Capture();
    }

    /**
     * Build complete customer context for Claude
     *
     * @param array $lead_data Lead information
     * @param string $session_id Session ID
     * @return array Customer context
     */
    public function build_context($lead_data, $session_id) {
        $context = array(
            'customer' => $this->format_lead_info($lead_data),
            'orders' => array(),
            'is_returning' => false,
        );

        // Check if existing WooCommerce customer
        $user = get_user_by('email', $lead_data['email']);
        if ($user) {
            $customer = new WC_Customer($user->ID);
            $context['is_returning'] = true;
            $context['customer']['user_id'] = $user->ID;
            $context['customer']['customer_since'] = date('F Y', strtotime($user->user_registered));
            $context['customer']['total_spent'] = $customer->get_total_spent();
            $context['customer']['order_count'] = $customer->get_order_count();

            // Get recent orders
            $context['orders'] = $this->get_customer_orders($user->ID);
        } else {
            // Try to find orders by email for guest customers
            $context['orders'] = $this->get_orders_by_email($lead_data['email']);
            if (!empty($context['orders'])) {
                $context['is_returning'] = true;
            }
        }

        return $context;
    }

    /**
     * Format lead info
     *
     * @param array $lead_data Lead data
     * @return array Formatted lead info
     */
    private function format_lead_info($lead_data) {
        return array(
            'first_name' => $lead_data['first_name'],
            'last_name' => $lead_data['last_name'],
            'full_name' => $lead_data['first_name'] . ' ' . $lead_data['last_name'],
            'email' => $lead_data['email'],
            'phone' => $lead_data['phone'] ?? '',
        );
    }

    /**
     * Get customer orders with tracking
     *
     * @param int $customer_id Customer user ID
     * @param int $limit Number of orders to retrieve
     * @return array Formatted orders
     */
    public function get_customer_orders($customer_id, $limit = 5) {
        $orders = wc_get_orders(array(
            'customer_id' => $customer_id,
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        return $this->format_orders($orders);
    }

    /**
     * Get orders by email (for guests)
     *
     * @param string $email Customer email
     * @param int $limit Number of orders to retrieve
     * @return array Formatted orders
     */
    public function get_orders_by_email($email, $limit = 5) {
        $orders = wc_get_orders(array(
            'billing_email' => $email,
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        return $this->format_orders($orders);
    }

    /**
     * Format orders for Claude context
     *
     * @param array $orders Array of WC_Order objects
     * @return array Formatted orders
     */
    private function format_orders($orders) {
        $formatted = array();

        foreach ($orders as $order) {
            $order_id = $order->get_id();
            $tracking = $this->order_tracking->get_tracking_info($order_id);
            $shipping = $this->order_tracking->get_shipping_details($order_id);

            $items = array();
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $items[] = array(
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total(),
                    'sku' => $product ? $product->get_sku() : '',
                );
            }

            $formatted[] = array(
                'order_id' => $order_id,
                'order_number' => $order->get_order_number(),
                'date' => $order->get_date_created() ? $order->get_date_created()->format('F j, Y') : '',
                'status' => wc_get_order_status_name($order->get_status()),
                'status_key' => $order->get_status(),
                'total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'payment_method' => $order->get_payment_method_title(),
                'items' => $items,
                'item_count' => $order->get_item_count(),
                'tracking' => $tracking,
                'shipping' => $shipping,
            );
        }

        return $formatted;
    }

    /**
     * Get specific order by ID (verify ownership)
     *
     * @param int $order_id Order ID
     * @param string $email Customer email for verification
     * @return array Order data or error
     */
    public function get_order_for_customer($order_id, $email) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return array('error' => 'Order not found');
        }

        // Verify this order belongs to the customer
        if (strtolower($order->get_billing_email()) !== strtolower($email)) {
            return array('error' => 'Order does not match customer email');
        }

        $formatted = $this->format_orders(array($order));
        return !empty($formatted) ? $formatted[0] : array('error' => 'Error formatting order');
    }

    /**
     * Get product information
     *
     * @param int $product_id Product ID
     * @return array|null Product info or null
     */
    public function get_product_info($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return null;
        }

        return array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'in_stock' => $product->is_in_stock(),
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'short_description' => wp_strip_all_tags($product->get_short_description()),
            'categories' => wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names')),
            'url' => $product->get_permalink(),
        );
    }

    /**
     * Search products by name
     *
     * @param string $query Search query
     * @param int $limit Number of results
     * @return array Product results
     */
    public function search_products($query, $limit = 5) {
        $products = wc_get_products(array(
            's' => $query,
            'limit' => $limit,
            'status' => 'publish',
        ));

        $results = array();
        foreach ($products as $product) {
            $results[] = $this->get_product_info($product->get_id());
        }

        return array_filter($results);
    }

    /**
     * Format context as string for Claude system prompt
     *
     * @param array $context Customer context array
     * @return string Formatted context string
     */
    public function format_for_claude($context) {
        $output = "CUSTOMER INFORMATION:\n";
        $output .= "Name: {$context['customer']['full_name']}\n";
        $output .= "Email: {$context['customer']['email']}\n";

        if (!empty($context['customer']['phone'])) {
            $output .= "Phone: {$context['customer']['phone']}\n";
        }

        if ($context['is_returning']) {
            $output .= "Customer Type: Returning Customer\n";
            if (isset($context['customer']['order_count'])) {
                $output .= "Total Orders: {$context['customer']['order_count']}\n";
            }
            if (isset($context['customer']['total_spent'])) {
                $output .= "Total Spent: $" . number_format((float)$context['customer']['total_spent'], 2) . "\n";
            }
            if (isset($context['customer']['customer_since'])) {
                $output .= "Customer Since: {$context['customer']['customer_since']}\n";
            }
        } else {
            $output .= "Customer Type: New/Guest Customer\n";
        }

        if (!empty($context['orders'])) {
            $output .= "\nRECENT ORDERS:\n";
            foreach ($context['orders'] as $order) {
                $output .= "\n--- Order #{$order['order_number']} ---\n";
                $output .= "Date: {$order['date']}\n";
                $output .= "Status: {$order['status']}\n";
                $output .= "Total: $" . number_format((float)$order['total'], 2) . "\n";

                $output .= "Items:\n";
                foreach ($order['items'] as $item) {
                    $output .= "  - {$item['name']} (x{$item['quantity']})\n";
                }

                // Tracking info
                if (!empty($order['tracking']['tracking_numbers'])) {
                    $output .= "Tracking Number(s): " . implode(', ', $order['tracking']['tracking_numbers']) . "\n";
                    if (!empty($order['tracking']['carrier'])) {
                        $output .= "Carrier: {$order['tracking']['carrier']}\n";
                    }
                    if (!empty($order['tracking']['tracking_url'])) {
                        $output .= "Track Package: {$order['tracking']['tracking_url']}\n";
                    }
                }

                // Shipping address
                if (!empty($order['shipping']['address'])) {
                    $address = str_replace('<br/>', ', ', $order['shipping']['address']);
                    $address = str_replace('<br />', ', ', $address);
                    $address = str_replace("\n", ', ', $address);
                    $output .= "Shipping To: " . wp_strip_all_tags($address) . "\n";
                }
            }
        } else {
            $output .= "\nNo previous orders found for this customer.\n";
        }

        return $output;
    }
}
