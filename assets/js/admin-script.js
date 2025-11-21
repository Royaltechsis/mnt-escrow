/**
 * MyNaijaTask Escrow - Admin JavaScript
 */
(function($) {
    'use strict';

    // Resolve Dispute Handler
    $('.resolve-dispute').on('click', function() {
        var $btn = $(this);
        var escrowId = $btn.data('escrow-id');
        var decision = $btn.data('decision');
        
        var confirmMsg = decision === 'release' 
            ? 'Are you sure you want to release funds to the seller?' 
            : 'Are you sure you want to refund to the buyer?';
        
        if (!confirm(confirmMsg)) {
            return;
        }

        $btn.prop('disabled', true).addClass('loading');

        $.ajax({
            url: mntAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mnt_admin_resolve_dispute',
                nonce: mntAdmin.nonce,
                escrow_id: escrowId,
                decision: decision
            },
            success: function(response) {
                if (response.success) {
                    alert('Dispute resolved successfully!');
                    location.reload();
                } else {
                    alert('Failed to resolve dispute: ' + (response.data.message || 'Unknown error'));
                    $btn.prop('disabled', false).removeClass('loading');
                }
            },
            error: function() {
                alert('An error occurred');
                $btn.prop('disabled', false).removeClass('loading');
            }
        });
    });

    // Refresh stats periodically on dashboard
    if ($('.mnt-stats-grid').length) {
        setInterval(function() {
            location.reload();
        }, 60000); // Refresh every 60 seconds
    }

})(jQuery);
