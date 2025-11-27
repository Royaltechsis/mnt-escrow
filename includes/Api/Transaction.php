<?php
namespace MNT\Api;

class Transaction {

    /**
     * Get transaction details
     */
    public static function get($transaction_id) {
        return Client::post('/transaction/get', [
            'transaction_id' => $transaction_id
        ]);
    }

    /**
     * List all transactions for a user
     */
    public static function list_by_user($user_id, $type = null, $limit = null, $offset = null, $start_date = null, $end_date = null) {
        $params = [
            'user_id' => $user_id
        ];
        // Only add optional parameters if they have values
        if ($type && $type !== '') {
            $params['type'] = strtoupper($type); // API expects uppercase: DEPOSIT, WITHDRAWAL
        }
        if ($limit !== null) {
            $params['limit'] = $limit;
        }
        if ($offset !== null) {
            $params['offset'] = $offset;
        }
        // Only add dates if explicitly provided (for filtering)
        if ($start_date && $start_date !== '') {
            $params['start_date'] = $start_date;
        }
        if ($end_date && $end_date !== '') {
            $params['end_date'] = $end_date;
        }
        
        // Use GET request with query parameters
        return Client::get('/wallet/transaction_history?' . http_build_query($params));
    }

    /**
     * List all transactions (admin only)
     */
    public static function list_all($type = null, $limit = null, $offset = null, $start_date = null, $end_date = null, $user_id = null) {
        $params = [];
        if ($user_id && $user_id > 0) {
            $params['user_id'] = $user_id;
        }
        if ($type && $type !== '') {
            $params['type'] = strtoupper($type);
        }
        // Only add dates if explicitly provided
        if ($start_date && $start_date !== '') {
            $params['start_date'] = $start_date;
        }
        if ($end_date && $end_date !== '') {
            $params['end_date'] = $end_date;
        }
        if ($limit !== null) {
            $params['limit'] = $limit;
        }
        if ($offset !== null) {
            $params['offset'] = $offset;
        }
        
        // Use GET request for admin endpoint
        return Client::get('/admin/wallet/get_transactions?' . http_build_query($params));
    }

    /**
     * Get transaction statistics
     */
    public static function stats($user_id, $start_date = null, $end_date = null) {
        $data = ['user_id' => $user_id];
        if ($start_date) $data['start_date'] = $start_date;
        if ($end_date) $data['end_date'] = $end_date;
        return Client::post('/transaction/stats', $data);
    }
}
