# Transaction History - Quick Reference

## Shortcode Usage

### Display Transaction History
```php
[mnt_transaction_history]
```

## URL Parameters

### Filter by Date
```
?start_date=2024-01-01&end_date=2024-12-31
```

### Filter by Type
```
?tx_type=deposit
```

### Pagination
```
?tx_page=2
```

### Combined Filters
```
?start_date=2024-01-01&end_date=2024-12-31&tx_type=deposit&tx_page=1
```

## Transaction Types

| Filter Value | Description |
|--------------|-------------|
| `deposit` | Deposits |
| `withdrawal` | Withdrawals |
| `escrow_fund` | Escrow Funded |
| `escrow_release` | Escrow Released |
| `escrow_refund` | Escrow Refunded |
| `transfer_sent` | Transfers Sent |
| `transfer_received` | Transfers Received |

## API Integration

### User Transactions
```php
use MNT\Api\Transaction;

$result = Transaction::list_by_user(
    $user_id,           // Required
    $type,              // Optional: 'deposit', 'withdrawal', etc.
    $limit,             // Optional: default 50
    $offset,            // Optional: default 0
    $start_date,        // Optional: 'YYYY-MM-DD' (defaults to 7 days ago)
    $end_date           // Optional: 'YYYY-MM-DD' (defaults to today)
);

// Uses GET: /api/wallet/transaction_history
```

### All Transactions (Admin)
```php
use MNT\Api\Transaction;

$result = Transaction::list_all(
    $type,              // Optional
    $limit,             // Optional: default 50
    $offset,            // Optional: default 0
    $start_date,        // Optional: 'YYYY-MM-DD' (defaults to 7 days ago)
    $end_date,          // Optional: 'YYYY-MM-DD' (defaults to today)
    $user_id            // Optional
);

// Uses GET: /api/admin/wallet/get_transactions
```

## Default Behavior

**Date Range**: If no dates are provided, the system automatically:
- Start Date: 7 days before current date
- End Date: Current date

This means users will see the last week of transactions by default.

## Key CSS Classes

### Containers
- `.mnt-transaction-history` - Main container
- `.mnt-transaction-table` - Transaction table
- `.mnt-transaction-filters` - Filter section
- `.mnt-pagination` - Pagination section

### Transaction Elements
- `.tx-date` - Transaction date
- `.tx-id` - Transaction ID
- `.tx-type` - Transaction type
- `.tx-amount` - Transaction amount
- `.tx-status` - Transaction status

### Badges
- `.type-badge` - Type badge
- `.status-badge` - Status badge
- `.type-deposit`, `.type-withdrawal`, etc. - Type-specific classes
- `.status-completed`, `.status-pending`, etc. - Status-specific classes

## Admin Page Access

Navigate to: **WordPress Admin → MNT Escrow → Transactions**

Required capability: `manage_options` (Administrator role)

## File Locations

### Templates
- User View: `includes/ui/templates/transaction-history.php`

### API
- Transaction API: `includes/Api/Transaction.php`

### Admin
- Admin Dashboard: `includes/Admin/Dashboard.php`

### Styles
- Main Styles: `assets/css/style.css`

### Integration
- Billing Settings: `themes/taskup-child/extend/dashboard/dashboard-billing-settings.php`

## Common Tasks

### Get User's Recent 10 Transactions
```php
$result = \MNT\Api\Transaction::list_by_user($user_id, null, 10, 0);
$transactions = $result['transactions'] ?? [];
```

### Get Deposits Only
```php
$result = \MNT\Api\Transaction::list_by_user($user_id, 'deposit', 10, 0);
```

### Get Transactions for Date Range
```php
$result = \MNT\Api\Transaction::list_by_user(
    $user_id, 
    null, 
    10, 
    0, 
    '2024-01-01', 
    '2024-12-31'
);
```

### Export to CSV (Frontend)
Click the "Export to CSV" button - automatic download

## Troubleshooting

### Transactions Not Showing
1. Check if user is logged in
2. Verify MNT Escrow plugin is active
3. Check if wallet exists for user
4. Verify API endpoint returns data

### Filters Not Working
1. Check URL parameters are being passed
2. Verify API supports date/type filtering
3. Check JavaScript console for errors

### Admin Page Empty
1. Verify user has admin capabilities
2. Check API endpoint for list-all
3. Verify API returns total count

### Styling Issues
1. Clear browser cache
2. Check if style.css is loading
3. Verify no CSS conflicts

## Performance Tips

1. Use appropriate `limit` values (default 10)
2. Index database columns: `user_id`, `created_at`, `type`
3. Cache frequently accessed transactions
4. Use pagination for large datasets
5. Optimize API response time

## Support Contacts

For technical support, refer to:
- Main documentation: `TRANSACTION-HISTORY-GUIDE.md`
- Plugin documentation: `README.md`
- API documentation: `DEVELOPER-GUIDE.md`
