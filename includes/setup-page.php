<?php
/**
 * Setup escrow deposit page
 * Creates the page on plugin activation if it doesn't exist
 */

namespace MNT\Setup;

class EscrowPage {
    
    /**
     * Create escrow deposit page
     */
    public static function create_deposit_page() {
        // Check if page already exists
        $existing_page = get_page_by_path('escrow-deposit');
        
        if (!$existing_page) {
            $page_data = array(
                'post_title'    => __('Escrow Deposit', 'mnt-escrow'),
                'post_content'  => '[mnt_escrow_deposit]',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_name'     => 'escrow-deposit',
                'post_author'   => 1,
                'comment_status' => 'closed',
                'ping_status'   => 'closed'
            );
            
            $page_id = wp_insert_post($page_data);
            
            if ($page_id && !is_wp_error($page_id)) {
                // Store page ID in options for quick reference
                update_option('mnt_escrow_deposit_page_id', $page_id);
                
                // Add page meta
                update_post_meta($page_id, '_wp_page_template', 'default');
                
                return $page_id;
            }
        } else {
            // Update option with existing page ID
            update_option('mnt_escrow_deposit_page_id', $existing_page->ID);
            return $existing_page->ID;
        }
        
        return false;
    }
    
    /**
     * Get escrow deposit page URL
     */
    public static function get_deposit_page_url() {
        $page_id = get_option('mnt_escrow_deposit_page_id');
        
        if ($page_id) {
            return get_permalink($page_id);
        }
        
        // Fallback: try to find by slug
        $page = get_page_by_path('escrow-deposit');
        if ($page) {
            update_option('mnt_escrow_deposit_page_id', $page->ID);
            return get_permalink($page->ID);
        }
        
        return home_url('/escrow-deposit/');
    }
    
    /**
     * Delete page on plugin uninstall
     */
    public static function delete_deposit_page() {
        $page_id = get_option('mnt_escrow_deposit_page_id');
        
        if ($page_id) {
            wp_delete_post($page_id, true);
            delete_option('mnt_escrow_deposit_page_id');
        }
    }
}

// Don't register activation hook here - it should be in main plugin file
// This causes issues during plugin activation

// Check and create page on admin init if missing
add_action('admin_init', function() {
    if (!get_option('mnt_escrow_deposit_page_id')) {
        \MNT\Setup\EscrowPage::create_deposit_page();
    }
}, 20); // Priority 20 to run after other init hooks
