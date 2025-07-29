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
        
        // Handle payment-based order creation
        $this->handle_payment_based_order_creation();
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
        
        // Payment-based order creation defaults
        if (!get_option('licenceland_payment_based_orders')) {
            update_option('licenceland_payment_based_orders', 'yes');
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
    
    /**
     * Handle order creation only after successful payment
     * Prevents orders from being created before payment is completed
     */
    public function handle_payment_based_order_creation() {
        // Hook into order creation process
        add_action('woocommerce_checkout_create_order', [$this, 'prevent_order_creation_before_payment'], 5, 2);
        add_action('woocommerce_payment_complete', [$this, 'create_order_after_payment'], 10, 1);
        add_action('woocommerce_checkout_order_processed', [$this, 'handle_order_processed'], 10, 3);
        
        // Store checkout data in session for later use
        add_action('woocommerce_checkout_update_order_meta', [$this, 'store_checkout_data'], 10, 2);
        
        // Handle failed payments
        add_action('woocommerce_payment_complete_failed', [$this, 'handle_failed_payment'], 10, 1);
    }
    
    /**
     * Prevent order creation before payment is completed
     */
    public function prevent_order_creation_before_payment($order, $data) {
        // Check if payment-based order creation is enabled
        if (!get_option('licenceland_payment_based_orders', 'yes') === 'yes') {
            return;
        }
        
        // Store checkout data in session for later use
        WC()->session->set('licenceland_checkout_data', [
            'billing' => $data['billing'] ?? [],
            'shipping' => $data['shipping'] ?? [],
            'order_data' => $data,
            'cart_items' => WC()->cart->get_cart(),
            'cart_totals' => [
                'subtotal' => WC()->cart->get_subtotal(),
                'total' => WC()->cart->get_total('raw'),
                'tax_total' => WC()->cart->get_tax_total(),
                'shipping_total' => WC()->cart->get_shipping_total(),
                'discount_total' => WC()->cart->get_discount_total()
            ]
        ]);
        
        // Prevent the order from being created now
        throw new Exception(__('Order will be created after payment is completed.', 'licenceland'));
    }
    
    /**
     * Create order after successful payment
     */
    public function create_order_after_payment($order_id) {
        // Check if payment-based order creation is enabled
        if (!get_option('licenceland_payment_based_orders', 'yes') === 'yes') {
            return;
        }
        
        $checkout_data = WC()->session->get('licenceland_checkout_data');
        if (!$checkout_data) {
            self::log('No checkout data found for order creation after payment', 'error');
            return;
        }
        
        try {
            // Create the order with stored checkout data
            $order = $this->create_order_from_checkout_data($checkout_data);
            
            if ($order) {
                // Update the payment order with the new order ID
                $payment_order = wc_get_order($order_id);
                if ($payment_order) {
                    $payment_order->update_meta_data('_licenceland_actual_order_id', $order->get_id());
                    $payment_order->save();
                }
                
                // Clear the stored checkout data
                WC()->session->__unset('licenceland_checkout_data');
                
                self::log('Order created successfully after payment: ' . $order->get_id(), 'info');
            }
        } catch (Exception $e) {
            self::log('Failed to create order after payment: ' . $e->getMessage(), 'error');
        }
    }
    
    /**
     * Create order from stored checkout data
     */
    private function create_order_from_checkout_data($checkout_data) {
        // Create new order
        $order = wc_create_order();
        
        // Add cart items
        foreach ($checkout_data['cart_items'] as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            $variation_id = $cart_item['variation_id'] ?? 0;
            $quantity = $cart_item['quantity'];
            
            $order->add_product(
                wc_get_product($product_id),
                $quantity,
                [
                    'variation_id' => $variation_id,
                    'variation' => $cart_item['variation'] ?? []
                ]
            );
        }
        
        // Set billing address
        if (!empty($checkout_data['billing'])) {
            $order->set_address($checkout_data['billing'], 'billing');
        }
        
        // Set shipping address
        if (!empty($checkout_data['shipping'])) {
            $order->set_address($checkout_data['shipping'], 'shipping');
        }
        
        // Set order totals
        $order->set_subtotal($checkout_data['cart_totals']['subtotal']);
        $order->set_total($checkout_data['cart_totals']['total']);
        $order->set_tax_total($checkout_data['cart_totals']['tax_total']);
        $order->set_shipping_total($checkout_data['cart_totals']['shipping_total']);
        $order->set_discount_total($checkout_data['cart_totals']['discount_total']);
        
        // Set order status to processing (payment completed)
        $order->set_status('processing');
        
        // Add order note
        $order->add_order_note(__('Order created after successful payment completion.', 'licenceland'));
        
        // Save the order
        $order->save();
        
        return $order;
    }
    
    /**
     * Handle order processed (fallback for normal flow)
     */
    public function handle_order_processed($order_id, $posted_data, $order) {
        // This is a fallback for when payment-based order creation is disabled
        if (get_option('licenceland_payment_based_orders', 'yes') !== 'yes') {
            return;
        }
        
        // Add meta to indicate this order was created normally
        $order->update_meta_data('_licenceland_order_creation_method', 'normal');
        $order->save();
    }
    
    /**
     * Handle failed payment
     */
    public function handle_failed_payment($order_id) {
        // Clear stored checkout data on failed payment
        WC()->session->__unset('licenceland_checkout_data');
        
        self::log('Payment failed, checkout data cleared for order: ' . $order_id, 'info');
    }
    
    /**
     * Store checkout data for later use
     */
    public function store_checkout_data($order_id, $posted_data) {
        // This method is called when order is created normally
        // We store additional data that might be needed
        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_meta_data('_licenceland_checkout_data_stored', current_time('mysql'));
            $order->save();
        }
    }
}