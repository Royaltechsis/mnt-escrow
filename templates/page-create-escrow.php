<?php
// Add inline styles for the escrow form
echo '<style>
.mnt-escrow-create-form {
    max-width: 420px;
    margin: 40px auto;
    padding: 32px 28px 24px 28px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 16px rgba(0,0,0,0.07);
    font-family: inherit;
}
.mnt-escrow-create-form h2 {
    text-align: center;
    margin-bottom: 28px;
    font-size: 1.5rem;
    color: #222;
    font-weight: 600;
}
.mnt-escrow-create-form .form-group {
    margin-bottom: 22px;
}
.mnt-escrow-create-form label {
    display: block;
    margin-bottom: 7px;
    font-weight: 500;
    color: #444;
}
.mnt-escrow-create-form input[type="number"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 1rem;
    background: #fafbfc;
    transition: border 0.2s;
}
.mnt-escrow-create-form input[type="number"]:focus {
    border-color: #0073aa;
    outline: none;
}
.mnt-escrow-create-form .mnt-btn {
    width: 100%;
    padding: 12px 0;
    background: linear-gradient(90deg, #0073aa 0%, #005177 100%);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 1.1rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    margin-top: 10px;
}
.mnt-escrow-create-form .mnt-btn:hover {
    background: linear-gradient(90deg, #005177 0%, #0073aa 100%);
}
.mnt-escrow-create-form .notice {
    margin-bottom: 18px;
    padding: 10px 14px;
    border-radius: 5px;
    font-size: 1rem;
}
.mnt-escrow-create-form .notice-success {
    background: #e6f7ec;
    color: #217a3c;
    border: 1px solid #b7e4c7;
}
.mnt-escrow-create-form .notice-error {
    background: #fbeaea;
    color: #b30000;
    border: 1px solid #f5c2c7;
}
</style>';
/*
Template Name: Create Escrow Transaction
Description: Page for buyers to create an escrow for a project/task and hire a seller directly (no WooCommerce).
*/


if (!is_user_logged_in()) {
    wp_redirect(wp_login_url(get_permalink()));
    exit;
}

$escrow_response = null;
$escrow_error = null;



$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : get_current_user_id();
$merchant_id = isset($_GET['merchant_id']) ? intval($_GET['merchant_id']) : 0;
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$proposal_id = isset($_GET['proposal_id']) ? intval($_GET['proposal_id']) : 0;

// Fetch user details
$buyer = get_userdata($client_id);
$seller = get_userdata($merchant_id);

// Fetch project and proposal details
$project = $project_id ? get_post($project_id) : null;
$proposal = $proposal_id ? get_post($proposal_id) : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_escrow_nonce']) && wp_verify_nonce($_POST['create_escrow_nonce'], 'create_escrow')) {
    $project_id = intval($_POST['project_id']);
    $merchant_id = intval($_POST['merchant_id']);
    $client_id = intval($_POST['client_id']);
    $amount = floatval($_POST['amount']);
    $proposal_id = isset($_POST['proposal_id']) ? intval($_POST['proposal_id']) : 0;

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
            // Always pass project_id as a string, never as null/None
            $escrow_project_id = (string)$project_id;
            // Debug output for project_id
            echo '<div style="background:#ffe0e0;color:#a00;padding:10px 12px;margin-bottom:10px;border-radius:6px;">';
            echo '<strong>DEBUG:</strong> About to call Escrow API with:<br>';
            echo 'project_id (raw POST): '; var_dump($_POST['project_id']); echo '<br>';
            echo 'project_id (int): ' . $project_id . ' | (string): ' . $escrow_project_id . '<br>';
            echo 'merchant_id: ' . $merchant_id . ' | client_id: ' . $client_id . ' | amount: ' . $amount . '<br>';
            echo '</div>';
            $escrow_result = \MNT\Api\Escrow::create((string)$merchant_id, (string)$client_id, $amount, $escrow_project_id);
            if (!empty($escrow_result['escrow_id'])) {
                // Deduct wallet from buyer
                $wallet_result = \MNT\Api\wallet::admin_debit($client_id, $amount, 'Escrow for project #' . $project_id);

                // Link escrow to project
                update_post_meta($project_id, 'mnt_escrow_id', $escrow_result['escrow_id']);
                update_post_meta($project_id, 'mnt_escrow_amount', $amount);
                update_post_meta($project_id, 'mnt_escrow_buyer', $client_id);
                update_post_meta($project_id, 'mnt_escrow_seller', $merchant_id);
                update_post_meta($project_id, 'mnt_escrow_status', $escrow_result['status'] ?? 'pending');
                update_post_meta($project_id, 'mnt_escrow_created_at', current_time('mysql'));
                update_post_meta($project_id, '_post_project_status', 'hired');
                // Optionally update proposal status if proposal_id is passed
                if (!empty($proposal_id)) {
                    wp_update_post(['ID' => $proposal_id, 'post_status' => 'hired']);
                    update_post_meta($proposal_id, 'mnt_escrow_id', $escrow_result['escrow_id']);
                    update_post_meta($proposal_id, 'project_id', $project_id);
                }
                $escrow_response = $escrow_result;
            } else {
                // Show full error message and reason if available
                $msg = '';
                if (!empty($escrow_result['error'])) {
                    $msg .= esc_html($escrow_result['error']);
                } else {
                    $msg .= 'Failed to create escrow.';
                }
                if (!empty($escrow_result['reason'])) {
                    $msg .= '<br><strong>Reason:</strong> ' . esc_html($escrow_result['reason']);
                }
                if (!empty($escrow_result['message'])) {
                    $msg .= '<br><strong>Message:</strong> ' . esc_html($escrow_result['message']);
                }
                $escrow_error = $msg;
            }
        }
    }
}
?>
<div class="mnt-escrow-create-form">
    <h2>Create Escrow for Project</h2>

    <!-- Buyer Details -->
    <div class="form-group">
        <label>Buyer (Client)</label>
        <div style="background:#f6f8fa;padding:10px 12px;border-radius:6px;">
            <strong>ID:</strong> <?php echo esc_html($buyer ? $buyer->ID : ''); ?><br>
            <strong>Name:</strong> <?php echo esc_html($buyer ? $buyer->display_name : ''); ?><br>
            <strong>Email:</strong> <?php echo esc_html($buyer ? $buyer->user_email : ''); ?>
        </div>
    </div>
    <!-- Seller Details -->
    <div class="form-group">
        <label>Seller (Merchant)</label>
        <div style="background:#f6f8fa;padding:10px 12px;border-radius:6px;">
            <strong>ID:</strong> <?php echo esc_html($seller ? $seller->ID : ''); ?><br>
            <strong>Name:</strong> <?php echo esc_html($seller ? $seller->display_name : ''); ?><br>
            <strong>Email:</strong> <?php echo esc_html($seller ? $seller->user_email : ''); ?>
        </div>
    </div>
    <!-- Project Details -->
    <div class="form-group">
        <label>Project Details</label>
        <div style="background:#f6f8fa;padding:10px 12px;border-radius:6px;">
            <?php if ($project): ?>
                <strong>ID:</strong> <?php echo esc_html($project->ID); ?><br>
                <strong>Title:</strong> <?php echo esc_html($project->post_title); ?><br>
                <strong>Status:</strong> <?php echo esc_html($project->post_status); ?><br>
            <?php else: ?>
                <em>No project found.</em>
            <?php endif; ?>
        </div>
    </div>
    <!-- Proposal Details -->
    <div class="form-group">
        <label>Proposal Details</label>
        <div style="background:#f6f8fa;padding:10px 12px;border-radius:6px;">
            <?php if ($proposal): ?>
                <strong>ID:</strong> <?php echo esc_html($proposal->ID); ?><br>
                <strong>Title:</strong> <?php echo esc_html($proposal->post_title); ?><br>
                <strong>Status:</strong> <?php echo esc_html($proposal->post_status); ?><br>
            <?php else: ?>
                <em>No proposal found.</em>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($escrow_response || (isset($escrow_result['detail']) && $escrow_result['detail'] === 'Project already exist.')): ?>
        <div class="notice notice-success">
            <?php if ($escrow_response): ?>
                <strong>Escrow Created!</strong><br>
                <strong>Escrow ID:</strong> <?php echo esc_html($escrow_response['escrow_id']); ?><br>
                <strong>Status:</strong> <?php echo esc_html($escrow_response['status'] ?? ''); ?><br>
                <strong>Amount:</strong> ₦<?php echo esc_html($escrow_response['amount'] ?? ''); ?><br>
                <strong>Merchant ID:</strong> <?php echo esc_html($escrow_response['merchant_id'] ?? ''); ?><br>
                <strong>Client ID:</strong> <?php echo esc_html($escrow_response['client_id'] ?? ''); ?><br>
                <strong>Project ID:</strong> <?php echo esc_html($escrow_response['project_id'] ?? ''); ?><br>
                <?php if (!empty($escrow_response['message'])): ?>
                    <br><strong>Message:</strong> <?php echo esc_html($escrow_response['message']); ?>
                <?php endif; ?>
                <details style="margin-top:12px;">
                    <summary style="cursor:pointer;font-weight:600;">Show Raw API Response</summary>
                    <pre style="background:#222;color:#fff;padding:12px;border-radius:6px;overflow-x:auto;font-size:13px;max-height:300px;">
<?php echo esc_html(print_r($escrow_response, true)); ?>
                    </pre>
                </details>
            <?php else: ?>
                <strong>Escrow Already Exists for this Project.</strong><br>
                <strong>Project ID:</strong> <?php echo esc_html($project_id); ?><br>
                <strong>Merchant ID:</strong> <?php echo esc_html($merchant_id); ?><br>
                <strong>Client ID:</strong> <?php echo esc_html($client_id); ?><br>
                <strong>Amount:</strong> ₦<?php echo esc_html($amount); ?><br>
                <br><strong>Message:</strong> Project already exists. You can move funds to escrow below.
                <details style="margin-top:12px;">
                    <summary style="cursor:pointer;font-weight:600;">Show Raw API Response</summary>
                    <pre style="background:#222;color:#fff;padding:12px;border-radius:6px;overflow-x:auto;font-size:13px;max-height:300px;">
<?php echo esc_html(print_r($escrow_result, true)); ?>
                    </pre>
                </details>
            <?php endif; ?>
        </div>
        <!-- Modal for Complete Escrow -->
        <div id="mnt-complete-escrow-modal" style="display:block;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(0,0,0,0.45);">
            <div style="background:#fff;max-width:400px;margin:10vh auto;padding:32px 24px 24px 24px;border-radius:10px;box-shadow:0 2px 16px rgba(0,0,0,0.13);position:relative;">
                <h3 style="margin-top:0;">Move Funds to Escrow</h3>
                <p>Escrow created! To complete, click the button below to move funds from the buyer's wallet to escrow.</p>
                <button id="mnt-complete-escrow-btn" class="mnt-btn" data-project-id="<?php echo esc_attr($escrow_response['project_id'] ?? $project_id ?? ''); ?>" data-user-id="<?php echo esc_attr(isset($escrow_response['client_id']) ? $escrow_response['client_id'] : (isset($client_id) ? $client_id : '')); ?>">Complete</button>
                <div id="mnt-complete-escrow-message" style="margin-top:16px;"></div>
                <button onclick="document.getElementById('mnt-complete-escrow-modal').style.display='none'" style="position:absolute;top:10px;right:14px;background:none;border:none;font-size:22px;line-height:1;cursor:pointer;">&times;</button>
            </div>
        </div>
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
        <div class="notice notice-error">
            <?php echo $escrow_error; ?>
            <?php if (isset($escrow_result)): ?>
                <details style="margin-top:12px;">
                    <summary style="cursor:pointer;font-weight:600;">Show Raw API Response</summary>
                    <pre style="background:#222;color:#fff;padding:12px;border-radius:6px;overflow-x:auto;font-size:13px;max-height:300px;">
<?php echo esc_html(print_r($escrow_result, true)); ?>
                    </pre>
                </details>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('create_escrow', 'create_escrow_nonce'); ?>
        <input type="hidden" name="project_id" value="<?php echo esc_attr($project_id); ?>">
        <input type="hidden" name="merchant_id" value="<?php echo esc_attr($merchant_id); ?>">
        <input type="hidden" name="client_id" value="<?php echo esc_attr($client_id); ?>">
        <input type="hidden" name="proposal_id" value="<?php echo esc_attr($proposal_id); ?>">
        <div class="form-group">
            <label>Amount (₦)</label>
            <input type="number" name="amount" min="1" step="0.01" value="<?php echo esc_attr($amount); ?>" required>
        </div>
        <button type="submit" class="mnt-btn">Create Escrow & Hire Seller</button>
    </form>
</div>
