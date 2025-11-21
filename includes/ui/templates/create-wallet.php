<?php
if (!defined('ABSPATH')) exit;

$user_id = get_current_user_id();
$wallet_created = get_user_meta($user_id, 'mnt_wallet_created', true);
?>

<div class="mnt-create-wallet">
    <?php if ($wallet_created): ?>
        <div class="wallet-exists">
            <div class="success-icon">✓</div>
            <h3>Wallet Already Created</h3>
            <p>Your wallet has been successfully created and is ready to use.</p>
            <a href="<?php echo esc_url(home_url('/wallet')); ?>" class="mnt-btn mnt-btn-primary">View Wallet</a>
        </div>
    <?php else: ?>
        <div class="wallet-create-form">
            <div class="wallet-icon">💼</div>
            <h3>Create Your Wallet</h3>
            <p>Create a secure wallet to manage your funds on MyNaijaTask.</p>
            
            <div class="wallet-benefits">
                <ul>
                    <li>✓ Secure escrow transactions</li>
                    <li>✓ Easy deposits and withdrawals</li>
                    <li>✓ Real-time balance tracking</li>
                    <li>✓ Transaction history</li>
                </ul>
            </div>

            <button id="create-wallet-btn" class="mnt-btn mnt-btn-primary mnt-btn-large">
                Create Wallet Now
            </button>
            
            <div class="mnt-message" style="display:none;"></div>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('#create-wallet-btn').on('click', function() {
        var $btn = $(this);
        var $message = $('.mnt-message');
        
        $btn.prop('disabled', true).text('Creating...');
        $message.hide();

        $.ajax({
            url: mntEscrow.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mnt_wallet_action',
                nonce: mntEscrow.nonce,
                wallet_action: 'create'
            },
            success: function(response) {
                if (response.success) {
                    $message.addClass('success').text('Wallet created successfully!').show();
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    $message.addClass('error').text(response.data.message || 'Failed to create wallet').show();
                    $btn.prop('disabled', false).text('Create Wallet Now');
                }
            },
            error: function() {
                $message.addClass('error').text('An error occurred').show();
                $btn.prop('disabled', false).text('Create Wallet Now');
            }
        });
    });
});
</script>
