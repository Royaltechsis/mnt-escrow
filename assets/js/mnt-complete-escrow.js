// Debug: Check if jQuery is loaded
console.log('mnt-complete-escrow.js loaded. typeof $:', typeof $);
console.log('mntEscrow object:', typeof mntEscrow !== 'undefined' ? mntEscrow : 'undefined');

// Debug: Check if button exists on DOM ready
jQuery(function($) {
    console.log('DOM ready. #mnt-complete-escrow-btn exists:', $('#mnt-complete-escrow-btn').length);
    console.log('#mnt-complete-escrow-modal exists:', $('#mnt-complete-escrow-modal').length);
    console.log('#mnt-complete-escrow-modal visible:', $('#mnt-complete-escrow-modal').is(':visible'));
});

// Escrow Complete Funds Handler
$(document).on('click', '#mnt-complete-escrow-btn', function(e) {
    console.log('=== MNT: Release Funds Button Clicked ===');
    e.preventDefault();
    var $btn = $(this);
    var $message = $('#mnt-complete-escrow-message');
    $btn.prop('disabled', true).text('Processing...');
    $message.hide();

    var projectId = $btn.data('project-id');
    var userId = $btn.data('user-id');
    var sellerId = $btn.data('seller-id');
    
    console.log('Button data attributes:');
    console.log('  project-id:', projectId, '(type:', typeof projectId + ')');
    console.log('  user-id:', userId, '(type:', typeof userId + ')');
    console.log('  seller-id:', sellerId, '(type:', typeof sellerId + ')');
    console.log('');
    
    var ajaxData = {
        action: 'mnt_fund_escrow',
        project_id: projectId,
        user_id: userId,
        seller_id: sellerId,
        nonce: mntEscrow.nonce
    };
    
    console.log('=== AJAX Request Details ===');
    console.log('URL:', mntEscrow.ajaxUrl);
    console.log('Data being sent to WordPress AJAX:', ajaxData);
    console.log('');
    console.log('=== Expected Backend Flow ===');
    console.log('  1. WordPress AJAX handler: handle_fund_escrow_ajax()');
    console.log('  2. Extract seller_id from AJAX data (or fallback to post meta)');
    console.log('  3. Call Escrow API method: client_release_funds()');
    console.log('');
    console.log('=== Expected API Call ===');
    console.log('Endpoint: POST https://escrow-api-dfl6.onrender.com/api/escrow/client_release_funds');
    console.log('Purpose: Move funds from client wallet to escrow account (PENDING → FUNDED)');
    console.log('Note: For tasks, task_id is passed as project_id');
    console.log('API Payload: {');
    console.log('  "project_id": "' + projectId + '",  // task_id for task escrow');
    console.log('  "client_id": "' + userId + '",');
    console.log('  "merchant_id": "' + sellerId + '"');
    console.log('}');
    console.log('');

    $.ajax({
        url: mntEscrow.ajaxUrl,
        type: 'POST',
        data: ajaxData,
        success: function(response) {
            console.log('=== AJAX Response Received ===');
            console.log('Full Response:', response);
            console.log('Response.success:', response.success);
            console.log('Response.data:', response.data);
            console.log('');
            
            var msg = '';
            if (response.success) {
                msg = response.data.message || 'Funds moved to escrow successfully!';
                
                // Add order link if available
                if (response.data.order_id) {
                    console.log('Task purchased - Order ID:', response.data.order_id);
                }
                
                // Add API response details
                if (response.data.result) {
                    msg += '<br><details style="margin-top:12px;"><summary style="cursor:pointer;font-weight:600;">Show API Response</summary><pre style="white-space:pre-wrap;background:#1f2937;color:#f3f4f6;padding:10px;border-radius:4px;margin-top:8px;">' + JSON.stringify(response.data.result, null, 2) + '</pre></details>';
                }
                
                $message.removeClass('tk-alert-error').addClass('tk-alert-success').html(msg).show();
                
                // Check if already funded
                if (response.data.already_funded) {
                    $btn.text('Already Funded').prop('disabled', true);
                } else if (response.data.task_hired) {
                    $btn.text('Task Purchased!').prop('disabled', true);
                } else {
                    $btn.text('Funded Successfully').prop('disabled', true);
                }
                
                // Close modal and redirect
                setTimeout(function() {
                    $('#mnt-complete-escrow-modal').fadeOut();
                    $('.modal-backdrop').fadeOut(function() {
                        $(this).remove();
                    });
                    
                    // Redirect to buyer insights/ongoing tasks page if available
                    if (response.data.redirect_url && response.data.task_hired) {
                        console.log('Redirecting to:', response.data.redirect_url);
                        window.location.href = response.data.redirect_url;
                    } else {
                        // Fallback: reload page
                        location.reload();
                    }
                }, 2000); // Reduced to 2 seconds for faster redirect
            } else {
                msg = (response.data && response.data.message) ? response.data.message : 'Failed to fund escrow.';
                $message.removeClass('tk-alert-success').addClass('tk-alert-error').html(msg).show();
                $btn.prop('disabled', false).text('Release Funds');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr, status, error);
            var errorMsg = '<strong>An error occurred</strong><br><br>';
            if (xhr && xhr.responseText) {
                errorMsg += '<pre style="white-space:pre-wrap;background:#1f2937;color:#f3f4f6;padding:10px;border-radius:4px;max-height:300px;overflow:auto;">' + xhr.responseText + '</pre>';
            }
            $message.removeClass('tk-alert-success').addClass('tk-alert-error').html(errorMsg).show();
            $btn.prop('disabled', false).text('Release Funds');
        }
    });
});
