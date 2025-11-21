<?php
namespace MNT\Api;

class Escrow {

    /**
     * Create a new escrow transaction
     */
    public static function create($buyer_id, $seller_id, $amount, $task_id = null, $description = '') {
        return Client::post('/escrow/create', [
            'buyer_id' => $buyer_id,
            'seller_id' => $seller_id,
            'amount' => floatval($amount),
            'task_id' => $task_id,
            'description' => $description
        ]);
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
