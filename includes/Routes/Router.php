<?php
namespace MNT\Routes;

use MNT\Api\Wallet;
use MNT\Api\Escrow;
use MNT\Api\Transaction;
use MNT\Api\Payment;

class Router {

    public static function register() {
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_action('wp_ajax_mnt_wallet_action', [__CLASS__, 'handle_wallet_ajax']);
        add_action('wp_ajax_mnt_escrow_action', [__CLASS__, 'handle_escrow_ajax']);
        add_action('wp_ajax_mnt_deposit', [__CLASS__, 'handle_deposit']);
        add_action('wp_ajax_mnt_withdraw', [__CLASS__, 'handle_withdraw']);
        add_action('wp_ajax_mnt_transfer', [__CLASS__, 'handle_transfer']);
    }

    /**
     * Register REST API routes
     */
    public static function register_rest_routes() {
        // Wallet endpoints
        register_rest_route('mnt/v1', '/wallet/create', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_create_wallet'],
            'permission_callback' => [__CLASS__, 'check_user_permission']
        ]);

        register_rest_route('mnt/v1', '/wallet/balance', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_balance'],
            'permission_callback' => [__CLASS__, 'check_user_permission']
        ]);

        register_rest_route('mnt/v1', '/wallet/transactions', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_transactions'],
            'permission_callback' => [__CLASS__, 'check_user_permission']
        ]);

        // Escrow endpoints
        register_rest_route('mnt/v1', '/escrow/list', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_list_escrows'],
            'permission_callback' => [__CLASS__, 'check_user_permission']
        ]);

        register_rest_route('mnt/v1', '/escrow/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_escrow'],
            'permission_callback' => [__CLASS__, 'check_user_permission']
        ]);
    }

    /**
     * Permission callback
     */
    public static function check_user_permission() {
        return is_user_logged_in();
    }

    /**
     * REST: Create wallet
     */
    public static function rest_create_wallet($request) {
        $user_id = get_current_user_id();
        $result = Wallet::create($user_id);
        
        if ($result && isset($result['success']) && $result['success']) {
            return new \WP_REST_Response($result, 200);
        }
        
        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Failed to create wallet'
        ], 400);
    }

    /**
     * REST: Get balance
     */
    public static function rest_get_balance($request) {
        $user_id = get_current_user_id();
        $result = Wallet::balance($user_id);
        
        if ($result) {
            return new \WP_REST_Response($result, 200);
        }
        
        return new \WP_REST_Response(['success' => false], 400);
    }

    /**
     * REST: Get transactions
     */
    public static function rest_get_transactions($request) {
        $user_id = get_current_user_id();
        $limit = $request->get_param('limit') ?: 50;
        $offset = $request->get_param('offset') ?: 0;
        
        $result = Wallet::transactions($user_id, $limit, $offset);
        
        if ($result) {
            return new \WP_REST_Response($result, 200);
        }
        
        return new \WP_REST_Response(['success' => false], 400);
    }

    /**
     * REST: List escrows
     */
    public static function rest_list_escrows($request) {
        $user_id = get_current_user_id();
        $status = $request->get_param('status');
        
        $result = Escrow::list_by_user($user_id, $status);
        
        if ($result) {
            return new \WP_REST_Response($result, 200);
        }
        
        return new \WP_REST_Response(['success' => false], 400);
    }

    /**
     * REST: Get escrow details
     */
    public static function rest_get_escrow($request) {
        $escrow_id = $request->get_param('id');
        $result = Escrow::get($escrow_id);
        
        if ($result) {
            return new \WP_REST_Response($result, 200);
        }
        
        return new \WP_REST_Response(['success' => false], 400);
    }

    /**
     * AJAX: Wallet actions
     */
    public static function handle_wallet_ajax() {
        check_ajax_referer('mnt_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['wallet_action'] ?? '');
        $user_id = get_current_user_id();
        
        switch ($action) {
            case 'create':
                $result = Wallet::create($user_id);
                if ($result && isset($result['id'])) {
                    // Store wallet info in user meta
                    update_user_meta($user_id, 'mnt_wallet_created', true);
                    update_user_meta($user_id, 'mnt_wallet_id', $result['id']);
                    update_user_meta($user_id, 'mnt_wallet_uuid', $result['user_id']); // Backend UUID
                    
                    wp_send_json_success([
                        'wallet_id' => $result['id'],
                        'balance' => $result['balance'],
                        'message' => $result['message'] ?? 'Wallet created successfully'
                    ]);
                    return;
                }
                break;
            case 'balance':
                $result = Wallet::balance($user_id);
                break;
            default:
                wp_send_json_error(['message' => 'Invalid action']);
                return;
        }
        
        if ($result && isset($result['success']) && $result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Deposit
     */
    public static function handle_deposit() {
        check_ajax_referer('mnt_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $amount = floatval($_POST['amount'] ?? 0);
        
        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Invalid amount']);
            return;
        }
        
        // Get wallet_id from user meta
        $wallet_id = get_user_meta($user_id, 'mnt_wallet_id', true);
        
        $result = Wallet::deposit($user_id, $amount, $wallet_id);
        
        if ($result && isset($result['checkout_url'])) {
            // API returned checkout URL - success
            wp_send_json_success($result);
        } elseif ($result && isset($result['success']) && $result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Withdraw
     */
    public static function handle_withdraw() {
        check_ajax_referer('mnt_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $amount = floatval($_POST['amount'] ?? 0);
        $account_details = [
            'bank_code' => sanitize_text_field($_POST['bank_code'] ?? ''),
            'account_number' => sanitize_text_field($_POST['account_number'] ?? ''),
            'account_name' => sanitize_text_field($_POST['account_name'] ?? '')
        ];
        
        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Invalid amount']);
            return;
        }
        
        $result = Wallet::withdraw($user_id, $amount, $account_details);
        
        if ($result && isset($result['success']) && $result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Transfer
     */
    public static function handle_transfer() {
        check_ajax_referer('mnt_nonce', 'nonce');
        
        $from_user_id = get_current_user_id();
        $to_user_id = intval($_POST['to_user_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $description = sanitize_text_field($_POST['description'] ?? '');
        
        if ($amount <= 0 || $to_user_id <= 0) {
            wp_send_json_error(['message' => 'Invalid parameters']);
            return;
        }
        
        $result = Wallet::transfer($from_user_id, $to_user_id, $amount, $description);
        
        if ($result && isset($result['success']) && $result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Escrow actions
     */
    public static function handle_escrow_ajax() {
        check_ajax_referer('mnt_nonce', 'nonce');
        
        $action = sanitize_text_field($_POST['escrow_action'] ?? '');
        $escrow_id = intval($_POST['escrow_id'] ?? 0);
        $user_id = get_current_user_id();
        
        switch ($action) {
            case 'release':
                $result = Escrow::release($escrow_id, $user_id);
                break;
            case 'refund':
                $result = Escrow::refund($escrow_id, $user_id);
                break;
            case 'dispute':
                $reason = sanitize_textarea_field($_POST['reason'] ?? '');
                $result = Escrow::dispute($escrow_id, $user_id, $reason);
                break;
            case 'cancel':
                $reason = sanitize_textarea_field($_POST['reason'] ?? '');
                $result = Escrow::cancel($escrow_id, $reason);
                break;
            default:
                wp_send_json_error(['message' => 'Invalid action']);
                return;
        }
        
        if ($result && isset($result['success']) && $result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

}
