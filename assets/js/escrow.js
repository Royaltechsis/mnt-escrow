/**
 * MyNaijaTask Escrow - Frontend JavaScript
 */
(function($) {
    'use strict';

    // Deposit Form Handler
    $(document).on('submit', '#mnt-deposit-form', function(e) {
        e.preventDefault();
        
        console.log('Deposit form submitted');
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $message = $form.find('.mnt-message');
        var amount = $form.find('#deposit-amount').val();

        console.log('Amount:', amount);
        console.log('AJAX URL:', mntEscrow.ajaxUrl);

        $btn.prop('disabled', true).text('Processing...');
        $message.hide();

        $.ajax({
            url: mntEscrow.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mnt_deposit',
                nonce: mntEscrow.nonce,
                amount: amount
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                if (response.success && (response.data.checkout_url || response.data.authorization_url)) {
                    // Open Flutterwave/Paystack payment page in new tab
                    var paymentUrl = response.data.checkout_url || response.data.authorization_url;
                    console.log('Opening payment page in new tab:', paymentUrl);
                    window.open(paymentUrl, '_blank');
                    
                    // Show success message and re-enable button
                    $message.removeClass('error')
                           .addClass('success')
                           .html('Payment page opened in a new tab. Please complete your payment.')
                           .show();
                    $btn.prop('disabled', false).text('Deposit');
                } else {
                    console.log('Error:', response.data);
                    var errorMsg = '';
                    if (response.data && response.data.message) {
                        errorMsg = response.data.message;
                    } else if (typeof response.data === 'string') {
                        errorMsg = response.data;
                    } else {
                        errorMsg = 'Failed to initialize deposit.';
                    }
                    // Show raw response for debugging
                    errorMsg += '<br><pre style="white-space:pre-wrap;">' + JSON.stringify(response, null, 2) + '</pre>';
                    $message.addClass('error')
                           .html(errorMsg)
                           .show();
                    $btn.prop('disabled', false).text('Deposit');
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', xhr, status, error);
                var errorMsg = 'An error occurred';
                if (xhr && xhr.responseText) {
                    errorMsg += '<br><pre style="white-space:pre-wrap;">' + xhr.responseText + '</pre>';
                }
                $message.addClass('error').html(errorMsg).show();
                $btn.prop('disabled', false).text('Deposit');
            }
        });
    });

    // Withdraw Form Handler
    $(document).on('submit', '#mnt-withdraw-form', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $message = $form.find('.mnt-message');
        
        var formData = {
            action: 'mnt_withdraw',
            nonce: mntEscrow.nonce,
            amount: $form.find('#withdraw-amount').val(),
            bank_code: $form.find('#bank-code').val(),
            account_number: $form.find('#account-number').val(),
            account_name: $form.find('#account-name').val()
        };

        $btn.prop('disabled', true).text('Processing...');
        $message.hide();

        $.ajax({
            url: mntEscrow.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $message.addClass('success')
                           .text('Withdrawal request submitted successfully!')
                           .show();
                    $form[0].reset();
                    
                    // Reload page after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $message.addClass('error')
                           .text(response.data.message || 'Withdrawal failed')
                           .show();
                    $btn.prop('disabled', false).text('Withdraw');
                }
            },
            error: function() {
                $message.addClass('error').text('An error occurred').show();
                $btn.prop('disabled', false).text('Withdraw');
            }
        });
    });

    // Transfer Form Handler
    $(document).on('submit', '#mnt-transfer-form', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $message = $form.find('.mnt-message');
        var recipientEmail = $form.find('#recipient-email').val();
        
        // First, get user ID from email via AJAX
        $.ajax({
            url: mntEscrow.restUrl + '/user/by-email',
            type: 'GET',
            data: { email: recipientEmail },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', mntEscrow.restNonce);
                $btn.prop('disabled', true).text('Processing...');
                $message.hide();
            },
            success: function(userResponse) {
                if (userResponse && userResponse.id) {
                    // Now make the transfer
                    $.ajax({
                        url: mntEscrow.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'mnt_transfer',
                            nonce: mntEscrow.nonce,
                            to_user_id: userResponse.id,
                            amount: $form.find('#transfer-amount').val(),
                            description: $form.find('#transfer-description').val()
                        },
                        success: function(response) {
                            if (response.success) {
                                $message.addClass('success')
                                       .text('Transfer completed successfully!')
                                       .show();
                                $form[0].reset();
                                
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            } else {
                                $message.addClass('error')
                                       .text(response.data.message || 'Transfer failed')
                                       .show();
                                $btn.prop('disabled', false).text('Transfer');
                            }
                        },
                        error: function() {
                            $message.addClass('error').text('An error occurred').show();
                            $btn.prop('disabled', false).text('Transfer');
                        }
                    });
                } else {
                    $message.addClass('error').text('User not found').show();
                    $btn.prop('disabled', false).text('Transfer');
                }
            },
            error: function() {
                $message.addClass('error').text('Could not find user').show();
                $btn.prop('disabled', false).text('Transfer');
            }
        });
    });

    // Refresh balance periodically
    function refreshBalance() {
        $.ajax({
            url: mntEscrow.restUrl + '/wallet/balance',
            type: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', mntEscrow.restNonce);
            },
            success: function(response) {
                if (response && response.balance !== undefined) {
                    $('.mnt-wallet-balance').text('₦' + parseFloat(response.balance).toLocaleString('en-NG', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }));
                    $('.balance-amount').text('₦' + parseFloat(response.balance).toLocaleString('en-NG', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }));
                }
            }
        });
    }

    // Refresh balance every 30 seconds if wallet page is active
    if ($('.mnt-wallet-dashboard').length) {
        setInterval(refreshBalance, 30000);
    }

    // View escrow details
    $('.view-escrow').on('click', function(e) {
        e.preventDefault();
        var escrowId = $(this).data('escrow-id');
        
        // You can implement a modal or redirect to escrow details page
        window.location.href = '/escrow/' + escrowId;
    });

    // Merchant Release Funds Handler (for completed projects - triggers merchant_release_funds API)
    $(document).on('click', '#mnt-release-funds-btn', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var $message = $('#mnt-release-funds-message');
        var projectId = $btn.data('project-id');
        var sellerId = $btn.data('seller-id');
        
        console.log('=== MNT Release Funds - Button Clicked ===');
        console.log('Project ID:', projectId);
        console.log('Seller ID:', sellerId);
        
        if (!projectId || !sellerId) {
            alert('Missing project or seller information.');
            return;
        }
        
        // Confirm action
        if (!confirm('Are you sure you want to release the funds to your wallet? This action cannot be undone.')) {
            return;
        }
        
        // Disable button and show loading state
        $btn.prop('disabled', true).html('<i class="tb-icon-refresh-cw spinning"></i> Processing...');
        $message.hide();
        
        var requestData = {
            action: 'mnt_merchant_release_funds_action',
            nonce: mntEscrow.nonce,
            project_id: projectId,
            user_id: sellerId
        };
        
        console.log('=== Sending Release Funds AJAX Request ===');
        console.log('Request Data:', requestData);
        
        $.ajax({
            url: mntEscrow.ajaxUrl,
            type: 'POST',
            data: requestData,
            success: function(response) {
                console.log('=== Release Funds AJAX Success ===');
                console.log('Response:', response);
                
                if (response.success) {
                    $message
                        .removeClass('tk-alert-error')
                        .addClass('tk-alert-success')
                        .html('<i class="tb-icon-check-circle"></i> ' + (response.data.message || 'Funds released successfully!'))
                        .show();
                    $btn.html('<i class="tb-icon-check"></i> Completed').prop('disabled', true);
                    
                    // Reload page after 2 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : 'Failed to release funds.';
                    $message
                        .removeClass('tk-alert-success')
                        .addClass('tk-alert-error')
                        .html('<i class="tb-icon-x-circle"></i> ' + errorMsg)
                        .show();
                    $btn.html('<i class="tb-icon-dollar-sign"></i> Release Funds').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('=== Release Funds AJAX Error ===');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);
                
                $message
                    .removeClass('tk-alert-success')
                    .addClass('tk-alert-error')
                    .html('<i class="tb-icon-x-circle"></i> An error occurred. Please try again.')
                    .show();
                $btn.html('<i class="tb-icon-dollar-sign"></i> Release Funds').prop('disabled', false);
            }
        });
    });

    // Merchant Confirm Completion Handler (for hired projects - triggers merchant_confirm API)
    $(document).on('click', '.mnt-merchant-confirm-completion', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var projectId = $btn.data('project-id');
        var userId = $btn.data('user-id');
        
        console.log('=== MNT Merchant Confirm - Button Clicked ===');
        console.log('Project ID:', projectId);
        console.log('User ID:', userId);
        console.log('Button Element:', $btn);
        
        if (!projectId || !userId) {
            alert('Missing project or user information.\nProject ID: ' + projectId + '\nUser ID: ' + userId);
            console.error('Missing data - Project ID:', projectId, 'User ID:', userId);
            return;
        }
        
        // Disable button and show loading state
        var originalText = $btn.text();
        $btn.prop('disabled', true).text('Processing...');
        
        var requestData = {
            action: 'mnt_merchant_confirm_funds',
            nonce: mntEscrow.nonce,
            project_id: projectId,
            user_id: userId,
            confirm_status: true
        };
        
        console.log('=== Sending AJAX Request ===');
        console.log('URL:', mntEscrow.ajaxUrl);
        console.log('Request Data:', requestData);
        
        $.ajax({
            url: mntEscrow.ajaxUrl,
            type: 'POST',
            data: requestData,
            success: function(response) {
                console.log('=== AJAX Success Response ===');
                console.log('Full Response:', response);
                console.log('Response Data:', response.data);
                
                if (response.success) {
                    console.log('✅ Success - Merchant Confirmed');
                    console.log('Debug Info:', response.data.debug);
                    console.log('API Response:', response.data.result);
                    
                    // Show detailed success info
                    var debugInfo = response.data.debug || {};
                    var successMsg = 'Success!\n\n' +
                        'Project ID: ' + debugInfo.project_id + '\n' +
                        'User ID: ' + debugInfo.user_id + '\n' +
                        'Confirm Status: ' + debugInfo.confirm_status + '\n\n' +
                        'API Response: ' + JSON.stringify(debugInfo.api_response, null, 2);
                    
                    console.log(successMsg);
                    
                    // Update modal message based on whether funds were released
                    var modalMessage = response.data.message || 'Success! Funds will be released when buyer confirms.';
                    var modalTitle = response.data.both_confirmed ? 'Funds Released!' : 'Success!';
                    var modalIcon = response.data.both_confirmed ? 'tb-icon-check-circle' : 'tb-icon-check-circle';
                    
                    $('#mnt-merchant-success-modal .tk-popup_title h3').text(modalTitle);
                    $('#mnt-merchant-success-modal .modal-body p:first').text(modalMessage);
                    
                    if (response.data.both_confirmed) {
                        $('#mnt-merchant-success-modal .modal-body p:last').text('The funds have been transferred to your wallet!');
                    }
                    
                    // Show success modal
                    $('#mnt-merchant-success-modal').modal('show');
                    
                    // Reload page after 3 seconds
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    console.error('❌ Error Response');
                    console.error('Error Message:', response.data.message);
                    console.error('Debug Info:', response.data.debug);
                    console.error('Full Error:', response.data);
                    
                    var errorMsg = 'Error: ' + (response.data.message || 'Failed to confirm project completion.') + '\n\n';
                    if (response.data.debug) {
                        errorMsg += 'Debug Info:\n' + JSON.stringify(response.data.debug, null, 2);
                    }
                    
                    alert(errorMsg);
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('=== AJAX Error ===');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('XHR Response:', xhr.responseText);
                console.error('XHR Status:', xhr.status);
                console.error('Full XHR:', xhr);
                
                var errorMsg = 'AJAX Error!\n\n' +
                    'Status: ' + status + '\n' +
                    'Error: ' + error + '\n' +
                    'Response: ' + xhr.responseText;
                
                alert(errorMsg);
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // Handle payment callback
    var urlParams = new URLSearchParams(window.location.search);
    var reference = urlParams.get('reference');
    
    if (reference) {
        // Check if we've already processed this reference (prevent duplicate processing)
        var processedKey = 'mnt_processed_' + reference;
        if (sessionStorage.getItem(processedKey)) {
            // Already processed, just clean up URL
            window.history.replaceState({}, document.title, window.location.pathname);
            return;
        }
        
        // Mark as being processed
        sessionStorage.setItem(processedKey, 'true');
        
        // Remove reference from URL immediately to prevent re-processing on refresh
        window.history.replaceState({}, document.title, window.location.pathname);
        
        // Verify payment
        $.ajax({
            url: mntEscrow.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mnt_verify_payment',
                nonce: mntEscrow.nonce,
                reference: reference
            },
            success: function(response) {
                if (response.success) {
                    alert('Payment successful! Your wallet has been credited.');
                    location.reload();
                } else {
                    alert('Payment verification failed: ' + (response.data.message || 'Unknown error'));
                    // Remove processed flag on failure so user can retry
                    sessionStorage.removeItem(processedKey);
                }
            },
            error: function() {
                alert('Payment verification error. Please contact support.');
                // Remove processed flag on error so user can retry
                sessionStorage.removeItem(processedKey);
            }
        });
    }

    // Withdraw Form Handler
    $(document).on('submit', '#mnt-withdraw-form', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $message = $form.find('.mnt-message');
        var amount = $form.find('#withdraw-amount').val();
        var reason = $form.find('#withdraw-reason').val();

        $btn.prop('disabled', true).html('<i class="tb-icon-download"></i> Processing...');
        $message.hide();

        $.ajax({
            url: mntEscrow.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mnt_withdraw',
                nonce: mntEscrow.nonce,
                amount: amount,
                reason: reason
            },
            success: function(response) {
                if (response.success) {
                    $message.removeClass('tk-alert-warning')
                           .addClass('tk-alert-success')
                           .html(response.data.message || 'Withdrawal processed successfully')
                           .slideDown(300);
                    $form[0].reset();
                    
                    // Reload page after 2 seconds to update balance
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $message.removeClass('tk-alert-success')
                           .addClass('tk-alert-warning')
                           .html(response.data.message || 'Failed to process withdrawal')
                           .slideDown(300);
                }
                $btn.prop('disabled', false).html('<i class="tb-icon-download"></i> Withdraw Now');
            },
            error: function(xhr, status, error) {
                console.error('Withdraw AJAX Error:', status, error);
                $message.removeClass('tk-alert-success')
                       .addClass('tk-alert-warning')
                       .html('An error occurred. Please try again.')
                       .slideDown(300);
                $btn.prop('disabled', false).html('<i class="tb-icon-download"></i> Withdraw Now');
            }
        });
    });

    // Transfer Form Handler
    $(document).on('submit', '#mnt-transfer-form', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $message = $form.find('.mnt-message');
        var recipientEmail = $form.find('#recipient-email').val();
        var amount = $form.find('#transfer-amount').val();
        var description = $form.find('#transfer-description').val();

        $btn.prop('disabled', true).html('<i class="tb-icon-arrow-right-circle"></i> Processing...');
        $message.hide();

        $.ajax({
            url: mntEscrow.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mnt_transfer',
                nonce: mntEscrow.nonce,
                recipient_email: recipientEmail,
                amount: amount,
                description: description
            },
            success: function(response) {
                if (response.success) {
                    $message.removeClass('tk-alert-warning')
                           .addClass('tk-alert-success')
                           .html(response.data.message || 'Transfer completed successfully')
                           .slideDown(300);
                    $form[0].reset();
                    
                    // Reload page after 2 seconds to update balance
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $message.removeClass('tk-alert-success')
                           .addClass('tk-alert-warning')
                           .html(response.data.message || 'Failed to process transfer')
                           .slideDown(300);
                }
                $btn.prop('disabled', false).html('<i class="tb-icon-arrow-right-circle"></i> Transfer Now');
            },
            error: function(xhr, status, error) {
                console.error('Transfer AJAX Error:', status, error);
                $message.removeClass('tk-alert-success')
                       .addClass('tk-alert-warning')
                       .html('An error occurred. Please try again.')
                       .slideDown(300);
                $btn.prop('disabled', false).html('<i class="tb-icon-arrow-right-circle"></i> Transfer Now');
            }
        });
    });

})(jQuery);
