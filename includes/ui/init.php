<?php
namespace MNT\UI;

class Init {

    public static function register_hooks() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_shortcode('mnt_wallet_dashboard', [__CLASS__, 'wallet_dashboard_shortcode']);
        add_shortcode('mnt_wallet_balance', [__CLASS__, 'wallet_balance_shortcode']);
        add_shortcode('mnt_deposit_form', [__CLASS__, 'deposit_form_shortcode']);
        add_shortcode('mnt_withdraw_form', [__CLASS__, 'withdraw_form_shortcode']);
        add_shortcode('mnt_transfer_form', [__CLASS__, 'transfer_form_shortcode']);
        add_shortcode('mnt_transactions', [__CLASS__, 'transactions_shortcode']);
        add_shortcode('mnt_transaction_history', [__CLASS__, 'transaction_history_shortcode']);
        add_shortcode('mnt_escrow_box', [__CLASS__, 'escrow_box_shortcode']);
        add_shortcode('mnt_escrow_list', [__CLASS__, 'escrow_list_shortcode']);
        add_shortcode('mnt_create_wallet', [__CLASS__, 'create_wallet_shortcode']);
        add_shortcode('mnt_escrow_deposit', [__CLASS__, 'escrow_deposit_shortcode']);
            
        
        // AJAX handlers
        add_action('wp_ajax_mnt_create_escrow_transaction', [__CLASS__, 'handle_create_escrow_ajax']);
        add_action('wp_ajax_mnt_deposit', [__CLASS__, 'handle_deposit_ajax']);
        add_action('wp_ajax_mnt_withdraw', [__CLASS__, 'handle_withdraw_ajax']);
        add_action('wp_ajax_mnt_transfer', [__CLASS__, 'handle_transfer_ajax']);
        add_action('wp_ajax_mnt_complete_escrow_funds', [__CLASS__, 'handle_complete_escrow_funds_ajax']);
        add_action('wp_ajax_nopriv_mnt_complete_escrow_funds', [__CLASS__, 'handle_complete_escrow_funds_ajax']);
        add_action('wp_ajax_mnt_fund_escrow', [__CLASS__, 'handle_fund_escrow_ajax']);
        add_action('wp_ajax_nopriv_mnt_fund_escrow', [__CLASS__, 'handle_fund_escrow_ajax']);
        add_action('wp_ajax_mnt_merchant_confirm_funds', [__CLASS__, 'handle_merchant_confirm_funds_ajax']);
        add_action('wp_ajax_nopriv_mnt_merchant_confirm_funds', [__CLASS__, 'handle_merchant_confirm_funds_ajax']);
        add_action('wp_ajax_mnt_merchant_release_funds_action', [__CLASS__, 'handle_merchant_release_funds_ajax']);
        add_action('wp_ajax_nopriv_mnt_merchant_release_funds_action', [__CLASS__, 'handle_merchant_release_funds_ajax']);
        
        // Milestone approval handler
        add_action('wp_ajax_mnt_approve_milestone', [__CLASS__, 'handle_approve_milestone_ajax']);
        add_action('wp_ajax_nopriv_mnt_approve_milestone', [__CLASS__, 'handle_approve_milestone_ajax']);
        
        // Helper to get project ID from proposal
        add_action('wp_ajax_mnt_get_project_from_proposal', [__CLASS__, 'handle_get_project_from_proposal_ajax']);
        add_action('wp_ajax_nopriv_mnt_get_project_from_proposal', [__CLASS__, 'handle_get_project_from_proposal_ajax']);
        
        // Helper to get seller ID from proposal
        add_action('wp_ajax_mnt_get_seller_from_proposal', [__CLASS__, 'handle_get_seller_from_proposal_ajax']);
        add_action('wp_ajax_nopriv_mnt_get_seller_from_proposal', [__CLASS__, 'handle_get_seller_from_proposal_ajax']);
        
        // Create order before escrow page load
        add_action('wp_ajax_mnt_create_order_before_escrow', [__CLASS__, 'handle_create_order_before_escrow_ajax']);
        add_action('wp_ajax_nopriv_mnt_create_order_before_escrow', [__CLASS__, 'handle_create_order_before_escrow_ajax']);
        // Admin backfill endpoint to repair draft task orders
        add_action('wp_ajax_mnt_backfill_task_orders', [__CLASS__, 'handle_mnt_backfill_task_orders_ajax']);
        // Admin repair single order endpoint
        add_action('wp_ajax_mnt_admin_repair_order', [__CLASS__, 'handle_mnt_admin_repair_order_ajax']);
        // Client confirm (release funds) endpoint
        add_action('wp_ajax_mnt_client_confirm', [__CLASS__, 'handle_client_confirm_ajax']);
        add_action('wp_ajax_nopriv_mnt_client_confirm', [__CLASS__, 'handle_client_confirm_ajax']);

    }

    /**
     * Get seller task orders (IDs) used by Taskbot dashboard
     *
     * @param int $seller_id
     * @param string $order_type Optional: _task_status filter (e.g., 'hired')
     * @return array Array of order IDs
        *
        * Note: returns unique IDs only; uses DISTINCT and normalization to avoid duplicate IDs
     */
    public static function mnt_get_seller_task_orders($seller_id = 0, $order_type = 'any') {
        $seller_id = intval($seller_id);
        if (!$seller_id) {
            return [];
        }

        // Show orders regardless of their post_status to avoid missing items due to timing issues
        $order_status = null; // use 'any' in WP_Query below when null

        $meta_query = [
            'relation' => 'AND',
            [ 'key' => 'payment_type', 'value' => 'tasks', 'compare' => '=' ],
            // Accept either 'seller_id' or '_seller_id' meta key
            [
                'relation' => 'OR',
                [ 'key' => 'seller_id', 'value' => $seller_id, 'compare' => '=' ],
                [ 'key' => '_seller_id', 'value' => $seller_id, 'compare' => '=' ],
            ],
        ];

        if (!empty($order_type) && $order_type !== 'any') {
            $meta_query[] = [ 'key' => '_task_status', 'value' => $order_type, 'compare' => '=' ];
        }

            $args = [
                'posts_per_page' => -1,
                'post_type'      => 'shop_order',
                'post_status'    => 'any',
                'fields'         => 'ids',
                'meta_query'     => $meta_query,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ];

        $q = new \WP_Query($args);
        if (!empty($q->posts)) {
            // Normalize to unique integer IDs to be defensive against duplicates
            return array_values(array_unique(array_map('intval', $q->posts)));
        }

        // Fallback: WP_Query returned nothing but meta rows exist (edge-case with INNER JOIN)
        global $wpdb;
            // Use DISTINCT to avoid duplicate post IDs when multiple meta rows exist
            $found = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT pm1.post_id FROM {$wpdb->postmeta} pm1 JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id JOIN {$wpdb->posts} p ON p.ID = pm1.post_id WHERE pm1.meta_key = %s AND pm1.meta_value = %s AND pm2.meta_key IN (%s,%s) AND pm2.meta_value = %s ORDER BY p.post_date DESC",
                'payment_type', 'tasks', 'seller_id', '_seller_id', $seller_id
            ) );

        // Ensure every order has a task status; if missing, default to 'inqueue'
        $result = [];
        if (is_array($found)) {
            // Ensure unique, integer IDs in case of unexpected duplicates
            $found = array_values(array_unique(array_map('intval', $found)));
            foreach ($found as $pid) {
                $pid = intval($pid);
                $ts = get_post_meta($pid, '_task_status', true);
                if (empty($ts)) {
                    // default missing _task_status to 'inqueue' for dashboard visibility
                    error_log('MNT: Setting default _task_status=inqueue for order ' . $pid);
                    self::mnt_update_task_status($pid, 'inqueue', false);
                    $ts = 'inqueue';
                }
                // If a specific order_type was requested, filter by it
                if (!empty($order_type) && $order_type !== 'any') {
                    if ($ts === $order_type) {
                        $result[] = $pid;
                    }
                } else {
                    $result[] = $pid;
                }
            }
        }

        return $result;
    }

    /**
     * Get buyer task orders (IDs) used by Taskbot dashboard
     *
     * @param int $buyer_id
     * @param string $order_type Optional: _task_status filter (e.g., 'hired')
     * @return array Array of order IDs
        *
        * Note: returns unique IDs only; uses DISTINCT and normalization to avoid duplicate IDs
     */
    public static function mnt_get_buyer_task_orders($buyer_id = 0, $order_type = 'any') {
        $buyer_id = intval($buyer_id);
        if (!$buyer_id) {
            return [];
        }

        // Show orders regardless of their post_status to avoid missing items due to timing issues
        $order_status = null; // use 'any' in WP_Query below when null

        $meta_query = [
            'relation' => 'AND',
            [ 'key' => 'payment_type', 'value' => 'tasks', 'compare' => '=' ],
            // Accept either 'buyer_id' or '_buyer_id' meta key
            [
                'relation' => 'OR',
                [ 'key' => 'buyer_id', 'value' => $buyer_id, 'compare' => '=' ],
                [ 'key' => '_buyer_id', 'value' => $buyer_id, 'compare' => '=' ],
            ],
        ];

        if (!empty($order_type) && $order_type !== 'any') {
            $meta_query[] = [ 'key' => '_task_status', 'value' => $order_type, 'compare' => '=' ];
        }

            $args = [
                'posts_per_page' => -1,
                'post_type'      => 'shop_order',
                'post_status'    => 'any',
                'fields'         => 'ids',
                'meta_query'     => $meta_query,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ];

        $q = new \WP_Query($args);
        if (!empty($q->posts)) {
            // Normalize to unique integer IDs to be defensive against duplicates
            return array_values(array_unique(array_map('intval', $q->posts)));
        }

        // Fallback: WP_Query returned nothing but meta rows exist (edge-case with INNER JOIN)
        global $wpdb;
            // Use DISTINCT to avoid duplicate post IDs when multiple meta rows exist
            $found = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT pm1.post_id FROM {$wpdb->postmeta} pm1 JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id JOIN {$wpdb->posts} p ON p.ID = pm1.post_id WHERE pm1.meta_key = %s AND pm1.meta_value = %s AND pm2.meta_key IN (%s,%s) AND pm2.meta_value = %s ORDER BY p.post_date DESC",
                'payment_type', 'tasks', 'buyer_id', '_buyer_id', $buyer_id
            ) );

        // Ensure every order has a task status; if missing, default to 'inqueue'
        $result = [];
        if (is_array($found)) {
            // Ensure unique, integer IDs in case of unexpected duplicates
            $found = array_values(array_unique(array_map('intval', $found)));
            foreach ($found as $pid) {
                $pid = intval($pid);
                $ts = get_post_meta($pid, '_task_status', true);
                if (empty($ts)) {
                    error_log('MNT: Setting default _task_status=inqueue for order ' . $pid);
                    self::mnt_update_task_status($pid, 'inqueue', false);
                    $ts = 'inqueue';
                }
                if (!empty($order_type) && $order_type !== 'any') {
                    if ($ts === $order_type) {
                        $result[] = $pid;
                    }
                } else {
                    $result[] = $pid;
                }
            }
        }

        return $result;
    }
    
    /**
     * Enqueue plugin scripts and styles
     */
    public static function enqueue_scripts() {
        // Enqueue main styles
        wp_enqueue_style(
            'mnt-escrow-style',
            MNT_ESCROW_URL . 'assets/css/style.css',
            [],
            MNT_ESCROW_VERSION
        );
        
        // Enqueue main escrow JavaScript
        wp_enqueue_script(
            'mnt-escrow-js',
            MNT_ESCROW_URL . 'assets/js/escrow.js',
            ['jquery'],
            MNT_ESCROW_VERSION,
            true
        );
        
        // Enqueue complete escrow JavaScript
        wp_enqueue_script(
            'mnt-complete-escrow-js',
            MNT_ESCROW_URL . 'assets/js/mnt-complete-escrow.js',
            ['jquery'],
            MNT_ESCROW_VERSION,
            true
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script('mnt-complete-escrow-js', 'mntEscrow', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mnt_nonce'),
            'currentUserId' => get_current_user_id(),
        ]);
        
        wp_localize_script('mnt-escrow-js', 'mntEscrow', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mnt_nonce'),
            'currentUserId' => get_current_user_id(),
        ]);
    }

    /**
     * Ensure a post/order has a task status and update it centrally.
     *
     * @param int $post_id
     * @param string $status
     * @param bool $set_wc_status If true, also attempt to update the WooCommerce order status mapping
     * @return bool
     */
    public static function mnt_update_task_status( $post_id, $status = 'inqueue', $set_wc_status = false ) {
        $post_id = intval($post_id);
        if ( ! $post_id ) {
            return false;
        }

        $status = sanitize_text_field( $status );

        // Update canonical meta keys used by the dashboard
        update_post_meta( $post_id, '_task_status', $status );
        update_post_meta( $post_id, '_taskbot_order_status', $status );

        // Optionally map task status to a WC order status when this is an order
        if ( $set_wc_status && function_exists('wc_get_order') ) {
            $order = wc_get_order( $post_id );
            if ( $order ) {
                $map = [
                    'hired'     => 'processing',
                    'inqueue'   => 'pending',
                    'pending'   => 'pending',
                    'completed' => 'completed',
                    'cancelled' => 'cancelled',
                    'refunded'  => 'refunded',
                ];
                $wc_status = isset($map[$status]) ? $map[$status] : null;
                if ( $wc_status ) {
                    try {
                        $order->set_status( $wc_status );
                        $order->save();
                        clean_post_cache( $post_id );
                        wp_cache_delete( $post_id, 'post_meta' );
                    } catch (\Exception $e) {
                        error_log('MNT: Error setting WC order status for order ' . $post_id . ': ' . $e->getMessage());
                    }
                }
            }
        }

        return true;
    }

    /**
     * AJAX Handler: Get Project ID from Proposal ID
     */
    public static function handle_get_project_from_proposal_ajax() {
        check_ajax_referer('mnt_nonce', 'nonce');
        
        $proposal_id = isset($_POST['proposal_id']) ? intval($_POST['proposal_id']) : 0;
        
        if (!$proposal_id) {
            wp_send_json_error(['message' => 'Missing proposal ID.']);
            return;
        }
        
        // Get project ID from proposal meta
        $project_id = get_post_meta($proposal_id, 'project_id', true);
        
        if (!$project_id) {
            // Try alternative meta key
            $project_id = get_post_meta($proposal_id, '_project_id', true);
        }
        
        if (!$project_id) {
            // Try getting from proposal post parent
            $proposal = get_post($proposal_id);
            if ($proposal && $proposal->post_parent) {
                $project_id = $proposal->post_parent;
            }
        }
        
        if ($project_id) {
            wp_send_json_success(['project_id' => $project_id]);
        } else {
            wp_send_json_error(['message' => 'Project ID not found for this proposal.']);
        }
    }

    /**
     * AJAX Handler: Get Seller ID from Proposal ID
     */
    public static function handle_get_seller_from_proposal_ajax() {
        check_ajax_referer('mnt_nonce', 'nonce');
        
        $proposal_id = isset($_POST['proposal_id']) ? intval($_POST['proposal_id']) : 0;
        
        if (!$proposal_id) {
            wp_send_json_error(['message' => 'Missing proposal ID.']);
            return;
        }
        
        // Get seller ID from proposal author
        $proposal = get_post($proposal_id);
        
        if ($proposal && $proposal->post_author) {
            $seller_id = $proposal->post_author;
            wp_send_json_success(['seller_id' => $seller_id]);
        } else {
            wp_send_json_error(['message' => 'Seller ID not found for this proposal.']);
        }
    }

    /**
     * AJAX Handler: Approve Milestone and Release Funds to Seller Wallet
     * Called when buyer clicks "Approve" button on milestone
     */
    public static function handle_approve_milestone_ajax() {
        check_ajax_referer('mnt_nonce', 'nonce');
        
        $proposal_id = isset($_POST['proposal_id']) ? intval($_POST['proposal_id']) : 0;
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $milestone_key = isset($_POST['milestone_key']) ? sanitize_text_field($_POST['milestone_key']) : '';
        $user_id = get_current_user_id();
        
        error_log('MNT Approve Milestone - Received: proposal_id=' . $proposal_id . ', project_id=' . $project_id . ', milestone_key=' . $milestone_key . ', user_id=' . $user_id);
        
        if (!$proposal_id || !$project_id || !$milestone_key) {
            wp_send_json_error(['message' => 'Missing required parameters.']);
            return;
        }
        
        // First check if milestone escrow exists
        $milestone_escrows = get_post_meta($project_id, 'mnt_milestone_escrows', true);
        error_log('MNT Approve Milestone - Stored milestone escrows: ' . json_encode($milestone_escrows));
        
        if (empty($milestone_escrows[$milestone_key])) {
            error_log('MNT Approve Milestone - ERROR: Milestone not found in local storage!');
            wp_send_json_error([
                'message' => 'Error: This milestone has not been paid for yet. Please pay for the milestone first using the "Escrow" button.',
                'debug' => [
                    'milestone_key' => $milestone_key,
                    'stored_milestones' => array_keys($milestone_escrows ?: [])
                ]
            ]);
            return;
        }
        
        // Get seller ID for API call
        $seller_id = get_post_meta($project_id, 'mnt_escrow_seller', true);
        if (!$seller_id) {
            error_log('MNT Approve Milestone - ERROR: Cannot find seller_id for project ' . $project_id);
            wp_send_json_error([
                'message' => 'Error: Cannot approve milestone - seller information not found.'
            ]);
            return;
        }
        
        error_log('');
        error_log('=== MNT APPROVE MILESTONE - API CALL ===');
        error_log('Endpoint: POST https://escrow-api-dfl6.onrender.com/api/escrow/client_confirm_milestone');
        error_log('Payload to be sent:');
        error_log('  project_id: ' . $project_id . ' (type: ' . gettype($project_id) . ')');
        error_log('  client_id: ' . $user_id . ' (type: ' . gettype($user_id) . ')');
        error_log('  merchant_id: ' . $seller_id . ' (type: ' . gettype($seller_id) . ')');
        error_log('  milestone_key: ' . $milestone_key . ' (type: ' . gettype($milestone_key) . ')');
        error_log('  confirm_status: true (boolean)');
        error_log('Making API call...');
        
        // Call the client_confirm_milestone API to release funds to seller wallet
        // IMPORTANT: Do NOT update local milestone status until we get success from API
        $result = \MNT\Api\Escrow::client_confirm_milestone($project_id, $user_id, $seller_id, $milestone_key, true);
        
        error_log('');
        error_log('=== MNT APPROVE MILESTONE - API RESPONSE ===');
        error_log('Response: ' . json_encode($result));
        error_log('is_null: ' . (is_null($result) ? 'YES' : 'NO'));
        error_log('is_array: ' . (is_array($result) ? 'YES' : 'NO'));
        error_log('has error: ' . (isset($result['error']) ? 'YES - ' . $result['error'] : 'NO'));
        error_log('has detail: ' . (isset($result['detail']) ? 'YES - ' . $result['detail'] : 'NO'));
        error_log('=========================================');
        error_log('');
        
        // Check for successful response
        // Only consider success if API explicitly succeeds (no error/detail fields)
        $is_success = is_array($result) && !isset($result['error']) && !isset($result['detail']);
        
        if ($is_success) {
            error_log('SUCCESS: API approved milestone. Updating local milestone status...');
            // Update milestone status in proposal meta
            $proposal_meta = get_post_meta($proposal_id, 'proposal_meta', true);
            if (!empty($proposal_meta['milestone'][$milestone_key])) {
                $proposal_meta['milestone'][$milestone_key]['status'] = 'completed';
                $proposal_meta['milestone'][$milestone_key]['completed_at'] = current_time('mysql');
                update_post_meta($proposal_id, 'proposal_meta', $proposal_meta);
            }
            
            // Update milestone escrow status
            $milestone_escrows = get_post_meta($project_id, 'mnt_milestone_escrows', true);
            if (!empty($milestone_escrows[$milestone_key])) {
                $milestone_escrows[$milestone_key]['status'] = 'released';
                $milestone_escrows[$milestone_key]['released_at'] = current_time('mysql');
                update_post_meta($project_id, 'mnt_milestone_escrows', $milestone_escrows);
            }
            
            error_log('Local milestone status updated successfully.');
            
            wp_send_json_success([
                'message' => $result['message'] ?? 'Milestone approved! Funds released to seller wallet.',
                'result' => $result
            ]);
        } else {
            // API call failed - DO NOT update milestone status locally
            $error_msg = isset($result['detail']) ? $result['detail'] : (isset($result['error']) ? $result['error'] : (isset($result['message']) ? $result['message'] : 'Failed to approve milestone.'));
            
            error_log('FAILED: API did not approve milestone. NOT updating local status.');
            error_log('Error message: ' . $error_msg);
            error_log('MNT Approve Milestone - Full API Response: ' . print_r($result, true));
            
            // Build detailed error message
            $detailed_error = '<strong>Failed to approve milestone:</strong><br><br>';
            
            // Provide helpful message if milestone not found
            if (stripos($error_msg, 'not found') !== false || stripos($error_msg, 'None') !== false) {
                $detailed_error .= '• <strong>Milestone not found in API:</strong> The milestone exists locally but the API cannot find it.<br>';
                $detailed_error .= '• This usually means the milestone escrow was not successfully created in the API.<br>';
                $detailed_error .= '• <strong>Solution:</strong> Try paying for the milestone again using the "Escrow" button.<br><br>';
            }
            
            // Add the original error message
            $detailed_error .= '<strong>API Error:</strong> ' . esc_html($error_msg) . '<br><br>';
            
            // Add full API response for debugging
            if (!empty($result)) {
                $detailed_error .= '<strong>Full API Response:</strong><br>';
                $detailed_error .= '<pre style="background: #1f2937; color: #f3f4f6; padding: 10px; border-radius: 4px; overflow: auto; max-height: 300px; font-size: 11px;">';
                $detailed_error .= esc_html(print_r($result, true));
                $detailed_error .= '</pre>';
            }
            
            wp_send_json_error([
                'message' => $detailed_error,
                'result' => $result
            ]);
        }
    }

    /**
     * AJAX Handler: Merchant Release Funds (seller releases funds after both confirmed)
     */
    public static function handle_merchant_release_funds_ajax() {
        check_ajax_referer('mnt_nonce', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        error_log('MNT Merchant Release Funds - Received: project_id=' . $project_id . ', user_id=' . $user_id);
        
        if (!$project_id || !$user_id) {
            wp_send_json_error(['message' => 'Missing project or user ID.']);
        }
        
        // Call the merchant_release_funds API
        $result = \MNT\Api\Escrow::merchant_release_funds($project_id, $user_id);
        
        error_log('MNT Merchant Release Funds - API Response: ' . json_encode($result));
        
        if ($result && !isset($result['error'])) {
            // Update meta to mark funds as released
            $proposal_id = get_post_meta($project_id, 'proposal_id', true);
            if (!$proposal_id) {
                $proposal_id = $project_id;
            }
            update_post_meta($proposal_id, 'mnt_funds_released', true);
            update_post_meta($proposal_id, 'mnt_funds_released_at', current_time('mysql'));
            
            wp_send_json_success([
                'message' => $result['message'] ?? 'Funds released successfully to your wallet!',
                'result' => $result
            ]);
        } else {
            $error_msg = isset($result['error']) ? $result['error'] : (isset($result['message']) ? $result['message'] : 'Failed to release funds.');
            wp_send_json_error([
                'message' => $error_msg,
                'result' => $result
            ]);
        }
    }

    /**
     * AJAX Handler: Merchant Confirm Funds (seller confirms project completion)
     */
    public static function handle_merchant_confirm_funds_ajax() {
        check_ajax_referer('mnt_nonce', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $confirm_status = isset($_POST['confirm_status']) ? filter_var($_POST['confirm_status'], FILTER_VALIDATE_BOOLEAN) : true;
        
        // Debug: Log received data
        error_log('MNT Merchant Confirm - Received Data: ' . print_r([
            'project_id' => $project_id,
            'user_id' => $user_id,
            'confirm_status' => $confirm_status,
            'raw_post' => $_POST
        ], true));
        
        if (!$project_id || !$user_id) {
            error_log('MNT Merchant Confirm - Missing project or user ID');
            wp_send_json_error(['message' => 'Missing project or user ID.', 'debug' => [
                'project_id' => $project_id,
                'user_id' => $user_id
            ]]);
        }
        
        // Call the API with confirm_status explicitly set to true
        error_log('MNT Merchant Confirm - Calling API with: project_id=' . $project_id . ', user_id=' . $user_id . ', confirm_status=true');
        $result = \MNT\Api\Escrow::merchant_confirm((string)$project_id, (string)$user_id, true);
        
        // Debug: Log API response
        error_log('MNT Merchant Confirm - API Response: ' . print_r($result, true));
        
        if (!empty($result) && !isset($result['error'])) {
            // Check if both parties have now confirmed
            $seller_id = get_post_meta($project_id, 'mnt_escrow_seller', true);
            $escrow_details = \MNT\Api\Escrow::get_escrow_by_id($project_id, $seller_id);
            error_log('MNT Merchant Confirm - Escrow Details: ' . print_r($escrow_details, true));
            
            $both_confirmed = false;
            $release_result = null;
            
            if ($escrow_details && isset($escrow_details['client_agree']) && isset($escrow_details['merchant_agree'])) {
                if ($escrow_details['client_agree'] === true && $escrow_details['merchant_agree'] === true) {
                    // Both parties confirmed - automatically release funds
                    // IMPORTANT: Use merchant_id (seller) from escrow details
                    $seller_id = isset($escrow_details['merchant_id']) ? $escrow_details['merchant_id'] : $user_id;
                    error_log('MNT Merchant Confirm - Both parties confirmed! Releasing funds to seller #' . $seller_id);
                    $release_result = \MNT\Api\Escrow::merchant_release_funds((string)$project_id, (string)$seller_id);
                    error_log('MNT Merchant Confirm - Release Funds Result: ' . print_r($release_result, true));
                    $both_confirmed = true;
                }
            }
            
            $message = $both_confirmed 
                ? 'Success! Both parties confirmed. Funds released to your wallet!' 
                : 'Success! Funds will be released when buyer confirms.';
            
            wp_send_json_success([
                'message' => $message,
                'result' => $result,
                'both_confirmed' => $both_confirmed,
                'release_result' => $release_result,
                'debug' => [
                    'project_id' => $project_id,
                    'user_id' => $user_id,
                    'confirm_status' => true,
                    'api_response' => $result,
                    'escrow_details' => $escrow_details,
                    'both_confirmed' => $both_confirmed,
                    'release_result' => $release_result
                ]
            ]);
        } else {
            $msg = $result['message'] ?? ($result['error'] ?? 'Failed to confirm project completion.');
            error_log('MNT Merchant Confirm - Error: ' . $msg);
            wp_send_json_error([
                'message' => $msg, 
                'result' => $result,
                'debug' => [
                    'project_id' => $project_id,
                    'user_id' => $user_id,
                    'confirm_status' => true,
                    'api_response' => $result,
                    'error_details' => $result
                ]
            ]);
        }
    }

    /**
     * AJAX Handler: Fund Escrow - Move funds from client wallet to escrow account
     * This is for the task escrow page when user clicks "Release Funds to Escrow"
     * Flow: Client Wallet → Escrow Account (pending → funded)
     */
    public static function handle_fund_escrow_ajax() {
        check_ajax_referer('mnt_nonce', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? sanitize_text_field($_POST['project_id']) : '';
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $seller_id_from_ajax = isset($_POST['seller_id']) ? intval($_POST['seller_id']) : 0;
        
        error_log('');
        error_log('╔════════════════════════════════════════════════════════════════╗');
        error_log('║  MNT FUND ESCROW AJAX HANDLER CALLED                          ║');
        error_log('╚════════════════════════════════════════════════════════════════╝');
        error_log('');
        error_log('📥 RECEIVED DATA:');
        error_log('  project_id: ' . $project_id);
        error_log('  task_id: ' . $task_id);
        error_log('  order_id: ' . $order_id);
        error_log('  user_id (client): ' . $user_id);
        error_log('  seller_id: ' . $seller_id_from_ajax);
        error_log('');
        
        if (empty($project_id) || empty($user_id)) {
            error_log('MNT Fund Escrow - ERROR: Missing project_id or user_id');
            wp_send_json_error(['message' => 'Project ID and User ID are required.']);
            return;
        }
        
        error_log('=== MNT Fund Escrow - Determining Seller ID ===');
        error_log('seller_id_from_ajax: ' . ($seller_id_from_ajax ?: 'EMPTY'));
        error_log('seller_id_from_ajax (int value): ' . intval($seller_id_from_ajax));
        error_log('seller_id_from_ajax is_numeric: ' . (is_numeric($seller_id_from_ajax) ? 'YES' : 'NO'));
        
        // Use seller_id from AJAX if provided (it should always be provided now)
        $seller_id = intval($seller_id_from_ajax);
        error_log('After intval conversion - seller_id: ' . $seller_id);
        
        // Fallback: Try to get seller ID from task_id if AJAX didn't provide it
        if ($seller_id <= 0 && $task_id > 0) {
            error_log('seller_id from AJAX is empty or zero, trying fallbacks with task_id: ' . $task_id);
            
            // First try meta
            $seller_id = get_post_meta($task_id, 'mnt_escrow_seller', true);
            error_log('  [1] From task meta (mnt_escrow_seller): ' . ($seller_id ?: 'EMPTY'));
            
            // Then try task author
            if (!$seller_id) {
                $task = get_post($task_id);
                if ($task) {
                    $seller_id = $task->post_author;
                    error_log('  [2] From task author (post_author): ' . ($seller_id ?: 'EMPTY'));
                } else {
                    error_log('  [2] Task post not found');
                }
            }
        }
        
        // Final check
        if (!$seller_id || intval($seller_id) <= 0) {
            error_log('❌ CRITICAL: seller_id is still empty or invalid!');
            error_log('Final seller_id value: ' . ($seller_id ?: 'EMPTY/NULL') . ' (type: ' . gettype($seller_id) . ')');
            error_log('Final seller_id intval: ' . intval($seller_id));
            error_log('seller_id_from_ajax value: ' . $seller_id_from_ajax . ' (type: ' . gettype($seller_id_from_ajax) . ')');
            error_log('task_id value: ' . $task_id);
            error_log('project_id value: ' . $project_id);
            
            wp_send_json_error(['message' => '<strong>Missing Seller ID</strong><br><br>Cannot fund escrow without seller information.<br>Project ID: ' . $project_id]);
            return;
        }
        
        error_log('✅ Seller ID successfully determined: ' . $seller_id);
        if (empty($seller_id)) {
            error_log('MNT Fund Escrow - seller_id not in post meta, checking proposal...');
            
            // Try to get from proposal_id meta
            $proposal_id = get_post_meta($project_id, 'proposal_id', true);
            if ($proposal_id) {
                error_log('MNT Fund Escrow - Found proposal_id: ' . $proposal_id);
                
                // Get seller from proposal author
                $proposal = get_post($proposal_id);
                if ($proposal) {
                    $seller_id = $proposal->post_author;
                    error_log('MNT Fund Escrow - Got seller_id from proposal author: ' . $seller_id);
                    
                    // Save it for future use
                    update_post_meta($project_id, 'mnt_escrow_seller', $seller_id);
                }
            }
            
            // Still empty? Try getting from project meta '_seller_id'
            if (empty($seller_id)) {
                $seller_id = get_post_meta($project_id, '_seller_id', true);
                if ($seller_id) {
                    error_log('MNT Fund Escrow - Got seller_id from _seller_id meta: ' . $seller_id);
                    update_post_meta($project_id, 'mnt_escrow_seller', $seller_id);
                }
            }
        }
        
        // Final check - if still no seller_id, return error
        if (empty($seller_id)) {
            error_log('MNT Fund Escrow - ERROR: Cannot find seller_id for project ' . $project_id);
            wp_send_json_error([
                'message' => '<strong>Missing Seller ID</strong><br><br>Cannot fund escrow - seller information not found for this project.<br><br><strong>Project ID:</strong> ' . $project_id
            ]);
            return;
        }
        
        error_log('MNT Fund Escrow - Final seller_id: ' . $seller_id);
        
        // First check if escrow transaction exists
        $escrow_check = \MNT\Api\Escrow::get_escrow_by_id($project_id, $seller_id);
        error_log('MNT Fund Escrow - Escrow Check: ' . json_encode($escrow_check));
        
        // Check if API returned an error or empty result
        if (!$escrow_check) {
            $error_msg = '<strong>No Escrow Transaction Found</strong><br><br>';
            $error_msg .= 'API returned empty response.<br><br>';
            $error_msg .= '<strong>Project ID:</strong> ' . $project_id . '<br>';
            $error_msg .= '<strong>User ID:</strong> ' . $user_id . '<br>';
            
            error_log('MNT Fund Escrow - ERROR: Empty response from API for project ' . $project_id);
            
            wp_send_json_error(['message' => $error_msg]);
            return;
        }
        
        // Check if it's an error response (has 'detail' field indicating error)
        if (isset($escrow_check['detail']) && empty($escrow_check['project_id'])) {
            $error_msg = '<strong>No Escrow Transaction Found</strong><br><br>';
            $error_msg .= '<strong>API Response:</strong> ' . esc_html($escrow_check['detail']) . '<br><br>';
            $error_msg .= '<strong>Project ID:</strong> ' . $project_id . '<br>';
            $error_msg .= '<strong>User ID:</strong> ' . $user_id . '<br>';
            
            error_log('MNT Fund Escrow - ERROR: API error - ' . $escrow_check['detail']);
            
            wp_send_json_error(['message' => $error_msg]);
            return;
        }
        
        // If API returns array of escrows, get the first one
        $escrow_data = $escrow_check;
        if (isset($escrow_check[0]) && is_array($escrow_check[0])) {
            $escrow_data = $escrow_check[0];
            error_log('MNT Fund Escrow - Using first escrow from array');
        }
        
        // Check if escrow is already funded
        $escrow_status = isset($escrow_data['status']) ? strtoupper($escrow_data['status']) : '';
        error_log('MNT Fund Escrow - Escrow Status: ' . $escrow_status);
        
        if ($escrow_status === 'FUNDED') {
            $msg = '<strong>Escrow Already Funded</strong><br><br>';
            $msg .= 'This escrow has already been funded.<br><br>';
            $msg .= '<strong>Status:</strong> FUNDED<br>';
            $msg .= '<strong>Amount:</strong> ₦' . number_format($escrow_data['amount'] ?? 0, 2) . '<br>';
            $msg .= '<strong>Created:</strong> ' . ($escrow_data['created_at'] ?? 'N/A') . '<br><br>';
            $msg .= 'The funds are already in the escrow account. No further action needed.';
            
            wp_send_json_success([
                'message' => $msg,
                'already_funded' => true,
                'escrow_data' => $escrow_data
                ,'redirect_url' => (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('dashboard') : home_url('/'))
            ]);
            return;
        } elseif ($escrow_status === 'FINALIZED') {
            wp_send_json_error([
                'message' => '<strong>Escrow Already Completed</strong><br><br>This escrow has already been completed and funds released to seller. Status: FINALIZED'
            ]);
            return;
        }
        
        // NOTE: seller_id was already determined in the section above
        // Do NOT re-query from meta as it will override the correctly determined value
        // Only proceed if seller_id is valid
        
        error_log('=== MNT Fund Escrow - Preparing API Call ===');
        error_log('project_id: ' . $project_id . ' (type: ' . gettype($project_id) . ')');
        error_log('client_id (user_id): ' . $user_id . ' (type: ' . gettype($user_id) . ')');
        error_log('merchant_id (seller_id): ' . $seller_id . ' (type: ' . gettype($seller_id) . ')');
        error_log('seller_id is_numeric: ' . (is_numeric($seller_id) ? 'YES' : 'NO'));
        
        if (!$seller_id || intval($seller_id) <= 0) {
            error_log('MNT Fund Escrow - SECOND CHECK FAILED: seller_id is empty or invalid!');
            error_log('seller_id value: ' . ($seller_id ?: 'EMPTY') . ' (intval: ' . intval($seller_id) . ')');
            wp_send_json_error(['message' => '<strong>Missing Seller ID</strong><br><br>Cannot fund escrow without seller information.<br>Project ID: ' . $project_id]);
            return;
        }
        
        // Call the client_release_funds API endpoint
        // This moves money: Client Wallet → Escrow Account (pending → funded)
        error_log('');
        error_log('🌐 CALLING API ENDPOINT:');
        error_log('  Endpoint: POST /escrow/client_release_funds (base: https://escrow-api-dfl6.onrender.com/api)');
        error_log('  Full URL: https://escrow-api-dfl6.onrender.com/api/escrow/client_release_funds');
        error_log('');
        error_log('📦 API PAYLOAD:');
        error_log('  {');
        error_log('    "project_id": "' . $project_id . '",');
        error_log('    "client_id": "' . $user_id . '",');
        error_log('    "merchant_id": "' . $seller_id . '"');
        error_log('  }');
        error_log('');
        error_log('Calling Escrow::client_release_funds()...');
        
        $result = \MNT\Api\Escrow::client_release_funds($project_id, $user_id, $seller_id);
        
        error_log('');
        error_log('📨 API RESPONSE:');
        error_log(json_encode($result, JSON_PRETTY_PRINT));
        
 if ($result && !isset($result['error']) && !isset($result['detail'])) {
    try {
        error_log('MNT Fund Escrow - SUCCESS: Funds released, beginning post-processing');

        // Get the WooCommerce order
        $wc_order_id = $order_id ?: get_post_meta($task_id, 'mnt_last_order_id', true);

        update_post_meta($task_id, 'mnt_escrow_status', 'funded');
        update_post_meta($task_id, 'mnt_escrow_funded_at', current_time('mysql'));
        update_post_meta($task_id, 'mnt_wc_order_id', $wc_order_id);

        if ($wc_order_id) {
            // Set required Taskbot order meta
            update_post_meta($wc_order_id, 'payment_type', 'tasks');
            update_post_meta($wc_order_id, '_task_id', $task_id);
            update_post_meta($wc_order_id, '_mnt_task_id', $task_id); // Always save as _mnt_task_id for robust lookup
            update_post_meta($wc_order_id, 'buyer_id', $user_id);
            update_post_meta($wc_order_id, 'seller_id', $seller_id);


        // After escrow is funded, update Taskbot task status
            if (isset($result['status']) && $result['status'] === 'FUNDED' && isset($result['project_id'])) {
                global $wpdb;
                // Find the order (shop_order post type) that has this escrow project ID
                $found_order_id = $wpdb->get_var( $wpdb->prepare( 
                    "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s LIMIT 1", 
                    'mnt_escrow_project_id', $result['project_id'], 'shop_order' 
                ) );
                // Try to find task ID via multiple fallbacks
                $task_id = $found_order_id ? get_post_meta($found_order_id, '_mnt_task_id', true) : null;
                if (empty($task_id) && $found_order_id) {
                    $task_id = get_post_meta($found_order_id, '_task_id', true);
                }
                if (empty($task_id) && $found_order_id) {
                    $task_id = get_post_meta($found_order_id, 'task_product_id', true);
                }
                if (empty($task_id) && $found_order_id) {
                    // Try to read the invoice meta which may contain project_id
                    $invoice_meta = get_post_meta($found_order_id, 'cus_woo_product_data', true);
                    if (!empty($invoice_meta) && is_array($invoice_meta) && isset($invoice_meta['project_id'])) {
                        $task_id = $invoice_meta['project_id'];
                    }
                }

                // Additional fallbacks: attempt to find task via task meta stored elsewhere
                if (empty($task_id) && $found_order_id) {
                    // 1) Try mnt_last_order_id stored on task post meta
                    $task_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", 'mnt_last_order_id', $found_order_id ) );
                    if ($task_id) {
                        error_log("MNT: Found task via mnt_last_order_id lookup: " . $task_id . " for order " . $found_order_id);
                    }
                }

                if (empty($task_id) && isset($result['project_id'])) {
                    // 2) Try mnt_escrow_project_id stored on task post meta (matches API project id like 'order-7190')
                    $task_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1", 'mnt_escrow_project_id', $result['project_id'] ) );
                    if ($task_id) {
                        error_log("MNT: Found task via mnt_escrow_project_id lookup: " . $task_id . " for project " . $result['project_id']);
                    }
                }
                if (empty($task_id) && $found_order_id && class_exists('WC_Order')) {
                    $wc_order_for_items = wc_get_order($found_order_id);
                    if ($wc_order_for_items) {
                        foreach ($wc_order_for_items->get_items() as $item) {
                            if (method_exists($item, 'get_product_id')) {
                                $possible = $item->get_product_id();
                            } elseif (isset($item['product_id'])) {
                                $possible = $item['product_id'];
                            } else {
                                $possible = null;
                            }
                            if (!empty($possible)) {
                                $task_id = $possible;
                                break;
                            }
                        }
                    }
                }
                if ($task_id) {
                    // backfill meta for future lookups
                    $backfill_target = !empty($found_order_id) ? $found_order_id : $wc_order_id;
                    if (!empty($backfill_target) && empty(get_post_meta($backfill_target, '_mnt_task_id', true))) {
                        update_post_meta($backfill_target, '_mnt_task_id', $task_id);
                        error_log("MNT: Backfilled _mnt_task_id for order $backfill_target with task $task_id");
                    }
                    // Skip if the task is already hired
                    $existing_status = get_post_meta($task_id, '_task_status', true);
                    if ($existing_status !== 'hired') {
                        self::mnt_update_task_status_on_escrow_funded($task_id);
                    } else {
                        error_log("MNT: Task $task_id already hired; skipping update.");
                    }
                } else {
                    // Log helpful order meta for debugging missing task_id
                    $order_meta_debug_keys = ['_mnt_task_id', '_task_id', 'task_product_id', 'payment_type', 'cus_woo_product_data', 'mnt_escrow_project_id'];
                    $meta_debug = [];
                    foreach ($order_meta_debug_keys as $k) {
                        $meta_debug[$k] = get_post_meta($found_order_id ?: $wc_order_id, $k, true);
                    }
                    error_log('MNT: No Task ID linked to order ' . ($found_order_id ?: $wc_order_id) . ' — meta snapshot: ' . json_encode($meta_debug));
                }
            }
            $order = wc_get_order($wc_order_id);
            if ($order) {
                // Force WooCommerce to perform its internal updates and sync postmeta relationships
                try {
                    $order->update_status('processing', 'Escrow funded - intermediate update by MNT', true);
                } catch (\Exception $e) {
                    error_log('MNT: Failed to update order status via update_status(): ' . $e->getMessage());
                    // Fallback to set_status/save
                    $order->set_status('processing');
                    $order->save();
                }
                // Refresh caches
                clean_post_cache($order->get_id());
                wp_cache_delete($order->get_id(), 'post_meta');
            }
        }

        // Determine which order to complete (prefer found order if lookup succeeded)
        $complete_order_id = !empty($found_order_id) ? $found_order_id : $wc_order_id;
        if ($complete_order_id) {
            // First, force WooCommerce to update order status so internal joins are rebuilt
            $complete_order = wc_get_order($complete_order_id);
            if ($complete_order) {
                try {
                    $complete_order->update_status('processing', 'Escrow funded - finalized by MNT', true);
                } catch (\Exception $e) {
                    error_log('MNT: Failed to update complete order status via update_status(): ' . $e->getMessage());
                    $complete_order->set_status('processing');
                    $complete_order->save();
                }
                // Refresh caches so subsequent WP_Query sees the fresh status/meta
                clean_post_cache($complete_order_id);
                wp_cache_delete($complete_order_id, 'post_meta');
            }

            // Ensure required order meta is set on the actual order Taskbot will process
            // (Do this AFTER the status update so WP/WC meta joins include this order)
            update_post_meta($complete_order_id, 'payment_type', 'tasks');
            update_post_meta($complete_order_id, '_task_id', $task_id);
            update_post_meta($complete_order_id, '_mnt_task_id', $task_id);
            // Ensure Task product reference exists for dashboard listings
            if (empty(get_post_meta($complete_order_id, 'task_product_id', true))) {
                update_post_meta($complete_order_id, 'task_product_id', $task_id);
            }
            if (empty(get_post_meta($complete_order_id, '_product_id', true))) {
                update_post_meta($complete_order_id, '_product_id', $task_id);
            }
            if (empty(get_post_meta($complete_order_id, '_product_title', true))) {
                $task_title = get_the_title($task_id);
                update_post_meta($complete_order_id, '_product_title', $task_title);
            }
            update_post_meta($complete_order_id, 'buyer_id', $user_id);
            update_post_meta($complete_order_id, 'seller_id', $seller_id);
            update_post_meta($complete_order_id, '_buyer_id', $user_id);
            update_post_meta($complete_order_id, '_seller_id', $seller_id);
            // Use centralized status updater to keep meta + optional WC mapping consistent
            self::mnt_update_task_status($complete_order_id, 'hired', true);
            update_post_meta($complete_order_id, '_taskbot_order_status', 'hired');

            // Log order meta snapshot for visibility
            $meta_keys = ['payment_type','seller_id','_seller_id','buyer_id','_buyer_id','_task_id','_mnt_task_id','_task_status','_taskbot_order_status','_linked_profile'];
            $meta_snapshot = [];
            foreach ($meta_keys as $mk) {
                $meta_snapshot[$mk] = get_post_meta($complete_order_id, $mk, true);
            }
            error_log('MNT: Order meta snapshot before Taskbot hook for order ' . $complete_order_id . ': ' . json_encode($meta_snapshot));

            do_action('taskbot_complete_order', $complete_order_id);

            // Diagnostic: run the same WP_Query used by dashboard to verify order visibility
            $diagnostic_args = [
                'posts_per_page'    => -1,
                'post_type'         => 'shop_order',
                'post_status'       => ['wc-completed','wc-pending','wc-on-hold','wc-cancelled','wc-refunded','wc-processing'],
                'fields'            => 'ids',
                'meta_query'        => [
                    'relation' => 'AND',
                    [ 'key' => 'payment_type', 'value' => 'tasks', 'compare' => '=' ],
                    [ 'key' => 'seller_id', 'value' => $seller_id, 'compare' => '=' ],
                ],
            ];
            // Ensure caches are refreshed and filters run like the dashboard page
            clean_post_cache($complete_order_id);
            wp_cache_delete($complete_order_id, 'post_meta');
            $diagnostic_args['suppress_filters'] = false;
            $diagnostic_query = new \WP_Query($diagnostic_args);
            $diag_ids = $diagnostic_query->posts;
            error_log('MNT Diagnostic: Dashboard query found orders: ' . json_encode($diag_ids));
            // Log the actual SQL WP constructed for the diagnostic query (helps debug meta_query aliasing)
            if (property_exists($diagnostic_query, 'request')) {
                error_log('MNT Diagnostic SQL: ' . $diagnostic_query->request);
            }
            error_log('MNT Diagnostic: Is our order present? ' . (in_array($complete_order_id, $diag_ids) ? 'YES' : 'NO'));

            // Also log the actual post_status of the order for sanity
            if ($complete_order_id) {
                $ps = get_post_status($complete_order_id);
                error_log('MNT Diagnostic: Order ' . $complete_order_id . ' post_status=' . $ps);
            }

            // Direct DB check (bypass WP filters) to ensure meta exists on orders
            $found_via_db = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT pm1.post_id FROM {$wpdb->postmeta} pm1 JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id WHERE pm1.meta_key = %s AND pm1.meta_value = %s AND pm2.meta_key = %s AND pm2.meta_value = %s",
                'payment_type', 'tasks', 'seller_id', $seller_id
            ) );
            error_log('MNT Diagnostic DB check (payment_type=tasks & seller_id=' . $seller_id . '): ' . json_encode($found_via_db));

            // Additional DB check that also enforces post_status and post_type to mirror the WP_Query conditions
            $post_statuses = "'wc-completed','wc-pending','wc-on-hold','wc-cancelled','wc-refunded','wc-processing'";
            $sql = "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id WHERE pm1.meta_key = %s AND pm1.meta_value = %s AND pm2.meta_key = %s AND pm2.meta_value = %s AND p.post_type = 'shop_order' AND p.post_status IN ($post_statuses)";
            $prepared_sql = $wpdb->prepare( $sql, 'payment_type', 'tasks', 'seller_id', $seller_id );
            error_log('MNT Diagnostic Prepared SQL: ' . $prepared_sql);
            $found_with_status = $wpdb->get_col( $prepared_sql );
            error_log('MNT Diagnostic DB+status check (payment_type=tasks & seller_id=' . $seller_id . ' & post_status IN ' . $post_statuses . '): ' . json_encode($found_with_status));
            // Log full rows if nothing found to understand mismatch
            if (empty($found_with_status)) {
                $rows = $wpdb->get_results( $prepared_sql );
                error_log('MNT Diagnostic DB+status - rows: ' . json_encode($rows));
            }

            // Log post_status for each candidate found without status filter to inspect mismatches
            if (!empty($found_via_db)) {
                $status_map = [];
                foreach ($found_via_db as $pid) {
                    $row = $wpdb->get_row($wpdb->prepare("SELECT ID, post_status FROM {$wpdb->posts} WHERE ID = %d", $pid), ARRAY_A);
                    $status_map[$pid] = $row ? $row['post_status'] : null;
                }
                error_log('MNT Diagnostic: Post status map for candidates: ' . json_encode($status_map));
            }

            // Log the specific order post row for the complete order id
            if (!empty($complete_order_id)) {
                $post_row = $wpdb->get_row($wpdb->prepare("SELECT ID, post_status FROM {$wpdb->posts} WHERE ID = %d", $complete_order_id), ARRAY_A);
                error_log('MNT Diagnostic: Post row for complete_order_id ' . $complete_order_id . ': ' . json_encode($post_row));
            }
        } else {
            error_log('MNT: No order available to fire taskbot_complete_order');
        }

        wp_cache_delete($task_id, 'post_meta');
        wp_cache_delete($wc_order_id, 'post_meta');
        clean_post_cache($task_id);

        // Optional verification (safe)
        $hook_result = [
            'status'    => 'success',
            'order_id'  => $wc_order_id,
            'task_id'   => $task_id,
            'task_status' => get_post_meta($task_id, '_task_status', true),
            'order_status' => $wc_order_id ? wc_get_order($wc_order_id)->get_status() : null,
        ];

    } catch (Exception $e) {
        error_log('MNT Fund Escrow - TRY/CATCH ERROR: ' . $e->getMessage());

        $hook_result = [
            'status'  => 'error',
            'message' => $e->getMessage(),
        ];
    }

    // Provide a redirect URL back to the buyer dashboard to avoid reload-triggered duplicates
            $redirect_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('dashboard') : home_url('/');
            error_log('MNT Fund Escrow - redirect_url: ' . $redirect_url);

            wp_send_json_success([
                'message'    => '<strong>✅ Escrow Funded & Task Purchased!</strong>',
                'order_id'   => $wc_order_id,
                'task_hired' => true,
                'hook_result'=> $hook_result,
                'redirect_url' => $redirect_url,
            ]);
}

}

    /**
     * AJAX Handler: Complete Escrow Funds (client completes contract for non-milestone projects)
     * This releases funds from escrow to the seller's wallet
     */
    public static function handle_complete_escrow_funds_ajax() {
        check_ajax_referer('mnt_nonce', 'nonce');
        
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $proposal_id = isset($_POST['proposal_id']) ? intval($_POST['proposal_id']) : 0;
        
        error_log('MNT Complete Contract - Received: project_id=' . $project_id . ', user_id=' . $user_id . ', proposal_id=' . $proposal_id);
        
        if (!$project_id || !$user_id) {
            wp_send_json_error(['message' => 'Missing project or user ID.']);
            return;
        }
        
        // Get seller ID from proposal author (same as fund_escrow does)
        $seller_id = 0;
        
        if ($proposal_id) {
            $proposal = get_post($proposal_id);
            if ($proposal) {
                $seller_id = $proposal->post_author;
                error_log('MNT Complete Contract - Got seller_id from proposal author: ' . $seller_id);
            }
        }
        
        // Fallback to post meta if proposal_id not provided
        if (!$seller_id) {
            $seller_id = get_post_meta($project_id, 'mnt_escrow_seller', true);
            error_log('MNT Complete Contract - Got seller_id from post meta: ' . $seller_id);
        }
        
        if (!$seller_id) {
            wp_send_json_error(['message' => '<strong>Missing Seller ID</strong><br><br>Cannot complete escrow without seller information. Please provide proposal_id.']);
            return;
        }
        
        // First check if escrow transaction exists for this project
        $escrow_check = \MNT\Api\Escrow::get_escrow_by_id($project_id, $seller_id);
        error_log('MNT Complete Escrow - Escrow Check: ' . json_encode($escrow_check));
        
        if (!$escrow_check || isset($escrow_check['detail'])) {
            $error_msg = '<strong>No Escrow Transaction Found</strong><br><br>';
            $error_msg .= 'Could not find an escrow transaction for this project.<br><br>';
            $error_msg .= '<strong>Project ID:</strong> ' . $project_id . '<br>';
            $error_msg .= '<strong>User ID:</strong> ' . $user_id . '<br><br>';
            $error_msg .= '<strong>Possible reasons:</strong><br>';
            $error_msg .= '• This project was not hired through escrow<br>';
            $error_msg .= '• The escrow payment was not completed<br>';
            $error_msg .= '• This is a milestone project (use milestone approval instead)<br><br>';
            
            if (isset($escrow_check['detail'])) {
                $error_msg .= '<strong>API Response:</strong> ' . esc_html($escrow_check['detail']) . '<br>';
            }
            
            error_log('MNT Complete Escrow - ERROR: No escrow found for project ' . $project_id);
            
            wp_send_json_error(['message' => $error_msg]);
            return;
        }
        
        // Check if escrow is in FUNDED status
        $escrow_status = isset($escrow_check['status']) ? strtoupper($escrow_check['status']) : '';
        if ($escrow_status !== 'FUNDED') {
            $error_msg = '<strong>Invalid Escrow Status</strong><br><br>';
            $error_msg .= 'The escrow transaction is not in FUNDED status.<br><br>';
            $error_msg .= '<strong>Current Status:</strong> ' . esc_html($escrow_status) . '<br>';
            $error_msg .= '<strong>Project ID:</strong> ' . $project_id . '<br><br>';
            
            if ($escrow_status === 'FINALIZED') {
                $error_msg .= 'This escrow has already been completed and funds released.';
            } else if ($escrow_status === 'PENDING') {
                $error_msg .= 'This escrow is still pending. Please wait for it to be funded.';
            }
            
            error_log('MNT Complete Escrow - ERROR: Invalid status ' . $escrow_status . ' for project ' . $project_id);
            
            wp_send_json_error(['message' => $error_msg]);
            return;
        }
        
        error_log('=== MNT COMPLETE CONTRACT - API CALL DETAILS ===');
        error_log('Endpoint: POST https://escrow-api-dfl6.onrender.com/api/escrow/client_confirm');
        error_log('Method: client_confirm()');
        error_log('Payload that will be sent:');
        error_log('  project_id: ' . $project_id . ' (type: ' . gettype($project_id) . ')');
        error_log('  client_id: ' . $user_id . ' (type: ' . gettype($user_id) . ')');
        error_log('  merchant_id: ' . $seller_id . ' (type: ' . gettype($seller_id) . ')');
        error_log('  confirm_status: true');
        error_log('  milestone_key: null');
        error_log('Expected Result: Funds move from Escrow Account → Seller Wallet');
        error_log('Status Change: FUNDED → FINALIZED');
        error_log('================================================');
        
        // Call the client_confirm API to release funds from escrow to seller wallet
        // This moves: Escrow Account → Seller Wallet (funded → finalized)
        $result = \MNT\Api\Escrow::client_confirm($project_id, $user_id, $seller_id, true);
        
        error_log('=== MNT COMPLETE CONTRACT - API RESPONSE ===');
        error_log('Response: ' . json_encode($result));
        error_log('Has error: ' . (isset($result['error']) ? 'YES - ' . $result['error'] : 'NO'));
        error_log('Has detail: ' . (isset($result['detail']) ? 'YES - ' . $result['detail'] : 'NO'));
        error_log('Has message: ' . (isset($result['message']) ? 'YES - ' . $result['message'] : 'NO'));
        error_log('===========================================');
        
        if ($result && !isset($result['error']) && !isset($result['detail'])) {
            // Update project status to 'completed' when funds are successfully released to seller
            update_post_meta($project_id, '_post_project_status', 'completed');
            update_post_meta($project_id, 'mnt_escrow_status', 'finalized');
            update_post_meta($project_id, 'mnt_escrow_completed_at', current_time('mysql'));
            
            // Also update the post status if it's a proposal
            $proposal_id = get_post_meta($project_id, 'mnt_proposal_id', true);
            if ($proposal_id) {
                wp_update_post(['ID' => $proposal_id, 'post_status' => 'completed']);
            }
            
            $redirect_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('dashboard') : home_url('/');
            error_log('MNT Complete Escrow - redirect_url: ' . $redirect_url);
            wp_send_json_success([
                'message' => $result['message'] ?? 'Contract completed! Funds released to seller wallet.',
                'result' => $result,
                'redirect_url' => $redirect_url,
            ]);
        } else {
            $error_msg = isset($result['detail']) ? $result['detail'] : (isset($result['error']) ? $result['error'] : (isset($result['message']) ? $result['message'] : 'Failed to complete contract.'));
            
            error_log('MNT Complete Escrow - ERROR: ' . $error_msg);
            error_log('MNT Complete Escrow - Full API Response: ' . print_r($result, true));
            
            // Build detailed error message
            $detailed_error = '<strong>Failed to complete contract:</strong><br><br>';
            $detailed_error .= '<strong>API Error:</strong> ' . esc_html($error_msg) . '<br><br>';
            
            // Add full API response for debugging
            if (!empty($result)) {
                $detailed_error .= '<strong>Full API Response:</strong><br>';
                $detailed_error .= '<pre style="background: #1f2937; color: #f3f4f6; padding: 10px; border-radius: 4px; overflow: auto; max-height: 300px; font-size: 11px;">';
                $detailed_error .= esc_html(print_r($result, true));
                $detailed_error .= '</pre>';
            }
            
            wp_send_json_error([
                'message' => $detailed_error,
                'result' => $result
            ]);
        }
    }

    /**
     * AJAX Handler: Client Confirm - Release funds from escrow to seller wallet
     * Endpoint: calls Escrow::client_confirm(project_id, buyer_id, seller_id)
     */
    public static function handle_client_confirm_ajax() {
        check_ajax_referer('mnt_nonce', 'nonce');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;

        error_log('');
        error_log('╔════════════════════════════════════════════════════════════════╗');
        error_log('║  MNT CLIENT CONFIRM AJAX HANDLER CALLED                         ║');
        error_log('╚════════════════════════════════════════════════════════════════╝');
        error_log('');
        error_log('📥 RECEIVED DATA:');
        error_log('  order_id: ' . $order_id);
        error_log('  task_id: ' . $task_id);
        error_log('  current_user: ' . get_current_user_id());
        error_log('');

        if (empty($order_id) && empty($task_id)) {
            error_log('MNT Client Confirm - ERROR: Missing order_id and task_id');
            wp_send_json_error(['message' => 'Missing order or task identifier.']);
            return;
        }

        // Build project_id in task context: use order-<id>
        $project_id = 'order-' . $order_id;
        if (empty($order_id) && $task_id) {
            // If only task_id provided, attempt to map to order via meta
            $order_meta_candidates = get_posts([
                'post_type'  => 'shop_order',
                'post_status'=> 'any',
                'meta_query' => [
                    ['key' => 'task_product_id', 'value' => $task_id],
                ],
                'fields' => 'ids',
                'posts_per_page' => 1,
            ]);
            if (!empty($order_meta_candidates)) {
                $order_id = intval($order_meta_candidates[0]);
                $project_id = 'order-' . $order_id;
                error_log('MNT Client Confirm - Found order_id via task_id: ' . $order_id);
            }
        }

        $buyer_id = get_current_user_id();

        // Verify buyer matches order buyer meta when order_id is provided
        if ($order_id) {
            $order_buyer_meta = get_post_meta($order_id, 'buyer_id', true) ?: get_post_meta($order_id, '_buyer_id', true);
            error_log('MNT Client Confirm - Order buyer meta: ' . ($order_buyer_meta ?: 'EMPTY'));
            if (!empty($order_buyer_meta) && intval($order_buyer_meta) !== intval($buyer_id)) {
                error_log('MNT Client Confirm - ERROR: Current user does not match order buyer.');
                wp_send_json_error(['message' => 'You are not authorized to confirm this order.']);
                return;
            }
        }

        // Find seller id from order meta (common keys)
        $seller_id = get_post_meta($order_id, 'seller_id', true) ?: get_post_meta($order_id, '_seller_id', true);
        if (empty($seller_id) && $task_id) {
            $seller_id = get_post_meta($task_id, 'mnt_escrow_seller', true) ?: get_post_meta($task_id, '_seller_id', true);
        }

        error_log('MNT Client Confirm - Resolved IDs:');
        error_log('  project_id: ' . $project_id);
        error_log('  order_id: ' . $order_id);
        error_log('  task_id: ' . $task_id);
        error_log('  buyer_id: ' . $buyer_id);
        error_log('  seller_id: ' . ($seller_id ?: 'EMPTY'));

        if (empty($seller_id)) {
            error_log('MNT Client Confirm - ERROR: seller_id not found');
            wp_send_json_error(['message' => 'Seller ID could not be determined for this order.']);
            return;
        }

        // Call Escrow API client_confirm
        error_log('Endpoint: POST https://escrow-api-dfl6.onrender.com/api/escrow/client_confirm');
        error_log('Method: client_confirm()');

        // Log payload that will be sent to external API
        $payload = [
            'project_id' => $project_id,
            'client_id' => $buyer_id,
            'merchant_id' => $seller_id,
            'confirm_status' => true,
            'task_id' => $task_id,
            'order_id' => $order_id
        ];
        error_log('MNT Client Confirm - Payload to be sent: ' . json_encode($payload));

        $result = \MNT\Api\Escrow::client_confirm($project_id, $buyer_id, $seller_id, true);
        error_log('MNT Escrow: client_confirm API response: ' . json_encode($result));

        $is_success = false;
        if (is_array($result) && (isset($result['success']) && $result['success'] === true)) {
            $is_success = true;
        } elseif (is_array($result) && isset($result['status']) && $result['status'] === 'success') {
            $is_success = true;
        }

        if ($is_success) {
            // Mark order as client confirmed
            update_post_meta($order_id, 'mnt_client_confirmed', true);
            update_post_meta($order_id, 'mnt_client_confirmed_at', current_time('mysql'));

            error_log('MNT Escrow: client_confirm succeeded - funds released to seller');

            $redirect_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('dashboard') : home_url('/');
            wp_send_json_success([
                'message' => 'Client confirmation succeeded. Funds released to seller.',
                'result'  => $result,
                'project_id' => $project_id,
                'order_id' => $order_id,
                'seller_id' => $seller_id,
                'buyer_id' => $buyer_id,
                'redirect_url' => $redirect_url,
            ]);
            return;
        }

        $error_msg = $result['message'] ?? ($result['error'] ?? 'Client confirm failed.');
        error_log('MNT Escrow: client_confirm FAILED - ' . $error_msg);
        wp_send_json_error([
            'message' => $error_msg,
            'result'  => $result,
            'project_id' => $project_id,
            'order_id' => $order_id,
            'seller_id' => $seller_id,
            'buyer_id' => $buyer_id,
        ]);
    }

    /**
     * AJAX Handler: Deposit Funds
     */
    public static function handle_deposit_ajax() {
        check_ajax_referer('mnt_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'You must be logged in to deposit.']);
        }
        $user_id = get_current_user_id();
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        if ($amount < 100) {
            wp_send_json_error(['message' => 'Minimum deposit is ₦100.']);
        }
        // Call API to initialize deposit
        $result = \MNT\Api\wallet::deposit($user_id, $amount);
        if ((isset($result['checkout_url']) || isset($result['authorization_url'])) && empty($result['error'])) {
            wp_send_json_success([
                'checkout_url' => $result['checkout_url'] ?? $result['authorization_url'],
                'reference' => $result['reference'] ?? '',
            ]);
        } elseif (isset($result['error']) || isset($result['message'])) {
            wp_send_json_error(['message' => $result['error'] ?? $result['message']]);
        } else {
            wp_send_json_error(['message' => 'Failed to initialize deposit.']);
        }
    }

    /**
     * AJAX Handler: Withdraw Funds
     */
    public static function handle_withdraw_ajax() {
        check_ajax_referer('mnt_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'You must be logged in to withdraw.']);
        }
        
        $user_id = get_current_user_id();
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : '';
        
        if ($amount < 101) {
            wp_send_json_error(['message' => 'Minimum withdrawal is ₦101.']);
        }
        
        // Call API to process withdrawal
        $result = \MNT\Api\Wallet::withdraw($user_id, $amount, $reason);
        
        if (isset($result['status']) && strtoupper($result['status']) === 'SUCCESS') {
            wp_send_json_success([
                'message' => $result['message'] ?? 'Withdrawal processed successfully',
                'response' => $result
            ]);
        } elseif (isset($result['error']) || isset($result['message'])) {
            wp_send_json_error(['message' => $result['error'] ?? $result['message']]);
        } else {
            wp_send_json_error(['message' => 'Failed to process withdrawal.']);
        }
    }

    /**
     * AJAX Handler: Transfer Funds
     */
    public static function handle_transfer_ajax() {
        check_ajax_referer('mnt_nonce', 'nonce');
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'You must be logged in to transfer.']);
        }
        
        $user_id = get_current_user_id();
        $recipient_email = isset($_POST['recipient_email']) ? sanitize_email($_POST['recipient_email']) : '';
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $description = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '';
        
        if (empty($recipient_email)) {
            wp_send_json_error(['message' => 'Recipient email is required.']);
        }
        
        // Get recipient user by email
        $recipient = get_user_by('email', $recipient_email);
        if (!$recipient) {
            wp_send_json_error(['message' => 'Recipient not found. Please check the email address.']);
        }
        
        if ($amount < 100) {
            wp_send_json_error(['message' => 'Minimum transfer is ₦100.']);
        }
        
        // Call API to process transfer
        $result = \MNT\Api\Wallet::transfer($user_id, $recipient->ID, $amount, $description);
        
        if (isset($result['status']) && strtoupper($result['status']) === 'SUCCESS') {
            wp_send_json_success([
                'message' => $result['message'] ?? 'Transfer completed successfully',
                'response' => $result
            ]);
        } elseif (isset($result['error']) || isset($result['message'])) {
            wp_send_json_error(['message' => $result['error'] ?? $result['message']]);
        } else {
            wp_send_json_error(['message' => 'Failed to process transfer.']);
        }
    }

    /**
     * Wallet Dashboard Shortcode
     */
    public static function wallet_dashboard_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please login to view your wallet.</p>';
        }

        ob_start();
        include dirname(__FILE__) . '/templates/wallet-dashboard.php';
        return ob_get_clean();
    }

    /**
     * Wallet Balance Shortcode
     */
    public static function wallet_balance_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<span>₦0.00</span>';
        }

        $user_id = get_current_user_id();
        $result = \MNT\Api\Wallet::balance($user_id);
        $balance = $result['balance'] ?? 0;

        return '<span class="mnt-wallet-balance">₦' . number_format($balance, 2) . '</span>';
    }

    /**
     * Deposit Form Shortcode
     */
    public static function deposit_form_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>'.esc_html__('Please login to make a deposit.', 'taskbot').'</p>';
        }

        ob_start();
        ?>
        <div class="tk-themeform">
            <div class="tk-themeform__head">
                <h5><?php esc_html_e('Deposit Funds', 'taskbot'); ?></h5>
            </div>
            <form id="mnt-deposit-form" method="post" action="">
                <fieldset>
                    <div class="tk-themeform__wrap">
                        <div class="form-group">
                            <label class="tk-label"><?php esc_html_e('Amount (₦)', 'taskbot'); ?></label>
                            <div class="tk-placeholderholder">
                                <input type="number" id="deposit-amount" class="form-control tk-themeinput" name="amount" min="100" step="0.01" placeholder="<?php esc_attr_e('Enter amount', 'taskbot'); ?>" required>
                            </div>
                            <span class="tk-input-help"><?php esc_html_e('Minimum deposit: ₦100', 'taskbot'); ?></span>
                        </div>
                        <div class="form-group tk-btnarea">
                            <button type="submit" class="tk-btn-solid-lg">
                                <i class="tb-icon-plus-circle"></i> <?php esc_html_e('Deposit Now', 'taskbot'); ?>
                            </button>
                        </div>
                        <div class="mnt-message tk-alert" style="display:none; margin-top:15px;"></div>
                    </div>
                </fieldset>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Withdraw Form Shortcode
     */
    public static function withdraw_form_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>'.esc_html__('Please login to make a withdrawal.', 'taskbot').'</p>';
        }

        ob_start();
        ?>
        <div class="tk-themeform">
            <div class="tk-themeform__head">
                <h5><?php esc_html_e('Withdraw Funds', 'taskbot'); ?></h5>
            </div>
            <form id="mnt-withdraw-form">
                <fieldset>
                    <div class="tk-themeform__wrap">
                        <div class="form-group">
                            <label class="tk-label"><?php esc_html_e('Amount (₦)', 'taskbot'); ?></label>
                            <div class="tk-placeholderholder">
                                <input type="number" id="withdraw-amount" class="form-control tk-themeinput" name="amount" min="101" step="0.01" placeholder="<?php esc_attr_e('Enter amount', 'taskbot'); ?>" required>
                            </div>
                            <span class="tk-input-help"><?php esc_html_e('Minimum withdrawal: ₦101', 'taskbot'); ?></span>
                        </div>
                        <div class="form-group">
                            <label class="tk-label"><?php esc_html_e('Reason (Optional)', 'taskbot'); ?></label>
                            <div class="tk-placeholderholder">
                                <textarea id="withdraw-reason" class="form-control tk-themeinput" name="reason" rows="3" placeholder="<?php esc_attr_e('Withdrawal reason', 'taskbot'); ?>"></textarea>
                            </div>
                        </div>  <div class="form-group tk-btnarea">
                            <button type="submit" class="tk-btn-solid-lg">
                                <i class="tb-icon-download"></i> <?php esc_html_e('Withdraw Now', 'taskbot'); ?>
                            </button>
                        </div>
                        <div class="mnt-message tk-alert" style="display:none; margin-top:15px;"></div>
                    </div>
                </fieldset>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Transfer Form Shortcode
     */
    public static function transfer_form_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>'.esc_html__('Please login to make a transfer.', 'taskbot').'</p>';
        }

        ob_start();
        ?>
        <div class="tk-themeform">
            <div class="tk-themeform__head">
                <h5><?php esc_html_e('Transfer Funds', 'taskbot'); ?></h5>
            </div>
            <form id="mnt-transfer-form">
                <fieldset>
                    <div class="tk-themeform__wrap">
                        <div class="form-group">
                            <label class="tk-label"><?php esc_html_e('Recipient Email', 'taskbot'); ?></label>
                            <div class="tk-placeholderholder">
                                <input type="email" id="recipient-email" class="form-control tk-themeinput" name="recipient_email" placeholder="<?php esc_attr_e('Enter recipient email', 'taskbot'); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="tk-label"><?php esc_html_e('Amount (₦)', 'taskbot'); ?></label>
                            <div class="tk-placeholderholder">
                                <input type="number" id="transfer-amount" class="form-control tk-themeinput" name="amount" min="100" step="0.01" placeholder="<?php esc_attr_e('Enter amount', 'taskbot'); ?>" required>
                            </div>
                            <span class="tk-input-help"><?php esc_html_e('Minimum transfer: ₦100', 'taskbot'); ?></span>
                        </div>
                        <div class="form-group">
                            <label class="tk-label"><?php esc_html_e('Description (Optional)', 'taskbot'); ?></label>
                            <div class="tk-placeholderholder">
                                <textarea id="transfer-description" class="form-control tk-themeinput" name="description" rows="3" placeholder="<?php esc_attr_e('Transfer description', 'taskbot'); ?>"></textarea>
                            </div>
                        </div>
                        <div class="form-group tk-btnarea">
                            <button type="submit" class="tk-btn-solid-lg">
                                <i class="tb-icon-arrow-right-circle"></i> <?php esc_html_e('Transfer Now', 'taskbot'); ?>
                            </button>
                        </div>
                        <div class="mnt-message tk-alert" style="display:none; margin-top:15px;"></div>
                    </div>
                </fieldset>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Transactions Shortcode
     */
    public static function transactions_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please login to view transactions.</p>';
        }

        $atts = shortcode_atts([
            'limit' => 20
        ], $atts);

        $user_id = get_current_user_id();
        $result = \MNT\Api\Wallet::transactions($user_id, $atts['limit']);
        $transactions = $result['transactions'] ?? [];

        ob_start();
        ?>
        <div class="mnt-transactions">
            <h3>Transaction History</h3>
            <?php if (empty($transactions)): ?>
                <p>No transactions yet.</p>
            <?php else: ?>
                <table class="mnt-transactions-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td><?php echo esc_html($tx['date'] ?? ''); ?></td>
                                <td><?php echo esc_html(ucfirst($tx['type'] ?? '')); ?></td>
                                <td class="<?php echo ($tx['type'] === 'credit' ? 'credit' : 'debit'); ?>">
                                    ₦<?php echo number_format($tx['amount'] ?? 0, 2); ?>
                                </td>
                                <td><?php echo esc_html(ucfirst($tx['status'] ?? '')); ?></td>
                                <td><?php echo esc_html($tx['description'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Escrow Box Shortcode
     */
    public static function escrow_box_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please login to view escrow details.</p>';
        }

        $atts = shortcode_atts([
            'escrow_id' => '',
            'task_id' => get_the_ID()
        ], $atts);

        if (!$atts['escrow_id'] && $atts['task_id']) {
            $atts['escrow_id'] = get_post_meta($atts['task_id'], 'mnt_escrow_id', true);
        }

        if (!$atts['escrow_id']) {
            return '<p>No escrow found.</p>';
        }

        ob_start();
        $escrow_id = $atts['escrow_id'];
        $task_id = $atts['task_id'];
        include dirname(__FILE__) . '/templates/escrow-box.php';
        return ob_get_clean();
    }

    /**
     * Escrow List Shortcode
     */
    public static function escrow_list_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please login to view escrows.</p>';
        }

        $atts = shortcode_atts([
            'status' => null
        ], $atts);

        $user_id = get_current_user_id();
        $result = \MNT\Api\Escrow::list_by_user($user_id, $atts['status']);
        $escrows = $result['escrows'] ?? [];

        ob_start();
        ?>
        <div class="mnt-escrow-list">
            <h3>My Escrows</h3>
            <?php if (empty($escrows)): ?>
                <p>No escrows found.</p>
            <?php else: ?>
                <table class="mnt-escrow-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Task</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($escrows as $escrow): ?>
                            <tr>
                                <td><?php echo esc_html($escrow['id'] ?? ''); ?></td>
                                <td><?php echo esc_html($escrow['description'] ?? ''); ?></td>
                                <td>₦<?php echo number_format($escrow['amount'] ?? 0, 2); ?></td>
                                <td><span class="status-<?php echo esc_attr($escrow['status']); ?>">
                                    <?php echo esc_html(ucfirst($escrow['status'] ?? '')); ?>
                                </span></td>
                                <td><?php echo esc_html($escrow['role'] ?? ''); ?></td>
                                <td>
                                    <a href="#" class="view-escrow" data-escrow-id="<?php echo esc_attr($escrow['id']); ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Create Wallet Shortcode
     */
    public static function create_wallet_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please login to create a wallet.</p>';
        }

        ob_start();
        include dirname(__FILE__) . '/templates/create-wallet.php';
        return ob_get_clean();
    }

    /**
     * Transaction History Shortcode with Pagination and Filters
     */
    public static function transaction_history_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please login to view your transaction history.</p>';
        }

        ob_start();
        include dirname(__FILE__) . '/templates/transaction-history.php';
        return ob_get_clean();
    }

    /**
     * Escrow Deposit Page Shortcode
     */
    public static function escrow_deposit_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>Please login to create an escrow transaction.</p>';
        }

        ob_start();
        include dirname(__FILE__) . '/templates/escrow-deposit.php';
        return ob_get_clean();
    }

    /**
     * AJAX Handler: Create Escrow Transaction
     */
    public static function handle_create_escrow_ajax() {
        check_ajax_referer('mnt_create_escrow', 'nonce');
        
        $buyer_id = intval($_POST['buyer_id'] ?? 0);
        $seller_id = intval($_POST['seller_id'] ?? 0);
        $project_id = intval($_POST['project_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        
        if (!$buyer_id || !$seller_id || !$amount || !$project_id) {
            wp_send_json_error(['message' => 'Missing required parameters']);
        }
        
        // Verify project exists
        $project = get_post($project_id);
        if (!$project || $project->post_type !== 'product') {
            wp_send_json_error(['message' => 'Invalid project ID']);
        }
        
        // Check if buyer has sufficient funds
        $balance_result = \MNT\Api\Wallet::balance($buyer_id);
        $balance = isset($balance_result['balance']) ? floatval($balance_result['balance']) : 0;
        
        if ($balance < $amount) {
            wp_send_json_error(['message' => 'Insufficient funds in wallet. Please add funds first.']);
        }
        
        // IMPORTANT: Create the WooCommerce order first (wc_create_order -> set payment -> set total -> save)
        // then create the escrow transaction via API. This ensures WP/WC internal joins and meta
        // are properly initialized so dashboard queries include the order.
        
        // Get proposal ID if passed
        $proposal_id = isset($_POST['proposal_id']) ? intval($_POST['proposal_id']) : 0;
        
        // If we have a proposal, update it too
        if ($proposal_id) {
            wp_update_post(['ID' => $proposal_id, 'post_status' => 'hired']);
            update_post_meta($proposal_id, 'mnt_escrow_id', $escrow_id);
            update_post_meta($proposal_id, 'project_id', $project_id);
        }
        
        // Create WooCommerce order for tracking (must happen BEFORE calling Escrow::create())
        $order_url = '';
        $order = null;
        if (class_exists('WooCommerce')) {
            $order = wc_create_order(['customer_id' => $buyer_id]);
            if (!is_wp_error($order)) {
                // Add project as order item
                $product = wc_get_product($project_id);
                if ($product) {
                    $order->add_product($product, 1, ['subtotal' => $amount, 'total' => $amount]);
                }
                
                // Store escrow metadata
                $order->add_meta_data('mnt_escrow_id', $escrow_id);
                $order->add_meta_data('project_id', $project_id);
                $order->add_meta_data('task_product_id', $project_id);
                $order->add_meta_data('seller_id', $seller_id);
                $order->add_meta_data('buyer_id', $buyer_id);
                // Defer to centralized status updater after order is saved and escrow created
                $order->add_meta_data('payment_type', 'escrow');
                
                if ($proposal_id) {
                    $order->add_meta_data('proposal_id', $proposal_id);
                }
                
                // Add Taskbot-compatible invoice data for proper display
                $project_type = get_post_meta($project_id, 'project_type', true) ?: 'fixed';
                $milestone_id = isset($_POST['milestone_id']) ? intval($_POST['milestone_id']) : '';
                
                $invoice_data = [
                    'project_id' => $project_id,
                    'project_type' => $project_type,
                    'proposal_id' => $proposal_id,
                    'seller_shares' => $amount, // Seller receives this amount when released
                    'payment_method' => 'escrow',
                    'escrow_id' => $escrow_id
                ];
                
                if ($milestone_id) {
                    $invoice_data['milestone_id'] = $milestone_id;
                }
                
                $order->add_meta_data('cus_woo_product_data', $invoice_data);
                
                // Set payment method, total and save ORDER before creating the escrow
                $order->set_payment_method('mnt_escrow');
                $order->set_payment_method_title('Escrow Payment');
                $order->set_total($amount);
                $order->set_status('pending');
                $order->save();

                // Ensure task_id is stored for post-processing
                update_post_meta($order->get_id(), '_mnt_task_id', $project_id);

                // Now create escrow transaction via API (this will deduct funds from wallet)
                error_log('=== MNT Escrow Creation Request (post-order) ===');
                error_log('Seller: ' . $seller_id . ', Buyer: ' . $buyer_id . ', Amount: ' . $amount . ', Project: ' . $project_id . ', Order: ' . $order->get_id());
                $escrow_result = \MNT\Api\Escrow::create((string)$seller_id, (string)$buyer_id, $amount, (string)$project_id);
                error_log('=== MNT Escrow Creation Response ===');
                error_log('Response: ' . print_r($escrow_result, true));

                if (!$escrow_result || isset($escrow_result['error'])) {
                    $error_message = $escrow_result['error'] ?? 'Failed to create escrow transaction';
                    error_log('MNT Escrow Creation Failed: ' . print_r($escrow_result, true));
                    // Mark order meta so admin can diagnose
                    update_post_meta($order->get_id(), 'mnt_escrow_create_error', $error_message);
                    wp_send_json_error(['message' => $error_message]);
                }

                // Get escrow ID from response
                $escrow_id = $escrow_result['id'] ?? $escrow_result['escrow_id'] ?? '';
                $escrow_status = $escrow_result['status'] ?? 'pending';

                if (!$escrow_id) {
                    error_log('MNT Escrow Creation: No ID returned - ' . print_r($escrow_result, true));
                    update_post_meta($order->get_id(), 'mnt_escrow_create_error', 'no_id_returned');
                    wp_send_json_error(['message' => 'Escrow created but ID not returned']);
                }

                // Store escrow metadata in project and order
                update_post_meta($project_id, 'mnt_escrow_id', $escrow_id);
                update_post_meta($project_id, 'mnt_escrow_amount', $amount);
                update_post_meta($project_id, 'mnt_escrow_buyer', $buyer_id);
                update_post_meta($project_id, 'mnt_escrow_seller', $seller_id);
                update_post_meta($project_id, 'mnt_escrow_status', $escrow_status);
                update_post_meta($project_id, 'mnt_escrow_created_at', current_time('mysql'));
                // Update project status to hired
                update_post_meta($project_id, '_post_project_status', 'hired');

                // Store escrow metadata on the order
                $order->add_meta_data('mnt_escrow_id', $escrow_id);
                $order->add_meta_data('mnt_escrow_project_id', 'order-' . $order->get_id());
                $order->add_meta_data('payment_type', 'escrow');
                $order->save();

                // Centralized task status update for the created order
                self::mnt_update_task_status($order->get_id(), 'hired', true);

                // Build activity URL
                $order_url = Taskbot_Profile_Menu::taskbot_profile_menu_link('projects', $buyer_id, true, 'activity', $proposal_id ? $proposal_id : $order->get_id());
                if (!$order_url) {
                    $order_url = home_url('/dashboard/?ref=projects&mode=activity&id=' . ($proposal_id ? $proposal_id : $order->get_id()));
                }
            }
        }
        
        // Log success for debugging
        error_log('MNT Escrow Created Successfully: ' . $escrow_id . ' for project ' . $project_id);
        
        wp_send_json_success([
            'message' => 'Project hired successfully! Funds secured in escrow.',
            'escrow_id' => $escrow_id,
            'project_id' => $project_id,
            'redirect_url' => $order_url
        ]);
    }
    
    /**
     * AJAX handler to create WooCommerce order BEFORE escrow page loads
     * Called when user clicks "Proceed to secure checkout" on cart page
     */
    public static function handle_create_order_before_escrow_ajax() {
        error_log('=== MNT: CREATE ORDER BEFORE ESCROW AJAX ===');
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'mnt_create_order_nonce')) {
            error_log('❌ Nonce verification failed');
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $merchant_id = isset($_POST['merchant_id']) ? intval($_POST['merchant_id']) : 0;
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
        $package_key = isset($_POST['package_key']) ? sanitize_text_field($_POST['package_key']) : '';
        
        error_log('Task ID: ' . $task_id);
        error_log('Merchant ID: ' . $merchant_id);
        error_log('Client ID: ' . $client_id);
        error_log('Amount: ' . $amount);
        error_log('Package Key: ' . $package_key);
        
        // Validate inputs
        if (empty($task_id) || empty($merchant_id) || empty($client_id) || empty($amount)) {
            error_log('❌ Missing required parameters');
            wp_send_json_error(['message' => 'Missing required parameters']);
            return;
        }
        
        // Verify WooCommerce is active
        if (!class_exists('WooCommerce')) {
            error_log('❌ WooCommerce not found');
            wp_send_json_error(['message' => 'WooCommerce is not active']);
            return;
        }
        
        try {
            // Create WooCommerce order
            $wc_order = wc_create_order(['customer_id' => $client_id]);
            
            if (is_wp_error($wc_order)) {
                error_log('❌ Order creation failed: ' . $wc_order->get_error_message());
                wp_send_json_error(['message' => 'Failed to create order: ' . $wc_order->get_error_message()]);
                return;
            }
            
            // FIX #1: Get the Linked Profile ID (Required for Seller Dashboard)
            $linked_profile_id = get_user_meta($merchant_id, '_linked_profile', true);
            // Fallback: If no profile found (rare), use merchant_id, but log warning
            if (empty($linked_profile_id)) {
                error_log('⚠️  Warning: No Linked Profile found for User ' . $merchant_id);
            }
            
            // Get task details
            $task_post = get_post($task_id);
            $task_title = $task_post ? $task_post->post_title : 'Task #' . $task_id;
            
            // Add product item to order
            $item = new \WC_Order_Item_Product();
            $item->set_name($task_title);
            $item->set_quantity(1);
            $item->set_subtotal($amount);
            $item->set_total($amount);
            $item->set_product_id($task_id); // Links the item to the product internally
            $wc_order->add_item($item);
            
            // FIX #2: Add Underscores to Critical IDs (Taskbot expects '_seller_id', not 'seller_id')
            $wc_order->add_meta_data('_seller_id', $merchant_id);
            $wc_order->add_meta_data('_buyer_id', $client_id);
            $wc_order->add_meta_data('_linked_profile', $linked_profile_id); // Critical for Seller visibility
            
            // Helpful snapshots (for debugging)
            $wc_order->add_meta_data('_product_id', $task_id);
            $wc_order->add_meta_data('_product_title', $task_title);
            
            // Also keep non-underscore versions for compatibility
            $wc_order->add_meta_data('task_product_id', $task_id);
            $wc_order->add_meta_data('seller_id', $merchant_id);
            $wc_order->add_meta_data('buyer_id', $client_id);
            
            // Custom Escrow/Payment Fields
            $wc_order->add_meta_data('payment_type', 'tasks');
            $wc_order->add_meta_data('package_key', $package_key);
            
            // FIX #3: Taskbot-specific hidden fields (with underscores - critical for dashboard visibility)
            // Set to 'pending' now. Payment success webhook will update this to 'hired'.
            $wc_order->add_meta_data('_taskbot_order_status', 'pending');
            $wc_order->add_meta_data('_taskbot_order_type', 'task');
            // _task_status will be set via centralized helper after save to ensure consistent mappings
            
            // Set order total and status
            $wc_order->set_total($amount);
            $wc_order->set_status('pending');
            $wc_order->save();
            // Ensure WP post_status matches WooCommerce status so dashboard queries include the order
            wp_update_post(['ID' => $wc_order->get_id(), 'post_status' => 'wc-pending']);
            
            $wc_order_id = $wc_order->get_id();
            // Centralized task status updater (sets _task_status and _taskbot_order_status, and optionally WC status)
            self::mnt_update_task_status($wc_order_id, 'pending', true);
            $escrow_project_id = "order-{$wc_order_id}";
            
            // Add escrow project ID to order
            $wc_order->add_meta_data('mnt_escrow_project_id', $escrow_project_id);
            $wc_order->save();
            
            // Update task meta
            update_post_meta($task_id, 'mnt_last_order_id', $wc_order_id);
            update_post_meta($task_id, 'mnt_escrow_project_id', $escrow_project_id);
            // Always save task ID in order meta for robust post-processing
            update_post_meta($wc_order_id, '_mnt_task_id', $task_id);
            
            error_log('✅ Order Created Successfully: #' . $wc_order_id);
            error_log('Linked Profile ID: ' . ($linked_profile_id ?: 'NOT FOUND'));
            error_log('Escrow Project ID: ' . $escrow_project_id);
            
            wp_send_json_success([
                'message' => 'Order created successfully',
                'order_id' => $wc_order_id,
                'escrow_project_id' => $escrow_project_id
            ]);
            
        } catch (\Exception $e) {
            error_log('❌ Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }


    /**
 * Update Taskbot task status when escrow is funded
 *
 * @param string $project_id The Escrow Project ID (e.g., 'order-7181')
 */
    /**
     * Update Taskbot task status when escrow is funded
     *
     * @param string $project_id The Escrow Project ID (e.g., 'order-7181')
     */
    /**
     * Update Taskbot task status when escrow is funded
     *
     * @param int $task_id The Taskbot Task ID
     */
    public static function mnt_update_task_status_on_escrow_funded( $task_id ) {
        if ( ! $task_id ) {
            error_log("MNT: No Task ID provided to mnt_update_task_status_on_escrow_funded");
            return;
        }
        // Centralized status update for task post
        self::mnt_update_task_status( $task_id, 'hired', false );
        error_log("MNT: Task ID $task_id status updated to 'hired'");
    }

    /**
     * Admin AJAX: Backfill task orders that are stuck in 'draft'.
     * POST params:
     *  - run: (bool) if true, perform updates; otherwise dry-run
     *  - nonce: optional nonce for extra safety
     */
    public static function handle_mnt_backfill_task_orders_ajax() {
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Verify nonce
        if ( empty($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'mnt_admin_nonce') ) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $run = isset($_POST['run']) && ($_POST['run'] === '1' || $_POST['run'] === 1 || $_POST['run'] === true || $_POST['run'] === 'true');

        global $wpdb;
        // Find candidate orders: payment_type = 'tasks' and post_status = 'draft'
        $sql = "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = 'shop_order' AND p.post_status = 'draft'";
        $ids = $wpdb->get_col( $wpdb->prepare($sql, 'payment_type', 'tasks') );

        $report = [ 'count' => count($ids), 'updated' => [], 'skipped' => [] ];

        if ($run && !empty($ids)) {
            foreach ($ids as $id) {
                $order = wc_get_order($id);
                // Decide target status: if already hired, set processing; else pending
                $taskbot_status = get_post_meta($id, '_taskbot_order_status', true);
                $task_status = get_post_meta($id, '_task_status', true);
                $target = (!empty($taskbot_status) && $taskbot_status === 'hired') || (!empty($task_status) && $task_status === 'hired') ? 'processing' : 'pending';

                if ($order) {
                    try {
                        // Ensure task status meta is present and consistent
                        $existing_ts = get_post_meta($id, '_task_status', true);
                        if (empty($existing_ts)) {
                            error_log('MNT Backfill: setting default _task_status=inqueue for order ' . $id);
                            self::mnt_update_task_status($id, 'inqueue', false);
                        }

                        $order->update_status($target, 'Backfill by MNT: fixing draft -> wc-' . $target, true);
                        // Re-ensure Taskbot meta exists
                        update_post_meta($id, 'payment_type', 'tasks');
                        update_post_meta($id, '_mnt_task_id', get_post_meta($id, '_mnt_task_id', true));
                        clean_post_cache($id);
                        wp_cache_delete($id, 'post_meta');
                        $report['updated'][] = ['id' => $id, 'target' => $target];
                    } catch (Exception $e) {
                        $report['skipped'][] = ['id' => $id, 'error' => $e->getMessage()];
                    }
                } else {
                    // Fallback direct post_status update
                    try {
                        wp_update_post(['ID' => $id, 'post_status' => 'wc-' . $target]);
                        // Ensure a default _task_status exists when repairing
                        $existing_ts = get_post_meta($id, '_task_status', true);
                        if (empty($existing_ts)) {
                            error_log('MNT Backfill: setting default _task_status=inqueue for order ' . $id);
                            self::mnt_update_task_status($id, 'inqueue', false);
                        }
                        update_post_meta($id, 'payment_type', 'tasks');
                        clean_post_cache($id);
                        wp_cache_delete($id, 'post_meta');
                        $report['updated'][] = ['id' => $id, 'target' => $target];
                    } catch (Exception $e) {
                        $report['skipped'][] = ['id' => $id, 'error' => $e->getMessage()];
                    }
                }
            }
        }

        wp_send_json_success(['run' => $run, 'report' => $report]);
    }

    /**
     * Admin AJAX: Repair a single order by forcing WooCommerce status update and refreshing caches
     * POST params:
     * - order_id (int)
     * - nonce
     */
    public static function handle_mnt_admin_repair_order_ajax() {
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        if ( empty($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'mnt_admin_nonce') ) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error(['message' => 'Missing order ID']);
        }

        $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found']);
        }

        $previous = get_post_status($order_id);
        try {
            $order->update_status('processing', 'Admin repair: forced processing to repair dashboard visibility', true);
            clean_post_cache($order_id);
            wp_cache_delete($order_id, 'post_meta');
            // Ensure critical meta present
            update_post_meta($order_id, 'payment_type', get_post_meta($order_id, 'payment_type', true) ?: 'tasks');
            $seller = get_post_meta($order_id, 'seller_id', true) ?: get_post_meta($order_id, '_seller_id', true);
            if ($seller) update_post_meta($order_id, 'seller_id', $seller);
            // Ensure task status exists
            $existing_ts = get_post_meta($order_id, '_task_status', true);
            if (empty($existing_ts)) {
                error_log('MNT Admin Repair: setting default _task_status=inqueue for order ' . $order_id);
                self::mnt_update_task_status($order_id, 'inqueue', false);
            }
            wp_send_json_success(['order_id' => $order_id, 'previous_status' => $previous, 'new_status' => get_post_status($order_id)]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
