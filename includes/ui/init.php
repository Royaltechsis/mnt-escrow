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

        wp_localize_script('mnt-escrow-script', 'mntEscrow', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mnt_nonce'),
            'restUrl' => rest_url('mnt/v1'),
            'restNonce' => wp_create_nonce('wp_rest')
        ]);
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
            return '<p>Please login to make a deposit.</p>';
        }

        ob_start();
        ?>
        <div class="mnt-deposit-form">
            <h3>Deposit Funds</h3>
            <form id="mnt-deposit-form" method="post" action="">
                <div class="form-group">
                    <label for="deposit-amount">Amount (₦)</label>
                    <input type="number" id="deposit-amount" name="amount" min="100" step="0.01" required>
                </div>
                <button type="submit" class="mnt-btn">Deposit</button>
                <div class="mnt-message"></div>
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
            return '<p>Please login to make a withdrawal.</p>';
        }

        ob_start();
        ?>
        <div class="mnt-withdraw-form">
            <h3>Withdraw Funds</h3>
            <form id="mnt-withdraw-form">
                <div class="form-group">
                    <label for="withdraw-amount">Amount (₦)</label>
                    <input type="number" id="withdraw-amount" name="amount" min="100" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="bank-code">Bank</label>
                    <select id="bank-code" name="bank_code" required>
                        <option value="">Select Bank</option>
                        <option value="058">GTBank</option>
                        <option value="032">Union Bank</option>
                        <option value="033">UBA</option>
                        <option value="011">First Bank</option>
                        <option value="044">Access Bank</option>
                        <option value="057">Zenith Bank</option>
                        <!-- Add more banks as needed -->
                    </select>
                </div>
                <div class="form-group">
                    <label for="account-number">Account Number</label>
                    <input type="text" id="account-number" name="account_number" required>
                </div>
                <div class="form-group">
                    <label for="account-name">Account Name</label>
                    <input type="text" id="account-name" name="account_name" required>
                </div>
                <button type="submit" class="mnt-btn">Withdraw</button>
                <div class="mnt-message"></div>
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
            return '<p>Please login to make a transfer.</p>';
        }

        ob_start();
        ?>
        <div class="mnt-transfer-form">
            <h3>Transfer Funds</h3>
            <form id="mnt-transfer-form">
                <div class="form-group">
                    <label for="recipient-email">Recipient Email</label>
                    <input type="email" id="recipient-email" name="recipient_email" required>
                </div>
                <div class="form-group">
                    <label for="transfer-amount">Amount (₦)</label>
                    <input type="number" id="transfer-amount" name="amount" min="100" step="0.01" required>
                </div>
                <div class="form-group">
                    <label for="transfer-description">Description (optional)</label>
                    <input type="text" id="transfer-description" name="description">
                </div>
                <button type="submit" class="mnt-btn">Transfer</button>
                <div class="mnt-message"></div>
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
        $escrow_result = \MNT\Api\Escrow::create((string)$seller_id, (string)$buyer_id, $amount);
        
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
