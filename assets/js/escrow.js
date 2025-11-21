/**
 * MyNaijaTask Escrow - Frontend JavaScript
 */
(function($) {
    'use strict';

    // Deposit Form Handler
    $('#mnt-deposit-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $message = $form.find('.mnt-message');
        var amount = $form.find('#deposit-amount').val();

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
                if (response.success && response.data.authorization_url) {
                    // Redirect to Paystack payment page
                    window.location.href = response.data.authorization_url;
                } else {
                    $message.addClass('error')
                           .text(response.data.message || 'Failed to initialize deposit')
                           .show();
                    $btn.prop('disabled', false).text('Deposit');
                }
            },
            error: function() {
                $message.addClass('error').text('An error occurred').show();
                $btn.prop('disabled', false).text('Deposit');
            }
        });
    });

    // Withdraw Form Handler
    $('#mnt-withdraw-form').on('submit', function(e) {
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
    $('#mnt-transfer-form').on('submit', function(e) {
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
                    // Remove reference from URL
                    window.history.replaceState({}, document.title, window.location.pathname);
                    location.reload();
                } else {
                    alert('Payment verification failed.');
                }
            }
        });
    }

})(jQuery);
