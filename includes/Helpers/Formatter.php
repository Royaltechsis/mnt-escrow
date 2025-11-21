<?php
namespace MNT\Helpers;

class Formatter {

    /**
     * Format currency
     */
    public static function format_currency($amount, $currency = 'NGN') {
        $symbol = $currency === 'NGN' ? '₦' : '$';
        return $symbol . number_format($amount, 2);
    }

    /**
     * Format date
     */
    public static function format_date($date, $format = 'M d, Y h:i A') {
        return date($format, strtotime($date));
    }

    /**
     * Get status badge HTML
     */
    public static function get_status_badge($status) {
        $class = 'status-' . strtolower($status);
        return '<span class="' . esc_attr($class) . '">' . esc_html(ucfirst($status)) . '</span>';
    }

    /**
     * Sanitize amount
     */
    public static function sanitize_amount($amount) {
        return max(0, floatval($amount));
    }

    /**
     * Generate transaction reference
     */
    public static function generate_reference($prefix = 'MNT') {
        return $prefix . '_' . time() . '_' . wp_generate_password(8, false);
    }
}
