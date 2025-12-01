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
     * Release funds from client to escrow (custom endpoint)
     * @param int $user_id
     * @param int $project_id
     * @return array|false
     */
    public static function client_release_funds($user_id, $project_id) {
        $data = [
            'user_id' => (string)$user_id,
            'project_id' => (string)$project_id
        ];
        return Client::post('/escrow/client_release_funds', $data);
    }

    /**
     * Create a new escrow transaction (updated schema)
     *
     * @param string $merchant_id Seller or merchant ID
     * @param string $client_id   Buyer or client ID
     * @param float $amount       Amount (must be > 0)
     * @param string $project_id  Project or task ID (optional but recommended)
     * @return array|false        API response or false on failure
     */
    public static function create($merchant_id, $client_id, $amount, $project_id = null) {
        $data = [
            'merchant_id' => $merchant_id,
            'client_id'   => $client_id,
            'amount'      => floatval($amount)
        ];
        if ($project_id) {
            $data['project_id'] = $project_id;
        }
        return Client::post('/escrow/create_transaction', $data);
    }

    /**
     * Get escrow details
     */
    public static function get($escrow_id) {
        return Client::post('/escrow/get', [
            'escrow_id' => $escrow_id
        ]);
    }

    /**
     * Release funds to seller
     */
    public static function release($escrow_id, $buyer_id) {
        return Client::post('/escrow/release', [
            'escrow_id' => $escrow_id,
            'buyer_id' => $buyer_id
        ]);
    }

    /**
     * Refund to buyer
     */
    public static function refund($escrow_id, $seller_id) {
        return Client::post('/escrow/refund', [
            'escrow_id' => $escrow_id,
            'seller_id' => $seller_id
        ]);
    }

    /**
     * Cancel escrow
     */
    public static function cancel($escrow_id, $reason = '') {
        return Client::post('/escrow/cancel', [
            'escrow_id' => $escrow_id,
            'reason' => $reason
        ]);
    }

    /**
     * Open a dispute
     */
    public static function dispute($escrow_id, $user_id, $reason) {
        return Client::post('/escrow/dispute', [
            'escrow_id' => $escrow_id,
            'user_id' => $user_id,
            'reason' => $reason
        ]);
    }

    /**
     * Resolve a dispute
     */
    public static function resolve($escrow_id, $decision, $admin_id) {
        return Client::post('/escrow/resolve', [
            'escrow_id' => $escrow_id,
            'decision' => $decision, // 'release' or 'refund'
            'admin_id' => $admin_id
        ]);
    }

    /**
     * List escrows for a user
     */
    public static function list_by_user($user_id, $status = null) {
        $data = ['user_id' => $user_id];
        if ($status) {
            $data['status'] = $status;
        }
        return Client::post('/escrow/list', $data);
    }
}
