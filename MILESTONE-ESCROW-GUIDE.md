# Milestone-Based Escrow Implementation Guide

## Overview
This guide explains how to handle milestone-based escrow transactions where a project has multiple milestones, each requiring separate escrow creation.

## Current Situation
- ✅ Frontend now redirects milestone buttons to create-escrow page
- ⚠️ Backend creates escrow by project only (not by milestone)
- 🎯 Need: Backend should support multiple escrows per project (one per milestone)

---

## Frontend Implementation (✅ COMPLETE)

### Changes Made

**1. Proposal Detail Page - Milestone Buttons**
- **File:** `extend/dashboard/post-project/buyer/dashboard-proposals-detail.php`
- **Lines:** 158-175
- Milestone "Pay & Hire" buttons now redirect to `/create-escrow/` with milestone parameters

**2. Activity Page - Milestone Buttons**
- **File:** `templates/dashboard/post-project/dashboard-project-milestone.php`
- **Lines:** 118-133
- Milestone "Escrow" buttons now redirect to `/create-escrow/` with milestone parameters

### URL Parameters Sent
```
/create-escrow/?merchant_id=123&client_id=456&project_id=789&amount=5000&proposal_id=101&milestone_key=0&milestone_title=First%20Milestone
```

**Parameters:**
- `merchant_id` - Seller ID
- `client_id` - Buyer ID
- `project_id` - Project ID (same for all milestones)
- `amount` - Milestone amount
- `proposal_id` - Proposal ID
- `milestone_key` - Milestone index (0, 1, 2, etc.)
- `milestone_title` - Milestone name

---

## Backend Options for Milestone Support

You have **3 options** to handle milestone-based escrow on your backend:

### Option 1: Use Project ID + Milestone Key (RECOMMENDED)

**Approach:** Treat each milestone as a separate escrow using combined identifier.

**Backend Changes:**
```javascript
// Your backend API endpoint: POST /api/escrow/create_transaction

// Current schema:
{
  merchant_id: string,
  client_id: string,
  amount: number,
  project_id: string  // Currently required
}

// NEW schema - Add milestone support:
{
  merchant_id: string,
  client_id: string,
  amount: number,
  project_id: string,
  milestone_id: string,      // NEW: e.g., "project_789_milestone_0"
  milestone_title: string,   // NEW: e.g., "Design Phase"
  milestone_key: number      // NEW: e.g., 0, 1, 2
}
```

**Database Changes:**
```sql
-- Add to escrow_transactions table
ALTER TABLE escrow_transactions 
ADD COLUMN milestone_id VARCHAR(255),
ADD COLUMN milestone_title VARCHAR(255),
ADD COLUMN milestone_key INT;

-- Add index for querying
CREATE INDEX idx_milestone_id ON escrow_transactions(milestone_id);
```

**Benefits:**
- ✅ Multiple escrows per project
- ✅ Clear milestone tracking
- ✅ Easy to query by project or milestone
- ✅ Preserves project relationship

**Query Examples:**
```javascript
// Get all escrows for a project
db.escrow_transactions.find({ project_id: "789" })

// Get specific milestone escrow
db.escrow_transactions.find({ 
  project_id: "789", 
  milestone_key: 0 
})

// Get all pending milestone escrows
db.escrow_transactions.find({ 
  project_id: "789",
  status: "pending"
})
```

---

### Option 2: Virtual Project IDs Per Milestone

**Approach:** Generate unique project ID for each milestone.

**Frontend Change:**
```php
// In create-escrow URL generation
$virtual_project_id = $project_id . '_milestone_' . $key;

$milestone_escrow_url = add_query_arg([
    'merchant_id' => $seller_id,
    'client_id' => $user_identity,
    'project_id' => $virtual_project_id,  // "789_milestone_0"
    'amount' => floatval($price),
    // ... other params
], $escrow_page_url);
```

**Backend Changes:**
```javascript
// Parse virtual project ID
const parseProjectId = (projectId) => {
  if (projectId.includes('_milestone_')) {
    const [realProjectId, , milestoneKey] = projectId.split('_');
    return {
      isProject: false,
      isMilestone: true,
      projectId: realProjectId,
      milestoneKey: parseInt(milestoneKey)
    };
  }
  return {
    isProject: true,
    isMilestone: false,
    projectId: projectId,
    milestoneKey: null
  };
};
```

**Benefits:**
- ✅ No schema changes needed
- ✅ Works with existing backend
- ✅ Unique identifier per milestone
- ⚠️ Requires parsing logic

---

### Option 3: Separate Milestone Endpoint

**Approach:** Create dedicated endpoint for milestone escrow.

**New Backend Endpoint:**
```javascript
// POST /api/escrow/create_milestone_transaction
{
  merchant_id: string,
  client_id: string,
  amount: number,
  project_id: string,
  milestone_key: number,
  milestone_title: string
}
```

**Frontend Change:**
```php
// Check if it's a milestone
if (!empty($milestone_key)) {
    // Use milestone endpoint
    $result = Escrow::create_milestone(
        $merchant_id,
        $client_id,
        $amount,
        $project_id,
        $milestone_key,
        $milestone_title
    );
} else {
    // Use regular endpoint
    $result = Escrow::create(
        $merchant_id,
        $client_id,
        $amount,
        $project_id
    );
}
```

**Benefits:**
- ✅ Clear separation of concerns
- ✅ Dedicated validation for milestones
- ✅ Easy to maintain
- ⚠️ Requires new endpoint

---

## Recommended Implementation: Option 1

### Step 1: Update Frontend to Send Milestone Data

**File:** `includes/ui/init.php` (around line 460)

```php
// In handle_create_escrow_ajax() method
$milestone_key = isset($_POST['milestone_key']) ? intval($_POST['milestone_key']) : null;
$milestone_title = isset($_POST['milestone_title']) ? sanitize_text_field($_POST['milestone_title']) : '';

// Generate milestone ID if milestone transaction
$milestone_id = null;
if ($milestone_key !== null) {
    $milestone_id = $project_id . '_milestone_' . $milestone_key;
}

// Call API with milestone data
$result = Escrow::create(
    $seller_id,
    $buyer_id,
    $amount,
    $project_id,
    $milestone_id,      // NEW
    $milestone_title,   // NEW
    $milestone_key      // NEW
);
```

### Step 2: Update API Client

**File:** `includes/Api/Escrow.php`

```php
/**
 * Create a new escrow transaction with milestone support
 */
public static function create($merchant_id, $client_id, $amount, $project_id = null, $milestone_id = null, $milestone_title = null, $milestone_key = null) {
    $data = [
        'merchant_id' => $merchant_id,
        'client_id'   => $client_id,
        'amount'      => floatval($amount)
    ];
    
    if ($project_id) {
        $data['project_id'] = $project_id;
    }
    
    // Add milestone data if present
    if ($milestone_id !== null) {
        $data['milestone_id'] = $milestone_id;
        $data['milestone_title'] = $milestone_title;
        $data['milestone_key'] = $milestone_key;
    }
    
    return Client::post('/escrow/create_transaction', $data);
}
```

### Step 3: Update Backend API

**Your Backend (Node.js/Express example):**

```javascript
// POST /api/escrow/create_transaction
app.post('/api/escrow/create_transaction', async (req, res) => {
  const { 
    merchant_id, 
    client_id, 
    amount, 
    project_id,
    milestone_id,      // NEW
    milestone_title,   // NEW
    milestone_key      // NEW
  } = req.body;
  
  // Validation
  if (!merchant_id || !client_id || !amount) {
    return res.status(400).json({ error: 'Missing required fields' });
  }
  
  // Create escrow record
  const escrow = await db.escrow_transactions.create({
    id: generateUUID(),
    merchant_id,
    client_id,
    amount,
    project_id,
    milestone_id: milestone_id || null,
    milestone_title: milestone_title || null,
    milestone_key: milestone_key !== undefined ? milestone_key : null,
    status: 'pending',
    created_at: new Date()
  });
  
  res.json({
    success: true,
    escrow_id: escrow.id,
    message: milestone_id 
      ? `Milestone escrow created: ${milestone_title}` 
      : 'Project escrow created'
  });
});
```

### Step 4: Update Query Methods

**Get Escrows by Project:**
```javascript
// Get all escrows for a project (including milestones)
app.get('/api/escrow/get_all_transactions', async (req, res) => {
  const { user_id, actor } = req.query;
  
  let query = {};
  if (actor === 'client') {
    query.client_id = user_id;
  } else if (actor === 'merchant') {
    query.merchant_id = user_id;
  }
  
  const escrows = await db.escrow_transactions.find(query);
  
  // Group by project if needed
  const groupedByProject = escrows.reduce((acc, escrow) => {
    const projectId = escrow.project_id;
    if (!acc[projectId]) {
      acc[projectId] = {
        project_id: projectId,
        total_amount: 0,
        milestones: [],
        project_escrow: null
      };
    }
    
    if (escrow.milestone_id) {
      acc[projectId].milestones.push(escrow);
      acc[projectId].total_amount += escrow.amount;
    } else {
      acc[projectId].project_escrow = escrow;
      acc[projectId].total_amount += escrow.amount;
    }
    
    return acc;
  }, {});
  
  res.json({
    success: true,
    escrows: Object.values(groupedByProject)
  });
});
```

---

## WordPress Integration

### Update Template to Show Milestone Data

**File:** `templates/page-create-escrow.php`

```php
// Get milestone parameters
$milestone_key = isset($_GET['milestone_key']) ? intval($_GET['milestone_key']) : null;
$milestone_title = isset($_GET['milestone_title']) ? urldecode($_GET['milestone_title']) : '';

// Display in form
if ($milestone_key !== null) {
    echo '<div class="milestone-info">';
    echo '<h3>Milestone Escrow Payment</h3>';
    echo '<p><strong>Milestone:</strong> ' . esc_html($milestone_title) . '</p>';
    echo '<p><strong>Milestone #:</strong> ' . ($milestone_key + 1) . '</p>';
    echo '</div>';
}
```

### Update Form Submission

```php
// Add hidden fields to form
<input type="hidden" name="milestone_key" value="<?php echo esc_attr($milestone_key); ?>">
<input type="hidden" name="milestone_title" value="<?php echo esc_attr($milestone_title); ?>">
```

---

## Testing Checklist

### Test Scenario 1: Create Milestone Escrow
1. [ ] Navigate to project with milestones
2. [ ] Click "Pay & Hire with Escrow" on first milestone
3. [ ] Verify redirected to create-escrow page
4. [ ] Verify milestone title displayed
5. [ ] Submit payment
6. [ ] Verify escrow created with milestone_id

### Test Scenario 2: Multiple Milestones
1. [ ] Pay for first milestone
2. [ ] Pay for second milestone
3. [ ] Verify two separate escrow records
4. [ ] Verify both linked to same project_id
5. [ ] Verify different milestone_key values

### Test Scenario 3: Query Escrows
1. [ ] Get all escrows for project
2. [ ] Verify both milestone and project escrows returned
3. [ ] Verify correct grouping
4. [ ] Verify amounts sum correctly

---

## Database Schema Example

```sql
CREATE TABLE escrow_transactions (
  id VARCHAR(36) PRIMARY KEY,
  merchant_id VARCHAR(36) NOT NULL,
  client_id VARCHAR(36) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  project_id VARCHAR(36),
  milestone_id VARCHAR(100),        -- NEW: e.g., "789_milestone_0"
  milestone_title VARCHAR(255),     -- NEW: e.g., "Design Phase"
  milestone_key INT,                -- NEW: 0, 1, 2, etc.
  status VARCHAR(20) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_merchant (merchant_id),
  INDEX idx_client (client_id),
  INDEX idx_project (project_id),
  INDEX idx_milestone (milestone_id),
  INDEX idx_status (status)
);
```

---

## Summary

**Frontend Changes (✅ DONE):**
- Milestone buttons redirect to create-escrow page
- Milestone parameters included in URL
- Works on both proposal detail and activity pages

**Backend Changes (⚠️ REQUIRED):**
1. Add milestone fields to database schema
2. Update create_transaction endpoint to accept milestone data
3. Update query endpoints to filter/group by milestone
4. Add validation for milestone-specific logic

**Choose Option 1** for the cleanest, most maintainable solution that preserves the relationship between projects and their milestones while allowing independent escrow tracking.

---

**Next Steps:**
1. Implement backend schema changes
2. Update API endpoints
3. Test milestone escrow creation
4. Verify transaction history displays correctly
5. Test release/refund for milestone escrows
