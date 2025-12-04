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
        
        // Create escrow transaction
        $result = Escrow::create(
            $buyer_id,
            $seller_id,
            $amount,
            $task_id,
            'Task: ' . $task->post_title
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
        $escrow_id = get_post_meta($task_id, 'mnt_escrow_id', true);
        
        if ($escrow_id) {
            $result = Escrow::release($escrow_id, $buyer_id);
            
            if ($result && isset($result['success']) && $result['success']) {
                update_post_meta($task_id, 'mnt_escrow_status', 'released');
                
                // Update local log
                \MNT\Helpers\Logger::update_escrow_status($escrow_id, 'released');
                
                // Log the release
                do_action('mnt_escrow_released', $escrow_id, $task_id, $buyer_id);
            }
        }
    }

    /**
     * When buyer rejects task, refund escrow
     */
    public static function on_task_rejected($task_id, $buyer_id) {
        $escrow_id = get_post_meta($task_id, 'mnt_escrow_id', true);
        $seller_id = get_post_meta($task_id, 'mnt_seller_id', true);
        
        if ($escrow_id && $seller_id) {
            $result = Escrow::refund($escrow_id, $seller_id);
            
            if ($result && isset($result['success']) && $result['success']) {
                update_post_meta($task_id, 'mnt_escrow_status', 'refunded');
                
                // Log the refund
                do_action('mnt_escrow_refunded', $escrow_id, $task_id, $buyer_id);
            }
        }
    }

    /**
     * When task is disputed
     */
    public static function on_task_disputed($task_id, $user_id) {
        $escrow_id = get_post_meta($task_id, 'mnt_escrow_id', true);
        
        if ($escrow_id) {
            $reason = get_post_meta($task_id, 'dispute_reason', true) ?: 'Task disputed';
            $result = Escrow::dispute($escrow_id, $user_id, $reason);
            
            if ($result && isset($result['success']) && $result['success']) {
                update_post_meta($task_id, 'mnt_escrow_status', 'disputed');
                
                // Notify admin
                do_action('mnt_escrow_disputed', $escrow_id, $task_id, $user_id, $reason);
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

    /**
     * When proposal/project is completed by buyer
     * This fires when buyer marks the contract as completed
     */
    public static function on_proposal_completed($proposal_id, $buyer_id) {
        // Get project ID from proposal
        $project_id = get_post_meta($proposal_id, 'project_id', true);
        
        if (!$project_id) {
            $project_id = $proposal_id; // Sometimes proposal_id IS the project_id
        }
        
        error_log('MNT Escrow: on_proposal_completed called - proposal_id: ' . $proposal_id . ', buyer_id: ' . $buyer_id . ', project_id: ' . $project_id);
        
        // Call escrow client_confirm endpoint
        $result = Escrow::client_confirm($project_id, $buyer_id, true);
        
        error_log('MNT Escrow: client_confirm API response: ' . json_encode($result));
        
        // Check if API call succeeded (check for error absence or success flag)
        $is_success = ($result && !isset($result['error'])) || (isset($result['success']) && $result['success']);
        
        error_log('MNT Escrow: client_confirm success status: ' . ($is_success ? 'true' : 'false'));
        
        if ($is_success) {
            // Store confirmation in meta
            update_post_meta($proposal_id, 'mnt_client_confirmed', true);
            update_post_meta($proposal_id, 'mnt_client_confirmed_at', current_time('mysql'));
            
            // Check if both parties have now confirmed
            $escrow_details = Escrow::get_escrow_by_id($project_id);
            
            if ($escrow_details && isset($escrow_details['client_agree']) && isset($escrow_details['merchant_agree'])) {
                if ($escrow_details['client_agree'] === true && $escrow_details['merchant_agree'] === true) {
                    // Both parties confirmed - automatically release funds
                    // IMPORTANT: Use merchant_id (seller) from escrow details, not buyer_id
                    $seller_id = isset($escrow_details['merchant_id']) ? $escrow_details['merchant_id'] : '';
                    if ($seller_id) {
                        error_log('MNT Escrow: Both parties confirmed project #' . $project_id . '! Releasing funds to seller #' . $seller_id);
                        $release_result = Escrow::merchant_release_funds($project_id, $seller_id);
                        error_log('MNT Escrow: Release Funds Result: ' . json_encode($release_result));
                        
                        if ($release_result && !isset($release_result['error'])) {
                            update_post_meta($proposal_id, 'mnt_funds_released', true);
                            update_post_meta($proposal_id, 'mnt_funds_released_at', current_time('mysql'));
                        }
                    } else {
                        error_log('MNT Escrow: Cannot release funds - seller ID not found in escrow details');
                    }
                }
            }
            
            // Log the confirmation
            error_log('MNT Escrow: Client confirmed project #' . $project_id . ' by buyer #' . $buyer_id);
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
        // Call escrow client_confirm endpoint
        $result = Escrow::client_confirm($project_id, $buyer_id, true);
        
        // Check if API call succeeded (check for error absence or success flag)
        $is_success = ($result && !isset($result['error'])) || (isset($result['success']) && $result['success']);
        
        if ($is_success) {
            // Store confirmation in meta
            update_post_meta($project_id, 'mnt_client_confirmed', true);
            update_post_meta($project_id, 'mnt_client_confirmed_at', current_time('mysql'));
            
            // Check if both parties have now confirmed
            $escrow_details = Escrow::get_escrow_by_id($project_id);
            
            if ($escrow_details && isset($escrow_details['client_agree']) && isset($escrow_details['merchant_agree'])) {
                if ($escrow_details['client_agree'] === true && $escrow_details['merchant_agree'] === true) {
                    // Both parties confirmed - automatically release funds
                    // IMPORTANT: Use merchant_id (seller) from escrow details, not buyer_id
                    $seller_id = isset($escrow_details['merchant_id']) ? $escrow_details['merchant_id'] : '';
                    if ($seller_id) {
                        error_log('MNT Escrow: Both parties confirmed project #' . $project_id . '! Releasing funds to seller #' . $seller_id);
                        $release_result = Escrow::merchant_release_funds($project_id, $seller_id);
                        error_log('MNT Escrow: Release Funds Result: ' . json_encode($release_result));
                        
                        if ($release_result && !isset($release_result['error'])) {
                            update_post_meta($project_id, 'mnt_funds_released', true);
                            update_post_meta($project_id, 'mnt_funds_released_at', current_time('mysql'));
                        }
                    } else {
                        error_log('MNT Escrow: Cannot release funds - seller ID not found in escrow details');
                    }
                }
            }
            
            // Log the confirmation
            error_log('MNT Escrow: Client confirmed project #' . $project_id . ' by buyer #' . $buyer_id);
            do_action('mnt_client_confirmed', $project_id, null, $buyer_id);
        } else {
            // Log error
            error_log('MNT Escrow: Failed to confirm project #' . $project_id . ' - ' . json_encode($result));
        }
    }

    /**
     * Intercept rating proposal AJAX to trigger completion
     * This is called before the Taskbot AJAX handler
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
        
        // Call our completion handler - but catch any errors to prevent blocking
        try {
            self::on_proposal_completed($proposal_id, $buyer_id);
            error_log('MNT Escrow: on_proposal_completed executed successfully');
        } catch (\Exception $e) {
            error_log('MNT Escrow: Error in on_proposal_completed: ' . $e->getMessage());
            error_log('MNT Escrow: Stack trace: ' . $e->getTraceAsString());
        }
        
        error_log('MNT Escrow: Continuing to Taskbot handler');
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
            
            // Check if both parties have confirmed via escrow
            $escrow_details = Escrow::get_escrow_by_id($project_id);
            
            error_log('MNT Escrow: Escrow details for status check: ' . json_encode($escrow_details));
            
            $both_confirmed = false;
            if ($escrow_details && isset($escrow_details['client_agree']) && isset($escrow_details['merchant_agree'])) {
                $both_confirmed = ($escrow_details['client_agree'] === true && $escrow_details['merchant_agree'] === true);
            }
            
            // If both parties haven't confirmed, allow first attempt (client is confirming now)
            // Only prevent on subsequent attempts
            $already_prevented = get_transient('mnt_prevent_completion_' . $project_id);
            
            if (!$both_confirmed && !$already_prevented) {
                error_log('MNT Escrow: First completion attempt for project #' . $project_id . ' - allowing client to confirm');
                // Set transient to prevent infinite loop
                set_transient('mnt_prevent_completion_' . $project_id, true, 60);
                // Allow this attempt - client_confirm will be called
                return;
            }
            
            if (!$both_confirmed && $already_prevented) {
                error_log('MNT Escrow: Preventing project #' . $project_id . ' completion - both parties must confirm first');
                
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
            
            // Both confirmed - allow completion and handle buyer confirmation
            error_log('MNT Escrow: Both parties confirmed project #' . $project_id . ' - allowing completion');
            
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
