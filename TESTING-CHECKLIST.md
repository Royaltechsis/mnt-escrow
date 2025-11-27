# Testing Checklist - Escrow Hiring System

## Pre-Testing Setup

### 1. Plugin Activation
- [ ] Deactivate MNT Escrow plugin
- [ ] Reactivate MNT Escrow plugin
- [ ] Check for activation errors in debug log

### 2. Verify Page Created
- [ ] Go to: WordPress Admin → Pages → All Pages
- [ ] Search for: "Escrow Deposit"
- [ ] Status should be: Published
- [ ] URL should be: `yourdomain.com/escrow-deposit/`
- [ ] Content should contain: `[mnt_escrow_deposit]`

### 3. Clear Caches
- [ ] Clear WordPress cache (if using cache plugin)
- [ ] Clear browser cache (Ctrl + Shift + R)
- [ ] Clear server cache (Redis, Memcached, etc.)
- [ ] Flush permalinks: Settings → Permalinks → Save Changes

---

## Test Scenario 1: Guest User (Not Logged In)

### Steps:
1. [ ] Logout from WordPress
2. [ ] Navigate to a project with proposals
3. [ ] View proposal detail page
4. [ ] Look for hire button

### Expected Results:
- [ ] Button text: "Login to Hire" or similar
- [ ] Button links to WordPress login page
- [ ] After login, returns to proposal page

---

## Test Scenario 2: Logged In User Without Wallet

### Steps:
1. [ ] Login as buyer user
2. [ ] Delete wallet (if exists): Go to MNT Escrow admin
3. [ ] Navigate to proposal detail page
4. [ ] Look for hire button

### Expected Results:
- [ ] Button text: "Create Wallet to Hire"
- [ ] Button links to billing settings page
- [ ] Clicking creates wallet
- [ ] Button updates after wallet created

---

## Test Scenario 3: User With Insufficient Funds

### Steps:
1. [ ] Login as buyer with wallet
2. [ ] Ensure wallet balance < project amount
3. [ ] Navigate to proposal detail page
4. [ ] Look for hire button

### Expected Results:
- [ ] Button text: "Fund Wallet to Hire"
- [ ] Button links to fund/deposit page
- [ ] Shows current balance (if enabled)
- [ ] Shows required amount
- [ ] Warning message about insufficient funds

### Fund Wallet:
5. [ ] Click "Fund Wallet" button
6. [ ] Deposit sufficient amount (project amount + fees)
7. [ ] Return to proposal page
8. [ ] Button should now show: "Hire with Secure Escrow"

---

## Test Scenario 4: Successful Hire Flow

### Steps:
1. [ ] Login as buyer with sufficient balance
2. [ ] Project amount: ₦5,000 (example)
3. [ ] Wallet balance: ₦10,000 (sufficient)
4. [ ] Navigate to proposal detail page

### Expected Results - Proposal Page:
- [ ] Button text: "Hire [Seller] with Secure Escrow"
- [ ] Button has lock icon
- [ ] Shows wallet balance (if enabled)
- [ ] Button is clickable

### Click Hire Button:
5. [ ] Click "Hire with Secure Escrow" button
6. [ ] Should redirect to: `/escrow-deposit/?project_id=X&seller_id=Y&amount=Z`

### Expected Results - Escrow Deposit Page:
- [ ] Page loads without errors
- [ ] Project details card displays:
  - [ ] Project title
  - [ ] Seller name
  - [ ] Seller avatar
  - [ ] Project description
- [ ] Payment summary shows:
  - [ ] Project amount
  - [ ] Platform fee (if any)
  - [ ] Total amount
- [ ] Wallet balance displayed
- [ ] Balance is sufficient (green indicator)
- [ ] 4-step escrow explanation visible
- [ ] "Pay & Hire Seller" button visible

### Complete Payment:
7. [ ] Click "Pay & Hire Seller" button
8. [ ] Wait for processing (spinner shows)

### Expected Results - After Payment:
- [ ] Success message appears
- [ ] Redirects to project activity page
- [ ] No JavaScript errors in console

### Verify Database Changes:
9. [ ] Check project meta:
   - [ ] `mnt_escrow_id` exists
   - [ ] `mnt_escrow_amount` matches payment
   - [ ] `mnt_escrow_buyer` = buyer user ID
   - [ ] `mnt_escrow_seller` = seller user ID
   - [ ] `mnt_escrow_status` = "active" or "funded"
   - [ ] `mnt_escrow_created_at` timestamp

10. [ ] Check WooCommerce order created:
    - [ ] Order status: Processing or Completed
    - [ ] Order meta contains: `mnt_escrow_id`
    - [ ] Order meta contains: `project_id`
    - [ ] Order meta contains: `seller_id`
    - [ ] Order meta contains: `buyer_id`
    - [ ] Order meta: `_task_status` = "hired"
    - [ ] Order meta: `payment_type` = "escrow"

### Verify UI Updates:
11. [ ] Check buyer's project dashboard:
    - [ ] Escrow status badge visible
    - [ ] Badge shows correct status (FUNDED, ACTIVE, etc.)
    - [ ] Badge has appropriate color

12. [ ] Check transaction history:
    - [ ] Go to: Dashboard → Transaction History
    - [ ] New entry type: ESCROW_FUND
    - [ ] Amount matches project amount
    - [ ] Shows project title/reference
    - [ ] Timestamp correct

13. [ ] Check wallet balance:
    - [ ] Balance reduced by escrow amount
    - [ ] New balance = Old balance - Escrow amount
    - [ ] No duplicate deductions

---

## Test Scenario 5: Milestone Projects

### Steps:
1. [ ] Create project with milestones
2. [ ] Seller submits proposal with milestones
3. [ ] Buyer views proposal detail

### Expected Results - Proposal Page:
- [ ] Each milestone has "Pay & Hire with Escrow" button
- [ ] Button shows lock icon
- [ ] Clicking redirects to escrow deposit page
- [ ] Escrow page shows milestone details
- [ ] Payment amount matches milestone amount

### Complete Milestone Payment:
4. [ ] Pay for first milestone
5. [ ] Verify escrow created for milestone
6. [ ] Verify button updates/disables
7. [ ] Verify milestone status changes

---

## Test Scenario 6: Status Badges

### Steps:
1. [ ] Navigate to: Dashboard → My Projects (Buyer view)
2. [ ] View project listings

### Expected Results:
- [ ] Projects without escrow: No badge
- [ ] Projects with active escrow: Badge visible
- [ ] Badge shows status: FUNDED, RELEASED, CANCELLED, etc.
- [ ] Badge colors:
  - [ ] Blue = FUNDED/ACTIVE
  - [ ] Green = RELEASED/COMPLETED
  - [ ] Yellow = PENDING/DISPUTED
  - [ ] Red = CANCELLED/REFUNDED

---

## Test Scenario 7: Error Handling

### Test 7.1: Invalid Project ID
1. [ ] Visit: `/escrow-deposit/?project_id=99999&seller_id=1&amount=1000`
2. [ ] Should show error: "Invalid project"

### Test 7.2: Invalid Seller ID
1. [ ] Visit: `/escrow-deposit/?project_id=1&seller_id=99999&amount=1000`
2. [ ] Should show error: "Invalid seller"

### Test 7.3: Amount Mismatch
1. [ ] Visit escrow page with amount different from proposal
2. [ ] Should validate and show error

### Test 7.4: Network Errors
1. [ ] Temporarily disable API connection
2. [ ] Try to complete payment
3. [ ] Should show error: "Connection failed"
4. [ ] Should not deduct wallet balance
5. [ ] Should allow retry

### Test 7.5: Double Click Prevention
1. [ ] Click "Pay & Hire" button
2. [ ] Quickly click again before redirect
3. [ ] Should only process once
4. [ ] Button should disable during processing

---

## Test Scenario 8: Transaction History

### Steps:
1. [ ] Go to: Dashboard → Transaction History
2. [ ] Create escrow transaction (as tested above)
3. [ ] Refresh transaction history page

### Expected Results:
- [ ] New ESCROW_FUND entry appears
- [ ] Entry shows:
  - [ ] Transaction ID
  - [ ] Type: ESCROW_FUND
  - [ ] Amount (negative for buyer)
  - [ ] Project reference
  - [ ] Seller name
  - [ ] Timestamp
  - [ ] Status badge
- [ ] Pagination works (if > 10 transactions)
- [ ] Date filters work
- [ ] Transaction type filter works
- [ ] CSV export includes escrow transactions

---

## Test Scenario 9: Seller View

### Steps:
1. [ ] Login as seller user
2. [ ] Navigate to: Dashboard → My Projects

### Expected Results:
- [ ] Hired projects show escrow status
- [ ] Status: "Hired" with escrow badge
- [ ] Can view project activity
- [ ] Can see milestone completion buttons (if applicable)

### Transaction History (Seller):
3. [ ] Go to: Dashboard → Transaction History
4. [ ] Should see ESCROW_FUND entry
5. [ ] Amount shown as positive (incoming)
6. [ ] Shows buyer name

---

## Test Scenario 10: Admin View

### Steps:
1. [ ] Login as admin
2. [ ] Go to: MNT Escrow → All Transactions

### Expected Results:
- [ ] All escrow transactions visible
- [ ] Can filter by status, user, date
- [ ] Can view transaction details
- [ ] Admin actions available:
  - [ ] Force release
  - [ ] Force return
  - [ ] Cancel escrow
  - [ ] View related order

---

## Performance Tests

### Load Time:
- [ ] Escrow deposit page loads < 2 seconds
- [ ] AJAX payment processing completes < 3 seconds
- [ ] Transaction history loads < 2 seconds

### Database Queries:
- [ ] Check query count on escrow deposit page
- [ ] Should be < 30 queries
- [ ] No N+1 query problems

### Browser Compatibility:
- [ ] Chrome: All features work
- [ ] Firefox: All features work
- [ ] Safari: All features work
- [ ] Edge: All features work
- [ ] Mobile Chrome: All features work
- [ ] Mobile Safari: All features work

---

## Security Tests

### Authentication:
- [ ] Cannot access escrow page when logged out (should redirect)
- [ ] Cannot hire without login
- [ ] Cannot create escrow for other users' projects

### Authorization:
- [ ] Buyer can only hire their own projects
- [ ] Seller cannot hire themselves
- [ ] Admin can view all transactions

### Input Validation:
- [ ] SQL injection attempts blocked
- [ ] XSS attempts sanitized
- [ ] CSRF tokens validated
- [ ] Amount manipulation prevented

---

## Regression Tests

### Existing Features Still Work:
- [ ] Regular WooCommerce checkout (for packages)
- [ ] Wallet deposit via Paystack
- [ ] Wallet withdrawal
- [ ] Transaction history (non-escrow)
- [ ] Project creation
- [ ] Proposal submission
- [ ] Project editing
- [ ] User profile updates

---

## Issue Tracking

### Found Issues:
| # | Issue | Severity | Status | Notes |
|---|-------|----------|--------|-------|
| 1 |       | High/Med/Low | Open/Fixed | |
| 2 |       |          |        | |
| 3 |       |          |        | |

---

## Sign-Off

### Testing Completed By:
- Name: _____________________
- Date: _____________________
- Environment: Dev / Staging / Production

### Test Results:
- [ ] All tests passed
- [ ] Some tests failed (see issues above)
- [ ] Ready for production
- [ ] Needs fixes before production

### Notes:
```
Add any additional observations, recommendations, or concerns here.
```

---

## Quick Reference

### Test Users Needed:
- Admin user
- Buyer user (with wallet + funds)
- Buyer user (without wallet)
- Buyer user (insufficient funds)
- Seller user

### Test Data Needed:
- 1-2 projects with proposals
- 1-2 milestone projects
- Various price points (₦1000, ₦5000, ₦10000)

### Key URLs:
- Escrow Deposit: `/escrow-deposit/`
- Transaction History: `/dashboard/transactions/`
- My Projects: `/dashboard/projects/`
- Billings: `/dashboard/billing-settings/`
- Admin Escrow: `/wp-admin/admin.php?page=mnt-escrow-transactions`

### Debug Commands:
```php
// Check if page exists
$page = get_page_by_path('escrow-deposit');
var_dump($page);

// Check wallet balance
$balance = mnt_get_wallet_balance($user_id);
var_dump($balance);

// Check project meta
$escrow_id = get_post_meta($project_id, 'mnt_escrow_id', true);
var_dump($escrow_id);
```

---

**End of Testing Checklist**
