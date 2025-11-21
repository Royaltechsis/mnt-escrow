# 🚀 Quick Reference Card - MyNaijaTask Escrow Plugin

## 📁 Plugin Location
`/wp-content/plugins/mnt-escrow/`

## 🔌 Activation
WordPress Admin > Plugins > Activate "MyNaijaTask Escrow"

## ⚙️ Settings Page
WordPress Admin > MNT Escrow > Settings

## 📊 Dashboard
WordPress Admin > MNT Escrow > Dashboard

---

## 🎯 Essential Shortcodes

```
[mnt_wallet_dashboard]     → Complete wallet interface
[mnt_wallet_balance]       → Show balance only
[mnt_deposit_form]         → Deposit form
[mnt_withdraw_form]        → Withdrawal form
[mnt_escrow_box]          → Escrow status (on task pages)
```

---

## 🔗 API Classes Quick Reference

### Create Wallet
```php
\MNT\Api\Wallet::create($user_id);
```

### Check Balance
```php
\MNT\Api\Wallet::balance($user_id);
```

### Create Escrow
```php
\MNT\Api\Escrow::create($buyer_id, $seller_id, $amount, $task_id, $description);
```

### Release Escrow
```php
\MNT\Api\Escrow::release($escrow_id, $buyer_id);
```

### Refund Escrow
```php
\MNT\Api\Escrow::refund($escrow_id, $seller_id);
```

---

## 🔐 Required Configuration

1. **API URL:** `https://escrow-api-1vu6.onrender.com`
2. **Paystack Public Key:** `pk_test_xxx` or `pk_live_xxx`
3. **Paystack Secret Key:** `sk_test_xxx` or `sk_live_xxx`

---

## 📄 Pages to Create

1. **Wallet Dashboard:** `/wallet` with `[mnt_wallet_dashboard]`
2. **Transactions:** `/wallet/transactions` with `[mnt_transactions]`
3. **Callback:** `/wallet/deposit-callback` (empty page)

---

## 🎨 CSS Classes for Styling

```css
.mnt-wallet-dashboard      → Main wallet container
.mnt-wallet-balance-card   → Balance display card
.mnt-btn-primary          → Primary buttons
.mnt-escrow-box           → Escrow status box
.status-pending           → Status badges
.status-released
.status-disputed
```

---

## 🔧 Common Tasks

### Get User's Wallet Balance
```php
$balance = \MNT\Api\Wallet::balance(get_current_user_id());
echo $balance['balance'];
```

### Check if Escrow Exists for Task
```php
$escrow_id = get_post_meta($task_id, 'mnt_escrow_id', true);
if ($escrow_id) {
    // Escrow exists
}
```

### Log Custom Transaction
```php
\MNT\Helpers\Logger::log_transaction(
    $user_id, 
    $tx_id, 
    'deposit', 
    5000, 
    'completed',
    'Paystack deposit'
);
```

---

## 🐛 Debugging

### Enable Debug Mode
Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Check Logs
`/wp-content/debug.log`

### Test API Connection
```php
$result = \MNT\Api\Wallet::balance(1);
var_dump($result);
```

---

## 📱 REST API Quick Test

### Using cURL
```bash
# Get Balance
curl -X GET "https://yoursite.com/wp-json/mnt/v1/wallet/balance" \
  -H "X-WP-Nonce: YOUR_NONCE"

# List Escrows
curl -X GET "https://yoursite.com/wp-json/mnt/v1/escrow/list" \
  -H "X-WP-Nonce: YOUR_NONCE"
```

---

## 🎯 Taskbot Hook Points

```php
// When task is paid
do_action('taskbot_payment_completed', $task_id, $amount, $buyer_id);

// When seller delivers
do_action('taskbot_task_submitted', $task_id, $seller_id);

// When buyer approves
do_action('taskbot_task_approved', $task_id, $buyer_id);
```

---

## 📊 Database Tables

- `wp_mnt_transaction_log` → All transactions
- `wp_mnt_escrow_log` → All escrows

---

## 🚨 Common Issues & Fixes

| Issue | Fix |
|-------|-----|
| Wallet not creating | Check API URL in settings |
| Payment fails | Verify Paystack keys |
| Escrow not auto-creating | Check Taskbot hooks firing |
| Balance not updating | Clear cache, check API |
| 404 on REST API | Re-save permalinks |

---

## 📚 Documentation Files

- `README.md` → Overview
- `INSTALLATION.md` → Setup guide  
- `DEVELOPER-GUIDE.md` → Code examples
- `BUILD-SUMMARY.md` → Complete feature list

---

## ✅ Pre-Launch Checklist

- [ ] Plugin activated
- [ ] Settings configured
- [ ] Wallet pages created
- [ ] Test deposit (sandbox)
- [ ] Test escrow flow
- [ ] Test withdrawal
- [ ] Mobile responsive checked
- [ ] SSL certificate active
- [ ] Backup created

---

## 🆘 Emergency Contacts

If critical issue:
1. Deactivate plugin
2. Check debug.log
3. Contact support with:
   - WordPress version
   - PHP version
   - Error message
   - Steps to reproduce

---

## 🎓 Learning Resources

1. Review `DEVELOPER-GUIDE.md` for advanced usage
2. Check inline code comments
3. Explore template files in `/includes/ui/templates/`
4. Test REST endpoints with Postman

---

## 💡 Pro Tips

1. **Test in staging first** before production
2. **Keep Paystack in test mode** until ready
3. **Monitor first 10 transactions** closely
4. **Set up email notifications** for disputes
5. **Regular database backups** recommended

---

**Quick Support:** Check docs first, then contact with detailed error info.

**Version:** 1.0.0  
**Last Updated:** 2025

---

*Keep this card handy for quick reference!* 📌
