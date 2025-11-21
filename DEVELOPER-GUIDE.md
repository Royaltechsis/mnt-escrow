# MyNaijaTask Escrow Plugin - Developer Guide

## Quick Start

### Installation & Setup

1. **Activate the Plugin**
   ```
   WordPress Admin > Plugins > Activate "MyNaijaTask Escrow"
   ```

2. **Configure Settings**
   ```
   WordPress Admin > MNT Escrow > Settings
   - Set API Base URL
   - Add Paystack Keys
   - Enable auto-wallet creation
   ```

3. **Test Wallet Creation**
   - Login as any user
   - Add shortcode `[mnt_create_wallet]` to a page
   - Create your first wallet

## Using Shortcodes

### Wallet Dashboard Page
Create a full-featured wallet page:
```php
[mnt_wallet_dashboard]
```

### Individual Components
```php
[mnt_wallet_balance]         // Shows balance inline
[mnt_deposit_form]           // Deposit form only
[mnt_withdraw_form]          // Withdrawal form only
[mnt_transfer_form]          // Transfer form only
[mnt_transactions limit="20"] // Transaction history
```

### Escrow Components
```php
[mnt_escrow_box]             // On task pages (auto-detects task)
[mnt_escrow_box escrow_id="123"] // Specific escrow
[mnt_escrow_list]            // User's escrow list
[mnt_escrow_list status="pending"] // Filtered list
```

## Custom Development

### Checking Wallet Balance in Code
```php
$user_id = get_current_user_id();
$result = \MNT\Api\Wallet::balance($user_id);
$balance = $result['balance'] ?? 0;

echo "Balance: ₦" . number_format($balance, 2);
```

### Creating a Wallet Programmatically
```php
$user_id = 123;
$result = \MNT\Api\Wallet::create($user_id);

if ($result['success']) {
    $wallet_id = $result['wallet_id'];
    update_user_meta($user_id, 'mnt_wallet_created', true);
    update_user_meta($user_id, 'mnt_wallet_id', $wallet_id);
}
```

### Making a Deposit
```php
$user_id = get_current_user_id();
$amount = 5000; // NGN
$email = wp_get_current_user()->user_email;
$callback_url = home_url('/wallet/callback');

$result = \MNT\Api\Payment::initialize_paystack(
    $user_id, 
    $amount, 
    $email, 
    $callback_url
);

if ($result['success']) {
    $authorization_url = $result['authorization_url'];
    wp_redirect($authorization_url);
    exit;
}
```

### Creating an Escrow Manually
```php
$buyer_id = 10;
$seller_id = 20;
$amount = 15000;
$task_id = 456;
$description = 'Website Design Task';

$result = \MNT\Api\Escrow::create(
    $buyer_id,
    $seller_id,
    $amount,
    $task_id,
    $description
);

if ($result['success']) {
    $escrow_id = $result['escrow_id'];
    update_post_meta($task_id, 'mnt_escrow_id', $escrow_id);
}
```

### Releasing Escrow
```php
$escrow_id = 'ESC123456';
$buyer_id = get_current_user_id();

$result = \MNT\Api\Escrow::release($escrow_id, $buyer_id);

if ($result['success']) {
    echo "Funds released to seller!";
}
```

### Opening a Dispute
```php
$escrow_id = 'ESC123456';
$user_id = get_current_user_id();
$reason = "Work not delivered as agreed";

$result = \MNT\Api\Escrow::dispute($escrow_id, $user_id, $reason);
```

## Hooks & Filters

### Actions You Can Hook Into

```php
// When escrow is created
add_action('mnt_escrow_created', function($escrow_id, $task_id, $buyer_id, $seller_id, $amount) {
    // Send email notification
    // Log to analytics
}, 10, 5);

// When escrow is released
add_action('mnt_escrow_released', function($escrow_id, $task_id, $buyer_id) {
    // Award points/badges
    // Update statistics
}, 10, 3);

// When escrow is refunded
add_action('mnt_escrow_refunded', function($escrow_id, $task_id, $buyer_id) {
    // Handle refund logic
}, 10, 3);

// When dispute is opened
add_action('mnt_escrow_disputed', function($escrow_id, $task_id, $user_id, $reason) {
    // Notify admin
    // Send emails
}, 10, 4);

// When task is delivered
add_action('mnt_task_delivered', function($task_id, $escrow_id, $buyer_id, $seller_id) {
    // Notify buyer
}, 10, 4);
```

### Filters You Can Use

```php
// Override wallet balance display
add_filter('taskbot_wallet_balance', function($balance, $user_id) {
    // Custom balance calculation
    return $balance;
}, 10, 2);

// Override payout check
add_filter('taskbot_can_payout', function($can_payout, $user_id, $amount) {
    // Custom payout logic
    return $can_payout;
}, 10, 3);
```

## REST API Examples

### Get Wallet Balance
```javascript
fetch('/wp-json/mnt/v1/wallet/balance', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
})
.then(response => response.json())
.then(data => {
    console.log('Balance:', data.balance);
});
```

### Get Transactions
```javascript
fetch('/wp-json/mnt/v1/wallet/transactions?limit=20', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
})
.then(response => response.json())
.then(data => {
    console.log('Transactions:', data.transactions);
});
```

### Get Escrow List
```javascript
fetch('/wp-json/mnt/v1/escrow/list?status=pending', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce
    }
})
.then(response => response.json())
.then(data => {
    console.log('Escrows:', data.escrows);
});
```

## AJAX Examples

### Deposit via AJAX
```javascript
jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'mnt_deposit',
        nonce: mntEscrow.nonce,
        amount: 5000
    },
    success: function(response) {
        if (response.success) {
            window.location.href = response.data.authorization_url;
        }
    }
});
```

### Withdraw via AJAX
```javascript
jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'mnt_withdraw',
        nonce: mntEscrow.nonce,
        amount: 3000,
        bank_code: '058',
        account_number: '0123456789',
        account_name: 'John Doe'
    },
    success: function(response) {
        if (response.success) {
            alert('Withdrawal initiated!');
        }
    }
});
```

### Release Escrow via AJAX
```javascript
jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'mnt_escrow_action',
        nonce: mntEscrow.nonce,
        escrow_action: 'release',
        escrow_id: 'ESC123456'
    },
    success: function(response) {
        if (response.success) {
            alert('Funds released!');
            location.reload();
        }
    }
});
```

## Taskbot Integration

The plugin automatically integrates with Taskbot theme hooks:

### Automatic Wallet Creation
- New users get wallets on registration (if enabled)
- Wallets created automatically when users start tasks

### Automatic Escrow Creation
When a buyer pays for a task:
1. Plugin creates escrow automatically
2. Funds held in escrow
3. Seller can't access until delivery

### Escrow Lifecycle
1. **Task Created** → Wallets created for buyer/seller
2. **Payment Made** → Escrow created automatically
3. **Task Submitted** → Status changes to "delivered"
4. **Task Approved** → Escrow released to seller
5. **Task Rejected** → Escrow refunded to buyer
6. **Task Disputed** → Admin intervention required

## Database Queries

### Get User Transactions
```php
$user_id = get_current_user_id();
$transactions = \MNT\Helpers\Logger::get_user_transactions($user_id, 50, 0);

foreach ($transactions as $tx) {
    echo $tx->type . ': ' . $tx->amount;
}
```

### Get User Escrows
```php
$user_id = get_current_user_id();
$escrows = \MNT\Helpers\Logger::get_user_escrows($user_id, 'pending');

foreach ($escrows as $escrow) {
    echo 'Escrow: ' . $escrow->escrow_id . ' - ' . $escrow->amount;
}
```

## Troubleshooting

### Wallet Not Creating
Check:
- API base URL is correct
- User has proper permissions
- Check browser console for errors

### Escrow Not Appearing
- Verify Taskbot hooks are firing
- Check if task has buyer and seller
- Look for escrow_id in post meta

### Payment Not Working
- Verify Paystack keys are correct
- Check webhook URL is accessible
- Test in Paystack sandbox mode first

## Security Notes

- All AJAX requests use nonces
- REST API requires authentication
- Amounts are sanitized and validated
- User permissions checked on all operations

## Performance Tips

- Wallet balances are cached locally
- Use REST API for async operations
- Database tables indexed for speed
- Limit transaction queries with pagination

## Support

For issues:
1. Check WordPress debug log
2. Verify API connectivity
3. Check browser console
4. Review plugin settings

## Best Practices

1. Always check for wallet existence before operations
2. Use try-catch for API calls
3. Validate amounts before transactions
4. Log important events for audit trail
5. Test in staging before production

---

Built for MyNaijaTask by experienced WordPress developers.
