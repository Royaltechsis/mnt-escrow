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
        ?>
        <div class="wrap mnt-admin-wrap">
            <h1>All Transactions</h1>
            <p>Transaction log functionality - integrate with API to fetch all system transactions.</p>
        </div>
        <?php
    }

    /**
     * Disputes page
     */
    public static function disputes_page() {
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
        ?>
        <div class="wrap mnt-admin-wrap">
            <h1>Users & Wallets</h1>

            <div class="mnt-wallet-search-section">
                <h2>Search User Wallet</h2>
                
                <div class="mnt-search-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="user_search">Search User</label></th>
                            <td>
                                <input type="text" id="user_search" class="regular-text" 
                                       placeholder="Enter user ID, email, or username">
                                <button type="button" id="search_wallet_btn" class="button button-primary">
                                    Search Wallet
                                </button>
                                <p class="description">Enter WordPress user ID, email address, or username to find their wallet.</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="wallet_result" style="display: none; margin-top: 30px;">
                    <h3>Wallet Information</h3>
                    <div class="mnt-wallet-card">
                        <table class="widefat striped">
                            <tbody id="wallet_details">
                                <!-- Populated via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="wallet_error" class="notice notice-error" style="display: none; margin-top: 20px;">
                    <p id="error_message"></p>
                </div>
            </div>

            <style>
                .mnt-wallet-card {
                    background: #fff;
                    border: 1px solid #ccc;
                    padding: 20px;
                    border-radius: 4px;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                }
                .mnt-wallet-card table th {
                    width: 200px;
                    font-weight: 600;
                }
                .mnt-wallet-search-section {
                    background: #fff;
                    padding: 20px;
                    margin-top: 20px;
                    border: 1px solid #ccc;
                    border-radius: 4px;
                }
                .wallet-status-active {
                    color: #46b450;
                    font-weight: 600;
                }
                .wallet-status-inactive {
                    color: #dc3232;
                    font-weight: 600;
                }
            </style>

            <script>
            jQuery(document).ready(function($) {
                $('#search_wallet_btn').on('click', function() {
                    var searchTerm = $('#user_search').val().trim();
                    if (!searchTerm) {
                        alert('Please enter a user ID, email, or username');
                        return;
                    }

                    var $btn = $(this);
                    var originalText = $btn.text();
                    $btn.text('Searching...').prop('disabled', true);
                    $('#wallet_result').hide();
                    $('#wallet_error').hide();

                    // First, find the user
                    $.post(ajaxurl, {
                        action: 'mnt_find_user',
                        nonce: mntAdmin.nonce,
                        search_term: searchTerm
                    }, function(response) {
                        if (!response.success) {
                            $('#error_message').text(response.data.message || 'User not found');
                            $('#wallet_error').show();
                            $btn.text(originalText).prop('disabled', false);
                            return;
                        }

                        var userId = response.data.user_id;

                        // Now search wallet
                        $.post(ajaxurl, {
                            action: 'mnt_admin_search_wallet',
                            nonce: mntAdmin.nonce,
                            user_id: userId
                        }, function(walletResponse) {
                            $btn.text(originalText).prop('disabled', false);

                            if (walletResponse.success) {
                                var wallet = walletResponse.data.wallet;
                                var user = walletResponse.data.user;
                                
                                var html = '<tr><th>WordPress User ID:</th><td>' + user.id + '</td></tr>';
                                html += '<tr><th>Display Name:</th><td>' + user.display_name + '</td></tr>';
                                html += '<tr><th>Email:</th><td>' + user.email + '</td></tr>';
                                html += '<tr><th>Wallet ID:</th><td><code>' + (wallet.id || 'N/A') + '</code></td></tr>';
                                html += '<tr><th>Owner ID:</th><td><code>' + (wallet.owner_id || 'N/A') + '</code></td></tr>';
                                html += '<tr><th>Currency:</th><td>' + (wallet.currency || 'NGN') + '</td></tr>';
                                html += '<tr><th>Balance:</th><td><strong>₦' + parseFloat(wallet.balance || 0).toLocaleString('en-NG', {minimumFractionDigits: 2}) + '</strong></td></tr>';
                                html += '<tr><th>Frozen Status:</th><td>' + (wallet.is_frozen ? '<span class="wallet-status-inactive">🔒 Frozen</span>' : '<span class="wallet-status-active">✓ Active</span>') + '</td></tr>';
                                html += '<tr><th>Created At:</th><td>' + (wallet.created_at || 'N/A') + '</td></tr>';
                                html += '<tr><th>Updated At:</th><td>' + (wallet.updated_at || 'N/A') + '</td></tr>';
                                
                                $('#wallet_details').html(html);
                                $('#wallet_result').show();
                            } else {
                                $('#error_message').text(walletResponse.data.message || 'Failed to retrieve wallet');
                                $('#wallet_error').show();
                            }
                        });
                    });
                });

                // Allow search on Enter key
                $('#user_search').on('keypress', function(e) {
                    if (e.which === 13) {
                        $('#search_wallet_btn').click();
                    }
                });
            });
            </script>
        </div>
        <?php
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
