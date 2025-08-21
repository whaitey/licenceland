<?php
/**
 * CD Keys Management for LicenceLand
 * 
 * @package LicenceLand
 * @since 1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class LicenceLand_CD_Keys {
    
    public function init() {
        if (!LicenceLand_Core::is_feature_enabled('cd_keys')) {
            return;
        }
        
        // Product data tabs and panels
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_tabs']);
        add_action('woocommerce_product_data_panels', [$this, 'add_product_panels']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_meta']);
        
        // Order processing
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'assign_cd_key_to_order_item'], 10, 4);
        add_action('woocommerce_after_order_itemmeta', [$this, 'show_cd_key_in_admin'], 10, 3);
        add_filter('woocommerce_hidden_order_itemmeta', [$this, 'hide_cd_key_meta_in_admin']);
        
        // Email functionality
        add_action('woocommerce_email_after_order_table', [$this, 'append_email_templates'], 20, 4);
        
        // Stock management
        add_action('woocommerce_product_set_stock_status', [$this, 'check_stock_on_status_change'], 10, 3);
        
        // Backorder functionality
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'handle_backorder_creation'], 15, 4);
        add_action('woocommerce_process_product_meta', [$this, 'process_backorders_on_stock_update']);
        
        // Admin AJAX handlers
        add_action('wp_ajax_licenceland_get_cd_keys', [$this, 'ajax_get_cd_keys']);
        add_action('wp_ajax_licenceland_add_cd_keys', [$this, 'ajax_add_cd_keys']);
        add_action('wp_ajax_licenceland_delete_cd_key', [$this, 'ajax_delete_cd_key']);
        add_action('wp_ajax_licenceland_get_backorders', [$this, 'ajax_get_backorders']);
        add_action('wp_ajax_licenceland_process_backorder', [$this, 'ajax_process_backorder']);
    }
    
    public function activate() {
        // Create CD keys usage tracking table
        $this->create_usage_table();
        
        // Create backorders table
        $this->create_backorders_table();
        
        // Set default options
        if (!get_option('licenceland_cd_keys_default_threshold')) {
            update_option('licenceland_cd_keys_default_threshold', 5);
        }
    }
    
    /**
     * Add product data tabs
     */
    public function add_product_tabs($tabs) {
        $tabs['cd_keys'] = [
            'label' => __('CD Keys', 'licenceland'),
            'target' => 'cd_keys_data',
            'class' => ['show_if_simple', 'show_if_variable'],
            'priority' => 25
        ];
        
        $tabs['email_template'] = [
            'label' => __('Email Template', 'licenceland'),
            'target' => 'email_template_data',
            'class' => ['show_if_simple', 'show_if_variable'],
            'priority' => 26
        ];
        
        $tabs['backorders'] = [
            'label' => __('Backorders', 'licenceland'),
            'target' => 'backorders_data',
            'class' => ['show_if_simple', 'show_if_variable'],
            'priority' => 27
        ];
        
        return $tabs;
    }
    
    /**
     * Add product data panels
     */
    public function add_product_panels() {
        global $post;
        
        // CD Keys panel
        echo '<div id="cd_keys_data" class="panel woocommerce_options_panel">';
        
        // CD Keys textarea
        $keys = get_post_meta($post->ID, '_cd_keys', true);
        if (is_array($keys)) {
            $value = implode("\n", $keys);
        } elseif (is_string($keys)) {
            $value = $keys;
        } else {
            $value = '';
        }
        
        woocommerce_wp_textarea_input([
            'id' => '_cd_keys',
            'label' => __('CD Keys', 'licenceland'),
            'description' => __('Enter one CD key per line.', 'licenceland'),
            'value' => $value,
            'desc_tip' => true,
            'rows' => 10
        ]);
        
        // Stock threshold
        woocommerce_wp_text_input([
            'id' => '_cd_key_stock_threshold',
            'label' => __('Stock Alert Threshold', 'licenceland'),
            'description' => __('Send email alert when stock falls below this number.', 'licenceland'),
            'type' => 'number',
            'desc_tip' => true,
            'value' => get_post_meta($post->ID, '_cd_key_stock_threshold', true),
            'custom_attributes' => [
                'min' => '0',
                'step' => '1'
            ]
        ]);
        
        // Auto-assign option
        woocommerce_wp_checkbox([
            'id' => '_cd_key_auto_assign',
            'label' => __('Auto-assign to Pending Orders', 'licenceland'),
            'description' => __('Automatically assign new CD keys to pending orders when added.', 'licenceland'),
            'value' => get_post_meta($post->ID, '_cd_key_auto_assign', true) ?: 'yes'
        ]);
        
        echo '</div>';
        
        // Email template panel
        echo '<div id="email_template_data" class="panel woocommerce_options_panel">';
        
        $template = get_post_meta($post->ID, '_cd_email_template', true);
        echo '<p class="form-field">';
        echo '<label for="_cd_email_template">' . esc_html__('Email Template', 'licenceland') . '</label>';
        wp_editor($template, '_cd_email_template', [
            'textarea_name' => '_cd_email_template',
            'textarea_rows' => 8,
            'media_buttons' => false,
            'teeny' => true,
            'tinymce' => [
                'toolbar1' => 'bold,italic,underline,link,unlink',
                'toolbar2' => ''
            ]
        ]);
        echo '<p class="description">' . esc_html__('Use {cd_key} placeholder for the CD key. Leave empty for default template.', 'licenceland') . '</p>';
        echo '</div>';
        
        // Backorders panel
        echo '<div id="backorders_data" class="panel woocommerce_options_panel">';
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'licenceland_backorders';
        
        $backorders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE product_id = %d 
            ORDER BY created_at DESC",
            $post->ID
        ));
        
        if ($backorders) {
            echo '<h4>' . esc_html__('Pending Backorders', 'licenceland') . '</h4>';
            echo '<table class="widefat">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Order', 'licenceland') . '</th>';
            echo '<th>' . esc_html__('Customer', 'licenceland') . '</th>';
            echo '<th>' . esc_html__('Quantity', 'licenceland') . '</th>';
            echo '<th>' . esc_html__('Date', 'licenceland') . '</th>';
            echo '<th>' . esc_html__('Status', 'licenceland') . '</th>';
            echo '<th>' . esc_html__('Action', 'licenceland') . '</th>';
            echo '</tr></thead><tbody>';
            
            foreach ($backorders as $backorder) {
                $order = wc_get_order($backorder->order_id);
                $status_class = $backorder->status === 'pending' ? 'pending' : ($backorder->status === 'processed' ? 'processed' : 'cancelled');
                
                echo '<tr>';
                echo '<td><a href="' . esc_url(get_edit_post_link($backorder->order_id)) . '">#' . esc_html($order ? $order->get_order_number() : $backorder->order_id) . '</a></td>';
                echo '<td>' . esc_html($backorder->customer_name) . '<br><small>' . esc_html($backorder->customer_email) . '</small></td>';
                echo '<td>' . esc_html($backorder->quantity) . '</td>';
                echo '<td>' . esc_html(date('Y-m-d H:i', strtotime($backorder->created_at))) . '</td>';
                echo '<td><span class="backorder-status-' . esc_attr($status_class) . '">' . esc_html(ucfirst($backorder->status)) . '</span></td>';
                echo '<td>';
                
                if ($backorder->status === 'pending') {
                    echo '<button type="button" class="button process-backorder" data-backorder-id="' . esc_attr($backorder->id) . '">' . esc_html__('Process', 'licenceland') . '</button>';
                } elseif ($backorder->status === 'processed') {
                    echo '<small>' . esc_html__('Processed', 'licenceland') . '<br>' . esc_html(date('Y-m-d H:i', strtotime($backorder->processed_at))) . '</small>';
                }
                
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>' . esc_html__('No backorders for this product.', 'licenceland') . '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Save product meta
     */
    public function save_product_meta($post_id) {
        // Save CD keys
        if (isset($_POST['_cd_keys'])) {
            $raw = sanitize_textarea_field(wp_unslash($_POST['_cd_keys']));
            $lines = array_filter(array_map('trim', explode("\n", $raw)));
            $lines = array_unique($lines); // Remove duplicates
            update_post_meta($post_id, '_cd_keys', $lines);
        }
        
        // Save email template
        if (isset($_POST['_cd_email_template'])) {
            $template = wp_kses_post(wp_unslash($_POST['_cd_email_template']));
            update_post_meta($post_id, '_cd_email_template', $template);
        }
        
        // Save stock threshold
        if (isset($_POST['_cd_key_stock_threshold'])) {
            $threshold = absint($_POST['_cd_key_stock_threshold']);
            update_post_meta($post_id, '_cd_key_stock_threshold', $threshold);
        }
        
        // Save auto-assign option
        if (isset($_POST['_cd_key_auto_assign'])) {
            update_post_meta($post_id, '_cd_key_auto_assign', 'yes');
        } else {
            update_post_meta($post_id, '_cd_key_auto_assign', 'no');
        }
        
        // Process pending orders if auto-assign is enabled
        if (get_post_meta($post_id, '_cd_key_auto_assign', true) === 'yes') {
            $this->assign_pending_cd_keys_to_past_orders($post_id);
        }
        
        // Check stock and notify
        $this->check_stock_and_notify($post_id);
    }
    
    /**
     * Assign CD key to order item
     */
    public function assign_cd_key_to_order_item($item, $cart_item_key, $values, $order) {
        $product_id = $item->get_product_id();
        $qty = $item->get_quantity();
        $keys = get_post_meta($product_id, '_cd_keys', true);
        
        if (empty($keys)) {
            $keys = [];
        }
        
        if (is_string($keys)) {
            $keys = array_filter(array_map('trim', explode("\n", $keys)));
        }
        
        if (!is_array($keys)) {
            $keys = (array)$keys;
        }
        
        $assigned = [];
        $used_keys = [];
        
        for ($i = 0; $i < $qty; $i++) {
            foreach ($keys as $k => $key) {
                if (!in_array($key, $used_keys, true)) {
                    $assigned[] = $key;
                    $used_keys[] = $key;
                    unset($keys[$k]);
                    break;
                }
            }
        }
        
        if ($assigned) {
            $cd_key = (count($assigned) == 1) ? $assigned[0] : implode(", ", $assigned);
            $item->add_meta_data('_cd_key', $cd_key, true);
            
            // Update product meta
            update_post_meta($product_id, '_cd_keys', array_values($keys));
            
            // Log usage
            $this->log_cd_key_usage($assigned, $product_id, $order->get_id(), $item->get_id());
            
            // Check stock and notify
            $this->check_stock_and_notify($product_id);
        }
    }
    
    /**
     * Assign pending CD keys to past orders
     */
    public function assign_pending_cd_keys_to_past_orders($product_id) {
        $keys = get_post_meta($product_id, '_cd_keys', true);
        
        if (empty($keys)) {
            $keys = [];
        }
        
        if (is_string($keys)) {
            $keys = array_filter(array_map('trim', explode("\n", $keys)));
        }
        
        if (!is_array($keys)) {
            $keys = (array)$keys;
        }
        
        // Get orders that need CD keys
        $orders = wc_get_orders([
            'limit' => -1,
            'status' => ['processing', 'completed', 'on-hold'],
            'return' => 'ids'
        ]);
        
        foreach ($orders as $order_id) {
            $order = wc_get_order($order_id);
            
            foreach ($order->get_items() as $item_id => $item) {
                if ($item->get_product_id() == $product_id) {
                    $cd_key = wc_get_order_item_meta($item_id, '_cd_key', true);
                    $qty = $item->get_quantity();
                    
                    // If no CD key assigned or fewer than needed
                    if (empty($cd_key)) {
                        $to_assign = [];
                        
                        for ($i = 0; $i < $qty; $i++) {
                            if (!empty($keys)) {
                                $to_assign[] = array_shift($keys);
                            }
                        }
                        
                        if ($to_assign) {
                            $final = (count($to_assign) == 1) ? $to_assign[0] : implode(", ", $to_assign);
                            wc_update_order_item_meta($item_id, '_cd_key', $final);
                            
                            // Log usage
                            $this->log_cd_key_usage($to_assign, $product_id, $order_id, $item_id);
                            
                            // Send email
                            $this->send_cd_key_email_to_customer($order, $item, $final);
                        }
                    }
                }
            }
        }
        
        // Save remaining keys
        update_post_meta($product_id, '_cd_keys', $keys);
        $this->check_stock_and_notify($product_id);
    }
    
    /**
     * Check stock and send notification
     */
    private function check_stock_and_notify($product_id) {
        $keys = get_post_meta($product_id, '_cd_keys', true);
        $remaining = is_array($keys) ? count($keys) : 0;
        $threshold = get_post_meta($product_id, '_cd_key_stock_threshold', true);
        
        if ($threshold && $remaining <= $threshold) {
            $product = wc_get_product($product_id);
            $subject = sprintf(__('Stock Alert: %s', 'licenceland'), $product->get_name());
            $message = sprintf(
                __('The stock for product "%s" has fallen to %d items!', 'licenceland'),
                $product->get_name(),
                $remaining
            );
            
            wp_mail(get_option('admin_email'), $subject, $message);
        }
    }
    
    /**
     * Show CD key in admin order
     */
    public function show_cd_key_in_admin($item_id, $item, $product) {
        $cd_key = wc_get_order_item_meta($item_id, '_cd_key', true);
        
        if ($cd_key) {
            echo '<p><strong>' . esc_html__('CD Key:', 'licenceland') . '</strong> ' . esc_html($cd_key) . '</p>';
        }
    }
    
    /**
     * Append email templates
     */
    public function append_email_templates($order, $sent_to_admin, $plain_text, $email) {
        foreach ($order->get_items() as $item_id => $item) {
            $cd_key = wc_get_order_item_meta($item_id, '_cd_key', true);
            
            if ($cd_key) {
                $product_id = $item->get_product_id();
                $template = get_post_meta($product_id, '_cd_email_template', true);
                
                if ($template) {
                    $output = str_replace('{cd_key}', esc_html($cd_key), $template);
                    echo wpautop($output);
                } else {
                    echo '<p><strong>' . esc_html__('CD Key:', 'licenceland') . '</strong> ' . esc_html($cd_key) . '</p>';
                }
            }
        }
    }
    
    /**
     * Send CD key email to customer
     */
    private function send_cd_key_email_to_customer($order, $item, $cd_key) {
        $product_id = $item->get_product_id();
        $template = get_post_meta($product_id, '_cd_email_template', true);
        
        if ($template) {
            $output = str_replace('{cd_key}', esc_html($cd_key), $template);
        } else {
            $output = sprintf(__('Your CD key: %s', 'licenceland'), esc_html($cd_key));
        }
        
        $to = $order->get_billing_email();
        $subject = sprintf(__('CD Key for Order #%s', 'licenceland'), $order->get_order_number());
        
        wp_mail($to, $subject, wpautop($output));
    }
    
    /**
     * Hide CD key meta in admin
     */
    public function hide_cd_key_meta_in_admin($hidden) {
        $hidden[] = '_cd_key';
        return $hidden;
    }
    
    /**
     * Log CD key usage
     */
    private function log_cd_key_usage($keys, $product_id, $order_id, $order_item_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'licenceland_cd_keys_usage';
        
        foreach ($keys as $key) {
            $wpdb->insert(
                $table_name,
                [
                    'cd_key' => $key,
                    'product_id' => $product_id,
                    'order_id' => $order_id,
                    'order_item_id' => $order_item_id
                ],
                ['%s', '%d', '%d', '%d']
            );
        }
    }
    
    /**
     * Create usage tracking table
     */
    private function create_usage_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
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
     * Create backorders table
     */
    private function create_backorders_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'licenceland_backorders';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            order_item_id bigint(20) NOT NULL,
            product_id bigint(20) NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            customer_email varchar(255) NOT NULL,
            customer_name varchar(255) NOT NULL,
            status enum('pending', 'processed', 'cancelled') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            processed_at datetime NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Handle backorder creation when no CD keys available
     */
    public function handle_backorder_creation($item, $cart_item_key, $values, $order) {
        $product_id = $item->get_product_id();
        $qty = $item->get_quantity();
        $keys = get_post_meta($product_id, '_cd_keys', true);
        
        if (empty($keys)) {
            $keys = [];
        }
        
        if (is_string($keys)) {
            $keys = array_filter(array_map('trim', explode("\n", $keys)));
        }
        
        if (!is_array($keys)) {
            $keys = (array)$keys;
        }
        
        // If not enough keys available, create backorder
        if (count($keys) < $qty) {
            $this->create_backorder($order, $item, $product_id, $qty);
            
            // Add backorder note to order
            $order->add_order_note(
                sprintf(
                    __('CD Key backorder created for product #%d. Quantity: %d. Will be processed when stock is available.', 'licenceland'),
                    $product_id,
                    $qty
                )
            );
        }
    }
    
    /**
     * Create backorder entry
     */
    private function create_backorder($order, $item, $product_id, $quantity) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'licenceland_backorders';
        
        $wpdb->insert(
            $table_name,
            [
                'order_id' => $order->get_id(),
                'order_item_id' => $item->get_id(),
                'product_id' => $product_id,
                'quantity' => $quantity,
                'customer_email' => $order->get_billing_email(),
                'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'status' => 'pending'
            ],
            ['%d', '%d', '%d', '%d', '%s', '%s', '%s']
        );
        
        // Send backorder notification email
        $this->send_backorder_notification($order, $item, $product_id);
    }
    
    /**
     * Send backorder notification email
     */
    private function send_backorder_notification($order, $item, $product_id) {
        $product = wc_get_product($product_id);
        $to = $order->get_billing_email();
        $subject = sprintf(__('Backorder Notification - Order #%s', 'licenceland'), $order->get_order_number());
        
        $message = sprintf(
            __('Dear %s,

Your order #%s contains a product that is currently out of stock.

Product: %s
Quantity: %d

We will automatically send you the CD key(s) as soon as they become available.

Thank you for your patience!

Best regards,
%s', 'licenceland'),
            $order->get_billing_first_name(),
            $order->get_order_number(),
            $product->get_name(),
            $item->get_quantity(),
            get_bloginfo('name')
        );
        
        wp_mail($to, $subject, wpautop($message));
    }
    
    /**
     * Process backorders when stock is updated
     */
    public function process_backorders_on_stock_update($post_id) {
        $keys = get_post_meta($post_id, '_cd_keys', true);
        
        if (empty($keys)) {
            return;
        }
        
        if (is_string($keys)) {
            $keys = array_filter(array_map('trim', explode("\n", $keys)));
        }
        
        if (!is_array($keys)) {
            $keys = (array)$keys;
        }
        
        // If we have keys available, process pending backorders
        if (!empty($keys)) {
            $this->process_pending_backorders($post_id, $keys);
        }
    }
    
    /**
     * Process pending backorders for a product
     */
    private function process_pending_backorders($product_id, &$available_keys) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'licenceland_backorders';
        
        // Get pending backorders for this product
        $backorders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE product_id = %d AND status = 'pending' 
            ORDER BY created_at ASC",
            $product_id
        ));
        
        foreach ($backorders as $backorder) {
            if (count($available_keys) >= $backorder->quantity) {
                // We have enough keys, process this backorder
                $this->process_single_backorder($backorder, $available_keys);
            } else {
                // Not enough keys, stop processing
                break;
            }
        }
    }
    
    /**
     * Process a single backorder
     */
    private function process_single_backorder($backorder, &$available_keys) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'licenceland_backorders';
        
        // Assign CD keys
        $assigned_keys = [];
        for ($i = 0; $i < $backorder->quantity; $i++) {
            if (!empty($available_keys)) {
                $assigned_keys[] = array_shift($available_keys);
            }
        }
        
        if (!empty($assigned_keys)) {
            $cd_key = (count($assigned_keys) == 1) ? $assigned_keys[0] : implode(", ", $assigned_keys);
            
            // Update order item meta
            wc_update_order_item_meta($backorder->order_item_id, '_cd_key', $cd_key);
            
            // Update backorder status
            $wpdb->update(
                $table_name,
                [
                    'status' => 'processed',
                    'processed_at' => current_time('mysql')
                ],
                ['id' => $backorder->id],
                ['%s', '%s'],
                ['%d']
            );
            
            // Get order and item for email (guard against missing order/item)
            $order = wc_get_order($backorder->order_id);
            if ($order) {
                $item = $order->get_item($backorder->order_item_id);
                if ($item) {
                    // Send CD key email
                    $this->send_cd_key_email_to_customer($order, $item, $cd_key);
                    // Add order note
                    $order->add_order_note(
                        sprintf(
                            __('CD Key backorder processed and sent to customer. Keys: %s', 'licenceland'),
                            $cd_key
                        )
                    );
                } else {
                    $order->add_order_note(
                        sprintf(
                            __('CD Key backorder processed. Order item not found for email. Keys: %s', 'licenceland'),
                            $cd_key
                        )
                    );
                }
            }
            
            // Log usage
            $this->log_cd_key_usage($assigned_keys, $backorder->product_id, $backorder->order_id, $backorder->order_item_id);
        }
    }
    
    /**
     * AJAX handlers for admin interface
     */
    public function ajax_get_cd_keys() {
        check_ajax_referer('licenceland_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'licenceland'));
        }
        
        $product_id = absint($_POST['product_id']);
        $keys = get_post_meta($product_id, '_cd_keys', true);
        
        if (!is_array($keys)) {
            $keys = [];
        }
        
        wp_send_json_success($keys);
    }
    
    public function ajax_add_cd_keys() {
        check_ajax_referer('licenceland_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'licenceland'));
        }
        
        $product_id = absint($_POST['product_id']);
        $new_keys = sanitize_textarea_field($_POST['keys']);
        $lines = array_filter(array_map('trim', explode("\n", $new_keys)));
        
        $existing_keys = get_post_meta($product_id, '_cd_keys', true);
        if (!is_array($existing_keys)) {
            $existing_keys = [];
        }
        
        $all_keys = array_merge($existing_keys, $lines);
        $all_keys = array_unique($all_keys);
        
        update_post_meta($product_id, '_cd_keys', $all_keys);
        
        wp_send_json_success([
            'message' => sprintf(__('Added %d new CD keys', 'licenceland'), count($lines)),
            'total_keys' => count($all_keys)
        ]);
    }
    
    public function ajax_delete_cd_key() {
        check_ajax_referer('licenceland_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'licenceland'));
        }
        
        $product_id = absint($_POST['product_id']);
        $key_to_delete = sanitize_text_field($_POST['key']);
        
        $keys = get_post_meta($product_id, '_cd_keys', true);
        if (!is_array($keys)) {
            $keys = [];
        }
        
        $keys = array_filter($keys, function($key) use ($key_to_delete) {
            return $key !== $key_to_delete;
        });
        
        update_post_meta($product_id, '_cd_keys', array_values($keys));
        
        wp_send_json_success([
            'message' => __('CD key deleted successfully', 'licenceland'),
            'total_keys' => count($keys)
        ]);
    }
    
    /**
     * AJAX handler to get backorders for a product
     */
    public function ajax_get_backorders() {
        check_ajax_referer('licenceland_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'licenceland'));
        }
        
        $product_id = absint($_POST['product_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'licenceland_backorders';
        
        $backorders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE product_id = %d 
            ORDER BY created_at DESC",
            $product_id
        ));
        
        wp_send_json_success($backorders);
    }
    
    /**
     * AJAX handler to manually process a backorder
     */
    public function ajax_process_backorder() {
        check_ajax_referer('licenceland_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'licenceland'));
        }
        
        $backorder_id = absint($_POST['backorder_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'licenceland_backorders';
        
        // Get backorder
        $backorder = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $backorder_id
        ));
        
        if (!$backorder || $backorder->status !== 'pending') {
            wp_send_json_error(__('Backorder not found or already processed', 'licenceland'));
        }
        
        // Get available keys
        $keys = get_post_meta($backorder->product_id, '_cd_keys', true);
        if (empty($keys)) {
            wp_send_json_error(__('No CD keys available for this product', 'licenceland'));
        }
        
        if (is_string($keys)) {
            $keys = array_filter(array_map('trim', explode("\n", $keys)));
        }
        
        if (!is_array($keys)) {
            $keys = (array)$keys;
        }
        
        if (count($keys) < $backorder->quantity) {
            wp_send_json_error(__('Not enough CD keys available', 'licenceland'));
        }
        
        // Process the backorder
        $this->process_single_backorder($backorder, $keys);
        
        // Update product meta with remaining keys
        update_post_meta($backorder->product_id, '_cd_keys', $keys);
        
        wp_send_json_success([
            'message' => __('Backorder processed successfully', 'licenceland')
        ]);
    }
}