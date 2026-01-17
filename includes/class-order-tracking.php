<?php
/**
 * Order Tracking Class
 *
 * Handles WooCommerce order tracking integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_AI_Chat_Order_Tracking {

    /**
     * Get tracking info for an order
     * Supports: WooCommerce Shipment Tracking, AfterShip, AST, custom meta
     *
     * @param int $order_id Order ID
     * @return array|null Tracking information or null
     */
    public function get_tracking_info($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }

        $tracking = array(
            'order_id' => $order_id,
            'status' => $order->get_status(),
            'status_label' => wc_get_order_status_name($order->get_status()),
            'date_created' => $order->get_date_created() ? $order->get_date_created()->format('F j, Y') : '',
            'tracking_numbers' => array(),
            'carrier' => '',
            'tracking_url' => '',
            'estimated_delivery' => '',
        );

        // Check for WooCommerce Shipment Tracking plugin
        if (function_exists('wc_st_get_tracking_items')) {
            $tracking_items = wc_st_get_tracking_items($order_id);
            if (!empty($tracking_items)) {
                foreach ($tracking_items as $item) {
                    if (!empty($item['tracking_number'])) {
                        $tracking['tracking_numbers'][] = $item['tracking_number'];
                    }
                    if (empty($tracking['carrier']) && !empty($item['tracking_provider'])) {
                        $tracking['carrier'] = $item['tracking_provider'];
                    }
                    if (empty($tracking['tracking_url']) && !empty($item['tracking_link'])) {
                        $tracking['tracking_url'] = $item['tracking_link'];
                    }
                }
            }
        }

        // Check for AST (Advanced Shipment Tracking)
        $ast_tracking = $order->get_meta('_wc_shipment_tracking_items', true);
        if (!empty($ast_tracking) && is_array($ast_tracking)) {
            foreach ($ast_tracking as $item) {
                if (!empty($item['tracking_number']) && !in_array($item['tracking_number'], $tracking['tracking_numbers'])) {
                    $tracking['tracking_numbers'][] = $item['tracking_number'];
                }
                if (empty($tracking['carrier']) && !empty($item['tracking_provider'])) {
                    $tracking['carrier'] = $item['tracking_provider'];
                }
            }
        }

        // Check common meta keys for tracking
        $common_tracking_keys = array(
            '_tracking_number',
            'tracking_number',
            '_shipment_tracking_number',
            'fedex_tracking_number',
            'ups_tracking_number',
            'usps_tracking_number',
            'dhl_tracking_number',
        );

        foreach ($common_tracking_keys as $key) {
            $value = $order->get_meta($key, true);
            if (!empty($value) && !in_array($value, $tracking['tracking_numbers'])) {
                $tracking['tracking_numbers'][] = $value;
            }
        }

        // Get carrier from meta if not set
        if (empty($tracking['carrier'])) {
            $carrier_keys = array('_shipping_carrier', 'shipping_carrier', '_tracking_provider', 'tracking_provider');
            foreach ($carrier_keys as $key) {
                $carrier = $order->get_meta($key, true);
                if (!empty($carrier)) {
                    $tracking['carrier'] = $carrier;
                    break;
                }
            }
        }

        // Generate tracking URL if we have number but no URL
        if (!empty($tracking['tracking_numbers']) && empty($tracking['tracking_url'])) {
            $tracking['tracking_url'] = $this->generate_tracking_url(
                $tracking['tracking_numbers'][0],
                $tracking['carrier']
            );
        }

        return $tracking;
    }

    /**
     * Generate tracking URL based on carrier
     *
     * @param string $tracking_number Tracking number
     * @param string $carrier Carrier name
     * @return string Tracking URL
     */
    public function generate_tracking_url($tracking_number, $carrier = '') {
        $carrier_lower = strtolower($carrier);
        $tracking_number = urlencode($tracking_number);

        $carriers = array(
            'usps' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=' . $tracking_number,
            'ups' => 'https://www.ups.com/track?tracknum=' . $tracking_number,
            'fedex' => 'https://www.fedex.com/fedextrack/?trknbr=' . $tracking_number,
            'dhl' => 'https://www.dhl.com/en/express/tracking.html?AWB=' . $tracking_number,
            'ontrac' => 'https://www.ontrac.com/trackingdetail.asp?tracking=' . $tracking_number,
            'lasership' => 'https://www.lasership.com/track/' . $tracking_number,
        );

        foreach ($carriers as $key => $url) {
            if (strpos($carrier_lower, $key) !== false) {
                return $url;
            }
        }

        // Default to 17track for unknown carriers
        return 'https://t.17track.net/en#nums=' . $tracking_number;
    }

    /**
     * Get shipping method details
     *
     * @param int $order_id Order ID
     * @return array|null Shipping details or null
     */
    public function get_shipping_details($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }

        $shipping_methods = $order->get_shipping_methods();
        $shipping_info = array();

        foreach ($shipping_methods as $method) {
            $shipping_info[] = array(
                'method' => $method->get_method_title(),
                'cost' => $method->get_total(),
            );
        }

        return array(
            'methods' => $shipping_info,
            'address' => $order->get_formatted_shipping_address(),
            'shipping_total' => $order->get_shipping_total(),
        );
    }

    /**
     * Get order timeline/history
     *
     * @param int $order_id Order ID
     * @return array Order timeline events
     */
    public function get_order_timeline($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return array();
        }

        $timeline = array();

        // Order created
        if ($order->get_date_created()) {
            $timeline[] = array(
                'date' => $order->get_date_created()->format('M j, Y g:i a'),
                'timestamp' => $order->get_date_created()->getTimestamp(),
                'event' => 'Order placed',
            );
        }

        // Payment date
        if ($order->get_date_paid()) {
            $timeline[] = array(
                'date' => $order->get_date_paid()->format('M j, Y g:i a'),
                'timestamp' => $order->get_date_paid()->getTimestamp(),
                'event' => 'Payment received',
            );
        }

        // Date completed
        if ($order->get_date_completed()) {
            $timeline[] = array(
                'date' => $order->get_date_completed()->format('M j, Y g:i a'),
                'timestamp' => $order->get_date_completed()->getTimestamp(),
                'event' => 'Order completed',
            );
        }

        // Get order notes (customer-facing only)
        $notes = wc_get_order_notes(array(
            'order_id' => $order_id,
            'type' => 'customer',
        ));

        foreach ($notes as $note) {
            $timeline[] = array(
                'date' => $note->date_created->format('M j, Y g:i a'),
                'timestamp' => $note->date_created->getTimestamp(),
                'event' => wp_strip_all_tags($note->content),
            );
        }

        // Sort by timestamp
        usort($timeline, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });

        // Remove timestamp from output
        return array_map(function($item) {
            unset($item['timestamp']);
            return $item;
        }, $timeline);
    }

    /**
     * Get order status explanation
     *
     * @param string $status Order status
     * @return string Status explanation
     */
    public function get_status_explanation($status) {
        $explanations = array(
            'pending' => 'Order received, awaiting payment',
            'processing' => 'Payment received, order is being prepared for shipment',
            'on-hold' => 'Awaiting payment confirmation or review',
            'completed' => 'Order has been shipped and/or delivered',
            'cancelled' => 'Order has been cancelled',
            'refunded' => 'Order has been refunded',
            'failed' => 'Payment failed or was declined',
            'shipped' => 'Package is in transit to you',
            'delivered' => 'Package has been delivered',
        );

        return isset($explanations[$status]) ? $explanations[$status] : 'Order is being processed';
    }

    /**
     * Check if order has been shipped
     *
     * @param int $order_id Order ID
     * @return bool Whether order has tracking info
     */
    public function is_shipped($order_id) {
        $tracking = $this->get_tracking_info($order_id);
        return !empty($tracking['tracking_numbers']);
    }

    /**
     * Get estimated delivery date if available
     *
     * @param int $order_id Order ID
     * @return string|null Estimated delivery date or null
     */
    public function get_estimated_delivery($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return null;
        }

        // Check common meta keys for estimated delivery
        $delivery_keys = array(
            '_estimated_delivery',
            'estimated_delivery',
            '_delivery_date',
            'delivery_date',
            '_estimated_delivery_date',
        );

        foreach ($delivery_keys as $key) {
            $value = $order->get_meta($key, true);
            if (!empty($value)) {
                // Try to format as date if it's a timestamp
                if (is_numeric($value)) {
                    return date('F j, Y', $value);
                }
                return $value;
            }
        }

        return null;
    }
}
