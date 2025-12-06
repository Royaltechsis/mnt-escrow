<?php
if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$page = isset($_GET['tx_page']) ? max(1, intval($_GET['tx_page'])) : 1;
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
$search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Fetch wallet transactions from API
$transactions_result = \MNT\Api\Transaction::list_by_user(
    $user_id, 
    $transaction_type, 
    null, // Don't limit API, we'll paginate after
    null,
    $start_date,
    $end_date
);

// API returns array directly, not wrapped in 'transactions' key
// Handle case where API returns error string or non-array response
$wallet_transactions = [];
if (is_array($transactions_result)) {
    // Check if it's a list of transactions or a wrapped response
    if (isset($transactions_result['transactions'])) {
        $wallet_transactions = $transactions_result['transactions'];
    } elseif (isset($transactions_result[0]) && is_array($transactions_result[0])) {
        // Direct array of transactions
        $wallet_transactions = $transactions_result;
    } elseif (isset($transactions_result['message'])) {
        // API returned an error message in array format
        $wallet_transactions = [];
    } else {
        // Unknown format, treat as empty
        $wallet_transactions = [];
    }
}

// Add source marker to wallet transactions
if (!empty($wallet_transactions)) {
    foreach ($wallet_transactions as &$tx) {
        if (is_array($tx)) {
            $tx['source'] = 'Wallet';
        }
    }
    unset($tx);
}

// Fetch escrow transactions from API
$escrow_transactions = [];
if (class_exists('MNT\Api\Escrow')) {
    // Determine actor based on user type
    $user_type = apply_filters('taskbot_get_user_type', $user_id);
    $actor = ($user_type === 'sellers') ? 'merchant' : 'client';
    
    error_log('MNT Transaction History - User ID: ' . $user_id . ', User Type: ' . $user_type . ', Actor: ' . $actor);
    
    $escrow_result = \MNT\Api\Escrow::get_all_transactions($user_id, $actor);
    if (is_array($escrow_result)) {
        foreach ($escrow_result as $escrow_tx) {
            // Get project title if project_id exists
            $project_id = !empty($escrow_tx['project_id']) ? intval($escrow_tx['project_id']) : null;
            $project_title = 'Unknown Project';
            if ($project_id) {
                $project = get_post($project_id);
                if ($project) {
                    $project_title = $project->post_title;
                }
            }
            
            // Check if this escrow has milestones
            if (!empty($escrow_tx['milestones']) && is_array($escrow_tx['milestones'])) {
                // Process each milestone as a separate transaction
                foreach ($escrow_tx['milestones'] as $milestone) {
                    $milestone_key = $milestone['key'] ?? '';
                    $milestone_id = !empty($milestone_key) ? $milestone_key . ' (mk)' : 'N/A';
                    $milestone_finished = $milestone['finished'] ?? false;
                    
                    $escrow_transactions[] = [
                        'id' => $milestone_id,
                        'type' => 'escrow_milestone',
                        'amount' => $milestone['amount'] ?? 0,
                        'status' => $milestone_finished ? 'completed' : 'funded',
                        'reference_code' => 'Milestone: ' . ($milestone['name'] ?? 'Unnamed') . ' - ' . $project_title,
                        'timestamp' => $escrow_tx['created_at'] ?? date('Y-m-d H:i:s'),
                        'source' => 'Escrow',
                        'finalized_at' => $milestone_finished ? ($escrow_tx['finalized_at'] ?? null) : null,
                        'project_id' => $project_id,
                        'milestone_key' => $milestone_key,
                        'milestone_name' => $milestone['name'] ?? 'Unnamed',
                        'milestone_description' => $milestone['description'] ?? ''
                    ];
                }
            } else {
                // Regular escrow transaction without milestones
                $escrow_id = $escrow_tx['project_id'] ?? ($escrow_tx['id'] ?? '');
                $transaction_id = !empty($escrow_id) ? $escrow_id . ' (pd)' : 'N/A';
                
                $escrow_transactions[] = [
                    'id' => $transaction_id,
                    'type' => 'escrow_transaction',
                    'amount' => $escrow_tx['amount'] ?? 0,
                    'status' => strtolower($escrow_tx['status'] ?? 'pending'),
                    'reference_code' => 'Project: ' . $project_title,
                    'timestamp' => $escrow_tx['created_at'] ?? date('Y-m-d H:i:s'),
                    'source' => 'Escrow',
                    'finalized_at' => $escrow_tx['finalized_at'] ?? null,
                    'project_id' => $project_id
                ];
            }
        }
    }
}

// Merge wallet and escrow transactions
$all_transactions = array_merge($wallet_transactions, $escrow_transactions);

// Sort by timestamp descending (newest first)
usort($all_transactions, function($a, $b) {
    return strtotime($b['timestamp'] ?? '') - strtotime($a['timestamp'] ?? '');
});

// Apply search filter if provided
if (!empty($search_query)) {
    $all_transactions = array_filter($all_transactions, function($tx) use ($search_query) {
        $search_lower = strtolower($search_query);
        $id = strtolower($tx['id'] ?? '');
        $reference = strtolower($tx['reference_code'] ?? '');
        $type = strtolower($tx['type'] ?? '');
        $status = strtolower($tx['status'] ?? '');
        
        return strpos($id, $search_lower) !== false ||
               strpos($reference, $search_lower) !== false ||
               strpos($type, $search_lower) !== false ||
               strpos($status, $search_lower) !== false;
    });
}

// Calculate pagination
$total_count = count($all_transactions);
$total_pages = ceil($total_count / $per_page);

// Get current page transactions
$transactions = array_slice($all_transactions, $offset, $per_page);

// Get wallet balance for context
$wallet_result = \MNT\Api\wallet::balance($user_id);
$balance = $wallet_result['balance'] ?? 0;
?>

<div class="mnt-transaction-history">
    <div class="mnt-transaction-header">
        <h2>Transaction History</h2>
        <div class="mnt-current-balance">
            <span class="balance-label">Current Balance:</span>
            <span class="balance-amount">₦<?php echo number_format($balance, 2); ?></span>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="mnt-transaction-filters">
        <form method="get" action="" class="mnt-filter-form" id="filter-form">
            <?php
            // Preserve other query parameters
            foreach ($_GET as $key => $value) {
                if (!in_array($key, ['start_date', 'end_date', 'tx_type', 'tx_page', 'search'])) {
                    echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                }
            }
            ?>
            <div class="filter-row">
                <div class="filter-group">
                    <label for="start_date">From:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>" placeholder="All time">
                </div>

                <div class="filter-group">
                    <label for="end_date">To:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>" placeholder="All time">
                </div>

                <div class="filter-group">
                    <label for="tx_type">Type:</label>
                    <select id="tx_type" name="tx_type">
                        <option value="">All Types</option>
                        <option value="deposit" <?php selected($transaction_type, 'deposit'); ?>>Deposits</option>
                        <option value="withdrawal" <?php selected($transaction_type, 'withdrawal'); ?>>Withdrawals</option>
                        <option value="escrow_transaction" <?php selected($transaction_type, 'escrow_transaction'); ?>>Escrow Transaction</option>
                        <option value="escrow_milestone" <?php selected($transaction_type, 'escrow_milestone'); ?>>Escrow Milestone</option>
                        <option value="escrow_fund" <?php selected($transaction_type, 'escrow_fund'); ?>>Escrow Funded</option>
                        <option value="escrow_release" <?php selected($transaction_type, 'escrow_release'); ?>>Escrow Released</option>
                        <option value="escrow_refund" <?php selected($transaction_type, 'escrow_refund'); ?>>Escrow Refunded</option>
                        <option value="transfer_sent" <?php selected($transaction_type, 'transfer_sent'); ?>>Transfers Sent</option>
                        <option value="transfer_received" <?php selected($transaction_type, 'transfer_received'); ?>>Transfers Received</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" name="action" value="filter" class="mnt-btn mnt-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                        </svg>
                        Filter
                    </button>
                    <a href="<?php echo esc_url(remove_query_arg(['start_date', 'end_date', 'tx_type', 'tx_page', 'search'])); ?>" 
                       class="mnt-btn mnt-btn-secondary">Clear All</a>
                </div>
            </div>

            <div class="search-row">
                <div class="search-group">
                    <label for="search">Search:</label>
                    <input type="text" id="search" name="search" value="<?php echo esc_attr($search_query); ?>" 
                           placeholder="Search by ID, reference, type, or status...">
                </div>
                <div class="search-actions">
                    <button type="submit" name="action" value="search" class="mnt-btn mnt-btn-search">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="m21 21-4.35-4.35"></path>
                        </svg>
                        Search
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Transaction Summary -->
    <div class="mnt-transaction-summary">
        <p>
            Showing <strong><?php echo number_format($total_count); ?></strong> transaction(s)
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
            <?php if (!empty($search_query)): ?>
                - Search: <strong>"<?php echo esc_html($search_query); ?>"</strong>
            <?php endif; ?>
        </p>
    </div>

    <!-- Transactions Table -->
    <?php if (empty($transactions)): ?>
        <div class="mnt-no-transactions">
            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
            <p>No transactions found</p>
            <?php if ($start_date || $end_date || $transaction_type): ?>
                <a href="<?php echo esc_url(remove_query_arg(['start_date', 'end_date', 'tx_type', 'tx_page'])); ?>" 
                   class="mnt-btn mnt-btn-secondary">View All Transactions</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="mnt-transaction-table-wrapper">
                <table class="mnt-transaction-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Transaction ID</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Amount</th>
                        <th>Source</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx): ?>
                        <?php
                        // API returns: id, type, amount, status, reference_code, timestamp, source
                        $tx_type = strtolower($tx['type'] ?? 'unknown');
                        $amount = floatval($tx['amount'] ?? 0);
                        $status = strtolower($tx['status'] ?? 'pending');
                        $date = $tx['timestamp'] ?? '';
                        $reference = $tx['reference_code'] ?? '';
                        $tx_id = $tx['id'] ?? '';
                        $source = $tx['source'] ?? 'Wallet';
                        
                        // Determine if credit or debit based on transaction type
                        $is_credit = in_array($tx_type, ['deposit', 'escrow_release', 'escrow_refund', 'transfer_received', 'credit']);
                        $amount_class = $is_credit ? 'credit' : 'debit';
                        $amount_prefix = $is_credit ? '+' : '-';
                        
                        // Type labels
                        $type_label = ucwords(str_replace('_', ' ', $tx_type));
                        
                        // Status labels
                        $status_label = ucfirst($status);
                        
                        // Format transaction ID display
                        // For escrow transactions, show full ID with (pd) or (mk) suffix
                        // For wallet transactions, show shortened ID
                        $display_id = $tx_id;
                        if ($tx_type === 'escrow_transaction' || $tx_type === 'escrow_milestone') {
                            // Show full ID for escrow (already includes (pd) or (mk))
                            $display_id = $tx_id;
                        } else {
                            // Shorten wallet transaction IDs
                            if (strlen($tx_id) > 12) {
                                $display_id = substr($tx_id, 0, 8) . '...';
                            }
                        }
                        ?>
                        <tr class="tx-row tx-<?php echo esc_attr($tx_type); ?>">
                            <td class="tx-date">
                                <?php echo esc_html(date('M d, Y', strtotime($date))); ?>
                                <span class="tx-time"><?php echo esc_html(date('h:i A', strtotime($date))); ?></span>
                            </td>
                            <td class="tx-id">
                                <code><?php echo esc_html($display_id); ?></code>
                            </td>
                            <td class="tx-type">
                                <span class="type-badge type-<?php echo esc_attr($tx_type); ?>">
                                    <?php echo esc_html($type_label); ?>
                                </span>
                            </td>
                            <td class="tx-description">
                                <?php echo esc_html($reference ?: 'Transaction'); ?>
                            </td>
                            <td class="tx-amount <?php echo esc_attr($amount_class); ?>">
                                <strong><?php echo $amount_prefix; ?>₦<?php echo number_format($amount, 2); ?></strong>
                            </td>
                            <td class="tx-source">
                                <span class="source-badge source-<?php echo esc_attr(strtolower($source)); ?>">
                                    <?php echo esc_html($source); ?>
                                </span>
                            </td>
                            <td class="tx-status">
                                <span class="status-badge status-<?php echo esc_attr($status); ?>">
                                    <?php echo esc_html($status_label); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mnt-pagination">
            <div class="pagination-info">
                Showing <?php echo (($page - 1) * $per_page) + 1; ?> to 
                <?php echo min($page * $per_page, $total_count); ?> of 
                <?php echo number_format($total_count); ?> transactions
            </div>
            
            <div class="pagination-links">
                <?php
                // Build pagination URL
                $base_url = remove_query_arg('tx_page');
                $separator = strpos($base_url, '?') !== false ? '&' : '?';
                
                if ($page > 1): ?>
                    <a href="<?php echo esc_url($base_url . $separator . 'tx_page=1'); ?>" 
                       class="page-link first-page" title="First Page">&laquo;</a>
                    <a href="<?php echo esc_url($base_url . $separator . 'tx_page=' . ($page - 1)); ?>" 
                       class="page-link prev-page" title="Previous Page">&lsaquo;</a>
                <?php endif; ?>

                <?php
                // Show page numbers
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="<?php echo esc_url($base_url . $separator . 'tx_page=' . $i); ?>" 
                       class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo esc_url($base_url . $separator . 'tx_page=' . ($page + 1)); ?>" 
                       class="page-link next-page" title="Next Page">&rsaquo;</a>
                    <a href="<?php echo esc_url($base_url . $separator . 'tx_page=' . $total_pages); ?>" 
                       class="page-link last-page" title="Last Page">&raquo;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Export Button -->
    <?php if (!empty($transactions)): ?>
    <div class="mnt-export-actions">
        <button type="button" class="mnt-btn mnt-btn-outline" id="export-transactions">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
            </svg>
            Export to CSV
        </button>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#export-transactions').on('click', function() {
            var csv = 'Date,Time,Transaction ID,Type,Reference,Amount,Status\n';
            
            <?php foreach ($transactions as $tx): 
                $date = $tx['timestamp'] ?? '';
                $tx_type = strtolower($tx['type'] ?? 'unknown');
                $amount = floatval($tx['amount'] ?? 0);
                $is_credit = in_array($tx_type, ['deposit', 'escrow_release', 'escrow_refund', 'transfer_received', 'credit']);
                $amount_prefix = $is_credit ? '+' : '-';
                $type_label = ucwords(str_replace('_', ' ', $tx_type));
                $date_formatted = date("Y-m-d", strtotime($date));
                $time_formatted = date("H:i:s", strtotime($date));
                $tx_id = addslashes($tx['id'] ?? 'N/A');
                $reference = addslashes($tx['reference_code'] ?? '');
                $status = ucfirst($tx['status'] ?? 'pending');
            ?>
                csv += '<?php echo esc_attr($date_formatted); ?>,';
                csv += '<?php echo esc_attr($time_formatted); ?>,';
                csv += '"<?php echo esc_attr($tx_id); ?>",';
                csv += '"<?php echo esc_attr($type_label); ?>",';
                csv += '"<?php echo esc_attr($reference); ?>",';
                csv += '<?php echo esc_attr($amount_prefix . number_format($amount, 2)); ?>,';
                csv += '"<?php echo esc_attr($status); ?>"\n';
            <?php endforeach; ?>
            
            var blob = new Blob([csv], { type: 'text/csv' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'transactions_<?php echo date("Y-m-d"); ?>.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        });
    });
    </script>
    <?php endif; ?>
</div>
