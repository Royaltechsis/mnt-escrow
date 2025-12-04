<?php
if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$wallet_result = \MNT\Api\wallet::balance($user_id);
// API returns: {"user_id": "217", "balance": 500.0, "currency": "NGN"}
$balance = isset($wallet_result['balance']) ? floatval($wallet_result['balance']) : 0;
$wallet_id = $wallet_result['wallet_id'] ?? $wallet_result['user_id'] ?? '';

// Don't fetch old transactions - they're shown in transaction history page
// $transactions_result = \MNT\Api\wallet::transactions($user_id, 10);
// $transactions = $transactions_result['transactions'] ?? [];
?>

<div class="tk-project-wrapper">
    <div class="tk-project-box">
        <div class="tk-counterinfo tk-wallet-balance">
            <div class="tk-counterinfo_head">
                <h5><?php esc_html_e('Wallet Balance', 'taskbot'); ?></h5>
                <span class="tk-wallet-id"><?php esc_html_e('Wallet ID:', 'taskbot'); ?> <?php echo esc_html($wallet_id); ?></span>
            </div>
            <div class="tk-wallet-amount">
                <strong class="tk-amount-value">₦<?php echo number_format($balance, 2); ?></strong>
                <span class="tk-amount-label"><?php esc_html_e('Available Balance', 'taskbot'); ?></span>
            </div>
            <ul class="tk-wallet-actions">
                <li>
                    <a href="javascript:void(0);" class="tk-btn-border" id="show-deposit">
                        <i class="tb-icon-plus-circle"></i>
                        <?php esc_html_e('Deposit', 'taskbot'); ?>
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0);" class="tk-btn-border" id="show-withdraw">
                        <i class="tb-icon-download"></i>
                        <?php esc_html_e('Withdraw', 'taskbot'); ?>
                    </a>
                </li>
               <!--  <li>
                    <a href="javascript:void(0);" class="tk-btn-border" id="show-transfer">
                        <i class="tb-icon-arrow-right-circle"></i>
                        <?php esc_html_e('Transfer', 'taskbot'); ?>
                    </a>
                </li> -->
            </ul>
        </div>
    </div>
</div>

<div class="tk-project-wrapper" id="wallet-action-section" style="display:none;">
    <div class="tk-project-box">
        <div id="deposit-section" class="wallet-action-content">
            <?php echo do_shortcode('[mnt_deposit_form]'); ?>
        </div>
        <div id="withdraw-section" class="wallet-action-content">
            <?php echo do_shortcode('[mnt_withdraw_form]'); ?>
        </div>
        <div id="transfer-section" class="wallet-action-content">
            <?php echo do_shortcode('[mnt_transfer_form]'); ?>
        </div>
    </div>
</div>

   <!--  <div class="mnt-recent-transactions">
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
    </div> -->
</div>

<style>
.tk-wallet-balance {
    text-align: center;
    padding: 30px 20px;
}
.tk-counterinfo_head {
    margin-bottom: 20px;
}
.tk-counterinfo_head h5 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #1e293b;
}
.tk-wallet-id {
    font-size: 13px;
    color: #64748b;
}
.tk-wallet-amount {
    margin: 25px 0;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.tk-amount-value {
    font-size: 42px;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}
.tk-amount-label {
    font-size: 14px;
    color: #64748b;
    margin-top: 8px;
}
.tk-wallet-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    list-style: none;
    padding: 0;
    margin: 25px 0 0 0;
    flex-wrap: wrap;
}
.tk-wallet-actions li {
    margin: 0;
}
.tk-wallet-actions .tk-btn-border {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
}
.tk-wallet-actions .tk-btn-border i {
    font-size: 16px;
}
.wallet-action-content {
    display: none;
}
.wallet-action-content.active {
    display: block;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('#show-deposit').on('click', function(e) {
        e.preventDefault();
        $('.wallet-action-content').hide().removeClass('active');
        $('#deposit-section').show().addClass('active');
        $('#wallet-action-section').slideDown();
        $('html, body').animate({
            scrollTop: $('#wallet-action-section').offset().top - 100
        }, 500);
    });

    $('#show-withdraw').on('click', function(e) {
        e.preventDefault();
        $('.wallet-action-content').hide().removeClass('active');
        $('#withdraw-section').show().addClass('active');
        $('#wallet-action-section').slideDown();
        $('html, body').animate({
            scrollTop: $('#wallet-action-section').offset().top - 100
        }, 500);
    });

    $('#show-transfer').on('click', function(e) {
        e.preventDefault();
        $('.wallet-action-content').hide().removeClass('active');
        $('#transfer-section').show().addClass('active');
        $('#wallet-action-section').slideDown();
        $('html, body').animate({
            scrollTop: $('#wallet-action-section').offset().top - 100
        }, 500);
    });
});
</script>
