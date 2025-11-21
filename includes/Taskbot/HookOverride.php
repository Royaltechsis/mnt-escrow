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
