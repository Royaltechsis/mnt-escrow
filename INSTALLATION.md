# MyNaijaTask Escrow Plugin - Installation & Setup Guide

## 📋 Prerequisites

Before installing the plugin, ensure you have:

- ✅ WordPress 5.8 or higher
- ✅ PHP 7.4 or higher
- ✅ Taskbot theme installed and activated
- ✅ Access to the escrow API backend (https://escrow-api-1vu6.onrender.com)
- ✅ Paystack account with API keys

## 🚀 Installation Steps

### Step 1: Upload Plugin

1. Download the `mnt-escrow` plugin folder
2. Upload to `/wp-content/plugins/` via FTP or cPanel File Manager
3. Or zip the folder and upload via WordPress Admin > Plugins > Add New > Upload

### Step 2: Activate Plugin

1. Go to **WordPress Admin > Plugins**
2. Find **MyNaijaTask Escrow**
3. Click **Activate**

The plugin will automatically:
- Create necessary database tables
- Set default options
- Initialize core components

### Step 3: Configure Settings

1. Go to **WordPress Admin > MNT Escrow > Settings**
2. Configure the following:

#### API Settings
```
API Base URL: https://escrow-api-1vu6.onrender.com
```

#### Paystack Settings
```
Public Key: pk_test_xxxxxxxxxxxxx (or pk_live_xxxxxxxxxxxxx)
Secret Key: sk_test_xxxxxxxxxxxxx (or sk_live_xxxxxxxxxxxxx)
```

#### General Settings
- ✅ Enable "Automatically create wallets for new users"

3. Click **Save Settings**

### Step 4: Create Wallet Pages

Create the following pages in WordPress:

#### Main Wallet Page
- **Page Title:** My Wallet
- **Page Slug:** wallet
- **Content:** `[mnt_wallet_dashboard]`
- **Template:** Default or Full Width

#### Transactions Page (Optional)
- **Page Title:** Transaction History
- **Page Slug:** wallet/transactions
- **Content:** `[mnt_transactions limit="50"]`

#### Deposit Callback Page
- **Page Title:** Deposit Callback
- **Page Slug:** wallet/deposit-callback
- **Content:** (leave blank - handles Paystack redirects)

### Step 5: Update Menu

Add wallet page to your site menu:
1. Go to **Appearance > Menus**
2. Add "My Wallet" page to your main menu
3. For logged-in users only, use menu visibility plugin

### Step 6: Test the Plugin

#### Test Wallet Creation
1. Login as a test user
2. Visit the wallet page
3. If no wallet exists, click "Create Wallet"
4. Verify wallet is created successfully

#### Test Deposit (Sandbox)
1. Click "Deposit" button
2. Enter amount (e.g., 1000)
3. Use Paystack test card:
   - Card: 4084084084084081
   - CVV: 408
   - Expiry: Any future date
   - PIN: 0000
   - OTP: 123456

#### Test Escrow Flow
1. Create a test task as a buyer
2. Assign to a seller
3. Make payment
4. Verify escrow is created automatically
5. As seller, submit delivery
6. As buyer, approve task
7. Verify funds are released

## 🎨 Customization

### Add Wallet to User Dashboard

If using Taskbot theme, add wallet link to user dashboard:

```php
// In your child theme's functions.php
add_filter('taskbot_dashboard_menu', function($menu_items) {
    $menu_items['wallet'] = [
        'title' => 'My Wallet',
        'url' => home_url('/wallet'),
        'icon' => 'fa-wallet'
    ];
    return $menu_items;
});
```

### Customize Wallet Colors

Add to your theme's custom CSS:

```css
.mnt-wallet-balance-card {
    background: linear-gradient(135deg, #your-color-1, #your-color-2);
}

.mnt-btn-primary {
    background: #your-brand-color;
}
```

### Override Templates

Copy templates from plugin to your theme:

```
From: /wp-content/plugins/mnt-escrow/includes/ui/templates/
To: /wp-content/themes/your-theme/mnt-escrow/
```

Then modify as needed.

## 🔐 Security Setup

### Webhook Security

Set up Paystack webhook:
1. Login to Paystack Dashboard
2. Go to Settings > Webhooks
3. Add webhook URL: `https://yoursite.com/wp-json/mnt/v1/webhook/paystack`
4. Copy webhook secret
5. Add to plugin settings (future enhancement)

### SSL Certificate

Ensure your site has SSL:
- Required for Paystack payments
- Protects sensitive transactions

### File Permissions

Set proper permissions:
```bash
chmod 755 /wp-content/plugins/mnt-escrow
chmod 644 /wp-content/plugins/mnt-escrow/*.php
```

## 🧪 Testing Checklist

Before going live, test:

- [ ] Wallet creation
- [ ] Deposit with Paystack test mode
- [ ] Withdrawal (with test bank details)
- [ ] Transfer between users
- [ ] Escrow creation on task payment
- [ ] Escrow release on task approval
- [ ] Escrow refund on task rejection
- [ ] Dispute opening
- [ ] Admin dispute resolution
- [ ] Transaction history display
- [ ] Balance updates in real-time
- [ ] Email notifications (if configured)
- [ ] Mobile responsiveness

## 🔄 Going Live

### Switch to Live Mode

1. Update Paystack keys to live keys
2. Update API to production URL (if different)
3. Test with small real transaction
4. Monitor first few transactions closely

### Monitor Performance

Check:
- API response times
- Database query performance
- Error logs
- Transaction completion rate

## 🆘 Troubleshooting

### Common Issues

**Issue: Wallets not creating**
- Check API URL is correct
- Verify API is accessible
- Check WordPress debug log

**Issue: Payments failing**
- Verify Paystack keys
- Check SSL certificate
- Test in sandbox mode first

**Issue: Escrow not auto-creating**
- Verify Taskbot hooks are firing
- Check task has buyer and seller set
- Review debug logs

**Issue: Balance not updating**
- Clear WordPress cache
- Check API connection
- Verify transaction completed

### Enable Debug Mode

Add to wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs at: `/wp-content/debug.log`

## 📧 Support & Updates

### Getting Help

1. Check documentation (README.md, DEVELOPER-GUIDE.md)
2. Review error logs
3. Contact support with:
   - WordPress version
   - PHP version
   - Error messages
   - Steps to reproduce

### Plugin Updates

To update the plugin:
1. Deactivate old version
2. Upload new version
3. Activate new version
4. Clear all caches

## 🎯 Best Practices

1. **Always backup** before updates
2. **Test in staging** before production
3. **Monitor transactions** regularly
4. **Keep API keys secure** - never commit to git
5. **Use child theme** for customizations
6. **Enable logging** for audit trail
7. **Regular backups** of database tables

## 📊 Monitoring Success

Track these metrics:
- Total wallets created
- Total transaction volume
- Escrow completion rate
- Dispute resolution time
- User satisfaction

## 🎉 You're Ready!

Your MyNaijaTask Escrow plugin is now installed and configured!

Next steps:
- Review the DEVELOPER-GUIDE.md for advanced features
- Customize templates to match your brand
- Set up email notifications (optional)
- Configure automatic backups
- Monitor initial transactions

---

**Need Help?** Contact support with your WordPress version, error logs, and detailed description of the issue.

**Want to Contribute?** Report bugs or suggest features through proper channels.

Made with ❤️ for MyNaijaTask
