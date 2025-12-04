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
        add_action('wp_ajax_mnt_merchant_confirm_funds', [__CLASS__, 'handle_merchant_confirm_funds_ajax']);
        add_action('wp_ajax_nopriv_mnt_merchant_confirm_funds', [__CLASS__, 'handle_merchant_confirm_funds_ajax']);
        add_action('wp_ajax_mnt_merchant_release_funds_action', [__CLASS__, 'handle_merchant_release_funds_ajax']);
        add_action('wp_ajax_nopriv_mnt_merchant_release_funds_action', [__CLASS__, 'handle_merchant_release_funds_ajax']);

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
            $escrow_details = \MNT\Api\Escrow::get_escrow_by_id($project_id);
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
     * AJAX Handler: Complete Escrow Funds (move funds to escrow)
     */
    public static function handle_complete_escrow_funds_ajax() {
        check_ajax_referer('mnt_nonce', 'nonce');
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if (!$project_id || !$user_id) {
            wp_send_json_error(['message' => 'Missing project or user ID.']);
        }
        $result = \MNT\Api\Escrow::client_release_funds($user_id, $project_id);
        if (isset($result['status']) && strtoupper($result['status']) === 'FUNDED') {
            // Update project status to 'hired' when funds are successfully released
            update_post_meta($project_id, '_post_project_status', 'hired');
            update_post_meta($project_id, 'mnt_escrow_status', 'funded');
            
            // Also update the post status if it's a proposal
            $proposal_id = get_post_meta($project_id, 'mnt_proposal_id', true);
            if ($proposal_id) {
                wp_update_post(['ID' => $proposal_id, 'post_status' => 'hired']);
            }
            
            wp_send_json_success(['message' => $result['message'] ?? 'Funds released successfully', 'client_release_response' => $result]);
        } else {
            $msg = $result['message'] ?? ($result['error'] ?? 'Failed to move funds.');
            wp_send_json_error(['message' => $msg, 'client_release_response' => $result]);
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
            'restNonce' => wp_create_nonce('wp_rest')
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
        $escrow_result = \MNT\Api\Escrow::create((string)$seller_id, (string)$buyer_id, $amount, (string)$project_id);
        
        error_log('=== MNT Escrow Creation Request ===');
        error_log('Seller: ' . $seller_id . ', Buyer: ' . $buyer_id . ', Amount: ' . $amount . ', Project: ' . $project_id);
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
