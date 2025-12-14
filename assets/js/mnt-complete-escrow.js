// Debug: Check if jQuery is loaded
console.log('mnt-complete-escrow.js loaded. typeof jQuery:', typeof jQuery);
console.log('mntEscrow object:', typeof mntEscrow !== 'undefined' ? mntEscrow : 'undefined');

// Debug: Check if button exists on DOM ready
jQuery(function($) {
    console.log('DOM ready. #mnt-complete-escrow-btn exists:', $('#mnt-complete-escrow-btn').length);
    console.log('#mnt-complete-escrow-modal exists:', $('#mnt-complete-escrow-modal').length);
    console.log('#mnt-complete-escrow-modal visible:', $('#mnt-complete-escrow-modal').is(':visible'));
});

// Escrow Complete Funds Handler - Use jQuery instead of $
jQuery(document).on('click', '#mnt-complete-escrow-btn', function(e) {
    console.log('=== MNT: Release Funds Button Clicked ===');
    e.preventDefault();
    var $ = jQuery;
    var $btn = $(this);
    var $message = $('#mnt-complete-escrow-message');
    $btn.prop('disabled', true).text('Processing...');
    $message.hide();

    var projectId = $btn.data('project-id');
    var taskId = $btn.data('task-id');
    var orderId = $btn.data('order-id');
    var userId = $btn.data('user-id');
    var sellerId = $btn.data('seller-id');
    
    console.log('Button data attributes:');
    console.log('  project-id:', projectId, '(type:', typeof projectId + ')');
    console.log('  task-id:', taskId, '(type:', typeof taskId + ')');
    console.log('  order-id:', orderId, '(type:', typeof orderId + ')');
    console.log('  user-id:', userId, '(type:', typeof userId + ')');
    console.log('  seller-id:', sellerId, '(type:', typeof sellerId + ')');
    console.log('');
    
    var ajaxData = {
        action: 'mnt_fund_escrow',
        project_id: projectId,
        task_id: taskId,
        order_id: orderId,
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
    console.log('  2. Extract seller_id from AJAX data');
    console.log('  3. Call Escrow API method: client_release_funds()');
    console.log('');
    console.log('=== Expected API Call ===');
    console.log('Endpoint: POST https://escrow-api-dfl6.onrender.com/api/escrow/client_release_funds');
    console.log('Purpose: Move funds from client wallet to escrow account (PENDING → FUNDED)');
    console.log('Note: For tasks, task_id is passed as project_id');
    console.log('API Payload: {');
    console.log('  "project_id": "' + projectId + '",  // order-based ID for task escrow');
    console.log('  "client_id": "' + userId + '",  // buyer');
    console.log('  "merchant_id": "' + sellerId + '"  // seller');
    console.log('}');
    console.log('');

    $.ajax({
        url: mntEscrow.ajaxUrl,
        type: 'POST',
        data: ajaxData,
        success: function(response) {
            console.log('%c=== AJAX Response Received ===', 'color: #10b981; font-weight: bold; font-size: 14px;');
            console.log('Full Response:', response);
            console.log('Response.success:', response.success);
            console.log('Response.data:', response.data);
            
            // Log hook result if available
            if (response.data.hook_result) {
                console.log('%c=== WooCommerce Payment Complete Hook Result ===', 'color: #f59e0b; font-weight: bold; font-size: 14px;');
                console.log('Hook Fired:', response.data.hook_result.fired);
                console.log('Hook Status:', response.data.hook_result.status);
                console.log('Hook Message:', response.data.hook_result.message);
                console.log('Order ID:', response.data.hook_result.order_id);
                console.log('Order Status Before Hook:', response.data.hook_result.status_before);
                console.log('Order Status After Hook:', response.data.hook_result.status_after);
                console.log('Order buyer_id:', response.data.hook_result.buyer_id);
                console.log('Order seller_id:', response.data.hook_result.seller_id);
                console.log('Order payment_type:', response.data.hook_result.payment_type);
                console.log('Order _task_status:', response.data.hook_result._task_status);
                console.log('============================================');
            }
            
            var msg = '';
            if (response.success) {
                console.log('%c✅ SUCCESS: Escrow funded successfully!', 'color: #10b981; font-weight: bold; font-size: 14px;');
                msg = response.data.message || 'Funds moved to escrow successfully!';
                
                // Add order link if available
                if (response.data.order_id) {
                    console.log('Task purchased - Order ID:', response.data.order_id);
                }
                
                // Display raw API response directly
                var rawApiResponse = '';
                if (response.data.result) {
                    rawApiResponse = JSON.stringify(response.data.result, null, 2);
                } else {
                    rawApiResponse = JSON.stringify(response.data, null, 2);
                }
                
                msg += '<br><div style="margin-top:12px;"><strong>API Response:</strong><pre style="white-space:pre-wrap;background:#1f2937;color:#f3f4f6;padding:10px;border-radius:4px;margin-top:8px;max-height:400px;overflow:auto;font-size:12px;">' + rawApiResponse + '</pre></div>';
                
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
                console.log('%c❌ ERROR: Failed to fund escrow', 'color: #ef4444; font-weight: bold; font-size: 14px;');
                console.error('Error Response:', response.data);
                
                msg = (response.data && response.data.message) ? response.data.message : 'Failed to fund escrow.';
                
                // Display raw error response
                var rawErrorResponse = JSON.stringify(response.data, null, 2);
                msg += '<br><div style="margin-top:12px;"><strong>Full Error Response:</strong><pre style="white-space:pre-wrap;background:#1f2937;color:#f3f4f6;padding:10px;border-radius:4px;margin-top:8px;max-height:400px;overflow:auto;font-size:12px;">' + rawErrorResponse + '</pre></div>';
                
                $message.removeClass('tk-alert-success').addClass('tk-alert-error').html(msg).show();
                $btn.prop('disabled', false).text('Release Funds');
            }
        },
        error: function(xhr, status, error) {
            console.log('%c🔴 AJAX ERROR - Request Failed', 'color: #dc2626; font-weight: bold; font-size: 14px; background: #fecaca; padding: 8px;');
            console.error('Status:', status);
            console.error('Error:', error);
            console.error('XHR Status Code:', xhr.status);
            console.error('XHR Status Text:', xhr.statusText);
            console.error('Response Text:', xhr.responseText);
            
            var errorMsg = '<strong>❌ AJAX Error Occurred</strong><br><br>';
            errorMsg += '<strong>Status:</strong> ' + status + ' (' + xhr.status + ')<br>';
            errorMsg += '<strong>Error:</strong> ' + error + '<br><br>';
            
            if (xhr && xhr.responseText) {
                try {
                    var jsonResponse = JSON.parse(xhr.responseText);
                    console.error('Parsed JSON Error Response:', jsonResponse);
                    errorMsg += '<strong>Server Response:</strong><br><pre style="white-space:pre-wrap;background:#1f2937;color:#f3f4f6;padding:10px;border-radius:4px;max-height:300px;overflow:auto;font-size:12px;">' + JSON.stringify(jsonResponse, null, 2) + '</pre>';
                } catch(e) {
                    console.error('Raw Response (not JSON):', xhr.responseText);
                    errorMsg += '<strong>Server Response:</strong><br><pre style="white-space:pre-wrap;background:#1f2937;color:#f3f4f6;padding:10px;border-radius:4px;max-height:300px;overflow:auto;font-size:12px;">' + xhr.responseText + '</pre>';
                }
            }
            
            $message.removeClass('tk-alert-success').addClass('tk-alert-error').html(errorMsg).show();
            $btn.prop('disabled', false).text('Release Funds');
        }
    });
});
