<?php
/**
 * Plugin Name: MyNaijaTask Escrow
 * Description: Escrow + Wallet system using custom backend API. Integrates with Taskbot theme for secure task-based payments with automatic escrow creation and lifecycle management.
 * Version: 1.0.0
 * Author: MyNaijaTask
 * Text Domain: mnt-escrow
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if(!defined('ABSPATH')) exit;

// Define plugin constants
define('MNT_ESCROW_VERSION', '1.0.0');
define('MNT_ESCROW_PATH', plugin_dir_path(__FILE__));
define('MNT_ESCROW_URL', plugin_dir_url(__FILE__));

class MNT_Escrow {
    
    public function __construct() {
        require_once __DIR__ . '/includes/autoload.php';
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
        
        add_action('init', [$this, 'boot']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create custom database tables if needed
        $this->create_tables();
        
        // Set default options
        if (!get_option('mnt_api_base_url')) {
            update_option('mnt_api_base_url', 'https://escrow-api-1vu6.onrender.com');
        }
        if (!get_option('mnt_auto_create_wallet')) {
            update_option('mnt_auto_create_wallet', '1');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Create custom database tables
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Transaction log table (optional - for local backup)
        $table_name = $wpdb->prefix . 'mnt_transaction_log';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            transaction_id varchar(255) NOT NULL,
            type varchar(50) NOT NULL,
            amount decimal(10,2) NOT NULL,
            status varchar(50) NOT NULL,
            description text,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY transaction_id (transaction_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Escrow log table (optional - for local backup)
        $table_name2 = $wpdb->prefix . 'mnt_escrow_log';
        $sql2 = "CREATE TABLE IF NOT EXISTS $table_name2 (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            escrow_id varchar(255) NOT NULL,
            task_id bigint(20),
            buyer_id bigint(20) NOT NULL,
            seller_id bigint(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            status varchar(50) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY escrow_id (escrow_id),
            KEY task_id (task_id),
            KEY buyer_id (buyer_id),
            KEY seller_id (seller_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        dbDelta($sql2);
    }

    /**
     * Boot the plugin
     */
    public function boot() {
        // Initialize core components
        MNT\Routes\Router::register();
        MNT\UI\Init::register_hooks();
        MNT\Taskbot\HookOverride::boot();
        
        // Initialize admin dashboard
        if (is_admin()) {
            MNT\Admin\Dashboard::init();
        }
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'mnt-escrow',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }
}

// Initialize the plugin
new MNT_Escrow();
