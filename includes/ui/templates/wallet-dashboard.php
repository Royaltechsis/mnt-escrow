<?php
if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$wallet_result = \MNT\Api\Wallet::balance($user_id);
$balance = $wallet_result['balance'] ?? 0;
$wallet_id = $wallet_result['wallet_id'] ?? '';

$transactions_result = \MNT\Api\Wallet::transactions($user_id, 10);
$transactions = $transactions_result['transactions'] ?? [];
?>

<div class="mnt-wallet-dashboard">
    <div class="mnt-wallet-header">
        <h2>My Wallet</h2>
        <div class="wallet-id">Wallet ID: <?php echo esc_html($wallet_id); ?></div>
    </div>

    <div class="mnt-wallet-balance-card">
        <div class="balance-label">Available Balance</div>
        <div class="balance-amount">₦<?php echo number_format($balance, 2); ?></div>
        <div class="balance-actions">
            <a href="#deposit" class="mnt-btn mnt-btn-primary" id="show-deposit">Deposit</a>
            <a href="#withdraw" class="mnt-btn mnt-btn-secondary" id="show-withdraw">Withdraw</a>
            <a href="#transfer" class="mnt-btn mnt-btn-secondary" id="show-transfer">Transfer</a>
        </div>
    </div>

    <div class="mnt-wallet-actions" style="display:none;">
        <div id="deposit-section" class="action-section">
            <?php echo do_shortcode('[mnt_deposit_form]'); ?>
        </div>
        <div id="withdraw-section" class="action-section">
            <?php echo do_shortcode('[mnt_withdraw_form]'); ?>
        </div>
        <div id="transfer-section" class="action-section">
            <?php echo do_shortcode('[mnt_transfer_form]'); ?>
        </div>
    </div>

    <div class="mnt-recent-transactions">
        <h3>Recent Transactions</h3>
        <?php if (empty($transactions)): ?>
            <p class="no-transactions">No transactions yet.</p>
        <?php else: ?>
            <div class="transactions-list">
                <?php foreach ($transactions as $tx): ?>
                    <div class="transaction-item">
                        <div class="tx-icon">
                            <?php if ($tx['type'] === 'credit'): ?>
                                <span class="icon-credit">↓</span>
                            <?php else: ?>
                                <span class="icon-debit">↑</span>
                            <?php endif; ?>
                        </div>
                        <div class="tx-details">
                            <div class="tx-description"><?php echo esc_html($tx['description'] ?? 'Transaction'); ?></div>
                            <div class="tx-date"><?php echo esc_html(date('M d, Y h:i A', strtotime($tx['date'] ?? 'now'))); ?></div>
                        </div>
                        <div class="tx-amount <?php echo $tx['type'] === 'credit' ? 'credit' : 'debit'; ?>">
                            <?php echo $tx['type'] === 'credit' ? '+' : '-'; ?>₦<?php echo number_format($tx['amount'] ?? 0, 2); ?>
                        </div>
                        <div class="tx-status status-<?php echo esc_attr($tx['status'] ?? 'pending'); ?>">
                            <?php echo esc_html(ucfirst($tx['status'] ?? 'pending')); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="#" class="view-all-transactions">View All Transactions</a>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#show-deposit').on('click', function(e) {
        e.preventDefault();
        $('.action-section').hide();
        $('#deposit-section').show();
        $('.mnt-wallet-actions').show();
    });

    $('#show-withdraw').on('click', function(e) {
        e.preventDefault();
        $('.action-section').hide();
        $('#withdraw-section').show();
        $('.mnt-wallet-actions').show();
    });

    $('#show-transfer').on('click', function(e) {
        e.preventDefault();
        $('.action-section').hide();
        $('#transfer-section').show();
        $('.mnt-wallet-actions').show();
    });
});
</script>
