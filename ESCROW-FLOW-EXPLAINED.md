# MNT Escrow Flow Explained

## API Endpoints & Their Purpose

### 1. **Create Escrow Transaction**
- **Endpoint**: `POST /api/escrow/create_transaction`
- **Purpose**: Create a new escrow transaction
- **Status**: Sets escrow to `PENDING`
- **Money Movement**: No money moved yet, just creates the escrow record
- **Parameters**:
  ```json
  {
    "merchant_id": "seller_id",
    "client_id": "buyer_id",
    "amount": 5000,
    "project_id": "task_or_project_id",
    "milestone": []
  }
  ```

---

### 2. **Fund Escrow (Client Release Funds)**
- **Endpoint**: `POST /api/escrow/client_release_funds`
- **Purpose**: Move funds FROM buyer's wallet TO escrow account
- **Status Change**: `PENDING` → `FUNDED`
- **Money Movement**: **Buyer Wallet → Escrow Account**
- **When to Use**: After escrow is created, buyer funds it to secure the payment
- **Parameters**:
  ```json
  {
    "project_id": "6909",
    "user_id": "218"
  }
  ```
- **Note**: Despite the name "release_funds", this actually FUNDS the escrow, not releases to seller

---

### 3. **Release to Seller (Client Confirm)**
- **Endpoint**: `POST /api/escrow/client_confirm`
- **Purpose**: Move funds FROM escrow account TO seller's wallet
- **Status Change**: `FUNDED` → `FINALIZED`
- **Money Movement**: **Escrow Account → Seller Wallet**
- **When to Use**: When work is completed and buyer approves/confirms payment
- **Parameters**:
  ```json
  {
    "project_id": "6909",
    "user_id": "218",
    "confirm_status": true
  }
  ```
- **Note**: `confirm_status` must be `true` to release funds to seller

---

## Complete Escrow Flow

### For Regular Tasks (Non-Milestone)

```
1. CREATE ESCROW
   POST /api/escrow/create_transaction
   Status: PENDING
   Money: Not moved yet
   User Action: Click "Create Escrow" button
   
   ↓

2. SHOW MODAL - "Release Funds to Escrow"
   User sees popup with button to fund escrow
   
   ↓

3. FUND ESCROW (Manual - User clicks button)
   POST /api/escrow/client_release_funds
   Status: PENDING → FUNDED
   Money: Buyer Wallet → Escrow Account
   User Action: Click "Release Funds" button in modal
   
   Automatic Actions After Funding:
   - Task status updated to "HIRED"
   - WooCommerce order created (if WooCommerce active)
   - Task purchase completed
   - Seller notified of new task
   
   ↓

4. COMPLETE WORK
   Seller delivers, buyer reviews
   
   ↓

5. RELEASE TO SELLER
   POST /api/escrow/client_confirm
   Params: { project_id, user_id, confirm_status: true }
   Status: FUNDED → FINALIZED
   Money: Escrow Account → Seller Wallet
   User Action: Click "Complete Contract" button
```

---

### For Milestone Projects

```
1. CREATE MILESTONE ESCROW
   POST /api/escrow/create_milestone
   Status: PENDING
   Money: Not moved yet
   
   ↓

2. FUND ESCROW
   POST /api/escrow/client_release_funds
   Status: PENDING → FUNDED
   Money: Buyer Wallet → Escrow Account
   
   ↓

3. COMPLETE MILESTONE
   Seller delivers milestone work
   
   ↓

4. APPROVE MILESTONE
   POST /api/escrow/client_confirm_milestone
   Status: FUNDED → FINALIZED (for that milestone)
   Money: Escrow Account → Seller Wallet (milestone amount)
```

---

## Implementation in Code

### Task Escrow Creation (Manual Funding)
**File**: `page-create-escrow-task.php`

```php
// Create escrow with auto-fund disabled (5th parameter = false)
$escrow_result = Escrow::create($merchant_id, $client_id, $amount, $task_id, false);

// This only creates escrow in PENDING status
// User must click "Release Funds" button to fund it
// Modal appears with button calling mnt_fund_escrow AJAX action
```

### Fund Escrow (User Action)
**File**: `init.php` - `handle_fund_escrow_ajax()`

```php
// When user clicks "Release Funds to Escrow" button in modal
// This moves money from wallet to escrow account
$result = Escrow::fund_escrow($user_id, $project_id);

// Money Movement: Buyer Wallet → Escrow Account
// Status Change: PENDING → FUNDED
```

### Complete Contract (Release to Seller)
**File**: `init.php` - `handle_complete_escrow_funds_ajax()`

```php
// When buyer clicks "Complete Contract"
// This releases funds from escrow to seller wallet
$result = Escrow::client_confirm($project_id, $user_id, true);

// Money Movement: Escrow Account → Seller Wallet
// Status Change: FUNDED → FINALIZED
// confirm_status = true to release funds
```

### Milestone Approval
**File**: `escrow.js` - Milestone approval handler

```php
// When buyer clicks "Approve Milestone"
$result = Escrow::client_confirm_milestone($milestone_key, $user_id);

// Money Movement: Escrow Account → Seller Wallet (milestone amount)
// Status Change: FUNDED → FINALIZED (for that milestone)
```

---

## Key Functions in Escrow.php

### `fund_escrow($user_id, $project_id)`
- **API Endpoint**: `/escrow/client_release_funds`
- **Purpose**: Fund the escrow (buyer wallet → escrow)
- **Status**: PENDING → FUNDED
- **Alias**: `client_release_funds()` (deprecated, for backward compatibility)

### `client_confirm($project_id, $user_id, $confirm_status)`
- **API Endpoint**: `/escrow/client_confirm`
- **Purpose**: Release funds to seller (escrow → seller wallet)
- **Status**: FUNDED → FINALIZED
- **Parameters**: Set `confirm_status = true` to release funds

### `create($merchant_id, $client_id, $amount, $project_id, $auto_release)`
- **API Endpoint**: `/escrow/create_transaction`
- **Purpose**: Create new escrow
- **Status**: Creates as PENDING
- **Auto-Release**: If `$auto_release = true`, automatically calls `fund_escrow()` after creation

---

## Money Flow Summary

```
┌─────────────────┐
│  Buyer Wallet   │
└────────┬────────┘
         │
         │ client_release_funds (fund_escrow)
         │ Status: PENDING → FUNDED
         ↓
┌─────────────────┐
│ Escrow Account  │ ← Money held in escrow
└────────┬────────┘
         │
         │ client_confirm (confirm_status = true)
         │ Status: FUNDED → FINALIZED
         ↓
┌─────────────────┐
│ Seller Wallet   │
└─────────────────┘
```

---

## Status Transitions

- **PENDING**: Escrow created, not funded yet
- **FUNDED**: Money moved from buyer wallet to escrow account
- **FINALIZED**: Money released from escrow to seller wallet

---

## Important Notes

1. **`client_release_funds` endpoint does NOT release to seller**
   - Despite the name, it moves money TO escrow (funding it)
   - Money flow: Buyer Wallet → Escrow Account
   - Status change: PENDING → FUNDED
   - It's the first step after creating escrow
   
2. **`client_confirm` actually releases funds to seller**
   - This is what moves money from escrow to seller wallet
   - Money flow: Escrow Account → Seller Wallet
   - Status change: FUNDED → FINALIZED
   - Must pass `confirm_status: true` to release funds
   - Call this when work is approved/completed
   
3. **Manual funding for tasks**
   - Tasks create escrow in PENDING status
   - Modal appears with "Release Funds to Escrow" button
   - User must manually click button to fund escrow
   - AJAX action: `mnt_fund_escrow` calls `/escrow/client_release_funds`
   - Funds move from buyer wallet to escrow account (PENDING → FUNDED)
   - Provides user control over when funds are committed
   
4. **For milestones, use `client_confirm_milestone`**
   - Similar to `client_confirm` but for milestone projects
   - Releases funds for specific milestone
   - Also requires `confirm_status: true`
