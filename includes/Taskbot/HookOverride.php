<?php
namespace MNT\Taskbot;

use MNT\Api\Wallet;
use MNT\Api\Escrow;

class HookOverride {

    public static function boot() {
        // Wallet creation removed from automatic registration/login
        // Users will create wallets manually via the wallet management tab
        // add_action('user_register', [__CLASS__, 'create_wallet_on_register']);
        // add_action('wp_login', [__CLASS__, 'ensure_wallet_exists'], 10, 2);
        
        // Override Taskbot wallet system - Balance & Payouts
        add_filter('taskbot_wallet_balance', [__CLASS__, 'override_wallet_balance'], 10, 2);
        add_filter('taskbot_get_wallet_balance', [__CLASS__, 'override_wallet_balance'], 10, 2);
        add_filter('taskbot_user_wallet_balance', [__CLASS__, 'override_wallet_balance'], 10, 2);
        add_filter('taskbot_can_payout', [__CLASS__, 'override_payout_check'], 10, 3);
        
        // Override Taskbot wallet transactions
        add_filter('taskbot_wallet_transactions', [__CLASS__, 'override_wallet_transactions'], 10, 2);
        add_filter('taskbot_get_transactions', [__CLASS__, 'override_wallet_transactions'], 10, 2);
        
        // Override Taskbot wallet deposit
        add_filter('taskbot_wallet_deposit', [__CLASS__, 'override_wallet_deposit'], 10, 3);
        add_filter('taskbot_before_deposit', [__CLASS__, 'override_wallet_deposit'], 10, 3);
        
        // Override Taskbot wallet credit (admin credit)
        add_filter('taskbot_wallet_credit', [__CLASS__, 'override_wallet_credit'], 10, 3);
        add_filter('taskbot_admin_credit_wallet', [__CLASS__, 'override_wallet_credit'], 10, 3);
        
        // Override Taskbot wallet transfer
        add_filter('taskbot_wallet_transfer', [__CLASS__, 'override_wallet_transfer'], 10, 4);
        add_filter('taskbot_transfer_funds', [__CLASS__, 'override_wallet_transfer'], 10, 4);
        
        // Intercept task creation and payment
        add_action('taskbot_task_created', [__CLASS__, 'on_task_created'], 10, 2);
        add_action('taskbot_payment_completed', [__CLASS__, 'on_payment_completed'], 10, 3);
        
        // Intercept task lifecycle
        add_action('taskbot_task_submitted', [__CLASS__, 'on_task_submitted'], 10, 2);
        add_action('taskbot_task_approved', [__CLASS__, 'on_task_approved'], 10, 2);
        add_action('taskbot_task_rejected', [__CLASS__, 'on_task_rejected'], 10, 2);
        add_action('taskbot_task_disputed', [__CLASS__, 'on_task_disputed'], 10, 2);
        
        // Intercept proposal/project completion
        add_action('taskbot_proposal_completed', [__CLASS__, 'on_proposal_completed'], 10, 2);
        add_action('taskbot_project_completed', [__CLASS__, 'on_project_completed'], 10, 2);
        add_action('wp_ajax_taskbot_rating_proposal', [__CLASS__, 'intercept_rating_proposal'], 1);
        
        // Hook into post status changes for completion detection
        self::hook_into_status_change();
        
        // Override withdrawal/payout actions
        add_filter('taskbot_before_withdrawal', [__CLASS__, 'handle_withdrawal'], 10, 3);
        
        // Add custom user meta for wallet tracking
        add_action('show_user_profile', [__CLASS__, 'show_wallet_info']);
        add_action('edit_user_profile', [__CLASS__, 'show_wallet_info']);
    }

    /**
     * Create wallet when user registers
     */
    public static function create_wallet_on_register($user_id) {
        $result = Wallet::create($user_id);
        if ($result && isset($result['id'])) {
            update_user_meta($user_id, 'mnt_wallet_created', true);
            update_user_meta($user_id, 'mnt_wallet_id', $result['id']);
            update_user_meta($user_id, 'mnt_wallet_uuid', $result['user_id']); // Backend UUID
        }
    }

    /**
     * Ensure wallet exists on login
     */
    public static function ensure_wallet_exists($user_login, $user) {
        if (!get_user_meta($user->ID, 'mnt_wallet_created', true)) {
            self::create_wallet_on_register($user->ID);
        }
    }

    /**
     * Override Taskbot wallet balance display
     */
    public static function override_wallet_balance($balance, $user_id) {
        $result = Wallet::balance($user_id);
        if ($result && isset($result['balance'])) {
            return $result['balance'];
        }
        return $balance;
    }

    /**
     * Override payout availability check
     */
    public static function override_payout_check($can_payout, $user_id, $amount) {
        $result = Wallet::balance($user_id);
        if ($result && isset($result['balance'])) {
            return $result['balance'] >= $amount;
        }
        return $can_payout;
    }

    /**
     * When task is created, ensure both buyer and seller have wallets
     */
    public static function on_task_created($task_id, $task_data) {
        $buyer_id = $task_data['buyer_id'] ?? get_current_user_id();
        $seller_id = $task_data['seller_id'] ?? 0;
        
        // Create wallets if they don't exist
        if (!get_user_meta($buyer_id, 'mnt_wallet_created', true)) {
            self::create_wallet_on_register($buyer_id);
        }
        
        if ($seller_id && !get_user_meta($seller_id, 'mnt_wallet_created', true)) {
            self::create_wallet_on_register($seller_id);
        }
        
        // Store task meta for later escrow creation
        update_post_meta($task_id, 'mnt_buyer_id', $buyer_id);
        update_post_meta($task_id, 'mnt_seller_id', $seller_id);
    }

    /**
     * When payment is completed, create escrow automatically
     */
    public static function on_payment_completed($task_id, $amount, $buyer_id) {
        $seller_id = get_post_meta($task_id, 'mnt_seller_id', true);
        $task = get_post($task_id);
        
        if (!$seller_id || !$task) {
            return;
        }
        
        // Ensure a WooCommerce order exists and is saved BEFORE creating escrow
        $order_id = get_post_meta($task_id, 'mnt_last_order_id', true);
        $order_obj = null;
        if (empty($order_id) && class_exists('WooCommerce')) {
            try {
                $order_obj = wc_create_order(['customer_id' => $buyer_id]);
                if (!is_wp_error($order_obj)) {
                    // add the task as an item so Taskbot/Woo can reference it
                    $product = wc_get_product($task_id);
                    if ($product) {
                        $order_obj->add_product($product, 1, ['subtotal' => $amount, 'total' => $amount]);
                    } else {
                        $item = new \WC_Order_Item_Product();
                        $item->set_name($task->post_title);
                        $item->set_quantity(1);
                        $item->set_subtotal($amount);
                        $item->set_total($amount);
                        $item->set_product_id($task_id);
                        $order_obj->add_item($item);
                    }
                    $order_obj->set_payment_method('mnt_escrow');
                    $order_obj->set_payment_method_title('Escrow Payment');
                    $order_obj->set_total($amount);
                    $order_obj->set_status('pending');
                    $order_obj->save();
                    $order_id = $order_obj->get_id();
                    update_post_meta($task_id, 'mnt_last_order_id', $order_id);
                    update_post_meta($task_id, 'mnt_escrow_project_id', 'order-' . $order_id);
                    update_post_meta($order_id, '_mnt_task_id', $task_id);
                    error_log('MNT: Created tracking order #' . $order_id . ' for task ' . $task_id . ' before escrow creation');
                } else {
                    error_log('MNT: Failed to create order for task ' . $task_id . ' - ' . $order_obj->get_error_message());
                    $order_obj = null;
                }
            } catch (\Exception $e) {
                error_log('MNT: Exception creating order for task ' . $task_id . ' - ' . $e->getMessage());
            }
        } else if (!empty($order_id)) {
            $order_obj = wc_get_order($order_id);
        }

        // Use the order-based project id when available to link escrow to order
        $project_id_for_api = !empty($order_id) ? 'order-' . $order_id : $task_id;

        // Create escrow transaction
        // API signature: create(merchant_id, client_id, project_id, amount)
        $result = Escrow::create(
            $seller_id,
            $buyer_id,
            $project_id_for_api,
            $amount
        );
        
        if ($result && isset($result['success']) && $result['success']) {
            $escrow_id = $result['escrow_id'] ?? '';
            update_post_meta($task_id, 'mnt_escrow_id', $escrow_id);
            update_post_meta($task_id, 'mnt_escrow_status', 'pending');
            update_post_meta($task_id, 'mnt_escrow_amount', $amount);
            
            // Log to database
            \MNT\Helpers\Logger::log_escrow(
                $escrow_id,
                $task_id,
                $buyer_id,
                $seller_id,
                $amount,
                'pending',
                'Task: ' . $task->post_title
            );
            
            // Log the escrow creation
            do_action('mnt_escrow_created', $escrow_id, $task_id, $buyer_id, $seller_id, $amount);
        }
    }

    /**
     * When seller submits task delivery
     */
    public static function on_task_submitted($task_id, $seller_id) {
        $escrow_id = get_post_meta($task_id, 'mnt_escrow_id', true);
        
        if ($escrow_id) {
            update_post_meta($task_id, 'mnt_escrow_status', 'delivered');
            
            // Notify buyer to approve or dispute
            $buyer_id = get_post_meta($task_id, 'mnt_buyer_id', true);
            do_action('mnt_task_delivered', $task_id, $escrow_id, $buyer_id, $seller_id);
        }
    }

    /**
     * When buyer approves task, release escrow
     */
    public static function on_task_approved($task_id, $buyer_id) {
        // Get seller ID for API call
        $seller_id = get_post_meta($task_id, 'mnt_seller_id', true);
        
        if ($seller_id) {
            // Use client_confirm to release funds from escrow to seller wallet
            $result = Escrow::client_confirm($task_id, $buyer_id, $seller_id, true);
            
            if ($result && !isset($result['error'])) {
                update_post_meta($task_id, 'mnt_escrow_status', 'released');
                
                // Update local log
                \MNT\Helpers\Logger::update_escrow_status($task_id, 'released');
                
                // Log the release
                do_action('mnt_escrow_released', $task_id, $task_id, $buyer_id);
            }
        }
    }

    /**
     * When buyer rejects task, cancel escrow transaction
     */
    public static function on_task_rejected($task_id, $buyer_id) {
        $seller_id = get_post_meta($task_id, 'mnt_seller_id', true);
        
        if ($seller_id) {
            // Use cancel_transaction to cancel and refund escrow
            $result = Escrow::cancel_transaction($task_id, $buyer_id, $seller_id);
            
            if ($result && !isset($result['error'])) {
                update_post_meta($task_id, 'mnt_escrow_status', 'refunded');
                
                // Log the refund
                do_action('mnt_escrow_refunded', $task_id, $task_id, $buyer_id);
            }
        }
    }

    /**
     * When task is disputed
     */
    public static function on_task_disputed($task_id, $user_id) {
        // Get the other party's ID
        $buyer_id = get_post_meta($task_id, 'mnt_buyer_id', true);
        $seller_id = get_post_meta($task_id, 'mnt_seller_id', true);
        
        // Determine if current user is buyer or seller
        $client_id = ($user_id == $buyer_id) ? $buyer_id : $seller_id;
        $merchant_id = ($user_id == $buyer_id) ? $seller_id : $buyer_id;
        
        if ($client_id && $merchant_id) {
            $reason = get_post_meta($task_id, 'dispute_reason', true) ?: 'Task disputed';
            // Use dispute_transaction with project_id, client_id, merchant_id, reason
            $result = Escrow::dispute_transaction($task_id, $client_id, $merchant_id, $reason);
            
            if ($result && !isset($result['error'])) {
                update_post_meta($task_id, 'mnt_escrow_status', 'disputed');
                
                // Notify admin
                do_action('mnt_escrow_disputed', $task_id, $task_id, $user_id, $reason);
            }
        }
    }

    /**
     * Handle withdrawal through our API
     */
    public static function handle_withdrawal($allowed, $user_id, $amount) {
        $account_details = [
            'bank_code' => get_user_meta($user_id, 'bank_code', true),
            'account_number' => get_user_meta($user_id, 'account_number', true),
            'account_name' => get_user_meta($user_id, 'account_name', true)
        ];
        
        $result = Wallet::withdraw($user_id, $amount, $account_details);
        
        if ($result && isset($result['success']) && $result['success']) {
            // Withdrawal initiated successfully
            return true;
        }
        
        // Prevent default withdrawal
        return false;
    }

    /**  check again 
     * When proposal/project is completed by buyer
     * This fires when buyer marks the contract as completed
     */
    public static function on_proposal_completed($proposal_id, $buyer_id) {
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
        
        if (!$project_id) {
            error_log('MNT Escrow: Cannot find project_id for proposal ' . $proposal_id);
            return;
        }
        
        error_log('MNT Escrow: on_proposal_completed called - proposal_id: ' . $proposal_id . ', buyer_id: ' . $buyer_id . ', project_id: ' . $project_id);
        
        // Get seller ID from meta
        $seller_id = get_post_meta($project_id, 'mnt_escrow_seller', true);
        if (!$seller_id) {
            error_log('MNT Escrow: Cannot confirm - missing seller ID for project ' . $project_id);
            return;
        }
        
        // Call escrow client_confirm endpoint
        $result = Escrow::client_confirm($project_id, $buyer_id, $seller_id, true);
        
        error_log('MNT Escrow: client_confirm API response: ' . json_encode($result));
        
        // Check if API call succeeded (check for error absence or success flag)
        $is_success = ($result && !isset($result['error'])) || (isset($result['success']) && $result['success']);
        
        error_log('MNT Escrow: client_confirm success status: ' . ($is_success ? 'true' : 'false'));
        
        if ($is_success) {
            // Store confirmation in meta
            update_post_meta($proposal_id, 'mnt_client_confirmed', true);
            update_post_meta($proposal_id, 'mnt_client_confirmed_at', current_time('mysql'));
            
            // Client confirmation releases funds to seller wallet - project is now complete
            error_log('MNT Escrow: Client confirmed project #' . $project_id . '! Funds released to seller.');
            update_post_meta($proposal_id, 'mnt_funds_released', true);
            update_post_meta($proposal_id, 'mnt_funds_released_at', current_time('mysql'));
            
            // Log the confirmation
            error_log('MNT Escrow: Client confirmed and released funds for project #' . $project_id . ' by buyer #' . $buyer_id);
            do_action('mnt_client_confirmed', $project_id, $proposal_id, $buyer_id);
        } else {
            // Log error
            error_log('MNT Escrow: Failed to confirm project #' . $project_id . ' - ' . json_encode($result));
        }
    }

    /**
     * When project is completed
     */
    public static function on_project_completed($project_id, $buyer_id) {
        // Get seller ID from meta
        $seller_id = get_post_meta($project_id, 'mnt_escrow_seller', true);
        if (!$seller_id) {
            error_log('MNT Escrow: Cannot confirm - missing seller ID for project ' . $project_id);
            return;
        }
        
        // Call escrow client_confirm endpoint
        $result = Escrow::client_confirm($project_id, $buyer_id, $seller_id, true);
        
        // Check if API call succeeded (check for error absence or success flag)
        $is_success = ($result && !isset($result['error'])) || (isset($result['success']) && $result['success']);
        
        if ($is_success) {
            // Store confirmation in meta
            update_post_meta($project_id, 'mnt_client_confirmed', true);
            update_post_meta($project_id, 'mnt_client_confirmed_at', current_time('mysql'));
            
            // Client confirmation releases funds to seller wallet - project is now complete
            error_log('MNT Escrow: Client confirmed project #' . $project_id . '! Funds released to seller.');
            update_post_meta($project_id, 'mnt_funds_released', true);
            update_post_meta($project_id, 'mnt_funds_released_at', current_time('mysql'));
            
            // Log the confirmation
            error_log('MNT Escrow: Client confirmed and released funds for project #' . $project_id . ' by buyer #' . $buyer_id);
            do_action('mnt_client_confirmed', $project_id, null, $buyer_id);
        } else {
            // Log error
            error_log('MNT Escrow: Failed to confirm project #' . $project_id . ' - ' . json_encode($result));
        }
    }

    /**
     * Intercept rating proposal AJAX to trigger completion
     * This is called before the Taskbot AJAX handler
     * If escrow fails, prevents Taskbot from completing the contract
     */
    public static function intercept_rating_proposal() {
        error_log('MNT Escrow: intercept_rating_proposal called');
        error_log('MNT Escrow: POST data: ' . json_encode($_POST));
        
        // Check if this is the complete contract action
        if (!isset($_POST['proposal_id']) || !isset($_POST['user_id'])) {
            error_log('MNT Escrow: Missing proposal_id or user_id - letting Taskbot handle');
            return; // Let Taskbot handle it
        }
        
        $proposal_id = intval($_POST['proposal_id']);
        $buyer_id = intval($_POST['user_id']);
        
        error_log('MNT Escrow: Processing - proposal_id: ' . $proposal_id . ', buyer_id: ' . $buyer_id);
        
        // Get project ID to validate escrow
        $project_id = get_post_meta($proposal_id, 'project_id', true);
        if (!$project_id) {
            $project_id = get_post_meta($proposal_id, '_project_id', true);
        }
        if (!$project_id) {
            $proposal = get_post($proposal_id);
            if ($proposal && $proposal->post_parent) {
                $project_id = $proposal->post_parent;
            }
        }
        
        if (!$project_id) {
            error_log('MNT Escrow: Cannot find project_id - blocking completion');
            wp_send_json_error([
                'message' => 'Cannot complete contract: Project information not found.'
            ]);
            return;
        }
        
        // Get seller ID for escrow validation
        $seller_id = get_post_meta($project_id, 'mnt_escrow_seller', true);
        if (!$seller_id) {
            error_log('MNT Escrow: Cannot find seller_id - blocking completion');
            wp_send_json_error([
                'message' => 'Cannot complete contract: Seller information not found.'
            ]);
            return;
        }
        
        // Call escrow client_confirm endpoint
        $result = Escrow::client_confirm($project_id, $buyer_id, $seller_id, true);
        
        error_log('MNT Escrow: client_confirm API response: ' . json_encode($result));
        
        // Check if API call succeeded
        $is_success = ($result && !isset($result['error']) && !isset($result['detail'])) || (isset($result['success']) && $result['success']);
        
        if (!$is_success) {
            // Escrow failed - block Taskbot completion
            $error_msg = isset($result['detail']) ? $result['detail'] : (isset($result['error']) ? $result['error'] : 'Failed to release escrow funds.');
            
            error_log('MNT Escrow: client_confirm FAILED - blocking completion: ' . $error_msg);
            
            wp_send_json_error([
                'message' => 'Cannot complete contract: ' . $error_msg
            ]);
            return;
        }
        
        // Escrow succeeded - update meta and mark funds as released
        error_log('MNT Escrow: client_confirm succeeded - funds released to seller');
        update_post_meta($proposal_id, 'mnt_client_confirmed', true);
        update_post_meta($proposal_id, 'mnt_client_confirmed_at', current_time('mysql'));
        update_post_meta($proposal_id, 'mnt_funds_released', true);
        update_post_meta($proposal_id, 'mnt_funds_released_at', current_time('mysql'));
        
        do_action('mnt_client_confirmed', $project_id, $proposal_id, $buyer_id);
        
        error_log('MNT Escrow: Escrow successful - continuing to Taskbot handler');
        // Continue - let Taskbot handle the AJAX response
    }

    /**
     * Alternative: Hook into post status change
     * This catches when proposal status changes to 'completed'
     */
    public static function hook_into_status_change() {
        add_action('transition_post_status', [__CLASS__, 'handle_status_transition'], 10, 3);
    }
    
    /**
     * Handle post status transition
     */
    public static function handle_status_transition($new_status, $old_status, $post) {
        // Check if this is a proposal being marked as completed
        if ($new_status === 'completed' && $old_status !== 'completed' && $post->post_type === 'proposals') {
            error_log('MNT Escrow: handle_status_transition - proposal ' . $post->ID . ' changing from ' . $old_status . ' to ' . $new_status);
            
            // Get project ID
            $project_id = get_post_meta($post->ID, 'project_id', true);
            if (!$project_id) {
                $project_id = $post->ID;
            }
            
            error_log('MNT Escrow: Project ID: ' . $project_id);
            
            // Check if client has confirmed and funds are released
            $client_confirmed = get_post_meta($post->ID, 'mnt_client_confirmed', true);
            $funds_released = get_post_meta($post->ID, 'mnt_funds_released', true);
            
            error_log('MNT Escrow: Status check - client_confirmed: ' . ($client_confirmed ? 'YES' : 'NO') . ', funds_released: ' . ($funds_released ? 'YES' : 'NO'));
            
            // If client hasn't confirmed yet, allow first attempt (client is confirming now)
            // Only prevent on subsequent attempts
            $already_prevented = get_transient('mnt_prevent_completion_' . $project_id);
            
            if (!$client_confirmed && !$already_prevented) {
                error_log('MNT Escrow: First completion attempt for project #' . $project_id . ' - allowing client to confirm');
                // Set transient to prevent infinite loop
                set_transient('mnt_prevent_completion_' . $project_id, true, 60);
                // Allow this attempt - client_confirm will be called
                return;
            }
            
            if (!$client_confirmed && $already_prevented) {
                error_log('MNT Escrow: Preventing project #' . $project_id . ' completion - client must confirm and release funds first');
                
                // Revert status back to hired
                remove_action('transition_post_status', [__CLASS__, 'handle_status_transition'], 10);
                wp_update_post([
                    'ID' => $post->ID,
                    'post_status' => 'hired'
                ]);
                add_action('transition_post_status', [__CLASS__, 'handle_status_transition'], 10, 3);
                
                // Clear the transient
                delete_transient('mnt_prevent_completion_' . $project_id);
                
                return;
            }
            
            // Client confirmed - funds released - allow completion
            error_log('MNT Escrow: Client confirmed and funds released for project #' . $project_id . ' - allowing completion');
            
            // Clear the transient
            delete_transient('mnt_prevent_completion_' . $project_id);
            
            // Get buyer ID (post author or from meta)
            $buyer_id = get_post_meta($post->ID, 'buyer_id', true);
            if (!$buyer_id) {
                $buyer_id = get_post_field('post_author', $project_id);
            }
            
            if ($buyer_id) {
                self::on_proposal_completed($post->ID, $buyer_id);
            }
        }
    }

    /**
     * Show wallet info in user profile
     */
    public static function show_wallet_info($user) {
        if (!current_user_can('edit_users')) {
            return;
        }
        
        $wallet_created = get_user_meta($user->ID, 'mnt_wallet_created', true);
        $wallet_id = get_user_meta($user->ID, 'mnt_wallet_id', true);
        
        $balance_data = Wallet::balance($user->ID);
        $balance = $balance_data['balance'] ?? 'N/A';
        
        ?>
        <h3>MyNaijaTask Escrow Wallet</h3>
        <table class="form-table">
            <tr>
                <th><label>Wallet Status</label></th>
                <td>
                    <?php echo $wallet_created ? '<span style="color: green;">✓ Created</span>' : '<span style="color: red;">✗ Not Created</span>'; ?>
                </td>
            </tr>
            <tr>
                <th><label>Wallet ID</label></th>
                <td><?php echo esc_html($wallet_id ?: 'N/A'); ?></td>
            </tr>
            <tr>
                <th><label>Balance</label></th>
                <td>₦<?php echo esc_html(number_format($balance, 2)); ?></td>
            </tr>
        </table>
        <?php
    }

    /**
     * Override wallet transactions
     */
    public static function override_wallet_transactions($default_transactions, $user_id) {
        $result = Wallet::transactions($user_id);
        
        if ($result && isset($result['transactions'])) {
            return $result['transactions'];
        }
        
        return $default_transactions;
    }

    /**
     * Override wallet deposit
     */
    public static function override_wallet_deposit($success, $user_id, $amount) {
        $result = Wallet::deposit($user_id, $amount);
        
        if ($result && isset($result['success']) && $result['success']) {
            return true;
        }
        
        return false;
    }

    /**
     * Override wallet credit
     */
    public static function override_wallet_credit($success, $user_id, $amount) {
        $result = Wallet::credit($user_id, $amount);
        
        if ($result && isset($result['success']) && $result['success']) {
            return true;
        }
        
        return false;
    }

    /**
     * Override wallet transfer
     */
    public static function override_wallet_transfer($success, $from_user_id, $to_user_id, $amount) {
        $result = Wallet::transfer($from_user_id, $to_user_id, $amount);
        
        if ($result && isset($result['success']) && $result['success']) {
            return true;
        }
        
        return false;
    }
}
