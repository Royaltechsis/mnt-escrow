// Escrow Hire Handler
$(document).on('submit', '#mnt-hire-form', function(e) {
    e.preventDefault();
    var $form = $(this);
    var $btn = $form.find('button[type="submit"]');
    var $message = $form.find('.mnt-message');
    $btn.prop('disabled', true).text('Processing...');
    $message.hide();

    var formData = $form.serializeArray();
    formData.push({ name: 'action', value: 'mnt_create_escrow_transaction' });
    formData.push({ name: 'nonce', value: mntEscrow.nonce });

    console.log('MNT: Creating Escrow - Endpoint:', mntEscrow.ajaxUrl);
    console.log('MNT: Creating Escrow - Payload:', formData);
    console.log('MNT: Creating Escrow - Formatted Data:', Object.fromEntries(formData.map(item => [item.name, item.value])));

    $.ajax({
        url: mntEscrow.ajaxUrl,
        type: 'POST',
        data: formData,
        success: function(response) {
            console.log('AJAX Response:', response);
            var msg = '';
            if (response.success) {
                msg = response.data.message || 'Project hired successfully!';
                if (response.data.client_release_error) {
                    msg += '<br><span style="color:red;">Release Error: ' + response.data.client_release_error + '</span>';
                }
                if (response.data.client_release_response) {
                    msg += '<br><pre style="white-space:pre-wrap;">' + JSON.stringify(response.data.client_release_response, null, 2) + '</pre>';
                }
                $message.removeClass('error').addClass('success').html(msg).show();
                if (response.data.redirect_url) {
                    setTimeout(function() { window.location.href = response.data.redirect_url; }, 2000);
                }
            } else {
                msg = (response.data && response.data.message) ? response.data.message : 'Failed to hire project.';
                msg += '<br><pre style="white-space:pre-wrap;">' + JSON.stringify(response, null, 2) + '</pre>';
                $message.removeClass('success').addClass('error').html(msg).show();
                $btn.prop('disabled', false).text('Hire');
            }
        },
        error: function(xhr, status, error) {
            var errorMsg = 'An error occurred';
            if (xhr && xhr.responseText) {
                errorMsg += '<br><pre style="white-space:pre-wrap;">' + xhr.responseText + '</pre>';
            }
            $message.removeClass('success').addClass('error').html(errorMsg).show();
            $btn.prop('disabled', false).text('Hire');
        }
    });
});
