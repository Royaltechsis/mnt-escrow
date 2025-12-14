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

    // Backfill Task Orders - Dry Run / Run
    $(document).on('click', '#mnt-backfill-dry, #mnt-backfill-run', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var run = $btn.attr('id') === 'mnt-backfill-run' ? '1' : '0';
        var $results = $('#mnt-backfill-results');

        if (!confirm(run === '1' ? 'This will update orders. Are you sure?' : 'This will only show what would be updated. Proceed?')) {
            return;
        }

        $btn.prop('disabled', true).addClass('loading');
        $results.show().text('Running...');

        $.ajax({
            url: mntAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mnt_backfill_task_orders',
                nonce: mntAdmin.nonce,
                run: run
            },
            success: function(resp) {
                $btn.prop('disabled', false).removeClass('loading');
                if (resp && resp.success) {
                    $results.text(JSON.stringify(resp.data, null, 2));
                } else {
                    $results.text('Error: ' + (resp && resp.data && resp.data.message ? resp.data.message : 'Unknown'));
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).removeClass('loading');
                $results.text('AJAX error: ' + xhr.status + ' ' + xhr.statusText);
            }
        });
    });

    // Repair single order handler
    $(document).on('click', '#mnt-repair-order', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var orderId = parseInt($('#mnt-repair-order-id').val(), 10);
        var $results = $('#mnt-repair-results');
        if (!orderId) {
            alert('Please enter a valid Order ID');
            return;
        }
        if (!confirm('This will force the order to processing. Proceed?')) return;
        $btn.prop('disabled', true).addClass('loading');
        $results.show().text('Running...');
        $.ajax({
            url: mntAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mnt_admin_repair_order',
                nonce: mntAdmin.nonce,
                order_id: orderId
            },
            success: function(resp) {
                $btn.prop('disabled', false).removeClass('loading');
                if (resp && resp.success) {
                    $results.text(JSON.stringify(resp.data, null, 2));
                } else {
                    $results.text('Error: ' + (resp && resp.data && resp.data.message ? resp.data.message : 'Unknown'));
                }
            },
            error: function(xhr) {
                $btn.prop('disabled', false).removeClass('loading');
                $results.text('AJAX error: ' + xhr.status + ' ' + xhr.statusText);
            }
        });
    });

})(jQuery);
