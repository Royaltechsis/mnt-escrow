<?php
namespace MNT\Api;

class wallet {

    /**
     * Create a new wallet for a user
     * POST /api/wallet/create
     * Schema: WalletRequestDTO {user_id: string, email: string, currency: CurrencyCode, created_at: datetime}
     */
    public static function create($wp_user_id) {
        $user = get_userdata($wp_user_id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        return Client::post('/wallet/create', [
            'user_id' => (string)$wp_user_id,
            'email' => $user->user_email,
            'currency' => 'NGN', // CurrencyCode enum: NGN, USD, EUR, GBP
            'created_at' => gmdate('c') // ISO 8601 format
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
     * GET /api/wallet/balance?user_id=X
     */
    public static function balance($wp_user_id) {
        return Client::get('/wallet/balance', [
            'user_id' => (string)$wp_user_id
        ]);
    }

    /**
     * Credit wallet (admin endpoint)
     * POST /api/admin/wallet/credit
     * Schema: TransactionRequestDTO {user_id: string, email: string, currency: CurrencyCode, 
     *         amount: number (>100), transaction_type: TransactionType, time: datetime, reason?: string}
     */
    public static function admin_credit($wp_user_id, $amount, $reason = '') {
        $user = get_userdata($wp_user_id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        return Client::post('/admin/wallet/credit', [
            'user_id' => (string)$wp_user_id,
            'email' => $user->user_email,
            'currency' => 'NGN',
            'amount' => floatval($amount),
            'transaction_type' => 'CREDIT',
            'time' => gmdate('c'),
            'reason' => $reason ?: null
        ]);
    }

    /**
     * Debit wallet (admin endpoint)
     * POST /api/admin/wallet/debit
     * Schema: TransactionRequestDTO {user_id: string, email: string, currency: CurrencyCode, 
     *         amount: number (>100), transaction_type: TransactionType, time: datetime, reason?: string}
     */
    public static function admin_debit($wp_user_id, $amount, $reason = '') {
        $user = get_userdata($wp_user_id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        return Client::post('/admin/wallet/debit', [
            'user_id' => (string)$wp_user_id,
            'email' => $user->user_email,
            'currency' => 'NGN',
            'amount' => floatval($amount),
            'transaction_type' => 'WITHDRAWAL',
            'time' => gmdate('c'),
            'reason' => $reason ?: null
        ]);
    }

    /**
     * Freeze wallet (admin endpoint)
     * POST /api/admin/wallet/freeze?user_id=X
     */
    public static function admin_freeze($wp_user_id) {
        return Client::post('/admin/wallet/freeze?' . http_build_query([
            'user_id' => (string)$wp_user_id
        ]), []);
    }

    /**
     * Unfreeze wallet (admin endpoint)
     * POST /api/admin/wallet/unfreeze?user_id=X
     */
    public static function admin_unfreeze($wp_user_id) {
        return Client::post('/admin/wallet/unfreeze?' . http_build_query([
            'user_id' => (string)$wp_user_id
        ]), []);
    }

    /**
     * Deposit funds to wallet (fund account)
     * POST /api/wallet/fund
     * Schema: PaymentRequest {email: string, amount: number (>0), metadata?: PaymentMetadata}
     * PaymentMetadata: {user_id: string, wallet_id?: string}
     */
    public static function deposit($wp_user_id, $amount, $wallet_id = null) {
        $user = get_userdata($wp_user_id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        // Ensure amount is valid
        $amount = floatval($amount);
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Amount must be greater than 0'];
        }

        // Get wallet_id if not provided
        if (!$wallet_id) {
            $wallet_result = self::get_by_user($wp_user_id);
            $wallet_id = $wallet_result['id'] ?? null;
        }

        $metadata = ['user_id' => (string)$wp_user_id];
        if ($wallet_id) {
            $metadata['wallet_id'] = (string)$wallet_id;
        }

        try {
            $result = Client::post('/wallet/fund', [
                'email' => $user->user_email,
                'amount' => $amount,
                'metadata' => $metadata
            ]);

            if (isset($result['error'])) {
                error_log('MNT Wallet Deposit Error: ' . print_r($result, true));
                return ['success' => false, 'message' => $result['error']];
            }

            return $result;
        } catch (\Exception $e) {
            error_log('MNT Wallet Deposit Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to initialize deposit: ' . $e->getMessage()];
        }
    }

    /**
     * Withdraw funds from wallet
     * POST /api/wallet/withdraw
     * Schema: TransactionRequestDTO {user_id: string, email: string, currency: CurrencyCode, 
     *         amount: number (>100), transaction_type: TransactionType, time: datetime, reason?: string}
     */
    public static function withdraw($wp_user_id, $amount, $reason = '') {
        $user = get_userdata($wp_user_id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }

        return Client::post('/wallet/withdraw', [
            'user_id' => (string)$wp_user_id,
            'email' => $user->user_email,
            'currency' => 'NGN',
            'amount' => floatval($amount),
            'transaction_type' => 'WITHDRAWAL',
            'time' => gmdate('c'),
            'reason' => $reason ?: null
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
