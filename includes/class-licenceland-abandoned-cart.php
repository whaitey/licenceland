<?php
/**
 * Abandoned Cart Reminder System for LicenceLand
 * 
 * @package LicenceLand
 * @since 1.0.8
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class LicenceLand_Abandoned_Cart {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'licenceland_abandoned_carts';
    }
    
    public function init() {
        // Create database table
        add_action('init', [$this, 'create_table']);
        
        // Track abandoned carts
        add_action('woocommerce_cart_updated', [$this, 'track_cart']);
        add_action('woocommerce_checkout_order_processed', [$this, 'remove_cart_on_order']);
        add_action('woocommerce_cart_emptied', [$this, 'remove_cart_on_empty']);
        
        // Schedule reminder emails
        add_action('licenceland_abandoned_cart_reminder', [$this, 'send_reminder_emails']);
        
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_licenceland_send_manual_reminder', [$this, 'send_manual_reminder']);
        add_action('wp_ajax_licenceland_delete_abandoned_cart', [$this, 'delete_abandoned_cart']);
        
        // Settings integration
        add_filter('licenceland_settings_sections', [$this, 'add_settings_section']);
        add_filter('licenceland_settings_fields', [$this, 'add_settings_fields']);
        
        // Schedule cron job if not already scheduled
        if (!wp_next_scheduled('licenceland_abandoned_cart_reminder')) {
            wp_schedule_event(time(), 'hourly', 'licenceland_abandoned_cart_reminder');
        }
    }
    
    public function activate() {
        $this->create_table();
        
        // Set default options
        if (!get_option('licenceland_abandoned_cart_enabled')) {
            update_option('licenceland_abandoned_cart_enabled', 'yes');
        }
        if (!get_option('licenceland_abandoned_cart_reminder_delay')) {
            update_option('licenceland_abandoned_cart_reminder_delay', 24); // hours
        }
        if (!get_option('licenceland_abandoned_cart_max_reminders')) {
            update_option('licenceland_abandoned_cart_max_reminders', 3);
        }
        if (!get_option('licenceland_abandoned_cart_email_subject')) {
            update_option('licenceland_abandoned_cart_email_subject', __('You left something in your cart!', 'licenceland'));
        }
        if (!get_option('licenceland_abandoned_cart_email_template')) {
            update_option('licenceland_abandoned_cart_email_template', $this->get_default_email_template());
        }
    }
    
    public function deactivate() {
        // Clear scheduled cron job
        wp_clear_scheduled_hook('licenceland_abandoned_cart_reminder');
    }
    
    /**
     * Create the abandoned carts table
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            user_email varchar(255) NOT NULL,
            user_ip varchar(45) DEFAULT NULL,
            cart_data longtext NOT NULL,
            cart_total decimal(10,2) DEFAULT 0.00,
            items_count int(11) DEFAULT 0,
            reminder_count int(11) DEFAULT 0,
            last_reminder_sent datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status varchar(20) DEFAULT 'active',
            shop_type varchar(20) DEFAULT 'lakossagi',
            PRIMARY KEY (id),
            KEY user_email (user_email),
            KEY status (status),
            KEY created_at (created_at),
            KEY reminder_count (reminder_count)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Track cart updates
     */
    public function track_cart() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $user_email = $this->get_user_email();
        $user_ip = $this->get_user_ip();
        $shop_type = $_COOKIE['ds_shop_type'] ?? 'lakossagi';
        
        if (empty($user_email)) {
            return; // Need email to send reminders
        }
        
        $cart_data = WC()->cart->get_cart();
        $cart_total = WC()->cart->get_total('raw');
        $items_count = WC()->cart->get_cart_contents_count();
        
        // Check if cart already exists
        $existing_cart = $this->get_cart_by_email($user_email);
        
        if ($existing_cart) {
            // Update existing cart
            $this->update_cart($existing_cart->id, [
                'cart_data' => json_encode($cart_data),
                'cart_total' => $cart_total,
                'items_count' => $items_count,
                'user_id' => $user_id,
                'user_ip' => $user_ip,
                'shop_type' => $shop_type,
                'status' => 'active',
                'reminder_count' => 0,
                'last_reminder_sent' => null
            ]);
        } else {
            // Create new cart
            $this->insert_cart([
                'user_id' => $user_id,
                'user_email' => $user_email,
                'user_ip' => $user_ip,
                'cart_data' => json_encode($cart_data),
                'cart_total' => $cart_total,
                'items_count' => $items_count,
                'shop_type' => $shop_type
            ]);
        }
    }
    
    /**
     * Remove cart when order is placed
     */
    public function remove_cart_on_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $user_email = $order->get_billing_email();
        if ($user_email) {
            $this->remove_cart_by_email($user_email);
        }
    }
    
    /**
     * Remove cart when cart is emptied
     */
    public function remove_cart_on_empty() {
        $user_email = $this->get_user_email();
        if ($user_email) {
            $this->remove_cart_by_email($user_email);
        }
    }
    
    /**
     * Send reminder emails
     */
    public function send_reminder_emails() {
        if (!get_option('licenceland_abandoned_cart_enabled', 'yes')) {
            return;
        }
        
        $delay_hours = (int) get_option('licenceland_abandoned_cart_reminder_delay', 24);
        $max_reminders = (int) get_option('licenceland_abandoned_cart_max_reminders', 3);
        
        // Get carts that need reminders
        $carts = $this->get_carts_for_reminder($delay_hours, $max_reminders);
        
        foreach ($carts as $cart) {
            $this->send_reminder_email($cart);
        }
    }
    
    /**
     * Send a reminder email for a specific cart
     */
    private function send_reminder_email($cart) {
        $user_email = $cart->user_email;
        $cart_data = json_decode($cart->cart_data, true);
        
        if (empty($cart_data)) {
            return;
        }
        
        $subject = get_option('licenceland_abandoned_cart_email_subject', __('You left something in your cart!', 'licenceland'));
        $template = get_option('licenceland_abandoned_cart_email_template', $this->get_default_email_template());
        
        // Build cart items list
        $items_html = '';
        foreach ($cart_data as $cart_item) {
            $product = wc_get_product($cart_item['product_id']);
            if ($product) {
                $items_html .= sprintf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                    esc_html($product->get_name()),
                    esc_html($cart_item['quantity']),
                    wc_price($cart_item['line_total'])
                );
            }
        }
        
        // Replace placeholders
        $template = str_replace(
            ['{customer_name}', '{cart_items}', '{cart_total}', '{checkout_url}', '{shop_name}'],
            [
                $this->get_customer_name($cart),
                $items_html,
                wc_price($cart->cart_total),
                wc_get_checkout_url(),
                get_bloginfo('name')
            ],
            $template
        );
        
        // Send email
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent = wp_mail($user_email, $subject, $template, $headers);
        
        if ($sent) {
            // Update cart with reminder sent
            $this->update_cart($cart->id, [
                'reminder_count' => $cart->reminder_count + 1,
                'last_reminder_sent' => current_time('mysql')
            ]);
            
            // Log the reminder
            LicenceLand_Core::log("Abandoned cart reminder sent to {$user_email} for cart ID {$cart->id}");
        }
    }
    
    /**
     * Get default email template
     */
    private function get_default_email_template() {
        return '
        <div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
            <h2>Hi {customer_name},</h2>
            <p>We noticed you left some items in your cart. Don\'t miss out on these great products!</p>
            
            <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                <thead>
                    <tr style="background-color: #f8f9fa;">
                        <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Product</th>
                        <th style="padding: 10px; text-align: center; border: 1px solid #ddd;">Quantity</th>
                        <th style="padding: 10px; text-align: right; border: 1px solid #ddd;">Price</th>
                    </tr>
                </thead>
                <tbody>
                    {cart_items}
                </tbody>
                <tfoot>
                    <tr style="background-color: #f8f9fa;">
                        <td colspan="2" style="padding: 10px; text-align: right; border: 1px solid #ddd;"><strong>Total:</strong></td>
                        <td style="padding: 10px; text-align: right; border: 1px solid #ddd;"><strong>{cart_total}</strong></td>
                    </tr>
                </tfoot>
            </table>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="{checkout_url}" style="background-color: #007cba; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block;">Complete Your Order</a>
            </div>
            
            <p style="color: #666; font-size: 14px;">
                This email was sent from {shop_name}. If you have any questions, please contact our support team.
            </p>
        </div>';
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'licenceland-settings',
            __('Abandoned Carts', 'licenceland'),
            __('Abandoned Carts', 'licenceland'),
            'manage_woocommerce',
            'licenceland-abandoned-carts',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        $carts = $this->get_all_carts();
        ?>
        <div class="wrap">
            <h1><?php _e('Abandoned Carts', 'licenceland'); ?></h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Email', 'licenceland'); ?></th>
                        <th><?php _e('Items', 'licenceland'); ?></th>
                        <th><?php _e('Total', 'licenceland'); ?></th>
                        <th><?php _e('Shop Type', 'licenceland'); ?></th>
                        <th><?php _e('Reminders Sent', 'licenceland'); ?></th>
                        <th><?php _e('Last Activity', 'licenceland'); ?></th>
                        <th><?php _e('Actions', 'licenceland'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($carts as $cart): ?>
                        <tr>
                            <td><?php echo esc_html($cart->user_email); ?></td>
                            <td><?php echo esc_html($cart->items_count); ?></td>
                            <td><?php echo wc_price($cart->cart_total); ?></td>
                            <td><?php echo esc_html($cart->shop_type); ?></td>
                            <td><?php echo esc_html($cart->reminder_count); ?></td>
                            <td><?php echo esc_html($cart->updated_at); ?></td>
                            <td>
                                <button class="button send-reminder" data-cart-id="<?php echo esc_attr($cart->id); ?>">
                                    <?php _e('Send Reminder', 'licenceland'); ?>
                                </button>
                                <button class="button delete-cart" data-cart-id="<?php echo esc_attr($cart->id); ?>">
                                    <?php _e('Delete', 'licenceland'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.send-reminder').on('click', function() {
                var cartId = $(this).data('cart-id');
                if (confirm('<?php _e('Send reminder email?', 'licenceland'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'licenceland_send_manual_reminder',
                        cart_id: cartId,
                        nonce: '<?php echo wp_create_nonce('licenceland_abandoned_cart'); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert('<?php _e('Reminder sent successfully!', 'licenceland'); ?>');
                            location.reload();
                        } else {
                            alert('<?php _e('Error sending reminder.', 'licenceland'); ?>');
                        }
                    });
                }
            });
            
            $('.delete-cart').on('click', function() {
                var cartId = $(this).data('cart-id');
                if (confirm('<?php _e('Delete this abandoned cart?', 'licenceland'); ?>')) {
                    $.post(ajaxurl, {
                        action: 'licenceland_delete_abandoned_cart',
                        cart_id: cartId,
                        nonce: '<?php echo wp_create_nonce('licenceland_abandoned_cart'); ?>'
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('<?php _e('Error deleting cart.', 'licenceland'); ?>');
                        }
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Send manual reminder
     */
    public function send_manual_reminder() {
        check_ajax_referer('licenceland_abandoned_cart', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die();
        }
        
        $cart_id = (int) $_POST['cart_id'];
        $cart = $this->get_cart_by_id($cart_id);
        
        if ($cart) {
            $this->send_reminder_email($cart);
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }
    
    /**
     * Delete abandoned cart
     */
    public function delete_abandoned_cart() {
        check_ajax_referer('licenceland_abandoned_cart', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die();
        }
        
        $cart_id = (int) $_POST['cart_id'];
        $this->delete_cart($cart_id);
        
        wp_send_json_success();
    }
    
    /**
     * Add settings section
     */
    public function add_settings_section($sections) {
        $sections['abandoned_cart'] = __('Abandoned Cart Reminders', 'licenceland');
        return $sections;
    }
    
    /**
     * Add settings fields
     */
    public function add_settings_fields($fields) {
        $fields['abandoned_cart'] = [
            'licenceland_abandoned_cart_enabled' => [
                'label' => __('Enable Abandoned Cart Reminders', 'licenceland'),
                'type' => 'checkbox',
                'description' => __('Send reminder emails for abandoned carts', 'licenceland')
            ],
            'licenceland_abandoned_cart_reminder_delay' => [
                'label' => __('Reminder Delay (hours)', 'licenceland'),
                'type' => 'number',
                'description' => __('How many hours to wait before sending the first reminder', 'licenceland'),
                'default' => 24
            ],
            'licenceland_abandoned_cart_max_reminders' => [
                'label' => __('Maximum Reminders', 'licenceland'),
                'type' => 'number',
                'description' => __('Maximum number of reminder emails to send', 'licenceland'),
                'default' => 3
            ],
            'licenceland_abandoned_cart_email_subject' => [
                'label' => __('Email Subject', 'licenceland'),
                'type' => 'text',
                'description' => __('Subject line for reminder emails', 'licenceland')
            ],
            'licenceland_abandoned_cart_email_template' => [
                'label' => __('Email Template', 'licenceland'),
                'type' => 'textarea',
                'description' => __('Email template. Use placeholders: {customer_name}, {cart_items}, {cart_total}, {checkout_url}, {shop_name}', 'licenceland')
            ]
        ];
        
        return $fields;
    }
    
    // Database helper methods
    
    private function get_user_email() {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            return $user->user_email;
        }
        
        // Try to get from session/cookie
        return WC()->session ? WC()->session->get('customer_email') : '';
    }
    
    private function get_user_ip() {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    private function get_customer_name($cart) {
        if ($cart->user_id) {
            $user = get_user_by('id', $cart->user_id);
            return $user ? $user->display_name : '';
        }
        return '';
    }
    
    private function get_cart_by_email($email) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_email = %s AND status = 'active'",
            $email
        ));
    }
    
    private function get_cart_by_id($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }
    
    private function get_carts_for_reminder($delay_hours, $max_reminders) {
        global $wpdb;
        
        $delay_time = date('Y-m-d H:i:s', strtotime("-{$delay_hours} hours"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE status = 'active' 
            AND reminder_count < %d 
            AND (last_reminder_sent IS NULL OR last_reminder_sent < %s)
            AND updated_at < %s",
            $max_reminders,
            $delay_time,
            $delay_time
        ));
    }
    
    private function get_all_carts() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE status = 'active' ORDER BY updated_at DESC"
        );
    }
    
    private function insert_cart($data) {
        global $wpdb;
        return $wpdb->insert($this->table_name, $data);
    }
    
    private function update_cart($id, $data) {
        global $wpdb;
        return $wpdb->update($this->table_name, $data, ['id' => $id]);
    }
    
    private function remove_cart_by_email($email) {
        global $wpdb;
        return $wpdb->update(
            $this->table_name,
            ['status' => 'removed'],
            ['user_email' => $email, 'status' => 'active']
        );
    }
    
    private function delete_cart($id) {
        global $wpdb;
        return $wpdb->delete($this->table_name, ['id' => $id]);
    }
} 