<?php
/**
 * Order Resend System for LicenceLand
 * 
 * @package LicenceLand
 * @since 1.0.9
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class LicenceLand_Order_Resend {
    
    public function init() {
        // Debug: Add admin notice to confirm class is loaded
        add_action('admin_notices', function() {
            if (isset($_GET['page']) && $_GET['page'] === 'licenceland-settings') {
                echo '<div class="notice notice-info"><p>Order Resend class loaded successfully!</p></div>';
            }
        });
        
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_licenceland_resend_order_email', [$this, 'resend_order_email']);
        add_action('wp_ajax_licenceland_resend_order_invoice', [$this, 'resend_order_invoice']);
        add_action('wp_ajax_licenceland_test_email', [$this, 'test_email']);
        add_action('wp_ajax_licenceland_resend_remote_order_email', [$this, 'resend_remote_order_email']);
        
        // Add resend buttons to order actions
        add_action('woocommerce_admin_order_actions_end', [$this, 'add_resend_buttons']);
        
        // Add bulk actions
        add_filter('bulk_actions-edit-shop_order', [$this, 'add_bulk_actions']);
        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handle_bulk_actions'], 10, 3);
        
        // Add admin notices for bulk actions
        add_action('admin_notices', [$this, 'bulk_action_admin_notices']);
    }
    
    public function activate() {
        // No specific activation needed for this feature
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'licenceland-settings',
            __('Order Resend', 'licenceland'),
            __('Order Resend', 'licenceland'),
            'manage_woocommerce',
            'licenceland-order-resend',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        // Handle form submission
        if (isset($_POST['licenceland_resend_order']) && wp_verify_nonce($_POST['licenceland_resend_nonce'], 'licenceland_resend_order')) {
            $order_id = (int) $_POST['order_id'];
            $email_type = sanitize_text_field($_POST['email_type']);
            
            if ($order_id && $email_type) {
                $result = $this->resend_email($order_id, $email_type);
                if ($result['success']) {
                    echo '<div class="notice notice-success"><p>' . esc_html($result['message']) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html($result['message']) . '</p></div>';
                }
            }
        }
        
        // Get recent orders
        $orders = wc_get_orders([
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['processing', 'completed', 'on-hold']
        ]);
        ?>
        <div class="wrap">
            <h1><?php _e('Order Resend', 'licenceland'); ?></h1>
            
            <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
            <div class="licenceland-debug-info">
                <h2><?php _e('Debug Information', 'licenceland'); ?></h2>
                <p><strong><?php _e('WooCommerce Version:', 'licenceland'); ?></strong> <?php echo WC()->version; ?></p>
                <p><strong><?php _e('WordPress Version:', 'licenceland'); ?></strong> <?php echo get_bloginfo('version'); ?></p>
                <p><strong><?php _e('PHP Version:', 'licenceland'); ?></strong> <?php echo PHP_VERSION; ?></p>
                <p><strong><?php _e('Email Debug Mode:', 'licenceland'); ?></strong> <?php echo defined('WP_DEBUG') && WP_DEBUG ? __('Enabled', 'licenceland') : __('Disabled', 'licenceland'); ?></p>
                
                <?php
                // Test email functionality
                $mailer = WC()->mailer();
                $emails = $mailer->get_emails();
                echo '<p><strong>' . __('Available WooCommerce Emails:', 'licenceland') . '</strong></p>';
                echo '<ul>';
                foreach ($emails as $email) {
                    echo '<li>' . esc_html($email->id ?? 'unknown') . ' - ' . esc_html(get_class($email)) . '</li>';
                }
                echo '</ul>';
                ?>
            </div>
            <?php endif; ?>
            
            <div class="licenceland-test-email">
                <h2><?php _e('Test Email Functionality', 'licenceland'); ?></h2>
                <p><?php _e('Use this to test if basic email functionality is working:', 'licenceland'); ?></p>
                <input type="email" id="test_email" placeholder="<?php _e('Enter email address to test', 'licenceland'); ?>" value="<?php echo esc_attr(get_option('admin_email')); ?>" style="width: 300px; margin-right: 10px;">
                <button type="button" class="button button-secondary" id="test_email_btn"><?php _e('Send Test Email', 'licenceland'); ?></button>
                <span id="test_email_result" style="margin-left: 10px;"></span>
            </div>
            
            <div class="licenceland-resend-form">
                <h2><?php _e('Resend Order Email', 'licenceland'); ?></h2>
                <form method="post" action="">
                    <?php wp_nonce_field('licenceland_resend_order', 'licenceland_resend_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="order_id"><?php _e('Order ID', 'licenceland'); ?></label>
                            </th>
                            <td>
                                <select name="order_id" id="order_id" required>
                                    <option value=""><?php _e('Select an order...', 'licenceland'); ?></option>
                                    <?php foreach ($orders as $order): ?>
                                        <option value="<?php echo esc_attr($order->get_id()); ?>">
                                            #<?php echo esc_html($order->get_order_number()); ?> - 
                                            <?php echo esc_html($order->get_billing_email()); ?> - 
                                            <?php echo esc_html($order->get_total()); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="email_type"><?php _e('Email Type', 'licenceland'); ?></label>
                            </th>
                            <td>
                                <select name="email_type" id="email_type" required>
                                    <option value=""><?php _e('Select email type...', 'licenceland'); ?></option>
                                    <option value="new_order"><?php _e('New Order', 'licenceland'); ?></option>
                                    <option value="customer_processing_order"><?php _e('Processing Order', 'licenceland'); ?></option>
                                    <option value="customer_completed_order"><?php _e('Completed Order', 'licenceland'); ?></option>
                                    <option value="customer_invoice"><?php _e('Customer Invoice', 'licenceland'); ?></option>
                                    <option value="customer_note"><?php _e('Customer Note', 'licenceland'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Resend Email', 'licenceland')); ?>
                </form>
            </div>
            
            <div class="licenceland-resend-history">
                <h2><?php _e('Recent Resend History', 'licenceland'); ?></h2>
                <?php $this->display_resend_history(); ?>
            </div>
        </div>
        
        <style>
        .licenceland-resend-form {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .licenceland-resend-history {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .licenceland-debug-info {
            background: #f0f6fc;
            padding: 15px;
            margin: 20px 0;
            border: 1px solid #0073aa;
            border-radius: 4px;
        }
        .licenceland-debug-info h2 {
            margin-top: 0;
            color: #0073aa;
        }
        .licenceland-debug-info ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .licenceland-debug-info li {
            margin: 5px 0;
        }
        .licenceland-test-email {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        .licenceland-test-email h2 {
            margin-top: 0;
        }
        #test_email_result {
            font-weight: bold;
        }
        #test_email_result.success {
            color: #46b450;
        }
        #test_email_result.error {
            color: #dc3232;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.licenceland-resend-btn').on('click', function() {
                var orderId = $(this).data('order-id');
                var emailType = $(this).data('email-type');
                var button = $(this);
                
                if (confirm('<?php _e('Are you sure you want to resend this email?', 'licenceland'); ?>')) {
                    button.prop('disabled', true).text('<?php _e('Sending...', 'licenceland'); ?>');
                    
                    $.post(ajaxurl, {
                        action: 'licenceland_resend_order_email',
                        order_id: orderId,
                        email_type: emailType,
                        nonce: '<?php echo wp_create_nonce('licenceland_resend_order'); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert(response.data);
                        } else {
                            alert(response.data || '<?php _e('Error sending email.', 'licenceland'); ?>');
                        }
                        button.prop('disabled', false).text(button.data('original-text') || '<?php _e('Resend', 'licenceland'); ?>');
                    }).fail(function() {
                        alert('<?php _e('Error sending email.', 'licenceland'); ?>');
                        button.prop('disabled', false).text(button.data('original-text') || '<?php _e('Resend', 'licenceland'); ?>');
                    });
                }
            });
            
            // Test email functionality
            $('#test_email_btn').on('click', function() {
                var testEmail = $('#test_email').val();
                var button = $(this);
                var resultSpan = $('#test_email_result');
                
                if (!testEmail) {
                    resultSpan.text('<?php _e('Please enter an email address.', 'licenceland'); ?>').removeClass('success error').addClass('error');
                    return;
                }
                
                button.prop('disabled', true).text('<?php _e('Sending...', 'licenceland'); ?>');
                resultSpan.text('').removeClass('success error');
                
                $.post(ajaxurl, {
                    action: 'licenceland_test_email',
                    test_email: testEmail,
                    nonce: '<?php echo wp_create_nonce('licenceland_resend_order'); ?>'
                }, function(response) {
                    if (response.success) {
                        resultSpan.text(response.data).removeClass('error').addClass('success');
                    } else {
                        resultSpan.text(response.data || '<?php _e('Error sending test email.', 'licenceland'); ?>').removeClass('success').addClass('error');
                    }
                    button.prop('disabled', false).text('<?php _e('Send Test Email', 'licenceland'); ?>');
                }).fail(function() {
                    resultSpan.text('<?php _e('Error sending test email.', 'licenceland'); ?>').removeClass('success').addClass('error');
                    button.prop('disabled', false).text('<?php _e('Send Test Email', 'licenceland'); ?>');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Add resend buttons to order actions
     */
    public function add_resend_buttons($order) {
        if (!$order) return;
        
        $order_id = $order->get_id();
        ?>
        <button type="button" class="button licenceland-resend-btn" 
                data-order-id="<?php echo esc_attr($order_id); ?>"
                data-email-type="new_order">
            <?php _e('Resend Order', 'licenceland'); ?>
        </button>
        <button type="button" class="button licenceland-resend-btn" 
                data-order-id="<?php echo esc_attr($order_id); ?>"
                data-email-type="customer_invoice">
            <?php _e('Resend Invoice', 'licenceland'); ?>
        </button>
        <?php
    }
    
    /**
     * Add bulk actions
     */
    public function add_bulk_actions($bulk_actions) {
        $bulk_actions['licenceland_resend_order'] = __('Resend Order Email', 'licenceland');
        $bulk_actions['licenceland_resend_invoice'] = __('Resend Invoice', 'licenceland');
        return $bulk_actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $doaction, $post_ids) {
        if ($doaction !== 'licenceland_resend_order' && $doaction !== 'licenceland_resend_invoice') {
            return $redirect_to;
        }
        
        $email_type = ($doaction === 'licenceland_resend_order') ? 'new_order' : 'customer_invoice';
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($post_ids as $post_id) {
            $result = $this->resend_email($post_id, $email_type);
            if ($result['success']) {
                $sent_count++;
            } else {
                $failed_count++;
            }
        }
        
        $redirect_to = add_query_arg([
            'licenceland_bulk_resend' => '1',
            'sent' => $sent_count,
            'failed' => $failed_count,
            'email_type' => $email_type
        ], $redirect_to);
        
        return $redirect_to;
    }
    
    /**
     * Display admin notices for bulk actions
     */
    public function bulk_action_admin_notices() {
        if (!isset($_REQUEST['licenceland_bulk_resend'])) {
            return;
        }
        
        $sent = (int) $_REQUEST['sent'];
        $failed = (int) $_REQUEST['failed'];
        $email_type = sanitize_text_field($_REQUEST['email_type']);
        
        $message = '';
        if ($sent > 0) {
            $message .= sprintf(
                _n('%d email sent successfully.', '%d emails sent successfully.', $sent, 'licenceland'),
                $sent
            );
        }
        if ($failed > 0) {
            $message .= ' ' . sprintf(
                _n('%d email failed to send.', '%d emails failed to send.', $failed, 'licenceland'),
                $failed
            );
        }
        
        if ($message) {
            $class = ($failed > 0) ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }
    
    /**
     * AJAX handler for resending order email
     */
    public function resend_order_email() {
        check_ajax_referer('licenceland_resend_order', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die();
        }
        
        $order_id = (int) $_POST['order_id'];
        $email_type = sanitize_text_field($_POST['email_type']);
        
        $result = $this->resend_email($order_id, $email_type);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX handler for resending invoice
     */
    public function resend_order_invoice() {
        check_ajax_referer('licenceland_resend_order', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die();
        }
        
        $order_id = (int) $_POST['order_id'];
        $result = $this->resend_email($order_id, 'customer_invoice');
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    /**
     * AJAX handler for testing email functionality
     */
    public function test_email() {
        check_ajax_referer('licenceland_resend_order', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die();
        }
        
        $test_email = sanitize_email($_POST['test_email'] ?? get_option('admin_email'));
        
        if (!is_email($test_email)) {
            wp_send_json_error(__('Invalid email address.', 'licenceland'));
        }
        
        $subject = __('LicenceLand Email Test', 'licenceland');
        $message = sprintf(
            __('This is a test email from LicenceLand plugin.

Time: %s
WordPress Version: %s
WooCommerce Version: %s
PHP Version: %s

If you receive this email, the basic email functionality is working.', 'licenceland'),
            current_time('Y-m-d H:i:s'),
            get_bloginfo('version'),
            WC()->version,
            PHP_VERSION
        );
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        $sent = wp_mail($test_email, $subject, wpautop($message), $headers);
        
        if ($sent) {
            LicenceLand_Core::log("Test email sent successfully to: {$test_email}", 'info');
            wp_send_json_success(sprintf(__('Test email sent successfully to %s.', 'licenceland'), $test_email));
        } else {
            LicenceLand_Core::log("Test email failed to send to: {$test_email}", 'error');
            wp_send_json_error(__('Failed to send test email. Please check your email configuration.', 'licenceland'));
        }
    }
    
    /**
     * Resend email for a specific order
     */
    private function resend_email($order_id, $email_type) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return [
                'success' => false,
                'message' => __('Order not found.', 'licenceland')
            ];
        }
        
        $customer_email = $order->get_billing_email();
        if (empty($customer_email)) {
            return [
                'success' => false,
                'message' => __('No customer email found for this order.', 'licenceland')
            ];
        }
        
        // Get the appropriate email class
        $email_class = $this->get_email_class($email_type);
        if (!$email_class) {
            return [
                'success' => false,
                'message' => __('Invalid email type.', 'licenceland')
            ];
        }
        
        // Send the email
        $sent = $this->send_email($order, $email_class, $email_type);
        
        if ($sent) {
            // Log the resend
            $this->log_resend($order_id, $email_type, $customer_email);
            
            return [
                'success' => true,
                'message' => sprintf(
                    __('Email sent successfully to %s.', 'licenceland'),
                    $customer_email
                )
            ];
        } else {
            return [
                'success' => false,
                'message' => __('Failed to send email.', 'licenceland')
            ];
        }
    }

    /**
     * Public API wrapper for resending email (for REST callbacks)
     */
    public function api_resend_email($order_id, $email_type) {
        return $this->resend_email($order_id, $email_type);
    }

    /**
     * AJAX: Resend email on remote site (proxy via Sync)
     */
    public function resend_remote_order_email() {
        check_ajax_referer('licenceland_resend_order', 'nonce');
        if (!current_user_can('manage_woocommerce')) {
            wp_die();
        }

        $order_id = (int) ($_POST['order_id'] ?? 0);
        $email_type = sanitize_text_field($_POST['email_type'] ?? '');
        $order = wc_get_order($order_id);
        if (!$order || !$email_type) {
            wp_send_json_error(__('Invalid input.', 'licenceland'));
        }

        $remote_order_id = $order->get_meta('_ll_remote_order_id');
        if (!$remote_order_id) {
            wp_send_json_error(__('No remote order mapping found.', 'licenceland'));
        }

        if (!function_exists('licenceland') || !licenceland()->sync) {
            wp_send_json_error(__('Sync module unavailable.', 'licenceland'));
        }

        $payload = json_encode([
            'remote_order_id' => (string)$remote_order_id,
            'email_type' => $email_type,
        ]);

        $res = licenceland()->sync->send_to_remote_public('POST', '/wp-json/licenceland/v1/sync/order/resend', $payload);
        if ($res['ok'] ?? false) {
            wp_send_json_success(__('Remote resend requested.', 'licenceland'));
        }
        wp_send_json_error(esc_html($res['error'] ?? __('Remote resend failed.', 'licenceland')));
    }
    
    /**
     * Get email class for the specified type
     */
    private function get_email_class($email_type) {
        $email_classes = [
            'new_order' => 'WC_Email_New_Order',
            'customer_processing_order' => 'WC_Email_Customer_Processing_Order',
            'customer_completed_order' => 'WC_Email_Customer_Completed_Order',
            'customer_invoice' => 'WC_Email_Customer_Invoice',
            'customer_note' => 'WC_Email_Customer_Note',
            'customer_refunded_order' => 'WC_Email_Customer_Refunded_Order',
            'customer_on_hold_order' => 'WC_Email_Customer_On_Hold_Order',
            'customer_cancelled_order' => 'WC_Email_Customer_Cancelled_Order'
        ];
        
        return isset($email_classes[$email_type]) ? $email_classes[$email_type] : null;
    }
    
    /**
     * Send email using direct wp_mail method (more reliable than WooCommerce email system)
     */
    private function send_email($order, $email_class, $email_type) {
        // Use the fallback method directly since it's more reliable
        $sent = $this->send_fallback_email($order, $email_type);
        
        // Debug: Log the attempt
        LicenceLand_Core::log("Order resend attempt: Order #{$order->get_id()}, Email type: {$email_type}, Sent: " . ($sent ? 'Yes' : 'No'), 'info');
        
        return $sent;
    }
    
    /**
     * Fallback email sending method using wp_mail
     */
    private function send_fallback_email($order, $email_type) {
        $customer_email = $order->get_billing_email();
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $order_number = $order->get_order_number();
        
        // Get email templates based on type
        $subject = '';
        $message = '';
        
        switch ($email_type) {
            case 'new_order':
                $subject = sprintf(__('New Order #%s - %s', 'licenceland'), $order_number, get_bloginfo('name'));
                $message = $this->get_new_order_template($order);
                break;
                
            case 'customer_processing_order':
                $subject = sprintf(__('Order #%s is being processed - %s', 'licenceland'), $order_number, get_bloginfo('name'));
                $message = $this->get_processing_order_template($order);
                break;
                
            case 'customer_completed_order':
                $subject = sprintf(__('Order #%s has been completed - %s', 'licenceland'), $order_number, get_bloginfo('name'));
                $message = $this->get_completed_order_template($order);
                break;
                
            case 'customer_invoice':
                $subject = sprintf(__('Invoice for Order #%s - %s', 'licenceland'), $order_number, get_bloginfo('name'));
                $message = $this->get_invoice_template($order);
                break;
                
            default:
                LicenceLand_Core::log("Unknown email type for fallback: {$email_type}", 'error');
                return false;
        }
        
        // Set up email headers with better formatting
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            'Reply-To: ' . get_option('admin_email'),
            'X-Mailer: WordPress/LicenceLand'
        ];
        
        // Send the email
        $sent = wp_mail($customer_email, $subject, $message, $headers);
        
        LicenceLand_Core::log("Email sent: " . ($sent ? 'Yes' : 'No') . " to {$customer_email} (Type: {$email_type})", 'info');
        
        return $sent;
    }
    
    /**
     * Get new order email template
     */
    private function get_new_order_template($order) {
        $order_number = $order->get_order_number();
        $total = $order->get_formatted_order_total();
        $items = $order->get_items();
        $customer_name = $order->get_billing_first_name();
        
        $items_html = '';
        foreach ($items as $item) {
            $items_html .= '<tr style="border-bottom: 1px solid #eee;">';
            $items_html .= '<td style="padding: 10px;">' . esc_html($item->get_name()) . '</td>';
            $items_html .= '<td style="padding: 10px; text-align: center;">' . esc_html($item->get_quantity()) . '</td>';
            $items_html .= '<td style="padding: 10px; text-align: right;">' . esc_html($order->get_formatted_line_subtotal($item)) . '</td>';
            $items_html .= '</tr>';
        }
        
        return sprintf(
            '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .order-table { width: 100%%; border-collapse: collapse; margin: 20px 0; }
                    .order-table th { background: #0073aa; color: white; padding: 10px; text-align: left; }
                    .order-table td { padding: 10px; }
                    .total { font-size: 18px; font-weight: bold; text-align: right; padding: 20px; background: #e7f3ff; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>%s</h1>
                    </div>
                    <div class="content">
                        <p>%s %s,</p>
                        <p>%s</p>
                        
                        <table class="order-table">
                            <thead>
                                <tr>
                                    <th>%s</th>
                                    <th style="text-align: center;">%s</th>
                                    <th style="text-align: right;">%s</th>
                                </tr>
                            </thead>
                            <tbody>
                                %s
                            </tbody>
                        </table>
                        
                        <div class="total">
                            <strong>%s: %s</strong>
                        </div>
                        
                        <p>%s</p>
                    </div>
                    <div class="footer">
                        <p>%s</p>
                    </div>
                </div>
            </body>
            </html>',
            sprintf(__('New Order #%s', 'licenceland'), $order_number),
            __('Dear', 'licenceland'),
            $customer_name ?: __('Customer', 'licenceland'),
            __('Thank you for your order! We have received your order and will process it shortly.', 'licenceland'),
            __('Product', 'licenceland'),
            __('Quantity', 'licenceland'),
            __('Total', 'licenceland'),
            $items_html,
            __('Order Total', 'licenceland'),
            $total,
            __('You will receive another email when your order is processed.', 'licenceland'),
            get_bloginfo('name')
        );
    }
    
    /**
     * Get processing order email template
     */
    private function get_processing_order_template($order) {
        $order_number = $order->get_order_number();
        $customer_name = $order->get_billing_first_name();
        
        return sprintf(
            '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>%s</h1>
                    </div>
                    <div class="content">
                        <p>%s %s,</p>
                        <p>%s</p>
                        <p>%s</p>
                    </div>
                    <div class="footer">
                        <p>%s</p>
                    </div>
                </div>
            </body>
            </html>',
            sprintf(__('Order #%s is being processed', 'licenceland'), $order_number),
            __('Dear', 'licenceland'),
            $customer_name ?: __('Customer', 'licenceland'),
            __('Your order is now being processed and will be shipped soon.', 'licenceland'),
            __('Thank you for your patience!', 'licenceland'),
            get_bloginfo('name')
        );
    }
    
    /**
     * Get completed order email template
     */
    private function get_completed_order_template($order) {
        $order_number = $order->get_order_number();
        $customer_name = $order->get_billing_first_name();
        
        return sprintf(
            '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #17a2b8; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>%s</h1>
                    </div>
                    <div class="content">
                        <p>%s %s,</p>
                        <p>%s</p>
                        <p>%s</p>
                    </div>
                    <div class="footer">
                        <p>%s</p>
                    </div>
                </div>
            </body>
            </html>',
            sprintf(__('Order #%s has been completed', 'licenceland'), $order_number),
            __('Dear', 'licenceland'),
            $customer_name ?: __('Customer', 'licenceland'),
            __('Your order has been completed successfully!', 'licenceland'),
            __('Thank you for your purchase!', 'licenceland'),
            get_bloginfo('name')
        );
    }
    
    /**
     * Get invoice email template
     */
    private function get_invoice_template($order) {
        $order_number = $order->get_order_number();
        $total = $order->get_formatted_order_total();
        $customer_name = $order->get_billing_first_name();
        
        return sprintf(
            '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #6f42c1; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .total { font-size: 18px; font-weight: bold; text-align: right; padding: 20px; background: #e7f3ff; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>%s</h1>
                    </div>
                    <div class="content">
                        <p>%s %s,</p>
                        <p>%s</p>
                        <div class="total">
                            <strong>%s: %s</strong>
                        </div>
                        <p>%s</p>
                    </div>
                    <div class="footer">
                        <p>%s</p>
                    </div>
                </div>
            </body>
            </html>',
            sprintf(__('Invoice for Order #%s', 'licenceland'), $order_number),
            __('Dear', 'licenceland'),
            $customer_name ?: __('Customer', 'licenceland'),
            __('Please find your invoice attached below.', 'licenceland'),
            __('Total Amount', 'licenceland'),
            $total,
            __('Thank you for your business!', 'licenceland'),
            get_bloginfo('name')
        );
    }
    
    /**
     * Log the resend action
     */
    private function log_resend($order_id, $email_type, $customer_email) {
        $log_entry = [
            'order_id' => $order_id,
            'email_type' => $email_type,
            'customer_email' => $customer_email,
            'admin_user' => get_current_user_id(),
            'timestamp' => current_time('mysql')
        ];
        
        // Store in order meta
        $existing_logs = get_post_meta($order_id, '_licenceland_resend_log', true);
        if (!is_array($existing_logs)) {
            $existing_logs = [];
        }
        
        $existing_logs[] = $log_entry;
        update_post_meta($order_id, '_licenceland_resend_log', $existing_logs);
        
        // Also log to main log
        LicenceLand_Core::log("Order email resent: Order #{$order_id}, Type: {$email_type}, Email: {$customer_email}");
    }
    
    /**
     * Display resend history
     */
    private function display_resend_history() {
        global $wpdb;
        
        // Get recent resend logs from order meta
        $recent_logs = $wpdb->get_results("
            SELECT pm.post_id, pm.meta_value, p.post_title
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_licenceland_resend_log'
            AND p.post_type = 'shop_order'
            ORDER BY pm.meta_id DESC
            LIMIT 20
        ");
        
        if (empty($recent_logs)) {
            echo '<p>' . __('No recent resend history.', 'licenceland') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Order', 'licenceland') . '</th>';
        echo '<th>' . __('Email Type', 'licenceland') . '</th>';
        echo '<th>' . __('Customer Email', 'licenceland') . '</th>';
        echo '<th>' . __('Sent By', 'licenceland') . '</th>';
        echo '<th>' . __('Date', 'licenceland') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($recent_logs as $log) {
            $resend_logs = maybe_unserialize($log->meta_value);
            if (!is_array($resend_logs)) continue;
            
            // Get the most recent log entry
            $latest_log = end($resend_logs);
            
            echo '<tr>';
            echo '<td><a href="' . admin_url('post.php?post=' . $log->post_id . '&action=edit') . '">' . esc_html($log->post_title) . '</a></td>';
            echo '<td>' . esc_html($this->get_email_type_label($latest_log['email_type'])) . '</td>';
            echo '<td>' . esc_html($latest_log['customer_email']) . '</td>';
            echo '<td>' . esc_html(get_user_by('id', $latest_log['admin_user'])->display_name ?? 'Unknown') . '</td>';
            echo '<td>' . esc_html($latest_log['timestamp']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    /**
     * Get human-readable email type label
     */
    private function get_email_type_label($email_type) {
        $labels = [
            'new_order' => __('New Order', 'licenceland'),
            'customer_processing_order' => __('Processing Order', 'licenceland'),
            'customer_completed_order' => __('Completed Order', 'licenceland'),
            'customer_invoice' => __('Customer Invoice', 'licenceland'),
            'customer_note' => __('Customer Note', 'licenceland')
        ];
        
        return $labels[$email_type] ?? $email_type;
    }
} 