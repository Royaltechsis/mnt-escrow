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

    /**
     * Milestone Approval Handler - Releases funds to seller wallet
     * Intercepts taskbot's milestone approval and calls MNT escrow API
     */
    $(document).on('click', '.tb_update_milestone', function(e) {
        var $btn = $(this);
        var status = $btn.data('status');
        var proposalId = $btn.data('id');
        var milestoneKey = $btn.data('key');
        
        // Only intercept buyer's approval (status = 'completed')
        if (status === 'completed') {
            e.preventDefault();
            e.stopImmediatePropagation();
            
            console.log('MNT: Intercepting milestone approval', {
                proposalId: proposalId,
                milestoneKey: milestoneKey,
                status: status
            });
            
            // Get project_id from data attribute or page context
            var projectId = $btn.data('project-id') || $btn.closest('[data-project-id]').data('project-id');
            
            // If project_id not found in data attributes, try to extract from page
            if (!projectId) {
                // Try to find it in the URL or page context
                var urlParams = new URLSearchParams(window.location.search);
                projectId = urlParams.get('project_id');
            }
            
            if (!projectId) {
                console.error('MNT: Could not find project_id for milestone approval');
                alert('Error: Project ID not found. Please refresh the page and try again.');
                return false;
            }
            
            // Fetch seller_id first to show in console logs
            var sellerId = null;
            if (proposalId) {
                $.ajax({
                    url: mntEscrow.ajaxUrl,
                    type: 'POST',
                    async: false,
                    data: {
                        action: 'mnt_get_seller_from_proposal',
                        nonce: mntEscrow.nonce,
                        proposal_id: proposalId
                    },
                    success: function(response) {
                        if (response.success && response.data.seller_id) {
                            sellerId = response.data.seller_id;
                        }
                    }
                });
            }
            
            // Get current user ID (client/buyer)
            var clientId = mntEscrow.currentUserId || null;
            
            var ajaxData = {
                action: 'mnt_approve_milestone',
                nonce: mntEscrow.nonce,
                proposal_id: proposalId,
                project_id: projectId,
                milestone_key: milestoneKey
            };
            
            console.log('=== MNT: MILESTONE APPROVAL CLICKED ===');
            console.log('URL:', mntEscrow.ajaxUrl);
            console.log('WordPress AJAX Data:', ajaxData);
            console.log('');
            console.log('=== Expected API Call ===');
            console.log('Endpoint: POST https://escrow-api-dfl6.onrender.com/api/escrow/client_confirm_milestone');
            console.log('API Payload:', {
                project_id: projectId,
                client_id: clientId || '(will be current_user_id from backend)',
                merchant_id: sellerId,
                milestone_key: milestoneKey,
                confirm_status: true
            });
            console.log('===================================');
            console.log('');
            
            if (!confirm('Approve this milestone and release funds to the seller?')) {
                return false;
            }
            
            var originalText = $btn.text();
            $btn.prop('disabled', true).text('Approving & releasing funds...');
            
            $.ajax({
                url: mntEscrow.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    console.log('');
                    console.log('=== MNT: MILESTONE APPROVAL RESPONSE ===');
                    console.log('Success:', response.success);
                    console.log('Message:', response.data && response.data.message);
                    console.log('Full Response:', response);
                    console.log('======================================');
                    console.log('');
                    
                    if (response.success) {
                        alert(response.data.message || 'Milestone approved! Funds released to seller wallet.');
                        // Only reload after successful API response
                        location.reload();
                    } else {
                        // Show full error message (includes formatted details from backend)
                        var errorHtml = response.data.message || 'Failed to approve milestone.';
                        
                        // Create a temporary div to show formatted HTML error
                        var $errorDiv = $('<div style="max-width: 600px; max-height: 500px; overflow: auto; text-align: left; padding: 20px;">' + errorHtml + '</div>');
                        
                        // Show in a modal or alert depending on what's available
                        if (typeof bootbox !== 'undefined') {
                            bootbox.alert({
                                message: errorHtml,
                                size: 'large'
                            });
                        } else {
                            // Fallback: create a simple modal
                            var $modal = $('<div class="mnt-error-modal" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); z-index: 99999; max-width: 700px; max-height: 80vh; overflow: auto;"><button class="mnt-close-modal" style="float: right; background: #dc2626; color: white; border: none; padding: 5px 15px; border-radius: 4px; cursor: pointer;">Close</button>' + errorHtml + '</div>');
                            var $overlay = $('<div class="mnt-error-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 99998;"></div>');
                            
                            $('body').append($overlay, $modal);
                            
                            $('.mnt-close-modal, .mnt-error-overlay').on('click', function() {
                                $('.mnt-error-modal, .mnt-error-overlay').remove();
                            });
                        }
                        
                        console.error('MNT Milestone Approval Error Details:', response.data);
                        $btn.prop('disabled', false).text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('MNT Milestone Approval Error:', error);
                    alert('Error: Failed to approve milestone. Please try again.');
                    $btn.prop('disabled', false).text(originalText);
                }
            });
            
            return false;
        }
    });

    /**
     * Complete Contract Handler - Non-milestone projects
     * Intercepts "Complete without review" and "Complete contract" buttons
     * Calls client_release_funds API to release funds from escrow to seller wallet
     */
    $(document).on('click', '.tb_complete_project, .tb_rating_project', function(e) {
        var $btn = $(this);
        var proposalId = $btn.data('proposal_id');
        var userId = $btn.data('user_id');
        
        // Check if this is a milestone project - if so, let default handler take over
        var isMilestoneProject = $btn.closest('.tb-completetask').find('.tb_update_milestone').length > 0;
        
        if (isMilestoneProject) {
            console.log('MNT: Milestone project detected, using default handler');
            return true; // Let taskbot handle milestone projects
        }
        
        e.preventDefault();
        e.stopImmediatePropagation();
        
        console.log('MNT: Intercepting complete contract button', {
            proposalId: proposalId,
            userId: userId,
            buttonClass: $btn.attr('class')
        });
        
        console.log('MNT: Current page URL:', window.location.href);
        console.log('MNT: URL search params:', window.location.search);
        
        // Get project_id from data attribute or page context
        var projectId = $btn.data('project-id') || $btn.data('project_id') || $btn.closest('[data-project-id]').data('project-id');
        
        console.log('MNT: Initial projectId from button:', projectId);
        console.log('MNT: Button data-project-id:', $btn.data('project-id'));
        console.log('MNT: Button data-project_id:', $btn.data('project_id'));
        console.log('MNT: Closest [data-project-id]:', $btn.closest('[data-project-id]').data('project-id'));
        
        // DON'T get from URL - the URL "id" parameter is actually the proposal_id, not project_id!
        // If project_id not found in button data, fetch it from proposal
        if (!projectId && proposalId) {
            console.log('MNT: Fetching project_id from proposal via AJAX...');
            // We need to make an AJAX call to get the project ID from proposal
            $.ajax({
                url: mntEscrow.ajaxUrl,
                type: 'POST',
                async: false, // Make synchronous to get projectId before proceeding
                data: {
                    action: 'mnt_get_project_from_proposal',
                    nonce: mntEscrow.nonce,
                    proposal_id: proposalId
                },
                success: function(response) {
                    console.log('MNT: Get project from proposal response:', response);
                    if (response.success && response.data.project_id) {
                        projectId = response.data.project_id;
                        console.log('MNT: Got projectId from proposal:', projectId);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('MNT: Error fetching project_id:', error);
                }
            });
        }
        
        console.log('MNT: Final projectId to be used:', projectId, '(type:', typeof projectId + ')');
        console.log('MNT: ProposalId for reference:', proposalId, '(type:', typeof proposalId + ')');
        
        if (!projectId) {
            console.error('MNT: Could not find project_id for complete contract');
            alert('Error: Project ID not found. Please refresh the page and try again.');
            return false;
        }
        
        // Fetch seller_id from proposal before proceeding
        var sellerId = null;
        if (proposalId) {
            $.ajax({
                url: mntEscrow.ajaxUrl,
                type: 'POST',
                async: false, // Make synchronous to get sellerId before logging
                data: {
                    action: 'mnt_get_seller_from_proposal',
                    nonce: mntEscrow.nonce,
                    proposal_id: proposalId
                },
                success: function(response) {
                    if (response.success && response.data.seller_id) {
                        sellerId = response.data.seller_id;
                    }
                }
            });
        }
        
        console.log('=== MNT: COMPLETE CONTRACT BUTTON CLICKED ===');
        console.log('Button data:', {
            proposalId: proposalId,
            userId: userId,
            projectId: projectId,
            sellerId: sellerId,
            buttonClass: $btn.attr('class')
        });
        console.log('');
        console.log('=== STEP 1: WordPress AJAX Request ===');
        console.log('URL:', mntEscrow.ajaxUrl);
        console.log('Action:', 'mnt_complete_escrow_funds');
        console.log('AJAX Data:', {
            action: 'mnt_complete_escrow_funds',
            nonce: mntEscrow.nonce,
            project_id: projectId,
            user_id: userId,
            proposal_id: proposalId
        });
        console.log('');
        console.log('=== STEP 2: Expected Backend Flow ===');
        console.log('Handler: handle_complete_escrow_funds_ajax() in init.php');
        console.log('Will get seller_id from proposal author (proposal_id:', proposalId + ')');
        console.log('Then call: Escrow->client_confirm()');
        console.log('');
        console.log('=== STEP 3: Expected API Call ===');
        console.log('Endpoint: POST https://escrow-api-dfl6.onrender.com/api/escrow/client_confirm');
        console.log('Expected Payload:', {
            project_id: projectId,
            client_id: userId,
            merchant_id: sellerId || '(will be fetched from proposal ' + proposalId + ' author)',
            confirm_status: true
        });
        console.log('Expected Response: {success: true, data: {...}}');
        console.log('===================================');
        console.log('');
        
        // Confirm action for "Complete without review"
        if ($btn.hasClass('tb_complete_project')) {
            if (!confirm('Are you sure you want to complete this contract without a review? Funds will be released to the seller.')) {
                return false;
            }
        }
        
        var originalText = $btn.text();
        $btn.prop('disabled', true).text('Releasing funds...');
        
        $.ajax({
            url: mntEscrow.ajaxUrl,
            type: 'POST',
            data: {
                action: 'mnt_complete_escrow_funds',
                nonce: mntEscrow.nonce,
                project_id: projectId,
                user_id: userId,
                proposal_id: proposalId
            },
            success: function(response) {
                console.log('');
                console.log('=== MNT: COMPLETE CONTRACT RESPONSE ===');
                console.log('Full Response:', response);
                console.log('Success:', response.success);
                console.log('Message:', response.data && response.data.message);
                console.log('=====================================');
                console.log('');
                
                if (response.success) {
                    // If this is "Complete contract" button (with review), process the review first
                    if ($btn.hasClass('tb_rating_project')) {
                        // Let the review be submitted, then complete
                        var rating = $('#tb_task_rating-' + proposalId).val();
                        var details = $('#tb_rating_details-' + proposalId).val();
                        var title = $('#tb_rating_title-' + proposalId).val();
                        
                        if (rating && details) {
                            alert(response.data.message || 'Contract completed! Funds released to seller wallet.');
                            // Continue with taskbot's review submission
                            location.reload();
                        } else {
                            alert('Please provide a rating and feedback before completing the contract.');
                            $btn.prop('disabled', false).text(originalText);
                            return;
                        }
                    } else {
                        // "Complete without review" - just show success and reload
                        alert(response.data.message || 'Contract completed! Funds released to seller wallet.');
                        location.reload();
                    }
                } else {
                    // Enhanced error handling
                    var errorMessage = response.data && response.data.message ? response.data.message : 'Failed to complete contract.';
                    
                    // Check if this is a "Transaction not found" error
                    if (errorMessage.includes('Transaction not found') || errorMessage.includes('not found')) {
                        errorMessage = 'No escrow transaction found for this project.\n\n' +
                            'Possible reasons:\n' +
                            '• The project was not hired through escrow\n' +
                            '• The escrow payment was not completed\n' +
                            '• The project ID does not match any escrow transaction\n\n' +
                            'Project ID: ' + projectId + '\n' +
                            'User ID: ' + userId + '\n\n' +
                            'Please check if this project was paid through escrow.';
                    }
                    
                    // Show error in a more user-friendly format
                    if (confirm(errorMessage + '\n\nWould you like to see the full technical details?')) {
                        // Show full error details
                        var fullError = 'Full Error Details:\n\n' + 
                            JSON.stringify(response, null, 2);
                        alert(fullError);
                    }
                    
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('MNT Complete Contract Error:', error);
                alert('Error: Failed to complete contract. Please try again.');
                $btn.prop('disabled', false).text(originalText);
            }
        });
        
        return false;
    });

})(jQuery);

