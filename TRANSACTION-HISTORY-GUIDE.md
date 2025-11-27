# Transaction History Feature - Complete Guide

## Overview
This document describes the complete transaction history feature that has been implemented for the MNT Escrow plugin. The feature includes:
- User transaction history with pagination (10 records per page)
- Advanced date filtering
- Transaction type filtering
- Admin dashboard for viewing all users' transactions
- CSV export functionality
- Responsive design

## Features Implemented

### 1. User Transaction History

#### Shortcode
```php
[mnt_transaction_history]
```

#### Features:
- **Pagination**: 10 transactions per page
- **Default Date Range**: Automatically shows last 7 days (one week before current date to current date)
- **Date Filters**: Filter by start date and end date
- **Transaction Type Filters**:
  - All Transactions
  - Deposits
  - Withdrawals
  - Escrow Funded
  - Escrow Released
  - Escrow Refunded
  - Transfers Sent
  - Transfers Received
- **Transaction Details**:
  - Date and time
  - Transaction ID
  - Type (with colored badges)
  - Description
  - Amount (credit/debit)
  - Balance after transaction
  - Status
- **CSV Export**: Download filtered transactions as CSV file

#### Usage in Template:
The shortcode has been integrated into the billing settings page:
```php
// In dashboard-billing-settings.php
if ($user_has_wallet && shortcode_exists('mnt_transaction_history')) {
    echo '<div class="tb-dhb-box-wrapper tb-transaction-history-section">';
    echo do_shortcode('[mnt_transaction_history]');
    echo '</div>';
}
```

### 2. Admin Transaction History

#### Location
WordPress Admin → MNT Escrow → Transactions

#### Features:
- View all users' transactions in one place
- **Default Date Range**: Automatically shows last 7 days
- Filter by:
  - User ID
  - Date range (start and end date)
  - Transaction type
- Pagination (10 records per page)
- User information with links to user profile
- Transaction details with colored badges
- Responsive table design

#### Access
Only administrators (users with `manage_options` capability) can access this page.

## Files Created/Modified

### New Files:
1. **`includes/ui/templates/transaction-history.php`**
   - User-facing transaction history template
   - Includes filters, pagination, and export functionality

2. **`TRANSACTION-HISTORY-GUIDE.md`** (this file)
   - Complete documentation

### Modified Files:
1. **`includes/Api/Transaction.php`**
   - Added date filtering parameters to `list_by_user()`
   - Added new `list_all()` method for admin access

2. **`includes/ui/init.php`**
   - Registered `mnt_transaction_history` shortcode
   - Added `transaction_history_shortcode()` method

3. **`includes/Admin/Dashboard.php`**
   - Completely rewrote `transactions_page()` method
   - Added comprehensive filtering and pagination
   - Added styled badges and responsive design

4. **`assets/css/style.css`**
   - Added complete styling for transaction history
   - Added responsive styles
   - Added print styles
   - Added pagination styles
   - Added badge styles for transaction types and statuses

5. **`extend/dashboard/dashboard-billing-settings.php`**
   - Integrated transaction history shortcode
   - Placed after wallet dashboard/create wallet section

## Transaction Types

The system supports the following transaction types:

| Type | Description | Display Color |
|------|-------------|---------------|
| `deposit` | User deposits funds into wallet | Green |
| `withdrawal` | User withdraws funds from wallet | Red |
| `escrow_fund` | Funds locked in escrow for a task | Yellow |
| `escrow_release` | Escrow released to seller | Blue |
| `escrow_refund` | Escrow refunded to buyer | Purple |
| `transfer_sent` | Transfer sent to another user | Pink |
| `transfer_received` | Transfer received from another user | Light Blue |

## Transaction Statuses

| Status | Description | Display Color |
|--------|-------------|---------------|
| `completed` / `success` | Transaction completed successfully | Green |
| `pending` | Transaction is processing | Yellow |
| `failed` | Transaction failed | Red |
| `processing` | Transaction is being processed | Blue |

## API Endpoints Used

### User Transactions
```php
GET /api/wallet/transaction_history
Query Parameters:
- user_id (required)
- type (optional): 'deposit', 'withdrawal', etc.
- limit (optional): default 50
- offset (optional): default 0
- start_date (optional): 'YYYY-MM-DD' - defaults to 7 days ago
- end_date (optional): 'YYYY-MM-DD' - defaults to today
```

### All Transactions (Admin)
```php
GET /api/admin/wallet/get_transactions
Query Parameters:
- user_id (optional): filter by specific user
- type (optional): transaction type
- limit (optional): default 50
- offset (optional): default 0
- start_date (optional): 'YYYY-MM-DD' - defaults to 7 days ago
- end_date (optional): 'YYYY-MM-DD' - defaults to today
```

## Database Requirements

The API should return transactions in the following format:

```json
{
    "success": true,
    "transactions": [
        {
            "id": "TXN123456",
            "transaction_id": "TXN123456",
            "user_id": 123,
            "type": "deposit",
            "amount": 5000.00,
            "balance_after": 15000.00,
            "status": "completed",
            "description": "Wallet deposit via Paystack",
            "reference": "REF123",
            "created_at": "2024-11-27 10:30:00",
            "date": "2024-11-27 10:30:00"
        }
    ],
    "total": 150
}
```

## Styling Classes

### Transaction Type Badges
- `.type-badge` - Base class for type badges
- `.type-deposit` - Green background
- `.type-withdrawal` - Red background
- `.type-escrow_fund` - Yellow background
- `.type-escrow_release` - Blue background
- `.type-escrow_refund` - Purple background
- `.type-transfer_sent` - Pink background
- `.type-transfer_received` - Light blue background

### Status Badges
- `.status-badge` - Base class for status badges
- `.status-completed`, `.status-success` - Green
- `.status-pending` - Yellow
- `.status-failed` - Red
- `.status-processing` - Blue

### Amount Colors
- `.tx-amount.credit` - Green for credit transactions
- `.tx-amount.debit` - Red for debit transactions

## Responsive Design

The transaction history is fully responsive:
- **Desktop**: Full table with all columns
- **Tablet**: Adjusted spacing and font sizes
- **Mobile**: Smaller fonts, adjusted padding, stacked filters

## CSV Export

Users can export their filtered transaction history:
1. Apply filters (optional)
2. Click "Export to CSV" button
3. File downloads with name format: `transactions_YYYY-MM-DD.csv`

CSV includes:
- Date
- Time
- Transaction ID
- Type
- Description
- Amount (with +/- prefix)
- Balance After
- Status

## Testing Checklist

### User Frontend
- [ ] Transaction history displays correctly on billing settings page
- [ ] Pagination works (10 records per page)
- [ ] Date filters work correctly
- [ ] Transaction type filter works
- [ ] "Clear Filters" button resets all filters
- [ ] Transaction details are accurate
- [ ] Amount colors are correct (green for credit, red for debit)
- [ ] Type badges display with correct colors
- [ ] Status badges display correctly
- [ ] CSV export works and includes all filtered data
- [ ] Responsive design works on mobile devices

### Admin Dashboard
- [ ] Admin can access MNT Escrow → Transactions
- [ ] All transactions display in table
- [ ] User ID filter works
- [ ] Date range filter works
- [ ] Transaction type filter works
- [ ] Pagination works correctly
- [ ] User links navigate to user edit page
- [ ] Transaction details are accurate
- [ ] Badges and colors display correctly
- [ ] Responsive design works

## Security Considerations

1. **User Access**: Only logged-in users can view their own transactions
2. **Admin Access**: Only users with `manage_options` capability can view all transactions
3. **Data Sanitization**: All user inputs are sanitized using WordPress functions
4. **Nonce Verification**: AJAX requests use nonce verification
5. **SQL Injection**: All database queries use prepared statements
6. **XSS Prevention**: All output is escaped using `esc_html()`, `esc_attr()`, etc.

## Future Enhancements

Potential improvements for future versions:
1. AJAX-based filtering (no page reload)
2. Advanced search by transaction ID or description
3. Date range presets (Last 7 days, Last month, etc.)
4. PDF export option
5. Email transaction receipts
6. Transaction details modal
7. Graphical transaction summary
8. Bulk operations
9. Transaction notes/comments
10. Advanced filtering (by amount range, etc.)

## Support

For issues or questions:
1. Check the API response format matches expected structure
2. Verify the MNT Escrow plugin is active
3. Check WordPress and PHP error logs
4. Verify user has appropriate permissions
5. Test with different transaction types and dates

## Changelog

### Version 1.0.0 (2024-11-27)
- Initial implementation
- User transaction history with pagination
- Default date range: last 7 days (one week before to current date)
- Date and type filtering
- Admin transaction management
- CSV export functionality
- Complete responsive design
- Comprehensive styling
- Updated API endpoints to use GET requests:
  - `/api/wallet/transaction_history` for user transactions
  - `/api/admin/wallet/get_transactions` for admin view
