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
    console.log('Complete button clicked');
    e.preventDefault();
    var $btn = $(this);
    var $message = $('#mnt-complete-escrow-message');
    $btn.prop('disabled', true).text('Processing...');
    $message.hide();

    var projectId = $btn.data('project-id');
    var userId = $btn.data('user-id');
    
    console.log('Project ID:', projectId);
    console.log('User ID:', userId);
    console.log('Ajax URL:', mntEscrow.ajaxUrl);

    $.ajax({
        url: mntEscrow.ajaxUrl,
        type: 'POST',
        data: {
            action: 'mnt_complete_escrow_funds',
            nonce: mntEscrow.nonce,
            project_id: projectId,
            user_id: userId
        },
        success: function(response) {
            console.log('AJAX Response:', response);
            var msg = '';
            if (response.success) {
                msg = response.data.message || 'Funds moved to escrow successfully!';
                if (response.data.client_release_error) {
                    msg += '<br><span style="color:red;">Release Error: ' + response.data.client_release_error + '</span>';
                }
                if (response.data.client_release_response) {
                    msg += '<br><pre style="white-space:pre-wrap;">' + JSON.stringify(response.data.client_release_response, null, 2) + '</pre>';
                }
                $message.removeClass('tk-alert-error').addClass('tk-alert-success').html(msg).show();
                $btn.text('Completed').prop('disabled', true);
                
                // Close modal after 3 seconds on success
                setTimeout(function() {
                    $('#mnt-complete-escrow-modal').fadeOut();
                    $('.modal-backdrop').fadeOut();
                }, 3000);
            } else {
                msg = (response.data && response.data.message) ? response.data.message : 'Failed to move funds.';
                msg += '<br><pre style="white-space:pre-wrap;">' + JSON.stringify(response, null, 2) + '</pre>';
                $message.removeClass('tk-alert-success').addClass('tk-alert-error').html(msg).show();
                $btn.prop('disabled', false).text('Release Funds');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', xhr, status, error);
            var errorMsg = 'An error occurred';
            if (xhr && xhr.responseText) {
                errorMsg += '<br><pre style="white-space:pre-wrap;">' + xhr.responseText + '</pre>';
            }
            $message.removeClass('tk-alert-success').addClass('tk-alert-error').html(errorMsg).show();
            $btn.prop('disabled', false).text('Release Funds');
        }
    });
});
