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
        // Register CPT as early as possible so list tables always recognize it
        add_action('plugins_loaded', [$this, 'register_cpt'], 0);
        
        // Handle payment-based order creation
        $this->handle_payment_based_order_creation();
        
        // Add update checker functionality
        $this->setup_update_checker();
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
    
    /**
     * Setup update checker functionality
     */
    private function setup_update_checker() {
        // Add admin action to force update check
        add_action('wp_ajax_licenceland_force_update_check', [$this, 'force_update_check']);
        add_action('wp_ajax_licenceland_debug_update_checker', [$this, 'debug_update_checker']);
        add_action('wp_ajax_licenceland_push_remote_payments', [$this, 'push_remote_payments']);
        add_action('wp_ajax_licenceland_push_all_products', [$this, 'push_all_products']);
        add_action('admin_notices', [$this, 'sync_push_result_notice']);

        // Submenu added from Settings class. Keep the renderer only.
        // Optional: also add CPT list (advanced)
        add_action('admin_menu', function(){
            add_submenu_page(
                LicenceLand_Settings::MENU_SLUG,
                __('Orders (CPT)', 'licenceland'),
                __('Orders (CPT)', 'licenceland'),
                'manage_woocommerce',
                'edit.php?post_type=licenceland_order_mirror'
            );
        });
        
        // Add admin notice for update checker status
        add_action('admin_notices', [$this, 'update_checker_notice']);
    }
    
    /**
     * Force update check via AJAX
     */
    public function force_update_check() {
        check_ajax_referer('licenceland_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        // Clear all update-related transients
        delete_site_transient('update_plugins');
        delete_site_transient('licenceland_update_check');
        delete_site_transient('puc_request_info_licenceland');
        delete_site_transient('puc_request_update_licenceland');
        
        // Clear WordPress update cache
        wp_clean_update_cache();
        
        // Force WordPress to check for updates
        wp_update_plugins();

        // Also ask the plugin update checker to run immediately
        if (function_exists('licenceland_puc_force_check')) {
            licenceland_puc_force_check();
        }
        
        // Log the update check
        self::log('Manual update check triggered by admin', 'info');
        
        wp_send_json_success(__('Update check completed. Please refresh the plugins page to see if updates are available.', 'licenceland'));
    }
    
    /**
     * Show update checker notice
     */
    public function update_checker_notice() {
        // Only show on plugin pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'licenceland') === false) {
            return;
        }
        
        // Check if we're on the main plugin page
        if (isset($_GET['page']) && $_GET['page'] === 'licenceland-settings') {
            // Get update checker status
            $update_checker_status = $this->get_update_checker_status();
            ?>
            <div class="notice notice-info is-dismissible">
                <p>
                    <strong><?php _e('LicenceLand Update Checker:', 'licenceland'); ?></strong>
                    <?php _e('Current version:', 'licenceland'); ?> <strong><?php echo LICENCELAND_VERSION; ?></strong>
                    <br>
                    <small><?php _e('Last check:', 'licenceland'); ?> <?php echo $update_checker_status['last_check']; ?></small>
                    <br>
                    <button type="button" class="button button-secondary" id="force_update_check">
                        <?php _e('Force Update Check', 'licenceland'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="debug_update_checker">
                        <?php _e('Debug Info', 'licenceland'); ?>
                    </button>
                    <span id="update_check_result" style="margin-left: 10px;"></span>
                </p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                $('#force_update_check').on('click', function() {
                    var button = $(this);
                    var resultSpan = $('#update_check_result');
                    
                    button.prop('disabled', true).text('<?php _e('Checking...', 'licenceland'); ?>');
                    resultSpan.text('').removeClass('success error');
                    
                    $.post(ajaxurl, {
                        action: 'licenceland_force_update_check',
                        nonce: '<?php echo wp_create_nonce('licenceland_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            resultSpan.text(response.data).removeClass('error').addClass('success');
                        } else {
                            resultSpan.text(response.data || '<?php _e('Update check failed.', 'licenceland'); ?>').removeClass('success').addClass('error');
                        }
                        button.prop('disabled', false).text('<?php _e('Force Update Check', 'licenceland'); ?>');
                    }).fail(function() {
                        resultSpan.text('<?php _e('Update check failed.', 'licenceland'); ?>').removeClass('success').addClass('error');
                        button.prop('disabled', false).text('<?php _e('Force Update Check', 'licenceland'); ?>');
                    });
                });
                
                $('#debug_update_checker').on('click', function() {
                    var button = $(this);
                    var resultSpan = $('#update_check_result');
                    
                    button.prop('disabled', true).text('<?php _e('Getting debug info...', 'licenceland'); ?>');
                    resultSpan.text('').removeClass('success error');
                    
                    $.post(ajaxurl, {
                        action: 'licenceland_debug_update_checker',
                        nonce: '<?php echo wp_create_nonce('licenceland_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            resultSpan.html('<pre style="background: #f0f0f0; padding: 10px; margin-top: 10px; font-size: 12px;">' + response.data + '</pre>').removeClass('error').addClass('success');
                        } else {
                            resultSpan.text(response.data || '<?php _e('Debug failed.', 'licenceland'); ?>').removeClass('success').addClass('error');
                        }
                        button.prop('disabled', false).text('<?php _e('Debug Info', 'licenceland'); ?>');
                    }).fail(function() {
                        resultSpan.text('<?php _e('Debug failed.', 'licenceland'); ?>').removeClass('success').addClass('error');
                        button.prop('disabled', false).text('<?php _e('Debug Info', 'licenceland'); ?>');
                    });
                });
            });
            </script>
            <?php
        }
    }
    
    /**
     * Get update checker status
     */
    private function get_update_checker_status() {
        $last_check = get_site_transient('puc_request_info_licenceland');
        $last_check_time = $last_check ? date('Y-m-d H:i:s', $last_check) : __('Never', 'licenceland');
        
        return [
            'last_check' => $last_check_time,
            'transients' => [
                'puc_request_info_licenceland' => get_site_transient('puc_request_info_licenceland'),
                'puc_request_update_licenceland' => get_site_transient('puc_request_update_licenceland'),
                'update_plugins' => get_site_transient('update_plugins')
            ]
        ];
    }
    
    /**
     * Debug update checker via AJAX
     */
    public function debug_update_checker() {
        check_ajax_referer('licenceland_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        
        $debug_info = [];
        
        // Plugin info
        $debug_info[] = "Plugin Version: " . LICENCELAND_VERSION;
        $debug_info[] = "WordPress Version: " . get_bloginfo('version');
        $debug_info[] = "PHP Version: " . PHP_VERSION;
        
        // Update checker status
        $status = $this->get_update_checker_status();
        $debug_info[] = "Last Update Check: " . $status['last_check'];
        
        // Transients
        $debug_info[] = "\nTransients:";
        foreach ($status['transients'] as $key => $value) {
            $debug_info[] = "  {$key}: " . ($value ? 'Set' : 'Not set');
        }
        
        // GitHub API test
        $debug_info[] = "\nGitHub API Test:";
        $response = wp_remote_get('https://api.github.com/repos/whaitey/licenceland/tags');
        if (is_wp_error($response)) {
            $debug_info[] = "  Error: " . $response->get_error_message();
        } else {
            $body = wp_remote_retrieve_body($response);
            $tags = json_decode($body, true);
            if ($tags) {
                $debug_info[] = "  Available tags:";
                foreach (array_slice($tags, 0, 5) as $tag) {
                    $debug_info[] = "    " . $tag['name'];
                }
            } else {
                $debug_info[] = "  Failed to parse response";
            }
        }
        
        wp_send_json_success(implode("\n", $debug_info));
    }

    /**
     * AJAX: Push remote payment allowlists via Sync API
     */
    public function push_remote_payments() {
        check_ajax_referer('licenceland_sync', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        if (!function_exists('licenceland') || !licenceland()->sync) {
            wp_send_json_error(__('Sync module unavailable.', 'licenceland'));
        }
        $lak = isset($_POST['lak']) ? array_map('sanitize_text_field', (array) $_POST['lak']) : [];
        $uzl = isset($_POST['uzl']) ? array_map('sanitize_text_field', (array) $_POST['uzl']) : [];
        $payload = json_encode([
            'ds_lak_payments' => array_values(array_unique($lak)),
            'ds_uzl_payments' => array_values(array_unique($uzl)),
        ]);
        $res = licenceland()->sync->send_to_remote_public('POST', '/wp-json/licenceland/v1/sync/settings/payments', $payload);
        if ($res['ok'] ?? false) {
            set_transient('licenceland_sync_push_notice', ['type' => 'success', 'msg' => __('Remote payments pushed successfully.', 'licenceland')], 60);
            wp_send_json_success();
        }
        set_transient('licenceland_sync_push_notice', ['type' => 'error', 'msg' => (string)($res['error'] ?? __('Push failed.', 'licenceland'))], 60);
        wp_send_json_error(esc_html($res['error'] ?? __('Push failed.', 'licenceland')));
    }

    /**
     * AJAX: Push all products to remotes (Primary only)
     */
    public function push_all_products() {
        check_ajax_referer('licenceland_sync', 'nonce');
        if (!current_user_can('manage_options')) { wp_die(); }
        if (!function_exists('licenceland') || !licenceland()->sync) {
            wp_send_json_error(__('Sync module unavailable.', 'licenceland'));
        }
        if (!licenceland()->sync->is_primary_site()) {
            wp_send_json_error(__('This action is only available on the Primary site.', 'licenceland'));
        }
        $paged = isset($_POST['page']) ? max(1, (int) $_POST['page']) : 1;
        $per = isset($_POST['per_page']) ? max(1, min(200, (int) $_POST['per_page'])) : 50;
        $q = new WP_Query([
            'post_type' => 'product',
            'post_status' => ['publish','draft','pending','private'],
            'fields' => 'ids',
            'posts_per_page' => $per,
            'paged' => $paged,
        ]);
        $ids = $q->posts ?: [];
        foreach ($ids as $pid) {
            licenceland()->sync->push_product_public((int)$pid);
        }
        $has_more = $q->max_num_pages > $paged;
        wp_send_json_success([
            'pushed' => count($ids),
            'page' => $paged,
            'has_more' => $has_more,
            'total_pages' => (int)$q->max_num_pages,
        ]);
    }

    public function sync_push_result_notice() {
        if (!is_admin()) { return; }
        $notice = get_transient('licenceland_sync_push_notice');
        if (!$notice || !is_array($notice)) { return; }
        delete_transient('licenceland_sync_push_notice');
        $type = $notice['type'] === 'success' ? 'notice-success' : 'notice-error';
        $msg = esc_html((string)$notice['msg']);
        echo '<div class="notice ' . esc_attr($type) . ' is-dismissible"><p>' . $msg . '</p></div>';
    }

    /**
     * Orders page (mirror view)
     */
    public function orders_page() {
        if (!current_user_can('manage_woocommerce')) { return; }
        // Filters form
        $site = isset($_GET['ll_site']) ? sanitize_text_field((string)$_GET['ll_site']) : '';
        $sku = isset($_GET['ll_sku']) ? sanitize_text_field((string)$_GET['ll_sku']) : '';
        $since = isset($_GET['ll_since']) ? sanitize_text_field((string)$_GET['ll_since']) : '';
        $until = isset($_GET['ll_until']) ? sanitize_text_field((string)$_GET['ll_until']) : '';
        echo '<div class="wrap"><h1>' . esc_html__('Mirrored Orders', 'licenceland') . '</h1>';
        echo '<form method="get" style="margin:10px 0;">';
        echo '<input type="hidden" name="page" value="licenceland-orders" />';
        echo '<input type="text" name="ll_site" placeholder="' . esc_attr__('Site contains...', 'licenceland') . '" value="' . esc_attr($site) . '" /> ';
        echo '<input type="text" name="ll_sku" placeholder="' . esc_attr__('SKU contains...', 'licenceland') . '" value="' . esc_attr($sku) . '" /> ';
        echo '<input type="date" name="ll_since" value="' . esc_attr($since) . '" /> ';
        echo '<input type="date" name="ll_until" value="' . esc_attr($until) . '" /> ';
        echo '<button class="button">' . esc_html__('Filter', 'licenceland') . '</button>';
        echo '</form>';

        // Try CPT entries first
        echo '<div class="wrap"><h1>' . esc_html__('Mirrored Orders', 'licenceland') . '</h1>';
        $q = new WP_Query([
            'post_type' => 'licenceland_order_mirror',
            'post_status' => 'any',
            'posts_per_page' => 50,
        ]);
        if ($q->have_posts()) {
            echo '<table class="widefat striped"><thead><tr>';
            echo '<th>' . esc_html__('Date', 'licenceland') . '</th>';
            echo '<th>' . esc_html__('Origin', 'licenceland') . '</th>';
            echo '<th>' . esc_html__('Remote ID', 'licenceland') . '</th>';
            echo '<th>' . esc_html__('Email', 'licenceland') . '</th>';
            echo '<th>' . esc_html__('Items', 'licenceland') . '</th>';
            echo '</tr></thead><tbody>';
            while ($q->have_posts()) { $q->the_post();
                $pid = get_the_ID();
                $origin = get_post_meta($pid, '_ll_origin_site', true);
                $rid = get_post_meta($pid, '_ll_remote_order_id', true);
                $email = get_post_meta($pid, '_ll_email', true);
                $items = get_post_meta($pid, '_ll_items', true);
                $itemsText = [];
                if (is_array($items)) { foreach ($items as $li) { $itemsText[] = (isset($li['sku'])?$li['sku']:'') . ' × ' . (isset($li['quantity'])?(int)$li['quantity']:1); } }
                echo '<tr>';
                echo '<td>' . esc_html(get_the_date('Y-m-d H:i')) . '</td>';
                echo '<td>' . esc_html($origin) . '</td>';
                echo '<td>' . esc_html($rid) . '</td>';
                echo '<td>' . esc_html($email) . '</td>';
                echo '<td>' . esc_html(implode(', ', $itemsText)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            wp_reset_postdata();
        } else {
            // Fallback: live fetch from configured remotes using signed GET
            $log = [];
            if (function_exists('licenceland') && licenceland()->sync) {
                $remotes = (array) get_option('ll_sync_remote_urls', []);
                $path = '/wp-json/licenceland/v1/sync/orders/list';
                $query = [];
                if ($sku) { $query['sku'] = $sku; }
                if ($since) { $query['since'] = $since; }
                if ($until) { $query['until'] = $until; }
                foreach ($remotes as $ru) {
                    $res = licenceland()->sync->signed_request((string)$ru, 'GET', $path, $query, '');
                    if (!empty($res['ok']) && !empty($res['orders']) && is_array($res['orders'])) {
                        foreach ($res['orders'] as $o) {
                            if ($site && stripos((string)($o['origin_site'] ?? ''), $site) === false) { continue; }
                            $log[] = $o;
                        }
                    }
                }
            }
            if (!empty($log) && is_array($log)) {
                echo '<table class="widefat striped"><thead><tr>';
                echo '<th>' . esc_html__('Date', 'licenceland') . '</th>';
                echo '<th>' . esc_html__('Origin', 'licenceland') . '</th>';
                echo '<th>' . esc_html__('Remote ID', 'licenceland') . '</th>';
                echo '<th>' . esc_html__('Email', 'licenceland') . '</th>';
                echo '<th>' . esc_html__('Items', 'licenceland') . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($log as $entry) {
                    $when = isset($entry['date']) ? (string)$entry['date'] : '';
                    $origin = isset($entry['origin_site']) ? (string)$entry['origin_site'] : '';
                    $rid = isset($entry['order_id']) ? (string)$entry['order_id'] : '';
                    $email = isset($entry['billing']['email']) ? (string)$entry['billing']['email'] : '';
                    $items = isset($entry['line_items']) && is_array($entry['line_items']) ? $entry['line_items'] : [];
                    $itemsText = [];
                    foreach ($items as $li) { $itemsText[] = (isset($li['sku'])?$li['sku']:'') . ' × ' . (isset($li['quantity'])?(int)$li['quantity']:1); }
                    echo '<tr>';
                    echo '<td>' . esc_html($when) . '</td>';
                    echo '<td>' . esc_html($origin) . '</td>';
                    echo '<td>' . esc_html($rid) . '</td>';
                    echo '<td>' . esc_html($email) . '</td>';
                    echo '<td>' . esc_html(implode(', ', $itemsText)) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>' . esc_html__('No mirrored orders yet.', 'licenceland') . '</p>';
            }
        }
        echo '</div>';
    }

    public function register_cpt() {
        // Custom post type for mirrored orders
        register_post_type('licenceland_order_mirror', [
            'labels' => [
                'name' => __('Orders', 'licenceland'),
                'singular_name' => __('Order', 'licenceland'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'capability_type' => 'post',
            'map_meta_cap' => false,
            'supports' => ['title','custom-fields'],
            'rewrite' => false,
        ]);
    }

    public function redirect_orders_friendly() {
        if (!is_admin()) { return; }
        if (!current_user_can('manage_woocommerce')) { return; }
        $req = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ($req && strpos($req, '/wp-admin/licenceland-orders') !== false) {
            wp_safe_redirect(admin_url('edit.php?post_type=licenceland_order_mirror'));
            exit;
        }
    }
}