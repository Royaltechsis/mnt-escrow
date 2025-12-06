<?php
namespace MNT\Api;

class Escrow {

    /**
     * Get all transactions for a user (client or merchant)
     * @param int $user_id
     * @param string $actor (client|merchant)
     * @return array|false
     */
    public static function get_all_transactions($user_id, $actor = 'client') {
        $params = [
            'user_id' => (string)$user_id,
            'actor' => $actor
        ];
        return Client::get('/escrow/get_all_transactions', $params);
    }

    /**
     * Client release funds - Move funds from client wallet to escrow account
     * POST /api/escrow/client_release_funds
     * This changes escrow status from PENDING to FUNDED
     * Money moves: Client Wallet → Escrow Account (held in escrow)
     * 
     * @param string $project_id Project ID
     * @param string $client_id Client/buyer user ID
     * @param string $merchant_id Merchant/seller user ID
     * @return array|false API response or false on failure
     */
    public static function client_release_funds($project_id, $client_id, $merchant_id) {
        $data = [
            'project_id' => (string)$project_id,
            'client_id' => (string)$client_id,
            'merchant_id' => (string)$merchant_id
        ];
        
        error_log('=== MNT API - client_release_funds ===');
        error_log('Endpoint: POST /escrow/client_release_funds');
        error_log('Purpose: Move funds from wallet to escrow (pending → funded)');
        error_log('Note: For tasks, task_id is passed as project_id');
        error_log('');
        error_log('=== API Payload ===');
        error_log(json_encode($data, JSON_PRETTY_PRINT));
        error_log('');
        error_log('Parameters breakdown:');
        error_log('  project_id: ' . $project_id . ' (type: ' . gettype($project_id) . ')');
        error_log('  client_id: ' . $client_id . ' (type: ' . gettype($client_id) . ')');
        error_log('  merchant_id: ' . $merchant_id . ' (type: ' . gettype($merchant_id) . ')');
        
        $result = Client::post('/escrow/client_release_funds', $data);
        
        error_log('API Response: ' . json_encode($result));
        error_log('Response is_null: ' . (is_null($result) ? 'YES' : 'NO'));
        error_log('Response is_array: ' . (is_array($result) ? 'YES' : 'NO'));
        error_log('Response has error: ' . (isset($result['error']) ? 'YES: ' . $result['error'] : 'NO'));
        error_log('Response has detail: ' . (isset($result['detail']) ? 'YES: ' . $result['detail'] : 'NO'));
        
        return $result;
    }
    
    /**
     * Alias for backward compatibility
     * @deprecated Use client_release_funds() instead
     */
   /*  public static function fund_escrow($client_id, $merchant_id, $project_id) {
        return self::client_release_funds($project_id, $client_id, $merchant_id);
    } */

    /**
     * Create a new escrow transaction
     * POST /api/escrow/create_transaction
     *
     * @param string $merchant_id Seller or merchant ID
     * @param string $client_id   Buyer or client ID
     * @param string $project_id  Project or task ID
     * @param float $amount       Amount (must be > 0)
     * @param bool $auto_release  If true, automatically fund escrow after creation (pending → funded)
     * @return array|false        API response or false on failure
     */
    public static function create($merchant_id, $client_id, $project_id, $amount, $auto_release = false) {
        $data = [
            'merchant_id' => (string)$merchant_id,
            'client_id'   => (string)$client_id,
            'project_id'  => (string)$project_id,
            'amount'      => floatval($amount)
        ];
        
        error_log('=== MNT API - CREATE REGULAR ESCROW ===');
        error_log('Method: Escrow::create()');
        error_log('Endpoint: POST /escrow/create_transaction');
        error_log('Purpose: Create new escrow transaction (PENDING status)');
        error_log('');
        error_log('=== API Payload ===');
        error_log(json_encode($data, JSON_PRETTY_PRINT));
        error_log('');
        error_log('Parameters breakdown:');
        error_log('  merchant_id: ' . $merchant_id . ' (type: ' . gettype($merchant_id) . ')');
        error_log('  client_id: ' . $client_id . ' (type: ' . gettype($client_id) . ')');
        error_log('  project_id: ' . $project_id . ' (type: ' . gettype($project_id) . ')');
        error_log('  amount: ' . $amount . ' (type: ' . gettype($amount) . ')');
        error_log('  auto_release: ' . ($auto_release ? 'YES' : 'NO'));
        
        $result = Client::post('/escrow/create_transaction', $data);
        
        error_log('MNT API - Escrow::create() result: ' . json_encode($result));
        error_log('MNT API - Result is_null: ' . (is_null($result) ? 'YES' : 'NO'));
        
        // If auto_release is true and escrow was created successfully, fund the escrow immediately
        if ($auto_release && $result && !isset($result['error']) && !isset($result['detail']) && $project_id) {
            error_log('MNT API - Auto-funding escrow for task/project: ' . $project_id);
            error_log('MNT API - Moving funds from wallet to escrow (pending → funded)');
            
            $fund_result = self::client_release_funds($project_id, $client_id, $merchant_id);
            
            error_log('MNT API - Auto-fund result: ' . json_encode($fund_result));
            
            // Add funding info to the result
            if ($fund_result && !isset($fund_result['error'])) {
                $result['auto_funded'] = true;
                $result['fund_response'] = $fund_result;
            } else {
                $result['auto_funded'] = false;
                $result['fund_error'] = $fund_result;
            }
        }
        
        return $result;
    }

    /** unresolved
     * Cancel escrow transaction
     * DELETE /api/escrow/cancel_transaction
     * 
     * @param string $project_id Project ID
     * @param string $client_id Client/buyer user ID
     * @param string $merchant_id Merchant/seller user ID
     * @return array|false API response or false on failure
     */
    public static function cancel_transaction($project_id, $client_id, $merchant_id) {
        $data = [
            'project_id' => (string)$project_id,
            'client_id' => (string)$client_id,
            'merchant_id' => (string)$merchant_id
        ];
        
        error_log('MNT API - cancel_transaction called');
        error_log('MNT API - Endpoint: /escrow/cancel_transaction (DELETE)');
        error_log('MNT API - Payload: ' . json_encode($data));
        
        $result = Client::delete('/escrow/cancel_transaction', $data);
        
        error_log('MNT API - cancel_transaction result: ' . json_encode($result));
        
        return $result;
    }

    /** unresolved
     * Dispute escrow transaction
     * POST /api/escrow/dispute_transaction
     * 
     * @param string $project_id Project ID
     * @param string $client_id Client/buyer ID
     * @param string $merchant_id Merchant/seller ID
     * @param string $reason Optional reason for dispute
     * @return array|false API response or false on failure
     */
    public static function dispute_transaction($project_id, $client_id, $merchant_id, $reason = '') {
        $data = [
            'project_id' => (string)$project_id,
            'client_id' => (string)$client_id,
            'merchant_id' => (string)$merchant_id
        ];
        
        if (!empty($reason)) {
            $data['reason'] = (string)$reason;
        }
        
        error_log('MNT API - dispute_transaction called');
        error_log('MNT API - Endpoint: /escrow/dispute_transaction');
        error_log('MNT API - Payload: ' . json_encode($data));
        
        $result = Client::post('/escrow/dispute_transaction', $data);
        
        error_log('MNT API - dispute_transaction result: ' . json_encode($result));
        
        return $result;
    }

    /**
     * Client confirm transaction - Releases funds from escrow to seller wallet
     * POST /api/escrow/client_confirm
     * This moves money from escrow account to seller wallet
     * Status change: FUNDED → FINALIZED
     * 
     * @param string $project_id Project ID
     * @param string $client_id Client/buyer user ID
     * @param string $merchant_id Merchant/seller user ID
     * @param bool $confirm_status Confirmation status true
     * @param string $milestone_key Optional milestone key
     * @return array|false API response or false on failure
     */
    public static function client_confirm($project_id, $client_id, $merchant_id, $confirm_status = true, $milestone_key = '') {
        $data = [
            'project_id' => (string)$project_id,
            'client_id' => (string)$client_id,
            'merchant_id' => (string)$merchant_id,
            'confirm_status' => (bool)$confirm_status
        ];
        
        // Add milestone_key if provided
        if (!empty($milestone_key)) {
            $data['milestone_key'] = (string)$milestone_key;
        }
        
        error_log('');
        error_log('=== MNT API - client_confirm() ===');
        error_log('Full Endpoint URL: https://escrow-api-dfl6.onrender.com/api/escrow/client_confirm');
        error_log('HTTP Method: POST');
        error_log('Purpose: Release funds from escrow account to seller wallet');
        error_log('Status Change: FUNDED → FINALIZED');
        error_log('');
        error_log('Payload being sent to API:');
        error_log(json_encode($data, JSON_PRETTY_PRINT));
        error_log('');
        error_log('Making HTTP request...');
        
        $result = Client::post('/escrow/client_confirm', $data);
        
        error_log('');
        error_log('API Response received:');
        error_log(json_encode($result, JSON_PRETTY_PRINT));
        error_log('Response is_null: ' . (is_null($result) ? 'YES' : 'NO'));
        error_log('Response is_array: ' . (is_array($result) ? 'YES' : 'NO'));
        error_log('Has error field: ' . (isset($result['error']) ? 'YES - ' . $result['error'] : 'NO'));
        error_log('Has detail field: ' . (isset($result['detail']) ? 'YES - ' . $result['detail'] : 'NO'));
        error_log('Has message field: ' . (isset($result['message']) ? 'YES - ' . $result['message'] : 'NO'));
        error_log('==================================');
        error_log('');
        
        return $result;
    }

    /**
     * Get escrow transaction details by project ID
     * GET /api/escrow/get_transaction
     * 
     * @param string $project_id Project ID
     * @param string $merchant_id Merchant ID
     * @return array|false API response or false on failure
     */
    public static function get_transaction($project_id, $merchant_id) {
        return Client::get('/escrow/get_transaction', [
            'project_id' => (string)$project_id,
            'merchant_id' => (string)$merchant_id
        ]);
    }
    
    /**
     * Get escrow details by project ID (alias for backward compatibility)
     * 
     * @param string $project_id Project ID
     * @param string $merchant_id Merchant ID
     * @return array|false API response or false on failure
     */
    public static function get_escrow_by_id($project_id, $merchant_id) {
        return self::get_transaction($project_id, $merchant_id);
    }

    /**
     * Create a milestone transaction (for milestone-based projects)
     * POST /api/escrow/create_milestone
     * 
     * @param string $merchant_id Seller or merchant ID
     * @param string $client_id Buyer or client ID
     * @param string $project_id Project ID
     * @param array $milestones Array of milestone objects with: key, title, amount, description
     * @return array|false API response or false on failure
     * 
     * Example milestone structure:
     * [
     *   [
     *     'key' => 'milestone_id',
     *     'title' => 'Milestone 1',
     *     'amount' => 100,
     *     'description' => 'Description for Milestone 1'
     *   ]
     * ]
     */
    public static function create_milestone_transaction($merchant_id, $client_id, $project_id, $milestones) {
        // Ensure milestone data structure matches API spec exactly
        $formatted_milestones = [];
        foreach ($milestones as $milestone) {
            $formatted_milestones[] = [
                'amount' => floatval($milestone['amount'] ?? 0),
                'description' => (string)($milestone['description'] ?? ''),
                'key' => (string)($milestone['key'] ?? ''),
                'title' => (string)($milestone['title'] ?? $milestone['milestone_name'] ?? '')
            ];
        }
        
        $data = [
            'client_id' => (string)$client_id,
            'merchant_id' => (string)$merchant_id,
            'milestone' => $formatted_milestones,
            'project_id' => (string)$project_id
        ];
        
        error_log('MNT API - create_milestone_transaction called');
        error_log('MNT API - Endpoint: /escrow/create_milestone');
        error_log('MNT API - Payload: ' . json_encode($data));
        
        $result = Client::post('/escrow/create_milestone', $data);
        
        error_log('MNT API - create_milestone_transaction result: ' . json_encode($result));
        error_log('MNT API - Result is_null: ' . (is_null($result) ? 'YES' : 'NO'));
        
        return $result;
    }

    /**
     * Client confirm milestone (buyer approves milestone and releases funds to seller wallet)
     * POST /api/escrow/client_confirm_milestone
     * 
     * @param string $project_id Project ID
     * @param string $client_id Client/buyer user ID
     * @param string $merchant_id Merchant/seller user ID
     * @param string $milestone_key Milestone key/ID
     * @param bool $confirm_status Confirmation status (defaults to false per API spec)
     * @return array|false API response or false on failure
     */
    public static function client_confirm_milestone($project_id, $client_id, $merchant_id, $milestone_key, $confirm_status = false) {
        // Match exact API specification format
        $data = [
            'project_id' => (string)$project_id,
            'client_id' => (string)$client_id,
            'merchant_id' => (string)$merchant_id,
            'milestone_key' => (string)$milestone_key,
            'confirm_status' => (bool)$confirm_status
        ];
        
        error_log('MNT API - client_confirm_milestone called');
        error_log('MNT API - Endpoint: /escrow/client_confirm_milestone');
        error_log('MNT API - Payload: ' . json_encode($data));
        
        $result = Client::post('/escrow/client_confirm_milestone', $data);
        
        error_log('MNT API - client_confirm_milestone result: ' . json_encode($result));
        error_log('MNT API - Result is_null: ' . (is_null($result) ? 'YES' : 'NO'));
        error_log('MNT API - Result is_array: ' . (is_array($result) ? 'YES' : 'NO'));
        
        return $result;
    }
}



