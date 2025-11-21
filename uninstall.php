<?php
/**
 * Uninstall script for MyNaijaTask Escrow Plugin
 * 
 * This file runs when the plugin is uninstalled (not just deactivated)
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('mnt_api_base_url');
delete_option('mnt_paystack_public_key');
delete_option('mnt_paystack_secret_key');
delete_option('mnt_auto_create_wallet');

// Delete user meta related to wallets
delete_metadata('user', 0, 'mnt_wallet_created', '', true);
delete_metadata('user', 0, 'mnt_wallet_id', '', true);

// Delete post meta related to escrows
delete_metadata('post', 0, 'mnt_escrow_id', '', true);
delete_metadata('post', 0, 'mnt_escrow_status', '', true);
delete_metadata('post', 0, 'mnt_escrow_amount', '', true);
delete_metadata('post', 0, 'mnt_buyer_id', '', true);
delete_metadata('post', 0, 'mnt_seller_id', '', true);

// Optionally delete custom database tables
// Uncomment these lines if you want to remove all data on uninstall
global $wpdb;

// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mnt_transaction_log");
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mnt_escrow_log");

// Clear any cached data
wp_cache_flush();
