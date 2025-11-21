<?php
namespace MNT\Helpers;

class Logger {

    /**
     * Log transaction to database
     */
    public static function log_transaction($user_id, $transaction_id, $type, $amount, $status, $description = '', $metadata = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mnt_transaction_log';
        
        $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'transaction_id' => $transaction_id,
                'type' => $type,
                'amount' => $amount,
                'status' => $status,
                'description' => $description,
                'metadata' => json_encode($metadata),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s']
        );
        
        return $wpdb->insert_id;
    }

    /**
     * Log escrow to database
     */
    public static function log_escrow($escrow_id, $task_id, $buyer_id, $seller_id, $amount, $status, $description = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mnt_escrow_log';
        
        $wpdb->insert(
            $table_name,
            [
                'escrow_id' => $escrow_id,
                'task_id' => $task_id,
                'buyer_id' => $buyer_id,
                'seller_id' => $seller_id,
                'amount' => $amount,
                'status' => $status,
                'description' => $description,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%d', '%d', '%f', '%s', '%s', '%s']
        );
        
        return $wpdb->insert_id;
    }

    /**
     * Update escrow status
     */
    public static function update_escrow_status($escrow_id, $new_status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mnt_escrow_log';
        
        $wpdb->update(
            $table_name,
            ['status' => $new_status, 'updated_at' => current_time('mysql')],
            ['escrow_id' => $escrow_id],
            ['%s', '%s'],
            ['%s']
        );
    }

    /**
     * Get transaction history from local database
     */
    public static function get_user_transactions($user_id, $limit = 50, $offset = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mnt_transaction_log';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        ));
    }

    /**
     * Get escrow history from local database
     */
    public static function get_user_escrows($user_id, $status = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mnt_escrow_log';
        
        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE (buyer_id = %d OR seller_id = %d) AND status = %s ORDER BY created_at DESC",
                $user_id,
                $user_id,
                $status
            ));
        } else {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE (buyer_id = %d OR seller_id = %d) ORDER BY created_at DESC",
                $user_id,
                $user_id
            ));
        }
    }
}
