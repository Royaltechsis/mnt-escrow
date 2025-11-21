<?php
namespace MNT\Api;

class Wallet {

    /**
     * Create a new wallet for a user
     */
    public static function create($wp_user_id) {
        $user = get_userdata($wp_user_id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        return Client::post('/wallet/create', [
            'user_id' => (string)$wp_user_id,
            'email' => $user->user_email,
            'currency' => 'NGN',
            'created_at' => current_time('c') // ISO 8601 format
        ]);
    }

    /**
     * Get wallet details
     */
    public static function get($wp_user_id) {
        return Client::post('/wallet/get', [
            'user_id' => $wp_user_id
        ]);
    }

    /**
     * Get wallet by user ID (admin endpoint)
     */
    public static function get_by_user($wp_user_id) {
        return Client::get('/admin/wallet/get_wallet', [
            'user_id' => (string)$wp_user_id
        ]);
    }

    /**
     * Get wallet balance
     */
    public static function balance($wp_user_id) {
        return Client::get('/wallet/balance', [
            'user_id' => $wp_user_id
        ]);
    }

    /**
     * Credit wallet (manual/admin credit)
     */
    public static function credit($wp_user_id, $amount) {
        return Client::post('/wallet/credit', [
            'user_id' => $wp_user_id,
            'amount'  => floatval($amount)
        ]);
    }

    /**
     * Deposit funds to wallet
     */
    public static function deposit($wp_user_id, $amount, $payment_method = 'paystack', $reference = null) {
        return Client::post('/wallet/deposit', [
            'user_id' => $wp_user_id,
            'amount' => floatval($amount),
            'payment_method' => $payment_method,
            'reference' => $reference
        ]);
    }

    /**
     * Withdraw funds from wallet
     */
    public static function withdraw($wp_user_id, $amount, $account_details = []) {
        return Client::post('/wallet/withdraw', [
            'user_id' => $wp_user_id,
            'amount' => floatval($amount),
            'account_details' => $account_details
        ]);
    }

    /**
     * Transfer between wallets
     */
    public static function transfer($from_user_id, $to_user_id, $amount, $description = '') {
        return Client::post('/wallet/transfer', [
            'from_user_id' => $from_user_id,
            'to_user_id' => $to_user_id,
            'amount' => floatval($amount),
            'description' => $description
        ]);
    }

    /**
     * Get transaction history
     */
    public static function transactions($wp_user_id, $limit = 50, $offset = 0) {
        return Client::post('/wallet/transactions', [
            'user_id' => $wp_user_id,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }
}
