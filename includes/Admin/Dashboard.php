<?php
namespace MNT\Admin;

use MNT\Api\Wallet;
use MNT\Api\Escrow;
use MNT\Api\Transaction;

class Dashboard {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('wp_ajax_mnt_admin_resolve_dispute', [__CLASS__, 'handle_resolve_dispute']);
        add_action('wp_ajax_mnt_admin_search_wallet', [__CLASS__, 'handle_wallet_search']);
        add_action('wp_ajax_mnt_find_user', [__CLASS__, 'handle_find_user']);
        add_action('wp_ajax_mnt_admin_freeze_wallet', [__CLASS__, 'handle_freeze_wallet']);
        add_action('wp_ajax_mnt_admin_unfreeze_wallet', [__CLASS__, 'handle_unfreeze_wallet']);
        add_action('wp_ajax_mnt_admin_get_user_transactions', [__CLASS__, 'handle_get_user_transactions']);
    }

    /**
     * Add admin menu
     */
    public static function add_admin_menu() {
        add_menu_page(
            'MNT Escrow',
            'MNT Escrow',
            'manage_options',
            'mnt-escrow',
            [__CLASS__, 'dashboard_page'],
            'dashicons-money-alt',
            30
        );

        add_submenu_page(
            'mnt-escrow',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'mnt-escrow',
            [__CLASS__, 'dashboard_page']
        );

        add_submenu_page(
            'mnt-escrow',
            'Settings',
            'Settings',
            'manage_options',
            'mnt-escrow-settings',
            [__CLASS__, 'settings_page']
        );

        add_submenu_page(
            'mnt-escrow',
            'Transactions',
            'Transactions',
            'manage_options',
            'mnt-escrow-transactions',
            [__CLASS__, 'transactions_page']
        );

        add_submenu_page(
            'mnt-escrow',
            'Disputes',
            'Disputes',
            'manage_options',
            'mnt-escrow-disputes',
            [__CLASS__, 'disputes_page']
        );

        add_submenu_page(
            'mnt-escrow',
            'Users & Wallets',
            'Users & Wallets',
            'manage_options',
            'mnt-escrow-wallets',
            [__CLASS__, 'wallets_page']
        );

        add_submenu_page(
            'mnt-escrow',
            'Escrow Management',
            'Escrow Management',
            'manage_options',
            'mnt-escrow-management',
            [__CLASS__, 'escrow_management_page']
        );
    }
    /**
     * Escrow Management Page
     */
    public static function escrow_management_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>
        <div class="wrap mnt-admin-wrap">
            <h1>Escrow Management</h1>

            <form method="get" action="" style="margin-bottom: 24px;">
                <input type="hidden" name="page" value="mnt-escrow-management">
                <label for="escrow_id_search"><strong>Search Escrow by ID:</strong></label>
                <input type="text" id="escrow_id_search" name="escrow_id" value="<?php echo esc_attr($_GET['escrow_id'] ?? ''); ?>" style="width: 300px;">
                <button type="submit" class="button button-primary">Search</button>
            </form>

            <div id="mnt-escrow-management-results">
                <?php if (!empty($_GET['escrow_id'])): ?>
                    <h2>Escrow Details (ID: <?php echo esc_html($_GET['escrow_id']); ?>)</h2>
                    <div class="notice notice-info">(API integration for details coming next step)</div>
                    <!-- TODO: Show escrow details and action buttons here -->
                <?php endif; ?>
            </div>

            <hr style="margin: 32px 0;">
            <h2>All Disputed Escrows</h2>
            <div class="notice notice-info">(API integration for disputes coming next step)</div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Escrow ID</th>
                        <th>Client ID</th>
                        <th>Merchant ID</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- TODO: Populate with API data -->
                    <tr><td colspan="6"><em>Disputed escrows will be listed here.</em></td></tr>
                </tbody>
            </table>
            <div style="margin-top: 24px;">
                <button class="button">Force Release Funds</button>
                <button class="button">Force Return Funds</button>
                <button class="button">Cancel Transaction</button>
                <button class="button">Dispute Transaction</button>
                <button class="button">Resolve Dispute</button>
            </div>

            <hr style="margin: 32px 0;">
            <h2>Backfill Task Orders (Repair Draft Orders)</h2>
            <p>Use this tool to find orders with <code>payment_type=tasks</code> that are stuck in <code>draft</code> and repair them so they appear in Taskbot dashboards. Start with <em>Dry Run</em> to see what would be changed.</p>
            <div>
                <button id="mnt-backfill-dry" class="button">Backfill (Dry Run)</button>
                <button id="mnt-backfill-run" class="button button-primary">Backfill (Run)</button>
            </div>
            <div id="mnt-backfill-results" style="margin-top:16px; white-space:pre-wrap; background:#fff; padding:12px; border:1px solid #eee; display:none;"></div>

            <hr style="margin: 32px 0;">
            <h2>Repair Single Order</h2>
            <p>Force an order into <code>processing</code> and refresh caches to test dashboard visibility.</p>
            <div>
                <input type="number" id="mnt-repair-order-id" placeholder="Order ID" style="width:120px; margin-right:8px;">
                <button id="mnt-repair-order" class="button">Repair Order</button>
            </div>
            <div id="mnt-repair-results" style="margin-top:12px; white-space:pre-wrap; background:#fff; padding:12px; border:1px solid #eee; display:none;"></div>
        </div>
        <style>
        .mnt-admin-wrap h2 { margin-top: 2em; }
        .mnt-admin-wrap table th, .mnt-admin-wrap table td { text-align: left; }
        </style>
        <?php
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook) {
        if (strpos($hook, 'mnt-escrow') === false) {
            return;
        }

        wp_enqueue_style(
            'mnt-admin-style',
            plugins_url('assets/css/admin-style.css', dirname(dirname(__FILE__))),
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'mnt-admin-script',
            plugins_url('assets/js/admin-script.js', dirname(dirname(__FILE__))),
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('mnt-admin-script', 'mntAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mnt_admin_nonce')
        ]);
    }

    /**
     * Dashboard page
     */
    public static function dashboard_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        ?>
        <div class="wrap mnt-admin-wrap">
            <h1>MyNaijaTask Escrow Dashboard</h1>

            <div class="mnt-stats-grid">
                <div class="mnt-stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo self::get_total_users_with_wallets(); ?></div>
                        <div class="stat-label">Total Wallets</div>
                    </div>
                </div>

                <div class="mnt-stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-content">
                        <div class="stat-value">₦<?php echo number_format(self::get_total_escrow_value(), 2); ?></div>
                        <div class="stat-label">Total in Escrow</div>
                    </div>
                </div>

                <div class="mnt-stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo self::get_active_escrows_count(); ?></div>
                        <div class="stat-label">Active Escrows</div>
                    </div>
                </div>

                <div class="mnt-stat-card">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo self::get_disputed_escrows_count(); ?></div>
                        <div class="stat-label">Disputes</div>
                    </div>
                </div>
            </div>

            <div class="mnt-recent-activity">
                <h2>Recent Activity</h2>
                <p>View detailed reports in the Transactions and Disputes pages.</p>
            </div>
        </div>
        <?php
    }

    /**
     * Settings page
     */
    public static function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        if (isset($_POST['mnt_save_settings'])) {
            check_admin_referer('mnt_settings_nonce');
            
            update_option('mnt_api_base_url', sanitize_text_field($_POST['api_base_url'] ?? ''));
            update_option('mnt_paystack_public_key', sanitize_text_field($_POST['paystack_public_key'] ?? ''));
            update_option('mnt_paystack_secret_key', sanitize_text_field($_POST['paystack_secret_key'] ?? ''));
            update_option('mnt_auto_create_wallet', isset($_POST['auto_create_wallet']) ? '1' : '0');
            
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }

        $api_base_url = get_option('mnt_api_base_url', 'https://escrow-api-1vu6.onrender.com');
        $paystack_public = get_option('mnt_paystack_public_key', '');
        $paystack_secret = get_option('mnt_paystack_secret_key', '');
        $auto_create = get_option('mnt_auto_create_wallet', '1');
        ?>
        <div class="wrap mnt-admin-wrap">
            <h1>Escrow Settings</h1>

            <form method="post" action="">
                <?php wp_nonce_field('mnt_settings_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="api_base_url">API Base URL</label></th>
                        <td>
                            <input type="url" id="api_base_url" name="api_base_url" 
                                   value="<?php echo esc_attr($api_base_url); ?>" class="regular-text">
                            <p class="description">The base URL for your escrow API backend.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="paystack_public_key">Paystack Public Key</label></th>
                        <td>
                            <input type="text" id="paystack_public_key" name="paystack_public_key" 
                                   value="<?php echo esc_attr($paystack_public); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="paystack_secret_key">Paystack Secret Key</label></th>
                        <td>
                            <input type="password" id="paystack_secret_key" name="paystack_secret_key" 
                                   value="<?php echo esc_attr($paystack_secret); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="auto_create_wallet">Auto-create Wallets</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="auto_create_wallet" name="auto_create_wallet" 
                                       value="1" <?php checked($auto_create, '1'); ?>>
                                Automatically create wallets for new users
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="mnt_save_settings" class="button button-primary">
                        Save Settings
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Transactions page
     */
    public static function transactions_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 10;
        $offset = ($page - 1) * $per_page;

        // Get filters - NO defaults, empty means all-time
        $start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) 
            ? sanitize_text_field($_GET['start_date']) 
            : '';
        $end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) 
            ? sanitize_text_field($_GET['end_date']) 
            : '';
        $transaction_type = isset($_GET['tx_type']) ? sanitize_text_field($_GET['tx_type']) : '';
        $search_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

        // Fetch all transactions from API
        $transactions_result = Transaction::list_all(
            $transaction_type, 
            null,
            null,
            $start_date,
            $end_date,
            $search_user_id
        );

        // API returns array directly
        $all_transactions = is_array($transactions_result) ? $transactions_result : [];

        // Calculate pagination
        $total_count = count($all_transactions);
        $total_pages = ceil($total_count / $per_page);

        // Get current page transactions
        $transactions = array_slice($all_transactions, $offset, $per_page);
        ?>
        <div class="wrap mnt-admin-wrap">
            <h1>All Transactions</h1>

            <!-- Filters Section -->
            <div class="mnt-admin-filters">
                <form method="get" action="" class="mnt-admin-filter-form">
                    <input type="hidden" name="page" value="mnt-escrow-transactions">
                    
                    <div class="filter-row">
                        <table class="form-table" style="margin: 0;">
                            <tr>
                                <th scope="row" style="padding: 5px 10px;"><label for="user_id">User ID:</label></th>
                                <td style="padding: 5px 10px;">
                                    <input type="number" id="user_id" name="user_id" 
                                           value="<?php echo esc_attr($search_user_id); ?>" 
                                           placeholder="Filter by user ID" class="small-text">
                                </td>
                                
                                <th scope="row" style="padding: 5px 10px;"><label for="start_date">From:</label></th>
                                <td style="padding: 5px 10px;">
                                    <input type="date" id="start_date" name="start_date" 
                                           value="<?php echo esc_attr($start_date); ?>" placeholder="All time">
                                </td>
                                
                                <th scope="row" style="padding: 5px 10px;"><label for="end_date">To:</label></th>
                                <td style="padding: 5px 10px;">
                                    <input type="date" id="end_date" name="end_date" 
                                           value="<?php echo esc_attr($end_date); ?>" placeholder="All time">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row" style="padding: 5px 10px;"><label for="tx_type">Type:</label></th>
                                <td style="padding: 5px 10px;">
                                    <select id="tx_type" name="tx_type">
                                        <option value="">All Types</option>
                                        <option value="deposit" <?php selected($transaction_type, 'deposit'); ?>>Deposits</option>
                                        <option value="withdrawal" <?php selected($transaction_type, 'withdrawal'); ?>>Withdrawals</option>
                                        <option value="escrow_fund" <?php selected($transaction_type, 'escrow_fund'); ?>>Escrow Funded</option>
                                        <option value="escrow_release" <?php selected($transaction_type, 'escrow_release'); ?>>Escrow Released</option>
                                        <option value="refund" <?php selected($transaction_type, 'refund'); ?>>Refunds</option>
                                        <option value="credit" <?php selected($transaction_type, 'credit'); ?>>Credits</option>
                                    </select>
                                </td>
                                <td colspan="2" style="padding: 5px 10px;">
                                    <button type="submit" name="action" value="filter" class="button button-primary">Filter</button>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=mnt-escrow-transactions')); ?>" 
                                       class="button">Clear Filters</a>
                                </td>
                            </tr>
                        </table>
                    </div>
                </form>
            </div>

            <!-- Transaction Summary -->
            <div class="mnt-admin-summary">
                <p>
                    Showing <strong><?php echo number_format($total_count); ?></strong> transaction(s)
                    <?php if ($search_user_id): ?>
                        for User ID: <strong><?php echo $search_user_id; ?></strong>
                    <?php endif; ?>
                    <?php if ($start_date && $end_date): ?>
                        from <strong><?php echo date('M d, Y', strtotime($start_date)); ?></strong> 
                        to <strong><?php echo date('M d, Y', strtotime($end_date)); ?></strong>
                    <?php elseif ($start_date): ?>
                        from <strong><?php echo date('M d, Y', strtotime($start_date)); ?></strong> onwards
                    <?php elseif ($end_date): ?>
                        up to <strong><?php echo date('M d, Y', strtotime($end_date)); ?></strong>
                    <?php else: ?>
                        <strong>(All time)</strong>
                    <?php endif; ?>
                    <?php if (!empty($transaction_type)): ?>
                        - Type: <strong><?php echo esc_html(ucfirst($transaction_type)); ?></strong>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Transactions Table -->
            <?php if (empty($transactions)): ?>
                <div class="notice notice-info">
                    <p>No transactions found.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Transaction ID</th>
                            <th>Wallet ID</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $tx): 
                            // API returns: id, transaction_type, amount, status, reference_code, timestamp, wallet_id
                            $tx_type = strtolower($tx['transaction_type'] ?? 'unknown');
                            $amount = floatval($tx['amount'] ?? 0);
                            $status = strtolower($tx['status'] ?? 'pending');
                            $date = $tx['timestamp'] ?? '';
                            $reference = $tx['reference_code'] ?? '';
                            $tx_id = $tx['id'] ?? '';
                            $wallet_id = $tx['wallet_id'] ?? '';
                            
                            // Determine if credit or debit
                            $is_credit = in_array($tx_type, ['deposit', 'escrow_release', 'refund', 'credit']);
                            $amount_class = $is_credit ? 'credit' : 'debit';
                            $amount_prefix = $is_credit ? '+' : '-';
                            
                            // Type label
                            $type_label = ucwords(str_replace('_', ' ', $tx_type));
                            $status_label = ucfirst($status);
                        ?>
                            <tr>
                                <td>
                                    <?php echo esc_html(date('M d, Y h:i A', strtotime($date))); ?>
                                </td>
                                <td>
                                    <code><?php echo esc_html(substr($tx_id, 0, 8)); ?>...</code>
                                </td>
                                <td>
                                    <code style="font-size: 11px;"><?php echo esc_html(substr($wallet_id, 0, 8)); ?>...</code>
                                </td>
                                <td>
                                    <span class="mnt-type-badge mnt-type-<?php echo esc_attr($tx_type); ?>">
                                        <?php echo esc_html($type_label); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html($reference ?: '-'); ?>
                                </td>
                                <td>
                                    <strong class="mnt-amount-<?php echo esc_attr($amount_class); ?>">
                                        <?php echo $amount_prefix; ?>₦<?php echo number_format($amount, 2); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="mnt-status-badge mnt-status-<?php echo esc_attr($status); ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php echo number_format($total_count); ?> items
                        </span>
                        <?php
                        $base_url = remove_query_arg('paged');
                        $separator = strpos($base_url, '?') !== false ? '&' : '?';
                        
                        echo paginate_links([
                            'base' => $base_url . $separator . 'paged=%#%',
                            'format' => '',
                            'current' => $page,
                            'total' => $total_pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;'
                        ]);
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <style>
            .mnt-admin-filters {
                background: #fff;
                padding: 20px;
                margin: 20px 0;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            .mnt-admin-summary {
                background: #f0f0f1;
                padding: 15px;
                margin: 15px 0;
                border-left: 4px solid #2271b1;
            }
            .mnt-type-badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .mnt-type-deposit { background: #d4edda; color: #155724; }
            .mnt-type-withdrawal { background: #f8d7da; color: #721c24; }
            .mnt-type-escrow_fund { background: #fff3cd; color: #856404; }
            .mnt-type-escrow_release { background: #d1ecf1; color: #0c5460; }
            .mnt-type-escrow_refund { background: #e2e3e5; color: #383d41; }
            .mnt-type-transfer_sent { background: #fce4ec; color: #880e4f; }
            .mnt-type-transfer_received { background: #e1f5fe; color: #01579b; }
            
            .mnt-status-badge {
                display: inline-block;
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
            }
            .mnt-status-completed, .mnt-status-success { background: #46b450; color: #fff; }
            .mnt-status-pending { background: #ffb900; color: #fff; }
            .mnt-status-failed { background: #dc3232; color: #fff; }
            
            .mnt-amount-credit { color: #46b450; }
            .mnt-amount-debit { color: #dc3232; }
        </style>
        <?php
    }

    /**
     * Disputes page
     */
    public static function disputes_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        global $wpdb;
        
        // Get all tasks with disputed escrows
        $disputed_tasks = $wpdb->get_results(
            "SELECT post_id, meta_value as escrow_id 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = 'mnt_escrow_status' 
             AND meta_value = 'disputed'"
        );
        ?>
        <div class="wrap mnt-admin-wrap">
            <h1>Escrow Disputes</h1>

            <?php if (empty($disputed_tasks)): ?>
                <p>No disputes at the moment.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Task ID</th>
                            <th>Escrow ID</th>
                            <th>Buyer</th>
                            <th>Seller</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disputed_tasks as $task): ?>
                            <?php
                            $task_id = $task->post_id;
                            $escrow_id = get_post_meta($task_id, 'mnt_escrow_id', true);
                            $buyer_id = get_post_meta($task_id, 'mnt_buyer_id', true);
                            $seller_id = get_post_meta($task_id, 'mnt_seller_id', true);
                            $amount = get_post_meta($task_id, 'mnt_escrow_amount', true);
                            ?>
                            <tr>
                                <td><?php echo esc_html($task_id); ?></td>
                                <td><?php echo esc_html($escrow_id); ?></td>
                                <td><?php echo esc_html(get_userdata($buyer_id)->display_name ?? 'N/A'); ?></td>
                                <td><?php echo esc_html(get_userdata($seller_id)->display_name ?? 'N/A'); ?></td>
                                <td>₦<?php echo number_format($amount, 2); ?></td>
                                <td>
                                    <button class="button button-primary resolve-dispute" 
                                            data-escrow-id="<?php echo esc_attr($escrow_id); ?>" 
                                            data-decision="release">
                                        Release to Seller
                                    </button>
                                    <button class="button resolve-dispute" 
                                            data-escrow-id="<?php echo esc_attr($escrow_id); ?>" 
                                            data-decision="refund">
                                        Refund to Buyer
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Handle dispute resolution
     */
    public static function handle_resolve_dispute() {
        check_ajax_referer('mnt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $escrow_id = sanitize_text_field($_POST['escrow_id'] ?? '');
        $decision = sanitize_text_field($_POST['decision'] ?? '');
        $admin_id = get_current_user_id();

        $result = Escrow::resolve($escrow_id, $decision, $admin_id);

        if ($result && isset($result['success']) && $result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Find user by ID, email, or username
     */
    public static function handle_find_user() {
        check_ajax_referer('mnt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $search_term = sanitize_text_field($_POST['search_term'] ?? '');

        if (!$search_term) {
            wp_send_json_error(['message' => 'Search term required']);
        }

        // Try as user ID first
        if (is_numeric($search_term)) {
            $user = get_userdata(intval($search_term));
            if ($user) {
                wp_send_json_success(['user_id' => $user->ID]);
            }
        }

        // Try as email
        if (is_email($search_term)) {
            $user = get_user_by('email', $search_term);
            if ($user) {
                wp_send_json_success(['user_id' => $user->ID]);
            }
        }

        // Try as username
        $user = get_user_by('login', $search_term);
        if ($user) {
            wp_send_json_success(['user_id' => $user->ID]);
        }

        wp_send_json_error(['message' => 'User not found']);
    }

    /**
     * Handle freeze wallet
     */
    public static function handle_freeze_wallet() {
        check_ajax_referer('mnt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        $wallet_id = sanitize_text_field($_POST['wallet_id'] ?? '');
        $result = Wallet::freeze($wallet_id);
        wp_send_json($result);
    }

    /**
     * Handle unfreeze wallet
     */
    public static function handle_unfreeze_wallet() {
        check_ajax_referer('mnt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        $wallet_id = sanitize_text_field($_POST['wallet_id'] ?? '');
        $result = Wallet::unfreeze($wallet_id);
        wp_send_json($result);
    }

    /**
     * Handle get user transactions
     */
    public static function handle_get_user_transactions() {
        check_ajax_referer('mnt_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        $user_id = intval($_POST['user_id'] ?? 0);
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        $result = Transaction::list_by_user($user_id, '', null, null, $start_date, $end_date);
        wp_send_json_success(['transactions' => $result]);
    }

    /**
     * Handle wallet search
     */
    public static function handle_wallet_search() {
        check_ajax_referer('mnt_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $user_id = intval($_POST['user_id'] ?? 0);

        if (!$user_id) {
            wp_send_json_error(['message' => 'Invalid user ID']);
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(['message' => 'User not found']);
        }

        $result = Wallet::get_by_user($user_id);

        // The API returns the wallet object directly, not wrapped
        if ($result && (isset($result['id']) || isset($result['owner_id']))) {
            wp_send_json_success([
                'wallet' => $result,
                'user' => [
                    'id' => $user->ID,
                    'email' => $user->user_email,
                    'display_name' => $user->display_name
                ]
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Wallet not found for this user',
                'debug' => $result
            ]);
        }
    }

    /**
     * Wallets page - Search user wallets
     */
    public static function wallets_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Get all WordPress users
        $users = get_users(['number' => -1]); // -1 means all users
        
        $user_wallets = [];
        
        // For each user, check if they have a wallet
        foreach ($users as $user) {
            $has_wallet = get_user_meta($user->ID, 'mnt_wallet_created', true);
            
            if ($has_wallet) {
                $wallet_data = Wallet::get_by_user($user->ID);
                
                // Debug logging
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('User ID: ' . $user->ID . ' (' . $user->user_email . ') | Wallet Data: ' . print_r($wallet_data, true));
                }
                
                $user_wallets[] = [
                    'user' => $user,
                    'wallet' => $wallet_data
                ];
            }
        }
        
        ?>
        <div class="wrap mnt-admin-wrap">
            <h1><?php echo esc_html__('Wallet Management', 'mnt-escrow'); ?></h1>
            
            <!-- Debug Output -->
            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                <div class="notice notice-info">
                    <p><strong>Debug Info:</strong></p>
                    <pre><?php 
                        echo "Total WordPress Users: " . count($users) . "\n";
                        echo "Users with Wallets: " . count($user_wallets) . "\n";
                    ?></pre>
                </div>
            <?php endif; ?>
            
            <div class="mnt-admin-card">
                <div class="mnt-admin-card-body">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Name', 'mnt-escrow'); ?></th>
                                <th><?php echo esc_html__('Email', 'mnt-escrow'); ?></th>
                                <th><?php echo esc_html__('Balance', 'mnt-escrow'); ?></th>
                                <th><?php echo esc_html__('Status', 'mnt-escrow'); ?></th>
                                <th><?php echo esc_html__('Actions', 'mnt-escrow'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($user_wallets)): ?>
                                <?php foreach ($user_wallets as $item): 
                                    $user = $item['user'];
                                    $wallet = $item['wallet'];
                                    $wallet_id = isset($wallet['id']) ? $wallet['id'] : get_user_meta($user->ID, 'mnt_wallet_id', true);
                                    $balance = isset($wallet['balance']) ? $wallet['balance'] : 0;
                                    $currency = isset($wallet['currency']) ? $wallet['currency'] : 'NGN';
                                    $is_frozen = isset($wallet['is_frozen']) ? $wallet['is_frozen'] : false;
                                ?>
                                    <tr data-wallet-id="<?php echo esc_attr($wallet_id); ?>" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                        <td>
                                            <strong><?php echo esc_html($user->display_name); ?></strong>
                                            <br><small>ID: <?php echo esc_html($user->ID); ?></small>
                                        </td>
                                        <td><?php echo esc_html($user->user_email); ?></td>
                                        <td>
                                            <strong><?php echo esc_html($currency); ?> <?php echo esc_html(number_format($balance, 2)); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($is_frozen): ?>
                                                <span class="mnt-status-badge mnt-status-frozen"><?php echo esc_html__('Frozen', 'mnt-escrow'); ?></span>
                                            <?php else: ?>
                                                <span class="mnt-status-badge mnt-status-active"><?php echo esc_html__('Active', 'mnt-escrow'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="button mnt-view-transactions" data-user-id="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html__('View Transactions', 'mnt-escrow'); ?></button>
                                            <?php if ($wallet_id): ?>
                                                <?php if ($is_frozen): ?>
                                                    <button class="button mnt-unfreeze-wallet" data-wallet-id="<?php echo esc_attr($wallet_id); ?>"><?php echo esc_html__('Unfreeze', 'mnt-escrow'); ?></button>
                                                <?php else: ?>
                                                    <button class="button mnt-freeze-wallet" data-wallet-id="<?php echo esc_attr($wallet_id); ?>"><?php echo esc_html__('Freeze', 'mnt-escrow'); ?></button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;"><?php echo esc_html__('No users with wallets found', 'mnt-escrow'); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Transaction History Modal -->
        <div id="mnt-transaction-modal" class="mnt-modal" style="display:none;">
            <div class="mnt-modal-content">
                <span class="mnt-modal-close">&times;</span>
                <h2><?php echo esc_html__('Transaction History', 'mnt-escrow'); ?></h2>
                <div class="mnt-date-filters">
                    <label><?php echo esc_html__('From:', 'mnt-escrow'); ?> <input type="date" id="mnt-start-date" /></label>
                    <label><?php echo esc_html__('To:', 'mnt-escrow'); ?> <input type="date" id="mnt-end-date" /></label>
                    <button class="button" id="mnt-filter-transactions"><?php echo esc_html__('Filter', 'mnt-escrow'); ?></button>
                </div>
                <div id="mnt-transaction-content">
                    <p><?php echo esc_html__('Loading...', 'mnt-escrow'); ?></p>
                </div>
            </div>
        </div>

        <style>
            .mnt-status-badge {
                padding: 4px 12px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
            }
            .mnt-status-active {
                background: #d4edda;
                color: #155724;
            }
            .mnt-status-frozen {
                background: #f8d7da;
                color: #721c24;
            }
            .mnt-modal {
                position: fixed;
                z-index: 100000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0,0,0,0.4);
            }
            .mnt-modal-content {
                background-color: #fefefe;
                margin: 5% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 900px;
                border-radius: 8px;
            }
            .mnt-modal-close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            .mnt-modal-close:hover {
                color: black;
            }
            .mnt-date-filters {
                margin: 20px 0;
                display: flex;
                gap: 15px;
                align-items: center;
            }
            .mnt-date-filters label {
                display: flex;
                gap: 8px;
                align-items: center;
            }
            #mnt-transaction-content table {
                width: 100%;
                margin-top: 15px;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            var currentUserId = null;

            // View Transactions
            $('.mnt-view-transactions').on('click', function() {
                currentUserId = $(this).data('user-id');
                $('#mnt-transaction-modal').show();
                loadTransactions(currentUserId);
            });

            // Close Modal
            $('.mnt-modal-close, .mnt-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#mnt-transaction-modal').hide();
                }
            });

            // Filter Transactions
            $('#mnt-filter-transactions').on('click', function() {
                if (currentUserId) {
                    loadTransactions(currentUserId, $('#mnt-start-date').val(), $('#mnt-end-date').val());
                }
            });

            // Load Transactions
            function loadTransactions(userId, startDate = '', endDate = '') {
                $('#mnt-transaction-content').html('<p><?php echo esc_js(__('Loading...', 'mnt-escrow')); ?></p>');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mnt_admin_get_user_transactions',
                        nonce: '<?php echo wp_create_nonce('mnt_admin_nonce'); ?>',
                        user_id: userId,
                        start_date: startDate,
                        end_date: endDate
                    },
                    success: function(response) {
                        if (response.success && response.data.transactions) {
                            displayTransactions(response.data.transactions);
                        } else {
                            $('#mnt-transaction-content').html('<p><?php echo esc_js(__('No transactions found', 'mnt-escrow')); ?></p>');
                        }
                    },
                    error: function() {
                        $('#mnt-transaction-content').html('<p><?php echo esc_js(__('Error loading transactions', 'mnt-escrow')); ?></p>');
                    }
                });
            }

            // Display Transactions
            function displayTransactions(transactions) {
                if (!transactions || transactions.length === 0) {
                    $('#mnt-transaction-content').html('<p><?php echo esc_js(__('No transactions found', 'mnt-escrow')); ?></p>');
                    return;
                }
                
                var html = '<table class="wp-list-table widefat fixed striped"><thead><tr>';
                html += '<th><?php echo esc_js(__('Date', 'mnt-escrow')); ?></th>';
                html += '<th><?php echo esc_js(__('Type', 'mnt-escrow')); ?></th>';
                html += '<th><?php echo esc_js(__('Amount', 'mnt-escrow')); ?></th>';
                html += '<th><?php echo esc_js(__('Status', 'mnt-escrow')); ?></th>';
                html += '<th><?php echo esc_js(__('Description', 'mnt-escrow')); ?></th>';
                html += '</tr></thead><tbody>';
                
                transactions.forEach(function(tx) {
                    html += '<tr>';
                    html += '<td>' + (tx.created_at || '—') + '</td>';
                    html += '<td>' + (tx.type || '—') + '</td>';
                    html += '<td>' + (tx.currency || 'USD') + ' ' + parseFloat(tx.amount || 0).toFixed(2) + '</td>';
                    html += '<td>' + (tx.status || '—') + '</td>';
                    html += '<td>' + (tx.description || '—') + '</td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                $('#mnt-transaction-content').html(html);
            }

            // Freeze Wallet
            $('.mnt-freeze-wallet').on('click', function() {
                var walletId = $(this).data('wallet-id');
                
                if (!confirm('<?php echo esc_js(__('Are you sure you want to freeze this wallet?', 'mnt-escrow')); ?>')) {
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mnt_admin_freeze_wallet',
                        nonce: '<?php echo wp_create_nonce('mnt_admin_nonce'); ?>',
                        wallet_id: walletId
                    },
                    success: function(response) {
                        if (response.success || !response.error) {
                            location.reload();
                        } else {
                            alert(response.message || '<?php echo esc_js(__('Failed to freeze wallet', 'mnt-escrow')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Error freezing wallet', 'mnt-escrow')); ?>');
                    }
                });
            });

            // Unfreeze Wallet
            $('.mnt-unfreeze-wallet').on('click', function() {
                var walletId = $(this).data('wallet-id');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mnt_admin_unfreeze_wallet',
                        nonce: '<?php echo wp_create_nonce('mnt_admin_nonce'); ?>',
                        wallet_id: walletId
                    },
                    success: function(response) {
                        if (response.success || !response.error) {
                            location.reload();
                        } else {
                            alert(response.message || '<?php echo esc_js(__('Failed to unfreeze wallet', 'mnt-escrow')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('Error unfreezing wallet', 'mnt-escrow')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Get WordPress user by owner_id from user meta
     */
    private static function get_user_by_owner_id($owner_id) {
        // First, try to find by mnt_wallet_uuid (the backend's UUID)
        $users = get_users([
            'meta_key' => 'mnt_wallet_uuid',
            'meta_value' => $owner_id,
            'number' => 1
        ]);
        
        if (!empty($users)) {
            return $users[0];
        }
        
        // If not found, the owner_id might be the WordPress user ID itself
        // (if the API stores user_id as owner_id)
        if (is_numeric($owner_id)) {
            $user = get_userdata(intval($owner_id));
            if ($user) {
                return $user;
            }
        }
        
        // Last resort: check if owner_id matches mnt_wallet_id
        $users = get_users([
            'meta_key' => 'mnt_wallet_id',
            'meta_value' => $owner_id,
            'number' => 1
        ]);
        
        return !empty($users) ? $users[0] : null;
    }

    // Helper methods for stats
    private static function get_total_users_with_wallets() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'mnt_wallet_created' AND meta_value = '1'"
        );
    }

    private static function get_total_escrow_value() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT SUM(CAST(meta_value AS DECIMAL(10,2))) 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = 'mnt_escrow_amount' 
             AND post_id IN (
                 SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE meta_key = 'mnt_escrow_status' 
                 AND meta_value IN ('pending', 'delivered')
             )"
        ) ?: 0;
    }

    private static function get_active_escrows_count() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = 'mnt_escrow_status' 
             AND meta_value IN ('pending', 'delivered')"
        );
    }

    private static function get_disputed_escrows_count() {
        global $wpdb;
        return $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = 'mnt_escrow_status' 
             AND meta_value = 'disputed'"
        );
    }
}
