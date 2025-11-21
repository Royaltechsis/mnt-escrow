<?php
if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$escrow_result = \MNT\Api\Escrow::get($escrow_id);
$escrow = $escrow_result['escrow'] ?? null;

if (!$escrow) {
    echo '<p>Escrow not found.</p>';
    return;
}

$buyer_id = $escrow['buyer_id'] ?? 0;
$seller_id = $escrow['seller_id'] ?? 0;
$amount = $escrow['amount'] ?? 0;
$status = $escrow['status'] ?? 'unknown';
$created_at = $escrow['created_at'] ?? '';
$description = $escrow['description'] ?? '';

$is_buyer = ($user_id == $buyer_id);
$is_seller = ($user_id == $seller_id);
$role = $is_buyer ? 'Buyer' : ($is_seller ? 'Seller' : 'Observer');
?>

<div class="mnt-escrow-box">
    <div class="escrow-header">
        <h3>Escrow Transaction</h3>
        <span class="escrow-status status-<?php echo esc_attr($status); ?>">
            <?php echo esc_html(ucfirst($status)); ?>
        </span>
    </div>

    <div class="escrow-details">
        <div class="detail-row">
            <span class="label">Escrow ID:</span>
            <span class="value"><?php echo esc_html($escrow_id); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Amount:</span>
            <span class="value amount">₦<?php echo number_format($amount, 2); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Your Role:</span>
            <span class="value"><?php echo esc_html($role); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Description:</span>
            <span class="value"><?php echo esc_html($description); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Created:</span>
            <span class="value"><?php echo esc_html(date('M d, Y h:i A', strtotime($created_at))); ?></span>
        </div>
    </div>

    <div class="escrow-actions">
        <?php if ($status === 'pending' || $status === 'delivered'): ?>
            
            <?php if ($is_buyer && $status === 'delivered'): ?>
                <div class="buyer-actions">
                    <p class="action-message">The seller has delivered the work. Please review and take action.</p>
                    <button class="mnt-btn mnt-btn-success" data-action="release" data-escrow-id="<?php echo esc_attr($escrow_id); ?>">
                        Approve & Release Payment
                    </button>
                    <button class="mnt-btn mnt-btn-danger" data-action="dispute" data-escrow-id="<?php echo esc_attr($escrow_id); ?>">
                        Open Dispute
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($is_seller && $status === 'pending'): ?>
                <div class="seller-actions">
                    <p class="action-message">Complete your work and mark the task as delivered.</p>
                </div>
            <?php endif; ?>

            <?php if ($is_seller && $status === 'delivered'): ?>
                <div class="seller-actions">
                    <p class="action-message">Waiting for buyer approval...</p>
                </div>
            <?php endif; ?>

        <?php elseif ($status === 'disputed'): ?>
            <div class="dispute-notice">
                <p>⚠️ This escrow is in dispute. An admin will review and make a decision.</p>
            </div>

        <?php elseif ($status === 'released'): ?>
            <div class="success-notice">
                <p>✓ Funds have been released to the seller.</p>
            </div>

        <?php elseif ($status === 'refunded'): ?>
            <div class="info-notice">
                <p>↩ Funds have been refunded to the buyer.</p>
            </div>

        <?php elseif ($status === 'cancelled'): ?>
            <div class="warning-notice">
                <p>✗ This escrow has been cancelled.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="escrow-message" style="display:none;"></div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.escrow-actions button').on('click', function() {
        var action = $(this).data('action');
        var escrowId = $(this).data('escrow-id');
        var $btn = $(this);
        var $message = $('.escrow-message');

        if (action === 'dispute') {
            var reason = prompt('Please provide a reason for the dispute:');
            if (!reason) return;
        }

        $btn.prop('disabled', true);
        $message.hide();

        $.ajax({
            url: mntEscrow.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mnt_escrow_action',
                nonce: mntEscrow.nonce,
                escrow_action: action,
                escrow_id: escrowId,
                reason: action === 'dispute' ? reason : ''
            },
            success: function(response) {
                if (response.success) {
                    $message.addClass('success').text('Action completed successfully!').show();
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $message.addClass('error').text(response.data.message || 'Action failed').show();
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                $message.addClass('error').text('An error occurred').show();
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>
