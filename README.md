# MyNaijaTask Escrow Plugin

A comprehensive WordPress plugin that integrates a Python-based Escrow API with the Taskbot theme to provide secure wallet and escrow functionality for task-based marketplace transactions.

## Features

### Core Functionality
- **Wallet Management**: Create and manage user wallets with real-time balance tracking
- **Escrow System**: Automatic escrow creation when tasks are paid
- **Payment Integration**: Paystack integration for deposits and withdrawals
- **Transaction History**: Complete transaction logging and history
- **Dispute Resolution**: Built-in dispute handling with admin resolution panel

### Taskbot Integration
- Overrides Taskbot's native wallet and payout system
- Automatic wallet creation for buyers and sellers
- Escrow lifecycle tied to task status changes
- Seamless integration with task creation and completion flows

### Admin Features
- Dashboard with statistics overview
- Transaction monitoring
- Dispute management interface
- Settings panel for API and payment configuration
- User wallet information in profile pages

### Frontend Features
- Wallet dashboard with balance display
- Deposit, withdrawal, and transfer forms
- Transaction history viewer
- Escrow status box for tasks
- Responsive and user-friendly interface

## Installation

1. Upload the `mnt-escrow` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **MNT Escrow > Settings** to configure:
   - API Base URL
   - Paystack keys
   - Auto-wallet creation settings

## Configuration

### API Settings
Set your escrow API base URL in the settings page:
- Default: `https://escrow-api-1vu6.onrender.com`

### Paystack Integration
Add your Paystack keys in the settings:
- Public Key
- Secret Key

## Shortcodes

### Wallet Shortcodes
- `[mnt_wallet_dashboard]` - Full wallet dashboard
- `[mnt_wallet_balance]` - Display current balance
- `[mnt_create_wallet]` - Wallet creation form
- `[mnt_deposit_form]` - Deposit form
- `[mnt_withdraw_form]` - Withdrawal form
- `[mnt_transfer_form]` - Transfer form
- `[mnt_transactions]` - Transaction history

### Escrow Shortcodes
- `[mnt_escrow_box]` - Escrow status for current task
- `[mnt_escrow_list]` - List of user's escrows

## API Endpoints

### REST API Routes
- `GET /wp-json/mnt/v1/wallet/balance` - Get wallet balance
- `POST /wp-json/mnt/v1/wallet/create` - Create wallet
- `GET /wp-json/mnt/v1/wallet/transactions` - Get transactions
- `GET /wp-json/mnt/v1/escrow/list` - List escrows
- `GET /wp-json/mnt/v1/escrow/{id}` - Get escrow details
- `POST /wp-json/mnt/v1/webhook/paystack` - Paystack webhook

### AJAX Actions
- `mnt_wallet_action` - Wallet operations
- `mnt_deposit` - Initiate deposit
- `mnt_withdraw` - Request withdrawal
- `mnt_transfer` - Transfer funds
- `mnt_escrow_action` - Escrow operations

## Taskbot Hooks

The plugin integrates with Taskbot using these hooks:
- `taskbot_task_created` - Create wallets for buyer/seller
- `taskbot_payment_completed` - Create escrow
- `taskbot_task_submitted` - Mark escrow as delivered
- `taskbot_task_approved` - Release escrow to seller
- `taskbot_task_rejected` - Refund escrow to buyer
- `taskbot_task_disputed` - Open dispute

## Database Tables

The plugin creates two optional tables for local logging:
- `wp_mnt_transaction_log` - Transaction history backup
- `wp_mnt_escrow_log` - Escrow transaction backup

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Taskbot theme (for full integration)
- Active escrow API backend
- Paystack account (for payments)

## File Structure

```
mnt-escrow/
├── mnt-escrow.php              # Main plugin file
├── README.md                   # Documentation
├── uninstall.php              # Uninstall script
├── includes/
│   ├── autoload.php           # Class autoloader
│   ├── Admin/
│   │   └── Dashboard.php      # Admin dashboard
│   ├── Api/
│   │   ├── client.php         # API client base
│   │   ├── Wallet.php         # Wallet API
│   │   ├── Escrow.php         # Escrow API
│   │   ├── Transaction.php    # Transaction API
│   │   └── Payment.php        # Payment API
│   ├── Routes/
│   │   └── Router.php         # AJAX & REST routes
│   ├── Taskbot/
│   │   └── HookOverride.php   # Taskbot integration
│   └── ui/
│       ├── init.php           # UI initialization
│       └── templates/
│           ├── wallet-dashboard.php
│           ├── escrow-box.php
│           └── create-wallet.php
└── assets/
    ├── css/
    │   ├── style.css          # Frontend styles
    │   └── admin-style.css    # Admin styles
    └── js/
        ├── escrow.js          # Frontend scripts
        └── admin-script.js    # Admin scripts
```

## Support

For issues and feature requests, contact support or visit the documentation.

## Changelog

### Version 1.0.0
- Initial release
- Wallet management system
- Escrow lifecycle management
- Taskbot integration
- Admin dashboard
- Paystack integration
- REST API endpoints
- Frontend UI components

## License

This plugin is proprietary software for MyNaijaTask.

## Credits

Developed for MyNaijaTask marketplace platform.
