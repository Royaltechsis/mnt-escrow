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
    public static function list_by_user($user_id, $type = null, $limit = 50, $offset = 0) {
        $data = [
            'user_id' => $user_id,
            'limit' => $limit,
            'offset' => $offset
        ];
        if ($type) {
            $data['type'] = $type; // 'deposit', 'withdraw', 'transfer', 'escrow'
        }
        return Client::post('/transaction/list', $data);
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
