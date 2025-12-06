<?php
// This template is included by the theme page-create-escrow.php
// Do not call get_header() or get_footer() here

$escrow_response = null;
$escrow_error = null;



$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : get_current_user_id();
$merchant_id = isset($_GET['merchant_id']) ? intval($_GET['merchant_id']) : 0;
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$proposal_id = isset($_GET['proposal_id']) ? intval($_GET['proposal_id']) : 0;
$milestone_key = isset($_GET['milestone_key']) ? sanitize_text_field($_GET['milestone_key']) : '';
$milestone_title = isset($_GET['milestone_title']) ? sanitize_text_field($_GET['milestone_title']) : '';

// DEBUG: Log URL parameters
error_log('CREATE ESCROW DEBUG - URL Params:');
error_log('milestone_key: ' . $milestone_key);
error_log('milestone_title: ' . $milestone_title);
error_log('proposal_id: ' . $proposal_id);

// Fetch user details
$buyer = get_userdata($client_id);
$seller = get_userdata($merchant_id);

// Fetch project and proposal details
$project = $project_id ? get_post($project_id) : null;
$proposal = $proposal_id ? get_post($proposal_id) : null;

// Fetch milestone details if milestone_key is provided
$milestone = null;
$is_milestone = false;
if ($proposal_id) {
    // Get proposal meta which contains milestones
    $proposal_meta = get_post_meta($proposal_id, 'proposal_meta', true);
    $proposal_meta = !empty($proposal_meta) ? $proposal_meta : array();
    
    $proposal_type = !empty($proposal_meta['proposal_type']) ? $proposal_meta['proposal_type'] : '';
    $milestones = !empty($proposal_meta['milestone']) ? $proposal_meta['milestone'] : array();
    $has_milestones = !empty($milestones) && is_array($milestones) && count($milestones) > 0;
    
    // A proposal is considered milestone-based if it has milestone data OR proposal_type is 'milestone'
    $is_milestone = ($proposal_type === 'milestone' || $has_milestones);
    
    if ($is_milestone && !empty($milestone_key) && $has_milestones) {
        // Search for milestone by key
        foreach ($milestones as $ms_key => $ms) {
            if ($ms_key === $milestone_key) {
                $milestone = $ms;
                $milestone['key'] = $ms_key; // Add the key to the milestone data
                break;
            }
        }
    }
}

// Check if there's an existing pending escrow for this project
$existing_escrow_status = null;
if ($project_id) {
    $existing_escrow_status = get_post_meta($project_id, 'mnt_escrow_status', true);
    // If existing escrow is pending, populate response to show modal
    if (strtoupper($existing_escrow_status) === 'PENDING') {
        $escrow_response = [
            'project_id' => $project_id,
            'status' => $existing_escrow_status,
            'amount' => get_post_meta($project_id, 'mnt_escrow_amount', true),
            'client_id' => get_post_meta($project_id, 'mnt_escrow_buyer', true),
            'merchant_id' => get_post_meta($project_id, 'mnt_escrow_seller', true),
            'message' => 'Existing pending escrow found for this project'
        ];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_escrow_nonce']) && wp_verify_nonce($_POST['create_escrow_nonce'], 'create_escrow')) {
    $project_id = intval($_POST['project_id']);
    $merchant_id = intval($_POST['merchant_id']);
    $client_id = intval($_POST['client_id']);
    $amount = floatval($_POST['amount']);
    $proposal_id = isset($_POST['proposal_id']) ? intval($_POST['proposal_id']) : 0;
    $milestone_key_post = isset($_POST['milestone_key']) ? sanitize_text_field($_POST['milestone_key']) : '';

    // Check for valid project_id
    if (empty($project_id) || $project_id === 0) {
        $escrow_error = '<strong>Error:</strong> Project ID is missing or invalid. Please ensure you are hiring for a valid project.';
    } else {
        $buyer_check = get_userdata($client_id);
        $seller_check = get_userdata($merchant_id);
        if (!$buyer_check || !$seller_check) {
            $escrow_error = '<strong>Error:</strong> ';
            if (!$buyer_check) {
                $escrow_error .= 'Buyer (client_id) user not found. ';
            }
            if (!$seller_check) {
                $escrow_error .= 'Seller (merchant_id) user not found.';
            }
        } else {
            $escrow_project_id = (string)$project_id;
            
            // Check if this is a milestone payment
            $is_milestone_payment = false;
            $milestone_data = null;
            
            if (!empty($proposal_id) && !empty($milestone_key_post)) {
                // Fetch proposal meta to get milestone details
                $proposal_meta_check = get_post_meta($proposal_id, 'proposal_meta', true);
                if (!empty($proposal_meta_check['milestone'][$milestone_key_post])) {
                    $milestone_data = $proposal_meta_check['milestone'][$milestone_key_post];
                    $is_milestone_payment = true;
                }
            }
            
            // Use appropriate API endpoint
            if ($is_milestone_payment && $milestone_data) {
                // Prepare milestone array for API
                $milestones_array = [[
                    'key' => $milestone_key_post,
                    'title' => $milestone_data['title'] ?? 'Milestone',
                    'amount' => floatval($milestone_data['price'] ?? $amount),
                    'description' => $milestone_data['detail'] ?? ''
                ]];
                
                error_log('MNT: Creating milestone escrow with data: ' . json_encode([
                    'merchant_id' => $merchant_id,
                    'client_id' => $client_id,
                    'project_id' => $escrow_project_id,
                    'milestone_key' => $milestone_key_post,
                    'milestones_array' => $milestones_array
                ]));
                
                $escrow_result = \MNT\Api\Escrow::create_milestone_transaction(
                    (string)$merchant_id,
                    (string)$client_id,
                    $escrow_project_id,
                    $milestones_array
                );
                
                error_log('MNT: Milestone escrow creation result: ' . json_encode($escrow_result));
                error_log('MNT: Milestone escrow result is_null: ' . (is_null($escrow_result) ? 'YES' : 'NO'));
                error_log('MNT: Milestone escrow result is_array: ' . (is_array($escrow_result) ? 'YES' : 'NO'));
                error_log('MNT: Milestone escrow result empty: ' . (empty($escrow_result) ? 'YES' : 'NO'));
            } else {
                // Regular escrow transaction
                // API signature: create($merchant_id, $client_id, $project_id, $amount, $auto_release)
                error_log('MNT: Creating regular escrow - merchant_id: ' . $merchant_id . ', client_id: ' . $client_id . ', project_id: ' . $escrow_project_id . ', amount: ' . $amount);
                $escrow_result = \MNT\Api\Escrow::create((string)$merchant_id, (string)$client_id, $escrow_project_id, $amount);
            }
            
            // Check if escrow was created successfully
            $escrow_success = !empty($escrow_result['status']) || !empty($escrow_result['project_id']) || (isset($escrow_result['message']) && stripos($escrow_result['message'], 'success') !== false);
            
            if ($escrow_success) {
                // Link escrow to project
                $escrow_id = $escrow_result['escrow_id'] ?? 'API-' . $project_id;
                update_post_meta($project_id, 'mnt_escrow_id', $escrow_id);
                update_post_meta($project_id, 'mnt_escrow_amount', $amount);
                update_post_meta($project_id, 'mnt_escrow_buyer', $client_id);
                update_post_meta($project_id, 'mnt_escrow_seller', $merchant_id);
                update_post_meta($project_id, 'mnt_escrow_status', $escrow_result['status'] ?? 'pending');
                update_post_meta($project_id, 'mnt_escrow_created_at', current_time('mysql'));
                update_post_meta($project_id, '_post_project_status', 'hired');
                
                // Store milestone escrow info if this is a milestone payment
                if ($is_milestone_payment && !empty($milestone_key_post)) {
                    // Store in project meta
                    $milestone_escrows = get_post_meta($project_id, 'mnt_milestone_escrows', true);
                    $milestone_escrows = !empty($milestone_escrows) ? $milestone_escrows : [];
                    $milestone_escrows[$milestone_key_post] = [
                        'escrow_id' => $escrow_id,
                        'amount' => $amount,
                        'status' => $escrow_result['status'] ?? 'pending',
                        'created_at' => current_time('mysql')
                    ];
                    update_post_meta($project_id, 'mnt_milestone_escrows', $milestone_escrows);
                    
                    // Update milestone status in proposal meta to mark it as paid/escrowed
                    $proposal_meta_update = get_post_meta($proposal_id, 'proposal_meta', true);
                    if (!empty($proposal_meta_update['milestone'][$milestone_key_post])) {
                        $proposal_meta_update['milestone'][$milestone_key_post]['status'] = 'hired';
                        $proposal_meta_update['milestone'][$milestone_key_post]['escrow_id'] = $escrow_id;
                        update_post_meta($proposal_id, 'proposal_meta', $proposal_meta_update);
                    }
                }
                
                // Optionally update proposal status if proposal_id is passed
                if (!empty($proposal_id)) {
                    wp_update_post(['ID' => $proposal_id, 'post_status' => 'hired']);
                    update_post_meta($proposal_id, 'mnt_escrow_id', $escrow_id);
                    update_post_meta($proposal_id, 'project_id', $project_id);
                }
                
                $escrow_response = $escrow_result;
            } else {
                // Show full error message and reason if available
                $msg = '';
                
                // Check if API returned null or empty
                if (is_null($escrow_result) || $escrow_result === false) {
                    $msg .= '<strong style="color: #dc2626;">API Connection Error:</strong><br>';
                    $msg .= '• The API did not return a response. This could indicate:<br>';
                    $msg .= '  - API server is down or unreachable<br>';
                    $msg .= '  - Network/connection timeout<br>';
                    $msg .= '  - Invalid API endpoint<br>';
                    $msg .= '<br><em>Please check the debug log for detailed error messages.</em><br><br>';
                } elseif (empty($escrow_result)) {
                    $msg .= '<strong style="color: #dc2626;">Empty API Response:</strong><br>';
                    $msg .= '• The API returned an empty response without any data or error information.<br>';
                    $msg .= '<br><em>Please check the debug log for the raw API request and response.</em><br><br>';
                }
                
                // Check for 'detail' field (API validation errors)
                if (!empty($escrow_result['detail'])) {
                    if (is_array($escrow_result['detail'])) {
                        // Handle array of validation errors
                        $msg .= '<strong>Validation Errors:</strong><br>';
                        foreach ($escrow_result['detail'] as $validation_error) {
                            if (is_array($validation_error)) {
                                $field = isset($validation_error['loc']) ? implode('.', $validation_error['loc']) : 'unknown';
                                $error_msg = $validation_error['msg'] ?? 'Unknown error';
                                $msg .= '• <strong>' . esc_html($field) . ':</strong> ' . esc_html($error_msg) . '<br>';
                            } else {
                                $msg .= '• ' . esc_html($validation_error) . '<br>';
                            }
                        }
                    } else {
                        // Single detail message
                        $msg .= '<strong>Error:</strong> ' . esc_html($escrow_result['detail']) . '<br>';
                    }
                }
                
                // Check for 'error' field
                if (!empty($escrow_result['error'])) {
                    $msg .= '<strong>Error:</strong> ' . esc_html($escrow_result['error']) . '<br>';
                }
                
                // Check for 'reason' field
                if (!empty($escrow_result['reason'])) {
                    $msg .= '<strong>Reason:</strong> ' . esc_html($escrow_result['reason']) . '<br>';
                }
                
                // Check for 'message' field
                if (!empty($escrow_result['message'])) {
                    $msg .= '<strong>Message:</strong> ' . esc_html($escrow_result['message']) . '<br>';
                }
                
                // Default message if nothing was captured
                if (empty($msg)) {
                    $msg .= 'Failed to create escrow. Please try again or contact support.<br>';
                }
                
                // Always append the full raw API response for debugging
                $msg .= '<br><strong style="color: #dc2626;">Full API Response:</strong><br>';
                $msg .= '<pre style="background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 8px; overflow: auto; max-height: 400px; font-size: 12px; white-space: pre-wrap; word-wrap: break-word;">';
                $msg .= esc_html(print_r($escrow_result, true));
                $msg .= '</pre>';
                
                $escrow_error = $msg;
            }
        }
    }
}
?>

<div class="tb-dhb-mainheading">
    <div>
        <h4><?php esc_html_e('Create Escrow Transaction', 'taskbot'); ?></h4>
        <div class="tk-sortby">
            <span><?php esc_html_e('Secure payment for project hiring', 'taskbot'); ?></span>
        </div>
    </div>
    <div class="tb-dhb-mainheading-right">
        <?php
        $projects_url = Taskbot_Profile_Menu::taskbot_profile_menu_link('projects', $client_id, true, 'listing');
        ?>
        <a href="<?php echo esc_url($projects_url); ?>" class="tb-btn tb-btn-cancel">
            <i class="tb-icon-x"></i> <?php esc_html_e('Cancel', 'taskbot'); ?>
        </a>
    </div>
</div>

<div class="tb-dhb-box">
    <div class="tb-dhb-box-wrapper">
                
        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">
                <!-- Buyer Details -->
                <div class="tb-form-group">
                    <h5 class="tb-dhb-subtitle"><?php esc_html_e('Buyer (Client)', 'taskbot'); ?></h5>
                    <div class="tb-userinfo-box">
                        <div class="tb-userinfo-content">
                            <span><strong><?php esc_html_e('ID:', 'taskbot'); ?></strong> <?php echo esc_html($buyer ? $buyer->ID : ''); ?></span>
                            <span><strong><?php esc_html_e('Name:', 'taskbot'); ?></strong> <?php echo esc_html($buyer ? $buyer->display_name : ''); ?></span>
                            <span><strong><?php esc_html_e('Email:', 'taskbot'); ?></strong> <?php echo esc_html($buyer ? $buyer->user_email : ''); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">

                <!-- Seller Details -->
                <div class="tb-form-group">
                    <h5 class="tb-dhb-subtitle"><?php esc_html_e('Seller (Merchant)', 'taskbot'); ?></h5>
                    <div class="tb-userinfo-box">
                        <div class="tb-userinfo-content">
                            <span><strong><?php esc_html_e('ID:', 'taskbot'); ?></strong> <?php echo esc_html($seller ? $seller->ID : ''); ?></span>
                            <span><strong><?php esc_html_e('Name:', 'taskbot'); ?></strong> <?php echo esc_html($seller ? $seller->display_name : ''); ?></span>
                            <span><strong><?php esc_html_e('Email:', 'taskbot'); ?></strong> <?php echo esc_html($seller ? $seller->user_email : ''); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 col-md-6 col-sm-12">

                <!-- Project Details -->
                <div class="tb-form-group">
                    <h5 class="tb-dhb-subtitle"><?php esc_html_e('Project Details', 'taskbot'); ?></h5>
                    <div class="tb-userinfo-box">
                        <div class="tb-userinfo-content">
                            <?php if ($project): ?>
                                <span><strong><?php esc_html_e('ID:', 'taskbot'); ?></strong> <?php echo esc_html($project->ID); ?></span>
                                <span><strong><?php esc_html_e('Title:', 'taskbot'); ?></strong> <?php echo esc_html($project->post_title); ?></span>
                                <span><strong><?php esc_html_e('Status:', 'taskbot'); ?></strong> <?php echo esc_html($project->post_status); ?></span>
                            <?php else: ?>
                                <em><?php esc_html_e('No project found.', 'taskbot'); ?></em>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">

                <!-- Proposal Details -->
                <div class="tb-form-group">
                    <h5 class="tb-dhb-subtitle"><?php esc_html_e('Proposal Details', 'taskbot'); ?></h5>
                    <div class="tb-userinfo-box">
                        <div class="tb-userinfo-content">
                            <?php if ($proposal): ?>
                                <span><strong><?php esc_html_e('ID:', 'taskbot'); ?></strong> <?php echo esc_html($proposal->ID); ?></span>
                                <span><strong><?php esc_html_e('Title:', 'taskbot'); ?></strong> <?php echo esc_html($proposal->post_title); ?></span>
                                <span><strong><?php esc_html_e('Status:', 'taskbot'); ?></strong> <?php echo esc_html($proposal->post_status); ?></span>
                                <?php if ($is_milestone): ?>
                                    <span><strong><?php esc_html_e('Type:', 'taskbot'); ?></strong> <span class="tk-badge-success" style="background: #28a745; color: white; padding: 4px 12px; border-radius: 4px; font-size: 12px;"><?php esc_html_e('Milestone Project', 'taskbot'); ?></span></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <em><?php esc_html_e('No proposal found.', 'taskbot'); ?></em>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_milestone && $milestone): ?>
        <!-- Milestone Details -->
        <div class="row">
            <div class="col-12">
                <div class="tb-form-group">
                    <h5 class="tb-dhb-subtitle" style="display: flex; align-items: center; gap: 8px;">
                        <i class="tb-icon-check-circle" style="color: #28a745; font-size: 20px;"></i> 
                        <?php esc_html_e('Milestone Payment Details', 'taskbot'); ?>
                    </h5>
                    <div class="tb-userinfo-box" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-left: 4px solid #28a745; padding: 20px;">
                        <div class="tb-userinfo-content">
                            <span><strong><?php esc_html_e('Milestone Key:', 'taskbot'); ?></strong> <code style="background: #fff; padding: 2px 8px; border-radius: 4px;"><?php echo esc_html($milestone['key'] ?? ''); ?></code></span>
                            <span><strong><?php esc_html_e('Title:', 'taskbot'); ?></strong> <?php echo esc_html($milestone['title'] ?? $milestone_title ?? 'Untitled'); ?></span>
                            <?php if (!empty($milestone['detail'])): ?>
                                <span><strong><?php esc_html_e('Description:', 'taskbot'); ?></strong> <?php echo esc_html($milestone['detail']); ?></span>
                            <?php endif; ?>
                            <span style="background: white; padding: 15px; border-radius: 8px; margin-top: 10px; display: inline-block;">
                                <strong><?php esc_html_e('Amount:', 'taskbot'); ?></strong> 
                                <span style="color: #28a745; font-weight: 700; font-size: 24px; margin-left: 8px;">
                                    ₦<?php echo esc_html(number_format($milestone['price'] ?? $amount, 2)); ?>
                                </span>
                            </span>
                            <?php if (!empty($milestone['status'])): ?>
                                <span><strong><?php esc_html_e('Status:', 'taskbot'); ?></strong> 
                                    <span class="tk-badge" style="background: #e0e7ff; color: #4f46e5; padding: 4px 12px; border-radius: 4px;">
                                        <?php echo esc_html(ucfirst($milestone['status'])); ?>
                                    </span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

                <?php if ($escrow_response || (isset($escrow_result['detail']) && $escrow_result['detail'] === 'Project already exist.')): ?>
                    <div class="tk-alert tk-alert-success">
                        <?php if ($escrow_response): ?>
                            <strong><?php esc_html_e('Escrow Created!', 'taskbot'); ?></strong><br>
                            <?php if (!empty($escrow_response['escrow_id'])): ?>
                                <strong><?php esc_html_e('Escrow ID:', 'taskbot'); ?></strong> <?php echo esc_html($escrow_response['escrow_id']); ?><br>
                            <?php endif; ?>
                            <strong><?php esc_html_e('Status:', 'taskbot'); ?></strong> <?php echo esc_html($escrow_response['status'] ?? ''); ?><br>
                            <strong><?php esc_html_e('Amount:', 'taskbot'); ?></strong> ₦<?php echo esc_html($escrow_response['amount'] ?? ''); ?><br>
                            <?php if (!empty($escrow_response['merchant_id'])): ?>
                                <strong><?php esc_html_e('Merchant ID:', 'taskbot'); ?></strong> <?php echo esc_html($escrow_response['merchant_id'] ?? ''); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($escrow_response['client_id'])): ?>
                                <strong><?php esc_html_e('Client ID:', 'taskbot'); ?></strong> <?php echo esc_html($escrow_response['client_id'] ?? ''); ?><br>
                            <?php endif; ?>
                            <strong><?php esc_html_e('Project ID:', 'taskbot'); ?></strong> <?php echo esc_html($escrow_response['project_id'] ?? ''); ?><br>
                            <?php if (!empty($escrow_response['message'])): ?>
                                <br><strong><?php esc_html_e('Message:', 'taskbot'); ?></strong> <?php echo esc_html($escrow_response['message']); ?>
                            <?php endif; ?>
                            <details style="margin-top:12px;">
                                <summary style="cursor:pointer;font-weight:600;"><?php esc_html_e('Show Raw API Response', 'taskbot'); ?></summary>
                                <pre class="tk-code-block"><?php echo esc_html(print_r($escrow_response, true)); ?></pre>
                            </details>
                        <?php else: ?>
                            <strong><?php esc_html_e('Escrow Already Exists for this Project.', 'taskbot'); ?></strong><br>
                            <strong><?php esc_html_e('Project ID:', 'taskbot'); ?></strong> <?php echo esc_html($project_id); ?><br>
                            <strong><?php esc_html_e('Merchant ID:', 'taskbot'); ?></strong> <?php echo esc_html($merchant_id); ?><br>
                            <strong><?php esc_html_e('Client ID:', 'taskbot'); ?></strong> <?php echo esc_html($client_id); ?><br>
                            <strong><?php esc_html_e('Amount:', 'taskbot'); ?></strong> ₦<?php echo esc_html($amount); ?><br>
                            <br><strong><?php esc_html_e('Message:', 'taskbot'); ?></strong> <?php esc_html_e('Project already exists. You can move funds to escrow below.', 'taskbot'); ?>
                            <details style="margin-top:12px;">
                                <summary style="cursor:pointer;font-weight:600;"><?php esc_html_e('Show Raw API Response', 'taskbot'); ?></summary>
                                <pre class="tk-code-block"><?php echo esc_html(print_r($escrow_result, true)); ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>

                    <!-- Modal for Complete Escrow -->
                    <?php 
                    // Show modal if status is PENDING or if project already exists
                    $show_modal = false;
                    if (!empty($escrow_response['status']) && strtoupper($escrow_response['status']) === 'PENDING') {
                        $show_modal = true;
                    } elseif (isset($escrow_result['detail']) && $escrow_result['detail'] === 'Project already exist.') {
                        $show_modal = true;
                    }
                    ?>
                    <?php if ($show_modal): ?>
                    <div class="modal fade tk-popup-modal show" id="mnt-complete-escrow-modal" tabindex="-1" aria-hidden="false" style="display:block !important;">
                        <div class="modal-backdrop fade show" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1040;"></div>
                        <div class="modal-dialog modal-dialog-centered" style="position:relative;z-index:1050;">
                            <div class="modal-content">
                                <div class="tk-popup_title">
                                    <h5><?php esc_html_e('Release Funds to Escrow', 'taskbot'); ?></h5>
                                    <a href="javascript:void(0);" class="close" onclick="jQuery('#mnt-complete-escrow-modal').hide(); jQuery('.modal-backdrop').remove();"><i class="tb-icon-x"></i></a>
                                </div>
                                <div class="modal-body tk-popup-content">
                                    <?php if ($escrow_response): ?>
                                        <p><?php esc_html_e('Escrow transaction created successfully with PENDING status. Click the button below to release funds from your wallet to the escrow account.', 'taskbot'); ?></p>
                                    <?php else: ?>
                                        <p><?php esc_html_e('An escrow transaction already exists for this project but funds have not been released yet. Click the button below to release funds from your wallet to the escrow account.', 'taskbot'); ?></p>
                                    <?php endif; ?>
                                    <button id="mnt-complete-escrow-btn" class="tk-btn-solid-lg" 
                                            data-project-id="<?php echo esc_attr($escrow_response['project_id'] ?? $project_id ?? ''); ?>" 
                                            data-user-id="<?php echo esc_attr(isset($escrow_response['client_id']) ? $escrow_response['client_id'] : (isset($client_id) ? $client_id : '')); ?>"
                                            data-seller-id="<?php echo esc_attr(isset($escrow_response['merchant_id']) ? $escrow_response['merchant_id'] : (isset($merchant_id) ? $merchant_id : '')); ?>">
                                        <?php esc_html_e('Release Funds', 'taskbot'); ?>
                                    </button>
                                    <div id="mnt-complete-escrow-message" class="tk-alert" style="margin-top:16px;display:none;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Manually include jQuery, mntEscrow object, and mnt-complete-escrow.js -->
                    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                    <script>
                    var mntEscrow = {
                        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
                        nonce: '<?php echo wp_create_nonce('mnt_nonce'); ?>'
                    };
                    </script>
                    <script src="<?php echo plugins_url('assets/js/mnt-complete-escrow.js', dirname(__FILE__)); ?>?v=debug"></script>

                <?php elseif ($escrow_error): ?>
                    <div class="tk-alert tk-alert-error">
                        <?php echo $escrow_error; ?>
                        <?php if (isset($escrow_result)): ?>
                            <details style="margin-top:12px;">
                                <summary style="cursor:pointer;font-weight:600;"><?php esc_html_e('Show Raw API Response', 'taskbot'); ?></summary>
                                <pre class="tk-code-block"><?php echo esc_html(print_r($escrow_result, true)); ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

        <div class="row">
            <div class="col-12">
                <form method="post" class="tb-themeform">
                    <?php wp_nonce_field('create_escrow', 'create_escrow_nonce'); ?>
                    <input type="hidden" name="project_id" value="<?php echo esc_attr($project_id); ?>">
                    <input type="hidden" name="merchant_id" value="<?php echo esc_attr($merchant_id); ?>">
                    <input type="hidden" name="client_id" value="<?php echo esc_attr($client_id); ?>">
                    <input type="hidden" name="proposal_id" value="<?php echo esc_attr($proposal_id); ?>">
                    <?php if (!empty($milestone_key)): ?>
                        <input type="hidden" name="milestone_key" value="<?php echo esc_attr($milestone_key); ?>">
                    <?php endif; ?>
                    <fieldset>
                        <div class="tb-themeform__wrap">
                            <div class="form-group">
                                <label class="tb-label"><?php esc_html_e('Escrow Amount (₦)', 'taskbot'); ?></label>
                                <input type="number" name="amount" class="form-control" min="1" step="0.01" value="<?php echo esc_attr($amount); ?>" placeholder="<?php esc_attr_e('Enter amount', 'taskbot'); ?>" required>
                            </div>
                            <div class="tb-dhbbtn-holder">
                                <button type="submit" class="tb-btn tb-greenbtn">
                                    <i class="tb-icon-lock"></i> <?php echo $is_milestone && !empty($milestone_key) ? esc_html__('Pay & Escrow Milestone', 'taskbot') : esc_html__('Create Escrow & Hire Seller', 'taskbot'); ?>
                                </button>
                                <a href="javascript:history.back()" class="tb-btn tb-btn-outline">
                                    <i class="tb-icon-arrow-left"></i> <?php esc_html_e('Go Back', 'taskbot'); ?>
                                </a>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Escrow Page Styling */
.tb-userinfo-box {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
    margin-bottom: 20px;
}

.tb-userinfo-content {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.tb-userinfo-content span {
    display: flex;
    align-items: center;
    font-size: 14px;
    color: #495057;
}

.tb-userinfo-content strong {
    min-width: 80px;
    color: #1e293b;
    font-weight: 600;
}

.tb-form-group {
    margin-bottom: 25px;
}

.tb-dhb-subtitle {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
}

.tb-dhb-mainheading {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 25px;
    gap: 20px;
}

.tb-dhb-mainheading h4 {
    margin: 0 0 8px 0;
    font-size: 24px;
    font-weight: 600;
    color: #1e293b;
}

.tb-dhb-mainheading-right {
    display: flex;
    gap: 10px;
}

.tb-btn-cancel {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    font-size: 14px;
    font-weight: 600;
    background: #fff;
    color: #6b7280;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.tb-btn-cancel:hover {
    background: #f3f4f6;
    color: #374151;
    border-color: #9ca3af;
}

.tb-btn-outline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 28px;
    font-size: 16px;
    font-weight: 600;
    background: #fff;
    color: #6b7280;
    border: 2px solid #d1d5db;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.tb-btn-outline:hover {
    background: #f3f4f6;
    color: #374151;
    border-color: #9ca3af;
    transform: translateY(-1px);
}

.tb-themeform {
    background: #fff;
    padding: 25px;
    border-radius: 8px;
    border: 1px solid #e9ecef;
}

.tb-themeform__wrap {
    padding: 0;
}

.tb-themeform .form-group {
    margin-bottom: 20px;
}

.tb-label {
    display: block;
    font-size: 15px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 10px;
}

.tb-themeform input[type="number"] {
    width: 100%;
    padding: 12px 16px;
    font-size: 15px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.tb-themeform input[type="number"]:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.tb-dhbbtn-holder {
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
    margin-top: 20px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.tb-greenbtn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 14px 32px;
    font-size: 16px;
    font-weight: 600;
    background: #10b981;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.tb-greenbtn:hover {
    background: #059669;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.tb-greenbtn i {
    font-size: 18px;
}

.tk-alert {
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid;
}

.tk-alert-success {
    background: #d1fae5;
    color: #065f46;
    border-color: #10b981;
}

.tk-alert-error {
    background: #fee2e2;
    color: #991b1b;
    border-color: #ef4444;
}

.tk-code-block {
    background: #2d3748;
    color: #f7fafc;
    padding: 12px;
    border-radius: 6px;
    overflow-x: auto;
    font-size: 13px;
    max-height: 300px;
    margin-top: 10px;
    font-family: 'Courier New', monospace;
}

.tk-popup-modal .modal-content {
    border-radius: 12px;
    border: none;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}

.tk-popup_title {
    padding: 20px 25px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tk-popup_title h5 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
}

.tk-popup-content {
    padding: 25px;
}

.tk-popup-content p {
    margin-bottom: 20px;
    color: #6b7280;
    line-height: 1.6;
}

.tk-btn-solid-lg {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 32px;
    font-size: 16px;
    font-weight: 600;
    background: #667eea;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
}

.tk-btn-solid-lg:hover {
    background: #5568d3;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

/* Responsive */
@media (max-width: 768px) {
    .tb-userinfo-content span {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }
    
    .tb-userinfo-content strong {
        min-width: auto;
    }
    
    .tb-themeform {
        padding: 20px 15px;
    }
}
</style>
