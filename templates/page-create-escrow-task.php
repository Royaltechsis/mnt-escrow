<?php
// This template is included by the theme page-create-escrow-task.php
// Do not call get_header() or get_footer() here
// For TASKS - maps task_id to project_id for API compatibility

$escrow_response = null;
$escrow_error = null;

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : get_current_user_id();
$merchant_id = isset($_GET['merchant_id']) ? intval($_GET['merchant_id']) : 0;
$task_id = isset($_GET['task_id']) ? intval($_GET['task_id']) : 0; // Task ID from cart
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$package_key = isset($_GET['package_key']) ? sanitize_text_field($_GET['package_key']) : '';

// Map task_id to project_id for API compatibility
$project_id = $task_id;

// Fetch user details
$buyer = get_userdata($client_id);
$seller = get_userdata($merchant_id);

// Get wallet balance for the logged-in user
$user_balance = 0;
if (class_exists('MNT\Api\Wallet')) {
    $wallet_result = \MNT\Api\Wallet::balance($client_id);
    if (!empty($wallet_result['balance'])) {
        $user_balance = floatval($wallet_result['balance']);
    }
}

// Fetch task details
$task = $task_id ? get_post($task_id) : null;
$task_product = $task_id ? wc_get_product($task_id) : null;

// Get package details if package_key is provided
$package_details = null;
if ($task_id && $package_key) {
    $taskbot_plans_values = get_post_meta($task_id, 'taskbot_product_plans', true);
    $taskbot_plans_values = !empty($taskbot_plans_values) ? $taskbot_plans_values : array();
    
    if (isset($taskbot_plans_values[$package_key])) {
        $package_details = $taskbot_plans_values[$package_key];
    }
}

// Check if order was created on cart page (from URL parameters)
$wc_order = null;
$wc_order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
$escrow_project_id = isset($_GET['escrow_project_id']) ? sanitize_text_field($_GET['escrow_project_id']) : null;

if ($wc_order_id && $escrow_project_id) {
    // Order was created on cart page - use existing order
    error_log('=== MNT: PAGE LOAD - Using Pre-Created Order from Cart ===');
    error_log('Order ID from URL: ' . $wc_order_id);
    error_log('Escrow Project ID from URL: ' . $escrow_project_id);
    
    $wc_order = wc_get_order($wc_order_id);
    
    if (!$wc_order || is_wp_error($wc_order)) {
        error_log('❌ Order not found: ' . $wc_order_id);
        $wc_order = null;
        $wc_order_id = null;
        $escrow_project_id = null;
    } else {
        error_log('✅ Successfully loaded pre-created order: #' . $wc_order_id);
    }
} else {
    // Fallback: Create order on page load if not provided (shouldn't happen in normal flow)
    error_log('=== MNT: PAGE LOAD - No order in URL, creating new one (FALLBACK) ===');
    
    if ($task_id && $client_id && $merchant_id && class_exists('WooCommerce')) {
        try {
            $wc_order = wc_create_order(['customer_id' => $client_id]);
            
            if (!is_wp_error($wc_order)) {
                $task_post = get_post($task_id);
                $task_title = $task_post ? $task_post->post_title : 'Task #' . $task_id;
                
                $item = new WC_Order_Item_Product();
                $item->set_name($task_title);
                $item->set_quantity(1);
                $item->set_subtotal($amount);
                $item->set_total($amount);
                $wc_order->add_item($item);
                
                $wc_order->add_meta_data('task_product_id', $task_id);
                $wc_order->add_meta_data('_mnt_seller_id', $merchant_id);
                $wc_order->add_meta_data('_mnt_buyer_id', $client_id);
                $wc_order->add_meta_data('payment_type', 'tasks');
                $wc_order->add_meta_data('package_key', $package_key);
                
                $wc_order->set_total($amount);
                $wc_order->set_status('pending');
                $wc_order->save();
                update_post_meta($wc_order->get_id(), '_mnt_task_id', $task_id);
                
                $wc_order_id = $wc_order->get_id();
                $escrow_project_id = "order-{$wc_order_id}";
                
                $wc_order->add_meta_data('mnt_escrow_project_id', $escrow_project_id);
                $wc_order->save();
                
                update_post_meta($task_id, 'mnt_last_order_id', $wc_order_id);
                update_post_meta($task_id, 'mnt_escrow_project_id', $escrow_project_id);
                
                error_log('✅ Fallback Order Created: #' . $wc_order_id);
                error_log('Escrow Project ID: ' . $escrow_project_id);
            } else {
                error_log('❌ Order creation failed: ' . $wc_order->get_error_message());
            }
        } catch (Exception $e) {
            error_log('❌ Exception: ' . $e->getMessage());
        }
    }
}

// Check if there's an existing escrow for this task
$existing_escrow_status = null;
if ($task_id) {
    $existing_escrow_id = get_post_meta($task_id, 'mnt_escrow_id', true);
    $existing_escrow_status = get_post_meta($task_id, 'mnt_escrow_status', true);
    
    if ($existing_escrow_id) {
        // Fetch current escrow status from API to get real-time status
        $seller_id = get_post_meta($task_id, 'mnt_escrow_seller', true);
        $current_escrow = \MNT\Api\Escrow::get_transaction($task_id, $seller_id);
        
        if ($current_escrow && isset($current_escrow['status'])) {
            // Update local status with current API status
            $existing_escrow_status = $current_escrow['status'];
            update_post_meta($task_id, 'mnt_escrow_status', $existing_escrow_status);
            
            // If escrow is pending, populate response to show modal
            if (strtoupper($existing_escrow_status) === 'PENDING') {
                $escrow_response = $current_escrow;
                $escrow_response['message'] = 'Existing pending escrow found for this task';
                
                // Ensure merchant_id is set for the modal button
                if (empty($escrow_response['merchant_id'])) {
                    $escrow_response['merchant_id'] = $seller_id ?: $merchant_id;
                    error_log('MNT: Added merchant_id to escrow_response: ' . $escrow_response['merchant_id']);
                }
            } elseif (strtoupper($existing_escrow_status) === 'FUNDED') {
                // Escrow is funded - trigger task hiring if not already hired
                $task_status = get_post_meta($task_id, '_post_project_status', true);
                $hired_status = get_post_meta($task_id, '_hired_status', true);
                
                error_log('MNT Page Load: Escrow FUNDED detected for task ' . $task_id);
                error_log('MNT Page Load: Task status = ' . $task_status . ', Hired status = ' . $hired_status);
                
                // Only hire if not already hired
                if ($task_status !== 'hired' && $hired_status !== 'hired') {
                    // Get buyer and seller IDs
                    $buyer_id = get_post_meta($task_id, 'mnt_escrow_buyer', true);
                    $seller_id = get_post_meta($task_id, 'mnt_escrow_seller', true);
                    
                    error_log('MNT Page Load: Buyer ID = ' . $buyer_id . ', Seller ID = ' . $seller_id);
                    error_log('MNT Page Load: WooCommerce active = ' . (class_exists('WooCommerce') ? 'YES' : 'NO'));
                    
                    if ($buyer_id && $seller_id) {
                        // Update task status to hired
                        update_post_meta($task_id, '_post_project_status', 'hired');
                        update_post_meta($task_id, '_hired_status', 'hired');
                        wp_update_post([
                            'ID' => $task_id,
                            'post_status' => 'hired'
                        ]);
                        
                        // Create WooCommerce order for the task purchase
                        if (class_exists('WooCommerce')) {
                            try {
                                $order = wc_create_order(['customer_id' => $buyer_id]);
                                
                                if (!is_wp_error($order)) {
                                    // Get task details
                                    $task_post = get_post($task_id);
                                    $task_title = $task_post ? $task_post->post_title : 'Task #' . $task_id;
                                    $escrow_amount = get_post_meta($task_id, 'mnt_escrow_amount', true);
                                    
                                    // Add task as order item
                                    $item = new WC_Order_Item_Product();
                                    $item->set_name($task_title);
                                    $item->set_quantity(1);
                                    $item->set_subtotal($escrow_amount);
                                    $item->set_total($escrow_amount);
                                    $order->add_item($item);
                                    
                                    // Store escrow metadata
                                    $order->add_meta_data('mnt_escrow_id', $existing_escrow_id);
                                    $order->add_meta_data('project_id', $task_id);
                                    $order->add_meta_data('task_product_id', $task_id);
                                    $order->add_meta_data('_mnt_seller_id', $seller_id);
                                    $order->add_meta_data('_mnt_buyer_id', $buyer_id);
                                    $order->add_meta_data('_task_status', 'hired');
                                    $order->add_meta_data('payment_type', 'escrow');
                                    $order->add_meta_data('escrow_funded', 'yes');
                                    
                                    // Add Taskbot-compatible invoice data
                                    $invoice_data = [
                                        'project_id' => $task_id,
                                        'project_type' => 'fixed',
                                        'seller_shares' => $escrow_amount,
                                        'payment_method' => 'escrow',
                                        'escrow_id' => $existing_escrow_id,
                                        'funded_at' => current_time('mysql')
                                    ];
                                    $order->add_meta_data('cus_woo_product_data', $invoice_data);
                                    
                                    $order->set_total($escrow_amount);
                                    $order->set_status('completed');
                                    update_post_meta($order->get_id(), '_mnt_task_id', $task_id);
                                    $order->save();
                                    
                                    $order_id = $order->get_id();
                                    update_post_meta($task_id, 'mnt_wc_order_id', $order_id);
                                    
                                    error_log('MNT: Task hired on page load - Task: ' . $task_id . ', Order: ' . $order_id);
                                } else {
                                    error_log('MNT Page Load: WooCommerce order creation failed - ' . $order->get_error_message());
                                }
                            } catch (Exception $e) {
                                error_log('MNT: Exception hiring task on page load: ' . $e->getMessage());
                            }
                        } else {
                            error_log('MNT Page Load: WooCommerce class not found');
                        }
                    } else {
                        error_log('MNT Page Load: Missing buyer or seller ID - Buyer: ' . $buyer_id . ', Seller: ' . $seller_id);
                    }
                } else {
                    error_log('MNT Page Load: Task already hired - Status: ' . $task_status . ', Hired: ' . $hired_status);
                }
                
                // Show funded status
                $escrow_response = $current_escrow;
                $escrow_response['message'] = 'This task escrow has been funded';
            }
        } else {
            // API call failed, use local meta as fallback
            if (strtoupper($existing_escrow_status) === 'PENDING') {
                $escrow_response = [
                    'project_id' => $task_id,
                    'status' => $existing_escrow_status,
                    'amount' => get_post_meta($task_id, 'mnt_escrow_amount', true),
                    'client_id' => get_post_meta($task_id, 'mnt_escrow_buyer', true),
                    'merchant_id' => get_post_meta($task_id, 'mnt_escrow_seller', true),
                    'message' => 'Existing pending escrow found for this task'
                ];
            }
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_escrow_nonce']) && wp_verify_nonce($_POST['create_escrow_nonce'], 'create_escrow_task')) {
    $task_id = intval($_POST['task_id']);
    $merchant_id = intval($_POST['merchant_id']);
    $client_id = intval($_POST['client_id']);
    $amount = floatval($_POST['amount']);
    $package_key = isset($_POST['package_key']) ? sanitize_text_field($_POST['package_key']) : '';

    error_log('=== MNT: FORM SUBMISSION - Using Pre-Created Order ===');
    error_log('Order ID from page load: ' . ($wc_order_id ?? 'NOT FOUND'));
    error_log('Escrow Project ID: ' . ($escrow_project_id ?? 'NOT FOUND'));

    // Check for valid task_id and pre-created order
    if (empty($task_id) || $task_id === 0) {
        $escrow_error = '<strong>Error:</strong> Task ID is missing or invalid.';
        error_log('MNT: ERROR - Invalid task ID');
    } elseif (empty($wc_order_id) || empty($escrow_project_id)) {
        $escrow_error = '<strong>Error:</strong> Order was not created on page load. Please refresh and try again.';
        error_log('MNT: ERROR - Order ID missing from page load');
    } else {
        // Check if this order already has an escrow created
        $wc_order_obj = wc_get_order($wc_order_id);
        $order_escrow_id = $wc_order_obj ? $wc_order_obj->get_meta('mnt_escrow_id') : '';
        
        if ($order_escrow_id) {
            $escrow_error = '<strong>Error:</strong> An escrow has already been created for this order (#' . $wc_order_id . '). Escrow ID: ' . $order_escrow_id . '. Please go back to cart and start fresh.';
            error_log('MNT: ERROR - Escrow already exists for order #' . $wc_order_id . ', escrow ID: ' . $order_escrow_id);
        } else {
            error_log('MNT: No existing escrow found for order #' . $wc_order_id . ' - proceeding with creation');
        $buyer_check = get_userdata($client_id);
        $seller_check = get_userdata($merchant_id);
        if (!$buyer_check || !$seller_check) {
            $escrow_error = '<strong>Error:</strong> Invalid buyer or seller.';
            error_log('MNT: ERROR - Invalid buyer or seller');
        } else {
            if (!isset($escrow_error)) {
                error_log('=== MNT: STEP 3 - Creating Escrow Transaction ===');
                error_log('Parameters:');
                error_log('  merchant_id: ' . $merchant_id);
                error_log('  client_id: ' . $client_id);
                error_log('  project_id: ' . $escrow_project_id);
                error_log('  amount: ' . $amount);
                error_log('');
                error_log('Calling API...');
                
                // Console log the payload before sending
                ?>
                <script>
                console.log('%c🚀 ESCROW API CALL - PAYLOAD BEING SENT', 'color: #10b981; font-weight: bold; font-size: 16px; background: #d1fae5; padding: 8px;');
                console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #10b981;');
                console.log('');
                console.log('%c📡 API Endpoint:', 'color: #0ea5e9; font-weight: bold;');
                console.log('POST https://escrow-api-dfl6.onrender.com/api/escrow/create_transaction');
                console.log('');
                console.log('%c📦 Request Payload:', 'color: #8b5cf6; font-weight: bold; font-size: 14px;');
                console.log(JSON.stringify({
                    merchant_id: "<?php echo (string)$merchant_id; ?>",
                    client_id: "<?php echo (string)$client_id; ?>",
                    project_id: "<?php echo isset($escrow_project_id) ? $escrow_project_id : ''; ?>",
                    amount: <?php echo $amount; ?>
                }, null, 2));
                console.log('');
                console.log('%c📋 Payload Details:', 'color: #f59e0b; font-weight: bold;');
                console.log('  merchant_id:', "<?php echo (string)$merchant_id; ?>", '(Seller/Freelancer)');
                console.log('  client_id:', "<?php echo (string)$client_id; ?>", '(Buyer/Customer)');
                console.log('  project_id:', "<?php echo isset($escrow_project_id) ? $escrow_project_id : ''; ?>", '(Order-based ID)');
                console.log('  amount:', <?php echo $amount; ?>, '(₦)');
                console.log('');
                console.log('%c🔧 Additional Context:', 'color: #ec4899; font-weight: bold;');
                console.log('  Task ID:', <?php echo $task_id; ?>);
                console.log('  WooCommerce Order ID:', <?php echo isset($wc_order_id) ? $wc_order_id : 0; ?>);
                console.log('  Package Key:', "<?php echo $package_key; ?>");
                console.log('  Auto Release:', false);
                console.log('');
                console.log('%c⏳ Sending request to API...', 'color: #06b6d4; font-style: italic;');
                console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #10b981;');
                </script>
                <?php
                
                // Regular escrow transaction for tasks - create only (PENDING status)
                // API signature: create($merchant_id, $client_id, $project_id, $amount, $auto_release)
                $escrow_result = \MNT\Api\Escrow::create((string)$merchant_id, (string)$client_id, $escrow_project_id, $amount, false);
            
            error_log('=== MNT: STEP 5 - API Response Received ===');
            error_log('HTTP Status: ' . (isset($escrow_result['http_code']) ? $escrow_result['http_code'] : 'N/A'));
            error_log('Response Type: ' . gettype($escrow_result));
            if (is_array($escrow_result)) {
                error_log('Response Keys: ' . implode(', ', array_keys($escrow_result)));
            }
            error_log('Full Response (JSON):');
            error_log(json_encode($escrow_result, JSON_PRETTY_PRINT));
            error_log('');
            
            // Check if escrow was created successfully
            $escrow_success = !empty($escrow_result['status']) || !empty($escrow_result['project_id']) || (isset($escrow_result['message']) && stripos($escrow_result['message'], 'success') !== false);
            
            if ($escrow_success) {
                error_log('=== MNT: STEP 6 - Escrow Created Successfully ===');
                
                // Link escrow to task
                $escrow_id = $escrow_result['escrow_id'] ?? $escrow_result['id'] ?? 'API-' . $escrow_project_id;
                error_log('Escrow ID from API: ' . $escrow_id);
                error_log('Escrow Status: ' . ($escrow_result['status'] ?? 'PENDING'));
                error_log('MNT: Response Keys: ' . implode(', ', array_keys($escrow_result)));
                
                error_log('=== MNT: STEP 7 - Storing Escrow Metadata ===');
                
                // Store metadata on TASK (for reference)
                update_post_meta($task_id, 'mnt_last_escrow_id', $escrow_id);
                error_log('Task meta updated: mnt_last_escrow_id = ' . $escrow_id);
                
                // Store metadata on WOOCOMMERCE ORDER (primary storage)
                if ($wc_order && $wc_order_id) {
                    error_log('Storing metadata on WooCommerce Order #' . $wc_order_id);
                    
                    $wc_order->add_meta_data('mnt_escrow_id', $escrow_id);
                    $wc_order->add_meta_data('mnt_escrow_project_id', $escrow_project_id);
                    $wc_order->add_meta_data('mnt_escrow_amount', $amount);
                    $wc_order->add_meta_data('mnt_escrow_status', $escrow_result['status'] ?? 'PENDING');
                    $wc_order->add_meta_data('task_product_id', $task_id);
                    $wc_order->add_meta_data('seller_id', $merchant_id);
                    $wc_order->save();
                    error_log('Order metadata saved successfully');
                }
                
                $escrow_response = $escrow_result;
                
                // Ensure all IDs are in the response for the modal button
                if (empty($escrow_response['merchant_id'])) {
                    $escrow_response['merchant_id'] = $merchant_id;
                }
                if (empty($escrow_response['client_id'])) {
                    $escrow_response['client_id'] = $client_id;
                }
                if (empty($escrow_response['project_id'])) {
                    $escrow_response['project_id'] = $escrow_project_id; // Use order-based ID
                }
                if (empty($escrow_response['escrow_id'])) {
                    $escrow_response['escrow_id'] = $escrow_id;
                }
                
                // Add additional tracking info
                $escrow_response['task_id'] = $task_id;
                $escrow_response['order_id'] = $wc_order_id;
                $escrow_response['escrow_project_id'] = $escrow_project_id;
                
                error_log('=== MNT: STEP 8 - Escrow Creation Complete ===');
                error_log('Summary:');
                error_log('  Escrow ID: ' . $escrow_id);
                error_log('  Escrow Project ID: ' . $escrow_project_id);
                error_log('  Task ID: ' . $task_id);
                error_log('  Order ID: ' . ($wc_order_id ?? 'N/A'));
                error_log('  Merchant ID: ' . $merchant_id);
                error_log('  Client ID: ' . $client_id);
                error_log('  Amount: ' . $amount);
                error_log('  Status: ' . ($escrow_result['status'] ?? 'PENDING'));
                error_log('');
                error_log('✅ Task escrow created successfully and ready for funding!');
            } else {
                error_log('MNT: === ESCROW CREATION FAILED ===');
                error_log('MNT: Full Error Response: ' . print_r($escrow_result, true));
                error_log('MNT: Error Response JSON: ' . json_encode($escrow_result, JSON_PRETTY_PRINT));
                
                // Show full error message and reason if available
                $msg = '';
                
                // Display the entire raw response for debugging
                $msg .= '<div style="background:#1f2937;color:#f3f4f6;padding:15px;border-radius:6px;margin-bottom:15px;font-family:monospace;font-size:12px;overflow:auto;max-height:400px;">';
                $msg .= '<strong style="color:#fbbf24;display:block;margin-bottom:10px;">🔍 Full API Response:</strong>';
                $msg .= '<pre style="margin:0;white-space:pre-wrap;word-wrap:break-word;">' . esc_html(json_encode($escrow_result, JSON_PRETTY_PRINT)) . '</pre>';
                $msg .= '</div>';
                
                $msg .= '<div style="margin-bottom:15px;">';
                
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
                if (empty($msg) || $msg === '<div style="margin-bottom:15px;">') {
                    $msg = '<div style="margin-bottom:15px;">';
                    $msg .= '<strong style="color:#ef4444;">⚠️ Failed to create escrow.</strong><br>';
                    $msg .= 'Please check the console logs or contact support.<br>';
                    $msg .= '</div>';
                }
                
                $msg .= '</div>'; // Close error details div
                
                // Add helpful debugging info
                $msg .= '<div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:12px;margin-top:10px;">';
                $msg .= '<strong style="color:#92400e;">💡 Debugging Info:</strong><br>';
                $msg .= '<code>merchant_id:</code> ' . esc_html($merchant_id) . '<br>';
                $msg .= '<code>client_id:</code> ' . esc_html($client_id) . '<br>';
                $msg .= '<code>project_id (task_id):</code> ' . esc_html($task_id) . '<br>';
                $escrow_error = $msg;
            }
            }
        }
        } // Close order escrow check
    }
}          
        
    

?>

<div class="tb-dhb-mainheading">
    <div>
        <h4><?php esc_html_e('Create Task Escrow Transaction', 'taskbot'); ?></h4>
        <div class="tk-sortby">
            <span><?php esc_html_e('Secure payment for task hiring', 'taskbot'); ?></span>
        </div>
    </div>
    <div class="tb-dhb-mainheading-right">
        <?php
        $tasks_url = wc_get_page_permalink('shop'); // Or use your tasks listing page
        ?>
        <a href="<?php echo esc_url($tasks_url); ?>" class="tb-btn tb-btn-cancel">
            <i class="tb-icon-x"></i> <?php esc_html_e('Cancel', 'taskbot'); ?>
        </a>
    </div>
</div>

<div class="tb-dhb-box">
    <div class="tb-dhb-box-wrapper">
        
        <!-- Order Information (Created on Page Load) -->
        <?php if ($wc_order_id && $escrow_project_id): ?>
        <div class="tk-alert" style="background: #e0f2fe; border-left: 4px solid #0ea5e9; color: #0c4a6e; margin-bottom: 20px;">
            <h5 style="margin: 0 0 10px 0; color: #0369a1;">
                <i class="tb-icon-check-circle" style="color: #0ea5e9;"></i> 
                <?php esc_html_e('Order Created Successfully', 'taskbot'); ?>
            </h5>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 12px; margin-top: 12px;">
                <div>
                    <strong><?php esc_html_e('WooCommerce Order ID:', 'taskbot'); ?></strong><br>
                    <code style="background: #bae6fd; padding: 4px 8px; border-radius: 4px; font-size: 13px;">#<?php echo esc_html($wc_order_id); ?></code>
                </div>
                <div>
                    <strong><?php esc_html_e('Escrow Project ID:', 'taskbot'); ?></strong><br>
                    <code style="background: #bae6fd; padding: 4px 8px; border-radius: 4px; font-size: 13px;"><?php echo esc_html($escrow_project_id); ?></code>
                </div>
                <div>
                    <strong><?php esc_html_e('Order Status:', 'taskbot'); ?></strong><br>
                    <span style="background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                        PENDING ESCROW
                    </span>
                </div>
            </div>
            <p style="margin: 12px 0 0 0; font-size: 13px; opacity: 0.9;">
                <strong>Next Step:</strong> Submit the form below to create the escrow transaction using this order.
            </p>
        </div>
        <?php elseif ($task_id): ?>
        <div class="tk-alert tk-alert-error" style="margin-bottom: 20px;">
            <strong><?php esc_html_e('Error:', 'taskbot'); ?></strong>
            <?php esc_html_e('Failed to create WooCommerce order. Please check if WooCommerce is active and try again.', 'taskbot'); ?>
        </div>
        <?php endif; ?>
                
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

                <!-- Task Details -->
                <div class="tb-form-group">
                    <h5 class="tb-dhb-subtitle"><?php esc_html_e('Task Details', 'taskbot'); ?></h5>
                    <div class="tb-userinfo-box">
                        <div class="tb-userinfo-content">
                            <?php if ($task): ?>
                                <span><strong><?php esc_html_e('ID:', 'taskbot'); ?></strong> <?php echo esc_html($task->ID); ?></span>
                                <span><strong><?php esc_html_e('Title:', 'taskbot'); ?></strong> <?php echo esc_html($task->post_title); ?></span>
                                <span><strong><?php esc_html_e('Status:', 'taskbot'); ?></strong> <?php echo esc_html($task->post_status); ?></span>
                            <?php else: ?>
                                <em><?php esc_html_e('No task found.', 'taskbot'); ?></em>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-6 col-sm-12">

                <!-- Package Details -->
                <?php if ($package_details): ?>
                <div class="tb-form-group">
                    <h5 class="tb-dhb-subtitle"><?php esc_html_e('Package Details', 'taskbot'); ?></h5>
                    <div class="tb-userinfo-box">
                        <div class="tb-userinfo-content">
                            <span><strong><?php esc_html_e('Package:', 'taskbot'); ?></strong> <?php echo esc_html(ucfirst($package_key)); ?></span>
                            <?php if (!empty($package_details['title'])): ?>
                                <span><strong><?php esc_html_e('Title:', 'taskbot'); ?></strong> <?php echo esc_html($package_details['title']); ?></span>
                            <?php endif; ?>
                            <?php if (isset($package_details['price'])): ?>
                                <span><strong><?php esc_html_e('Price:', 'taskbot'); ?></strong> ₦<?php echo esc_html(number_format($package_details['price'], 2)); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

                <?php if ($escrow_response || (isset($escrow_result['detail']) && $escrow_result['detail'] === 'Project already exist.')): ?>
                    <div class="tk-alert tk-alert-success">
                        <?php if ($escrow_response): ?>
                            <strong><?php esc_html_e('Escrow Created!', 'taskbot'); ?></strong><br>
                            <?php if (!empty($escrow_response['escrow_id'])): ?>
                                <strong><?php esc_html_e('Escrow ID:', 'taskbot'); ?></strong> <?php echo esc_html($escrow_response['escrow_id']); ?><br>
                            <?php endif; ?>
                            <strong><?php esc_html_e('Status:', 'taskbot'); ?></strong> <span style="color: #f59e0b; font-weight: 600;"><?php echo esc_html(strtoupper($escrow_response['status'] ?? 'PENDING')); ?></span><br>
                            
                            <?php if (strtoupper($escrow_response['status'] ?? '') === 'PENDING'): ?>
                                <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 12px; margin: 10px 0; border-radius: 4px;">
                                    <strong style="color: #92400e;">⚠ Escrow Created - Pending Funding</strong><br>
                                    <span style="color: #b45309;">Click the "Release Funds to Escrow" button below to move funds from your wallet to the escrow account.</span>
                                </div>
                            <?php endif; ?>
                            
                            <strong><?php esc_html_e('Amount:', 'taskbot'); ?></strong> ₦<?php echo esc_html($escrow_response['amount'] ?? ''); ?><br>
                            <?php if (!empty($escrow_response['merchant_id'])): ?>
                                <strong><?php esc_html_e('Merchant ID:', 'taskbot'); ?></strong> <?php echo esc_html($escrow_response['merchant_id'] ?? ''); ?><br>
                            <?php endif; ?>
                            <?php if (!empty($escrow_response['client_id'])): ?>
                                <strong><?php esc_html_e('Client ID:', 'taskbot'); ?></strong> <?php echo esc_html($escrow_response['client_id'] ?? ''); ?><br>
                            <?php endif; ?>
                            <strong><?php esc_html_e('Task ID:', 'taskbot'); ?></strong> <?php echo esc_html($escrow_response['project_id'] ?? ''); ?><br>
                            <?php if (!empty($escrow_response['message'])): ?>
                                <br><strong><?php esc_html_e('Message:', 'taskbot'); ?></strong> <?php echo esc_html($escrow_response['message']); ?>
                            <?php endif; ?>
                            <details style="margin-top:12px;">
                                <summary style="cursor:pointer;font-weight:600;"><?php esc_html_e('Show Raw API Response', 'taskbot'); ?></summary>
                                <pre class="tk-code-block"><?php echo esc_html(print_r($escrow_response, true)); ?></pre>
                            </details>
                        <?php else: ?>
                            <strong><?php esc_html_e('Escrow Already Exists for this Task.', 'taskbot'); ?></strong><br>
                            <strong><?php esc_html_e('Task ID:', 'taskbot'); ?></strong> <?php echo esc_html($task_id); ?><br>
                            <strong><?php esc_html_e('Merchant ID:', 'taskbot'); ?></strong> <?php echo esc_html($merchant_id); ?><br>
                            <strong><?php esc_html_e('Client ID:', 'taskbot'); ?></strong> <?php echo esc_html($client_id); ?><br>
                            <strong><?php esc_html_e('Amount:', 'taskbot'); ?></strong> ₦<?php echo esc_html($amount); ?><br>
                            <br><strong><?php esc_html_e('Message:', 'taskbot'); ?></strong> <?php esc_html_e('Task already exists. You can move funds to escrow below.', 'taskbot'); ?>
                            <details style="margin-top:12px;">
                                <summary style="cursor:pointer;font-weight:600;"><?php esc_html_e('Show Raw API Response', 'taskbot'); ?></summary>
                                <pre class="tk-code-block"><?php echo esc_html(print_r($escrow_result, true)); ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>

                    <!-- Modal for Complete Escrow -->
                    <?php 
                    // Show modal if status is PENDING or if task already exists
                    $show_modal = false;
                    if (!empty($escrow_response['status']) && strtoupper($escrow_response['status']) === 'PENDING') {
                        $show_modal = true;
                    } elseif (isset($escrow_result['detail']) && $escrow_result['detail'] === 'Project already exist.') {
                        $show_modal = true;
                    }
                    
                    // Debug log the button attributes
                    error_log('=== MNT: Modal Button Data Attributes ===');
                    error_log('project_id: ' . ($escrow_response['escrow_project_id'] ?? $escrow_project_id ?? 'EMPTY'));
                    error_log('client_id: ' . (isset($escrow_response['client_id']) ? $escrow_response['client_id'] : (isset($client_id) ? $client_id : 'EMPTY')));
                    error_log('merchant_id: ' . (isset($escrow_response['merchant_id']) ? $escrow_response['merchant_id'] : (isset($merchant_id) ? $merchant_id : 'EMPTY')));
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
                                        <p><?php esc_html_e('Task escrow transaction created successfully with PENDING status. Click the button below to release funds from your wallet to the escrow account.', 'taskbot'); ?></p>
                                    <?php else: ?>
                                        <p><?php esc_html_e('An escrow transaction already exists for this task but funds have not been released yet. Click the button below to release funds from your wallet to the escrow account.', 'taskbot'); ?></p>
                                    <?php endif; ?>
                                    <button id="mnt-complete-escrow-btn" class="tk-btn-solid-lg" 
                                            data-project-id="<?php echo esc_attr($escrow_response['escrow_project_id'] ?? $escrow_project_id ?? ''); ?>" 
                                            data-task-id="<?php echo esc_attr($task_id); ?>"
                                            data-order-id="<?php echo esc_attr($wc_order_id ?? ''); ?>"
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
        
        <!-- Debug and Release Funds Button Handler -->
        <script>
        console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #f59e0b; font-size: 16px;');
        console.log('%c🔍 CHECKING RELEASE FUNDS BUTTON SETUP', 'color: #f59e0b; font-weight: bold; font-size: 16px; background: #fef3c7; padding: 8px;');
        console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #f59e0b; font-size: 16px;');
        console.log('jQuery loaded:', typeof jQuery !== 'undefined');
        console.log('$ available:', typeof $ !== 'undefined');
        console.log('mntEscrow object:', typeof mntEscrow !== 'undefined' ? mntEscrow : 'NOT LOADED');
        
        jQuery(document).ready(function($) {
            console.log('%c✅ DOM READY - Checking Button', 'color: #10b981; font-weight: bold;');
            console.log('Button exists (#mnt-complete-escrow-btn):', $('#mnt-complete-escrow-btn').length);
            console.log('Modal exists (#mnt-complete-escrow-modal):', $('#mnt-complete-escrow-modal').length);
            console.log('Modal visible:', $('#mnt-complete-escrow-modal').is(':visible'));
            
            if ($('#mnt-complete-escrow-btn').length > 0) {
                var $btn = $('#mnt-complete-escrow-btn');
                console.log('%c📋 Button Data Attributes:', 'color: #8b5cf6; font-weight: bold;');
                console.log('  data-project-id:', $btn.attr('data-project-id'));
                console.log('  data-user-id:', $btn.attr('data-user-id'));
                console.log('  data-seller-id:', $btn.attr('data-seller-id'));
                console.log('');
                console.log('%c🔧 Checking mntEscrow object:', 'color: #f59e0b; font-weight: bold;');
                if (typeof mntEscrow !== 'undefined') {
                    console.log('  ajaxUrl:', mntEscrow.ajaxUrl);
                    console.log('  nonce:', mntEscrow.nonce ? '✓ Present' : '✗ Missing');
                } else {
                    console.log('%c  ❌ mntEscrow NOT DEFINED!', 'color: #ef4444; font-weight: bold;');
                }
                console.log('');
            } else {
                console.log('%c❌ ERROR: Button not found!', 'color: #ef4444; font-weight: bold;');
            }
        });
        </script>
        
        <!-- Log create task escrow form data before submission -->
        <script>
        jQuery(function($) {
            console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #10b981; font-size: 16px;');
            console.log('%c🎯 MNT TASK ESCROW PAGE LOADED', 'color: #10b981; font-weight: bold; font-size: 18px; background: #d1fae5; padding: 8px;');
            console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #10b981; font-size: 16px;');
            console.log('%cTimestamp:', 'font-weight: bold;', new Date().toISOString());
            console.log('');
            
            // Log pre-created order details immediately
            console.log('%c📦 PRE-CREATED ORDER DETAILS (FROM PAGE LOAD)', 'color: #0ea5e9; font-weight: bold; font-size: 15px; background: #e0f2fe; padding: 6px;');
            console.log('WooCommerce Order ID:', '<?php echo $wc_order_id ?? "NOT CREATED"; ?>');
            console.log('Escrow Project ID:', '<?php echo $escrow_project_id ?? "NOT CREATED"; ?>');
            console.log('Task ID:', '<?php echo $task_id; ?>');
            console.log('Buyer ID:', '<?php echo $client_id; ?>');
            console.log('Seller ID:', '<?php echo $merchant_id; ?>');
            console.log('Amount:', '<?php echo $amount; ?>');
            console.log('Package Key:', '<?php echo $package_key; ?>');
            console.log('');
            console.log('%c✅ Status:', 'font-weight: bold; color: #10b981;', 'Order ready for escrow creation');
            console.log('');
            console.log('Task ID:', '<?php echo $task_id; ?>');
            console.log('Buyer (Client) ID:', '<?php echo $client_id; ?>');
            console.log('Seller (Merchant) ID:', '<?php echo $merchant_id; ?>');
            console.log('Package Key:', '<?php echo $package_key; ?>');
            console.log('Amount:', '<?php echo $amount; ?>');
            console.log('');
            
            // Check hidden form fields
            console.log('%c🔍 HIDDEN FORM FIELD VALUES', 'color: #8b5cf6; font-weight: bold; font-size: 14px;');
            console.log('order_id field value:', $('input[name="order_id"]').val());
            console.log('escrow_project_id field value:', $('input[name="escrow_project_id"]').val());
            console.log('task_id field value:', $('input[name="task_id"]').val());
            console.log('merchant_id field value:', $('input[name="merchant_id"]').val());
            console.log('client_id field value:', $('input[name="client_id"]').val());
            console.log('');
            
            $('#mnt-create-task-escrow-form').on('submit', function(e) {
                var formData = $(this).serializeArray();
                var formObj = {};
                $.each(formData, function(i, field) {
                    formObj[field.name] = field.value;
                });
                
                console.log('');
                console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #3b82f6; font-size: 16px;');
                console.log('%c🚀 FORM SUBMISSION TRIGGERED', 'color: #3b82f6; font-weight: bold; font-size: 18px; background: #dbeafe; padding: 8px;');
                console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #3b82f6; font-size: 16px;');
                console.log('');
                
                console.log('%c📋 STEP 1: All Form Data', 'color: #8b5cf6; font-weight: bold; font-size: 14px;');
                console.log('Complete form object:', formObj);
                console.log('');
                
                console.log('%c🔑 STEP 2: Key IDs Being Submitted', 'color: #f59e0b; font-weight: bold; font-size: 14px;');
                console.log('  ✓ Task ID:', formObj.task_id);
                console.log('  ✓ Order ID:', formObj.order_id, '← WooCommerce Order (Pre-created)');
                console.log('  ✓ Escrow Project ID:', formObj.escrow_project_id, '← Will be used as project_id');
                console.log('  ✓ Merchant ID (Seller):', formObj.merchant_id);
                console.log('  ✓ Client ID (Buyer):', formObj.client_id);
                console.log('  ✓ Amount:', formObj.amount, '₦');
                console.log('  ✓ Package Key:', formObj.package_key || '(none)');
                console.log('');
                
                console.log('%c📡 STEP 3: API Payload That Will Be Sent', 'color: #ec4899; font-weight: bold; font-size: 14px; background: #fce7f3; padding: 6px;');
                console.log('Endpoint: POST /api/escrow/create_transaction');
                console.log('');
                console.log('Payload:');
                console.log(JSON.stringify({
                    merchant_id: formObj.merchant_id,
                    client_id: formObj.client_id,
                    project_id: formObj.escrow_project_id,
                    amount: parseFloat(formObj.amount)
                }, null, 2));
                console.log('');
                
                console.log('%c💡 STEP 4: Order-to-Project Mapping', 'color: #06b6d4; font-weight: bold; font-size: 14px;');
                console.log('  WooCommerce Order #' + formObj.order_id + ' → Escrow Project ID: ' + formObj.escrow_project_id);
                console.log('  This ensures each order gets a unique escrow transaction!');
                console.log('');
                
                console.log('%c⏳ STEP 5: Submitting to Backend...', 'color: #10b981; font-weight: bold; font-size: 14px;');
                console.log('Form is being submitted to PHP for processing.');
                console.log('Check server logs (wp-content/debug.log) for backend processing (STEP 1-8)');
                console.log('');
                console.log('%c━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', 'color: #3b82f6; font-size: 16px;');
            });
        });
        </script>
                console.log('The backend will now:');
                console.log('  1️⃣  ✅ Use existing WooCommerce order: #' + (formObj.order_id || '{CREATED}'));
                console.log('  2️⃣  ✅ Use escrow project ID: ' + (formObj.escrow_project_id || 'order-{order_id}'));
                console.log('  3️⃣  Call escrow API with order-based project_id for unique tracking');
                console.log('  4️⃣  Link escrow to the order and update order status');
                console.log('');
                
                console.log('%c🌐 STEP 4: Expected API Call', 'color: #06b6d4; font-weight: bold;');
            });
        });
        </script>

        <?php endif; ?>

        <!-- Error Message Display -->
        <?php if ($escrow_error): ?>
            <div class="tk-alert tk-alert-error">
                <?php echo $escrow_error; ?>
            </div>
        <?php endif; ?>

        <!-- Create Escrow Form -->
        <form method="POST" id="mnt-create-task-escrow-form" class="tb-themeform">
            <?php wp_nonce_field('create_escrow_task', 'create_escrow_nonce'); ?>
            <input type="hidden" name="task_id" value="<?php echo esc_attr($task_id); ?>">
            <input type="hidden" name="merchant_id" value="<?php echo esc_attr($merchant_id); ?>">
            <input type="hidden" name="client_id" value="<?php echo esc_attr($client_id); ?>">
            <input type="hidden" name="package_key" value="<?php echo esc_attr($package_key); ?>"></fieldset>
            
            <fieldset>
                        <div class="tb-themeform__wrap">
                            <div class="form-group">
                                <label class="tb-label"><?php esc_html_e('Escrow Amount (₦)', 'taskbot'); ?></label>
                                <input type="number" name="amount" class="form-control" value="<?php echo esc_attr($amount); ?>" step="0.01" min="0.01" required>
                                <em><?php esc_html_e('Amount to be held in escrow', 'taskbot'); ?></em>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="tb-btn"><?php esc_html_e('Create Escrow', 'taskbot'); ?></button>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>

    </div>
</div>

<style>
.tb-dhb-box { margin: 20px 0; }
.tb-userinfo-box { background: #f9fafb; padding: 15px; border-radius: 8px; margin-top: 10px; }
.tb-userinfo-content span { display: block; margin-bottom: 8px; }
.tk-alert { padding: 15px; border-radius: 8px; margin: 20px 0; }
.tk-alert-success { background: #d1fae5; border-left: 4px solid #10b981; color: #065f46; }
.tk-alert-error { background: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; }
.tk-code-block { background: #1f2937; color: #f3f4f6; padding: 15px; border-radius: 8px; overflow: auto; max-height: 400px; font-size: 12px; }
.tb-btn, .tk-btn-solid-lg { background: #4f46e5; color: white; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
.tb-btn:hover, .tk-btn-solid-lg:hover { background: #4338ca; }
.tb-btn-cancel { background: #6b7280; color: white; padding: 8px 16px; border-radius: 8px; text-decoration: none; }
.tb-btn-cancel:hover { background: #4b5563; color: white; }
</style>
