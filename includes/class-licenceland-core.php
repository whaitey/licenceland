<?php
/**
 * Core LicenceLand Plugin Class
 * 
 * @package LicenceLand
 * @since 1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class LicenceLand_Core {
    
    public function init() {
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_filter('plugin_action_links_' . LICENCELAND_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
    }
    
    public function activate() {
        // Set default options
        $this->set_default_options();
        
        // Create custom database tables if needed
        $this->create_tables();
        
        // Set activation flag
        update_option('licenceland_activated', true);
        update_option('licenceland_version', LICENCELAND_VERSION);
    }
    
    public function admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'licenceland') === false && strpos($hook, 'dual-shop') === false) {
            return;
        }
        
        wp_enqueue_style(
            'licenceland-admin',
            LICENCELAND_PLUGIN_URL . 'assets/css/admin.css',
            [],
            LICENCELAND_VERSION
        );
        
        wp_enqueue_script(
            'licenceland-admin',
            LICENCELAND_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            LICENCELAND_VERSION,
            true
        );
        
        wp_localize_script('licenceland-admin', 'licenceland_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('licenceland_nonce'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this item?', 'licenceland'),
                'saving' => __('Saving...', 'licenceland'),
                'saved' => __('Saved successfully!', 'licenceland'),
                'error' => __('An error occurred. Please try again.', 'licenceland')
            ]
        ]);
    }
    
    public function frontend_scripts() {
        if (!is_woocommerce() && !is_cart() && !is_checkout()) {
            return;
        }
        
        wp_enqueue_style(
            'licenceland-frontend',
            LICENCELAND_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            LICENCELAND_VERSION
        );
        
        wp_enqueue_script(
            'licenceland-frontend',
            LICENCELAND_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            LICENCELAND_VERSION,
            true
        );
        
        wp_localize_script('licenceland-frontend', 'licenceland_frontend', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('licenceland_frontend_nonce'),
            'shop_type' => $_COOKIE['ds_shop_type'] ?? 'lakossagi'
        ]);
    }
    
    public function admin_notices() {
        // Show activation notice
        if (get_option('licenceland_activated')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php 
                    printf(
                        __('LicenceLand has been activated successfully! <a href="%s">Configure the plugin</a> or <a href="%s">view documentation</a>.', 'licenceland'),
                        admin_url('admin.php?page=licenceland-settings'),
                        'https://github.com/whaitey/licenceland'
                    ); 
                    ?>
                </p>
            </div>
            <?php
            delete_option('licenceland_activated');
        }
        
        // Check for WooCommerce
        if (!class_exists('WooCommerce')) {
            ?>
            <div class="notice notice-error">
                <p>
                    <?php 
                    printf(
                        __('LicenceLand requires WooCommerce to function properly. Please <a href="%s">install and activate WooCommerce</a>.', 'licenceland'),
                        admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')
                    ); 
                    ?>
                </p>
            </div>
            <?php
        }
    }
    
    public function plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=licenceland-settings'),
            __('Settings', 'licenceland')
        );
        
        array_unshift($links, $settings_link);
        
        return $links;
    }
    
    private function set_default_options() {
        // CD Keys defaults
        if (!get_option('licenceland_cd_keys_enabled')) {
            update_option('licenceland_cd_keys_enabled', 'yes');
        }
        
        // Dual Shop defaults
        if (!get_option('licenceland_dual_shop_enabled')) {
            update_option('licenceland_dual_shop_enabled', 'yes');
        }
        
        // Default shop type
        if (!get_option('licenceland_default_shop_type')) {
            update_option('licenceland_default_shop_type', 'lakossagi');
        }
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // CD Keys usage tracking table
        $table_name = $wpdb->prefix . 'licenceland_cd_keys_usage';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cd_key varchar(255) NOT NULL,
            product_id bigint(20) NOT NULL,
            order_id bigint(20) NOT NULL,
            order_item_id bigint(20) NOT NULL,
            used_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY cd_key (cd_key),
            KEY product_id (product_id),
            KEY order_id (order_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get plugin information
     */
    public static function get_plugin_info() {
        return [
            'name' => 'LicenceLand',
            'version' => LICENCELAND_VERSION,
            'author' => 'ZeusWeb',
            'description' => __('Unified e-commerce solution with CD Key management and dual shop functionality.', 'licenceland'),
            'url' => 'https://github.com/whaitey/licenceland',
            'support' => 'https://github.com/whaitey/licenceland/issues'
        ];
    }
    
    /**
     * Check if a feature is enabled
     */
    public static function is_feature_enabled($feature) {
        $enabled_features = [
            'cd_keys' => get_option('licenceland_cd_keys_enabled', 'yes'),
            'dual_shop' => get_option('licenceland_dual_shop_enabled', 'yes')
        ];
        
        return isset($enabled_features[$feature]) && $enabled_features[$feature] === 'yes';
    }
    
    /**
     * Log debug information
     */
    public static function log($message, $level = 'info') {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[LicenceLand %s] %s: %s', LICENCELAND_VERSION, strtoupper($level), $message));
        }
    }
}