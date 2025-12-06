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
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $seller_id_from_ajax = isset($_POST['seller_id']) ? intval($_POST['seller_id']) : 0;
        
        error_log('MNT Fund Escrow - Received: project_id=' . $project_id . ', user_id=' . $user_id . ', seller_id=' . $seller_id_from_ajax);
        
        if (empty($project_id) || empty($user_id)) {
            error_log('MNT Fund Escrow - ERROR: Missing project_id or user_id');
            wp_send_json_error(['message' => 'Project ID and User ID are required.']);
            return;
        }
        
        // Prioritize seller_id from AJAX, then try post meta, then fallback methods
        $seller_id = $seller_id_from_ajax;
        
        if (empty($seller_id)) {
            // Get seller ID from post meta
            $seller_id = get_post_meta($project_id, 'mnt_escrow_seller', true);
        }
        
        error_log('=== MNT Fund Escrow - Initial Data ===');
        error_log('project_id: ' . $project_id);
        error_log('user_id (client_id): ' . $user_id);
        error_log('seller_id from AJAX: ' . ($seller_id_from_ajax ? $seller_id_from_ajax : 'EMPTY'));
        error_log('seller_id from post meta: ' . (get_post_meta($project_id, 'mnt_escrow_seller', true) ?: 'EMPTY'));
        error_log('seller_id being used: ' . ($seller_id ? $seller_id : 'EMPTY'));
        error_log('seller_id type: ' . gettype($seller_id));
        
        // If seller_id is not found, try to get it from proposal
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
            ]);
            return;
        } elseif ($escrow_status === 'FINALIZED') {
            wp_send_json_error([
                'message' => '<strong>Escrow Already Completed</strong><br><br>This escrow has already been completed and funds released to seller. Status: FINALIZED'
            ]);
            return;
        }
        
        // Get merchant/seller ID from meta
        $seller_id = get_post_meta($project_id, 'mnt_escrow_seller', true);
        
        error_log('=== MNT Fund Escrow - Preparing API Call ===');
        error_log('project_id: ' . $project_id . ' (type: ' . gettype($project_id) . ')');
        error_log('client_id (user_id): ' . $user_id . ' (type: ' . gettype($user_id) . ')');
        error_log('merchant_id (seller_id): ' . $seller_id . ' (type: ' . gettype($seller_id) . ')');
        error_log('seller_id is_empty: ' . (empty($seller_id) ? 'YES' : 'NO'));
        
        if (!$seller_id) {
            error_log('MNT Fund Escrow - ERROR: seller_id is empty!');
            wp_send_json_error(['message' => '<strong>Missing Seller ID</strong><br><br>Cannot fund escrow without seller information.<br>Project ID: ' . $project_id]);
            return;
        }
        
        // Call the client_release_funds API endpoint
        // This moves money: Client Wallet → Escrow Account (pending → funded)
        error_log('MNT Fund Escrow - Calling client_release_funds($project_id, $user_id, $seller_id)');
        $result = \MNT\Api\Escrow::client_release_funds($project_id, $user_id, $seller_id);
        
        error_log('MNT Fund Escrow - API Response: ' . json_encode($result));
        
        if ($result && !isset($result['error']) && !isset($result['detail'])) {
            // Update task/project meta to reflect funded status
            update_post_meta($project_id, 'mnt_escrow_status', 'funded');
            update_post_meta($project_id, 'mnt_escrow_funded_at', current_time('mysql'));
            
            // Trigger task purchase - update task status to "hired"
            error_log('MNT Fund Escrow - Triggering task purchase for task ' . $project_id);
            
            // Update task status to hired
            update_post_meta($project_id, '_post_project_status', 'hired');
            update_post_meta($project_id, '_hired_status', 'hired');
            wp_update_post([
                'ID' => $project_id,
                'post_status' => 'hired'
            ]);
            
            // Create WooCommerce order for the task purchase
            $order_id = null;
            $order_url = '';
            
            if (class_exists('WooCommerce') && $seller_id) {
                try {
                    $order = wc_create_order(['customer_id' => $user_id]);
                    
                    if (!is_wp_error($order)) {
                        // Get task/product details
                        $task_post = get_post($project_id);
                        $task_title = $task_post ? $task_post->post_title : 'Task #' . $project_id;
                        $escrow_amount = get_post_meta($project_id, 'mnt_escrow_amount', true);
                        
                        // Add custom line item for the task
                        $item = new WC_Order_Item_Product();
                        $item->set_name($task_title);
                        $item->set_quantity(1);
                        $item->set_subtotal($escrow_amount);
                        $item->set_total($escrow_amount);
                        $order->add_item($item);
                        
                        // Store escrow metadata
                        $order->add_meta_data('mnt_escrow_id', get_post_meta($project_id, 'mnt_escrow_id', true));
                        $order->add_meta_data('project_id', $project_id);
                        $order->add_meta_data('task_product_id', $project_id);
                        $order->add_meta_data('seller_id', $seller_id);
                        $order->add_meta_data('buyer_id', $user_id);
                        $order->add_meta_data('_task_status', 'hired');
                        $order->add_meta_data('payment_type', 'escrow');
                        $order->add_meta_data('escrow_funded', 'yes');
                        
                        // Add Taskbot-compatible invoice data
                        $invoice_data = [
                            'project_id' => $project_id,
                            'project_type' => 'fixed',
                            'seller_shares' => $escrow_amount,
                            'payment_method' => 'escrow',
                            'escrow_id' => get_post_meta($project_id, 'mnt_escrow_id', true),
                            'funded_at' => current_time('mysql')
                        ];
                        $order->add_meta_data('cus_woo_product_data', $invoice_data);
                        
                        $order->set_total($escrow_amount);
                        $order->set_status('completed'); // Mark as completed since it's paid via escrow
                        $order->save();
                        
                        $order_id = $order->get_id();
                        $order_url = $order->get_view_order_url();
                        
                        // Link order to task
                        update_post_meta($project_id, 'mnt_wc_order_id', $order_id);
                        
                        error_log('MNT Fund Escrow - WooCommerce order created: ' . $order_id);
                    } else {
                        error_log('MNT Fund Escrow - WooCommerce order creation failed: ' . $order->get_error_message());
                    }
                } catch (Exception $e) {
                    error_log('MNT Fund Escrow - Exception creating WC order: ' . $e->getMessage());
                }
            }
            
            $success_msg = '<strong>Escrow Funded & Task Purchased!</strong><br><br>';
            $success_msg .= 'Funds have been moved from your wallet to the escrow account.<br><br>';
            $success_msg .= '<strong>Status:</strong> FUNDED & HIRED<br>';
            if ($order_id) {
                $success_msg .= '<strong>Order ID:</strong> #' . $order_id . '<br>';
                if ($order_url) {
                    $success_msg .= '<a href="' . esc_url($order_url) . '" target="_blank" style="color:#3b82f6;text-decoration:underline;">View Order Details</a>';
                }
            }
            
            // Get redirect URL for ongoing tasks (buyer insights page)
            $redirect_url = '';
            if (class_exists('Taskbot_Profile_Menu')) {
                $redirect_url = Taskbot_Profile_Menu::taskbot_profile_menu_link('earnings', $user_id, true, 'insights');
            }
            
            wp_send_json_success([
                'message' => $success_msg,
                'result' => $result,
                'order_id' => $order_id,
                'task_hired' => true,
                'redirect_url' => $redirect_url
            ]);
        } else {
            $error_msg = isset($result['detail']) ? $result['detail'] : (isset($result['error']) ? $result['error'] : (isset($result['message']) ? $result['message'] : 'Failed to fund escrow.'));
            
            error_log('MNT Fund Escrow - ERROR: ' . $error_msg);
            error_log('MNT Fund Escrow - Full API Response: ' . print_r($result, true));
            
            // Build detailed error message
            $detailed_error = '<strong>Failed to fund escrow:</strong><br><br>';
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
            
            wp_send_json_success([
                'message' => $result['message'] ?? 'Contract completed! Funds released to seller wallet.',
                'result' => $result
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
     * Enqueue scripts and styles
     */
    public static function enqueue_scripts() {
        wp_enqueue_style(
            'mnt-escrow-style',
            plugins_url('assets/css/style.css', dirname(dirname(__FILE__))),
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'mnt-escrow-script',
            plugins_url('assets/js/escrow.js', dirname(dirname(__FILE__))),
            ['jquery'],
            '1.0.0',
            true
        );

        // Enqueue the hire handler script for escrow creation feedback
        wp_enqueue_script(
            'mnt-hire-script',
            plugins_url('assets/js/mnt-hire.js', dirname(dirname(__FILE__))),
            ['jquery'],
            '1.0.0',
            true
        );

        // Localize for escrow.js and mnt-complete-escrow.js
        $mnt_escrow_localize = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mnt_nonce'),
            'restUrl' => rest_url('mnt/v1'),
            'restNonce' => wp_create_nonce('wp_rest'),
            'currentUserId' => get_current_user_id()
        ];
        wp_localize_script('mnt-escrow-script', 'mntEscrow', $mnt_escrow_localize);
        // Enqueue and localize mnt-complete-escrow.js for modal Complete button
        // Ensure jQuery is loaded before mnt-complete-escrow.js
        wp_enqueue_script(
            'mnt-complete-escrow-script',
            plugins_url('assets/js/mnt-complete-escrow.js', dirname(dirname(__FILE__))),
            ['jquery'],
            '1.0.0',
            true
        );
        wp_localize_script('mnt-complete-escrow-script', 'mntEscrow', $mnt_escrow_localize);
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
        
        // Create escrow transaction via API (this automatically deducts from wallet)
        // IMPORTANT: Pass project_id so backend can link the escrow to this project
        error_log('=== MNT Escrow Creation Request ===');
        error_log('Seller: ' . $seller_id . ', Buyer: ' . $buyer_id . ', Amount: ' . $amount . ', Project: ' . $project_id);
        
        $escrow_result = \MNT\Api\Escrow::create((string)$seller_id, (string)$buyer_id, $amount, (string)$project_id);
        
        error_log('=== MNT Escrow Creation Response ===');
        error_log('Response: ' . print_r($escrow_result, true));
        
        if (!$escrow_result || isset($escrow_result['error'])) {
            $error_message = $escrow_result['error'] ?? 'Failed to create escrow transaction';
            error_log('MNT Escrow Creation Failed: ' . print_r($escrow_result, true));
            wp_send_json_error(['message' => $error_message]);
        }
        
        // Get escrow ID from response
        $escrow_id = $escrow_result['id'] ?? $escrow_result['escrow_id'] ?? '';
        $escrow_status = $escrow_result['status'] ?? 'funded';
        
        if (!$escrow_id) {
            error_log('MNT Escrow Creation: No ID returned - ' . print_r($escrow_result, true));
            wp_send_json_error(['message' => 'Escrow created but ID not returned']);
        }
        
        // Store escrow metadata in project
        update_post_meta($project_id, 'mnt_escrow_id', $escrow_id);
        update_post_meta($project_id, 'mnt_escrow_amount', $amount);
        update_post_meta($project_id, 'mnt_escrow_buyer', $buyer_id);
        update_post_meta($project_id, 'mnt_escrow_seller', $seller_id);
        update_post_meta($project_id, 'mnt_escrow_status', $escrow_status);
        update_post_meta($project_id, 'mnt_escrow_created_at', current_time('mysql'));
        
        // Update project status to hired
        update_post_meta($project_id, '_post_project_status', 'hired');
        
        // Get proposal ID if passed
        $proposal_id = isset($_POST['proposal_id']) ? intval($_POST['proposal_id']) : 0;
        
        // If we have a proposal, update it too
        if ($proposal_id) {
            wp_update_post(['ID' => $proposal_id, 'post_status' => 'hired']);
            update_post_meta($proposal_id, 'mnt_escrow_id', $escrow_id);
            update_post_meta($proposal_id, 'project_id', $project_id);
        }
        
        // Create WooCommerce order for tracking
        $order_url = '';
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
                $order->add_meta_data('_task_status', 'hired');
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
                
                $order->set_total($amount);
                $order->set_status('processing', 'Escrow funded - Project hired');
                $order->set_payment_method('mnt_escrow');
                $order->set_payment_method_title('Escrow Payment');
                $order->save();
                
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
}
