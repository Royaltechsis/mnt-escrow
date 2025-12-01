<?php
namespace MNT\Api;

class Payment {

    /**
     * Initialize Paystack payment
     */
    public static function initialize_paystack($user_id, $amount, $email, $callback_url = '') {
        return Client::post('/payment/paystack/initialize', [
            'user_id' => $user_id,
            'amount' => floatval($amount),
            'email' => $email,
            'callback_url' => $callback_url
        ]);
    }

    /**
     * Verify Paystack payment
     */
    public static function verify_paystack($reference) {
        return Client::post('/payment/paystack/verify', [
            'reference' => $reference
        ]);
    }

    /**
     * Process webhook from Paystack
     */
    /* public static function paystack_webhook($payload) {
        return Client::post('/payment/paystack/webhook', $payload);
    } */
}
