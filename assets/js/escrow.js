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
                    // Redirect to Paystack payment page
                    var paymentUrl = response.data.checkout_url || response.data.authorization_url;
                    console.log('Redirecting to:', paymentUrl);
                    window.location.href = paymentUrl;
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

})(jQuery);
