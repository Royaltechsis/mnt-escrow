<?php
namespace MNT\Api;

class Client {
    private static $base = "https://escrow-api-1vu6.onrender.com/api";

    public static function post($endpoint, $body) {
        // Debug: log the outgoing JSON payload
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('MNT API POST to ' . self::$base . $endpoint . ' with body: ' . print_r($body, true));
            // Removed echo debug output to prevent breaking AJAX JSON responses
        }
        $res = wp_remote_post(self::$base . $endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode($body),
            'timeout' => 20,
        ]);

        if (is_wp_error($res)) return false;

        return json_decode(wp_remote_retrieve_body($res), true);
    }

    public static function get($endpoint, $params = []) {
        $url = self::$base . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $res = wp_remote_get($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 20,
        ]);

        if (is_wp_error($res)) return false;

        return json_decode(wp_remote_retrieve_body($res), true);
    }
}
