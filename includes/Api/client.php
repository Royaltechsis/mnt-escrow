<?php
namespace MNT\Api;

class Client {
    private static $base = "https://escrow-api-dfl6.onrender.com/api";

    public static function post($endpoint, $body) {
        $url = self::$base . $endpoint;
        $json_body = json_encode($body);
        
        // Debug: log the outgoing JSON payload
        error_log('=== MNT API POST Request ===');
        error_log('URL: ' . $url);
        error_log('Body (array): ' . print_r($body, true));
        error_log('Body (JSON): ' . $json_body);
        
        $res = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $json_body,
            'timeout' => 20,
        ]);

        if (is_wp_error($res)) {
            error_log('=== MNT API POST Error ===');
            error_log('Error: ' . $res->get_error_message());
            return false;
        }

        $response_body = wp_remote_retrieve_body($res);
        $response_code = wp_remote_retrieve_response_code($res);
        $decoded = json_decode($response_body, true);
        
        // Debug: log the response
        error_log('=== MNT API POST Response ===');
        error_log('Status Code: ' . $response_code);
        error_log('Response Body (raw): ' . $response_body);
        error_log('Response Body (decoded): ' . print_r($decoded, true));

        return $decoded;
    }

    public static function get($endpoint, $params = []) {
        $url = self::$base . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        // Debug: log the outgoing GET request
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('MNT API GET to ' . $url);
        }

        $res = wp_remote_get($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 20,
        ]);

        if (is_wp_error($res)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('MNT API GET Error: ' . $res->get_error_message());
            }
            return false;
        }

        $body = wp_remote_retrieve_body($res);
        $response_code = wp_remote_retrieve_response_code($res);
        
        // Debug: log the response
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('MNT API GET Response Code: ' . $response_code);
            error_log('MNT API GET Response Body: ' . $body);
        }

        return json_decode($body, true);
    }
}
