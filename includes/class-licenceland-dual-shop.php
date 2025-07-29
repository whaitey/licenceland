<?php
/**
 * Dual Shop Management for LicenceLand
 * 
 * @package LicenceLand
 * @since 1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class LicenceLand_Dual_Shop {
    
    public function init() {
        if (!LicenceLand_Core::is_feature_enabled('dual_shop')) {
            return;
        }
        
        // Shop type management
        add_action('template_redirect', [$this, 'handle_shop_type_switching']);
        
        // Product availability
        add_action('add_meta_boxes', [$this, 'add_shop_availability_meta_box']);
        add_action('save_post_product', [$this, 'save_shop_availability']);
        add_filter('woocommerce_product_query', [$this, 'filter_products_by_shop_type']);
        add_action('template_redirect', [$this, 'check_product_availability']);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_cart_item_availability'], 10, 2);
        add_action('woocommerce_check_cart_items', [$this, 'check_cart_compatibility']);
        
        // Pricing
        add_action('woocommerce_product_options_pricing', [$this, 'add_business_price_field']);
        add_action('woocommerce_process_product_meta', [$this, 'save_business_price_field']);
        add_filter('woocommerce_product_get_price', [$this, 'maybe_swap_price'], 10, 2);
        add_filter('woocommerce_product_get_regular_price', [$this, 'maybe_swap_price'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'set_cart_item_price'], 20);
        
        // Payment methods
        add_filter('woocommerce_available_payment_gateways', [$this, 'filter_payment_gateways']);
        
        // Order management
        add_action('woocommerce_checkout_create_order', [$this, 'add_order_shop_meta'], 20, 2);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'add_order_shop_meta'], 20, 2);
        add_action('woocommerce_checkout_order_processed', [$this, 'add_order_shop_meta'], 20, 1);
        add_action('woocommerce_new_order', [$this, 'add_order_shop_meta'], 20, 1);
        
        // Admin columns
        add_filter('manage_edit-shop_order_columns', [$this, 'add_shop_side_column'], 20);
        add_action('manage_shop_order_posts_custom_column', [$this, 'render_shop_side_column'], 20, 2);
        add_filter('woocommerce_shop_order_list_table_columns', [$this, 'add_shop_side_column'], 20);
        add_action('woocommerce_shop_order_list_table_custom_column', [$this, 'render_shop_side_column'], 20, 2);
        
        // Invoice number functionality
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'add_szamlaszam_field']);
        add_action('woocommerce_process_shop_order_meta', [$this, 'save_szamlaszam_field']);
        
        // Elementor integration
        add_filter('elementor/theme/get_document', [$this, 'override_elementor_document'], 10, 2);
        add_action('wp_head', [$this, 'output_elementor_header'], 5);
        add_action('wp_footer', [$this, 'output_elementor_footer'], 5);
        
        // CSS and styling
        add_action('wp_enqueue_scripts', [$this, 'enqueue_shop_styles'], 99);
        add_filter('body_class', [$this, 'add_shop_body_class']);
        
        // Cart fragments
        add_filter('woocommerce_add_to_cart_fragments', [$this, 'update_cart_fragments']);
        
        // IP blocking
        add_action('woocommerce_checkout_process', [$this, 'block_by_ip']);
        add_action('woocommerce_checkout_process', [$this, 'block_by_email']);
        add_filter('woocommerce_available_payment_gateways', [$this, 'disable_gateways_for_ip']);
        add_filter('woocommerce_available_payment_gateways', [$this, 'disable_gateways_for_email']);
    }
    
    public function activate() {
        // Set default options
        $this->set_default_options();
    }
    
    /**
     * Handle shop type switching
     */
    public function handle_shop_type_switching() {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        
        // GET parameter based switching
        if (isset($_GET['shop']) && in_array($_GET['shop'], ['lakossagi', 'uzleti'], true)) {
            $shop = sanitize_text_field($_GET['shop']);
            
            // Set cookie
            setcookie('ds_shop_type', $shop, time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            $_COOKIE['ds_shop_type'] = $shop;
            
            // Set session
            if (function_exists('WC') && WC()->session) {
                WC()->session->set('ds_shop_type', $shop);
            }
            
            // Empty cart and redirect
            if (function_exists('WC')) {
                WC()->cart->empty_cart();
            }
            
            wp_safe_redirect(home_url('/' . $shop));
            exit;
        }
        
        // URL segment based switching
        $first = explode('/', $path)[0] ?? '';
        if (in_array($first, ['lakossagi', 'uzleti'], true)) {
            $shop = $first;
            
            setcookie('ds_shop_type', $shop, time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            $_COOKIE['ds_shop_type'] = $shop;
            
            if (function_exists('WC') && WC()->session) {
                WC()->session->set('ds_shop_type', $shop);
            }
            
            if (function_exists('WC')) {
                WC()->cart->empty_cart();
            }
        }
    }
    
    /**
     * Add shop availability meta box
     */
    public function add_shop_availability_meta_box() {
        add_meta_box(
            'ds_shop_availability',
            __('Shop Availability', 'licenceland'),
            [$this, 'shop_availability_meta_box_html'],
            'product',
            'side'
        );
    }
    
    public function shop_availability_meta_box_html($post) {
        $lakossagi = get_post_meta($post->ID, '_ds_available_lakossagi', true) ?: 'yes';
        $uzleti = get_post_meta($post->ID, '_ds_available_uzleti', true) ?: 'yes';
        
        wp_nonce_field('ds_shop_availability_save', 'ds_shop_availability_nonce');
        ?>
        <p>
            <label>
                <input type="checkbox" name="_ds_available_lakossagi" value="yes" <?php checked($lakossagi, 'yes'); ?>>
                <?php _e('Available in Consumer Shop', 'licenceland'); ?>
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="_ds_available_uzleti" value="yes" <?php checked($uzleti, 'yes'); ?>>
                <?php _e('Available in Business Shop', 'licenceland'); ?>
            </label>
        </p>
        <?php
    }
    
    /**
     * Save shop availability
     */
    public function save_shop_availability($post_id) {
        if (!isset($_POST['ds_shop_availability_nonce']) || 
            !wp_verify_nonce($_POST['ds_shop_availability_nonce'], 'ds_shop_availability_save')) {
            return;
        }
        
        update_post_meta($post_id, '_ds_available_lakossagi', $_POST['_ds_available_lakossagi'] ?? 'no');
        update_post_meta($post_id, '_ds_available_uzleti', $_POST['_ds_available_uzleti'] ?? 'no');
    }
    
    /**
     * Filter products by shop type
     */
    public function filter_products_by_shop_type($q) {
        if (is_admin()) {
            return;
        }
        
        $shop = $_COOKIE['ds_shop_type'] ?? 'lakossagi';
        $meta_key = ($shop === 'uzleti') ? '_ds_available_uzleti' : '_ds_available_lakossagi';
        
        $meta_query = $q->get('meta_query') ?: [];
        $meta_query[] = [
            'key' => $meta_key,
            'value' => 'yes',
            'compare' => '='
        ];
        
        $q->set('meta_query', $meta_query);
    }
    
    /**
     * Check product availability
     */
    public function check_product_availability() {
        if (!is_product()) {
            return;
        }
        
        global $post;
        $shop = $_COOKIE['ds_shop_type'] ?? 'lakossagi';
        $meta_key = ($shop === 'uzleti') ? '_ds_available_uzleti' : '_ds_available_lakossagi';
        $available = get_post_meta($post->ID, $meta_key, true);
        
        if ($available !== 'yes') {
            wp_redirect(home_url());
            exit;
        }
    }
    
    /**
     * Validate cart item availability
     */
    public function validate_cart_item_availability($valid, $product_id) {
        $shop = $_COOKIE['ds_shop_type'] ?? 'lakossagi';
        $meta_key = ($shop === 'uzleti') ? '_ds_available_uzleti' : '_ds_available_lakossagi';
        $available = get_post_meta($product_id, $meta_key, true);
        
        if ($available !== 'yes') {
            wc_add_notice(__('This product is not available in the current shop.', 'licenceland'), 'error');
            return false;
        }
        
        return $valid;
    }
    
    /**
     * Check cart compatibility
     */
    public function check_cart_items() {
        $shop = $_COOKIE['ds_shop_type'] ?? 'lakossagi';
        $meta_key = ($shop === 'uzleti') ? '_ds_available_uzleti' : '_ds_available_lakossagi';
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $available = get_post_meta($product_id, $meta_key, true);
            
            if ($available !== 'yes') {
                WC()->cart->remove_cart_item($cart_item['key']);
                wc_add_notice(__('A product has been removed because it is not available in the current shop.', 'licenceland'), 'error');
            }
        }
    }
    
    /**
     * Add business price field
     */
    public function add_business_price_field() {
        woocommerce_wp_text_input([
            'id' => '_ds_business_price',
            'label' => __('Business Price', 'licenceland'),
            'description' => __('Product price for business shop.', 'licenceland'),
            'data_type' => 'price',
        ]);
    }
    
    /**
     * Save business price field
     */
    public function save_business_price_field($post_id) {
        if (isset($_POST['_ds_business_price'])) {
            update_post_meta(
                $post_id,
                '_ds_business_price',
                wc_clean(wp_unslash($_POST['_ds_business_price']))
            );
        }
    }
    
    /**
     * Maybe swap price based on shop type
     */
    public function maybe_swap_price($price, $product) {
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return $price;
        }
        
        $shop = $_COOKIE['ds_shop_type'] ?? 'lakossagi';
        
        if ($shop === 'uzleti') {
            $business = get_post_meta($product->get_id(), '_ds_business_price', true);
            if ($business !== '') {
                return $business;
            }
        }
        
        return $price;
    }
    
    /**
     * Set cart item price
     */
    public function set_cart_item_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        $shop = $_COOKIE['ds_shop_type'] ?? 
                (function_exists('WC') && WC()->session ? WC()->session->get('ds_shop_type') : 'lakossagi');
        
        if ($shop !== 'uzleti') {
            return;
        }
        
        foreach ($cart->get_cart() as $item_key => $cart_item) {
            $business = get_post_meta($cart_item['product_id'], '_ds_business_price', true);
            if ($business !== '') {
                $cart->cart_contents[$item_key]['data']->set_price($business);
            }
        }
    }
    
    /**
     * Filter payment gateways
     */
    public function filter_payment_gateways($available) {
        $shop = $_COOKIE['ds_shop_type'] ?? 'lakossagi';
        $opt = $shop === 'uzleti' ? 'ds_uzl_payments' : 'ds_lak_payments';
        $allowed = (array) get_option($opt, []);
        
        foreach ($available as $id => $gateway) {
            if (!in_array($id, $allowed, true)) {
                unset($available[$id]);
            }
        }
        
        return $available;
    }
    
    /**
     * Add order shop meta
     */
    public function add_order_shop_meta($order) {
        if (is_numeric($order)) {
            $order = wc_get_order(absint($order));
        }
        
        if (!$order instanceof WC_Order) {
            return;
        }
        
        $shop = isset($_COOKIE['ds_shop_type'])
            ? sanitize_text_field(wp_unslash($_COOKIE['ds_shop_type']))
            : 'lakossagi';
        
        $order->update_meta_data('_ds_shop_type', $shop);
        
        // Store customer IP
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'no_ip';
        $order->update_meta_data('_ds_customer_ip', $ip);
        
        $order->save();
    }
    
    /**
     * Add shop side column
     */
    public function add_shop_side_column($columns) {
        $new = [];
        $added_shop = false;
        $added_szamla = false;
        
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            
            if ('order_date' === $key) {
                if (!$added_shop) {
                    $new['ds_shop_side'] = __('Shop Type', 'licenceland');
                    $added_shop = true;
                }
                if (!$added_szamla) {
                    $new['szamlaszam'] = __('Invoice Number', 'licenceland');
                    $added_szamla = true;
                }
            }
        }
        
        if (!$added_shop) {
            $new['ds_shop_side'] = __('Shop Type', 'licenceland');
        }
        if (!$added_szamla) {
            $new['szamlaszam'] = __('Invoice Number', 'licenceland');
        }
        
        return $new;
    }
    
    /**
     * Render shop side column
     */
    public function render_shop_side_column($column, $post_id) {
        $order = wc_get_order($post_id);
        
        if ('ds_shop_side' === $column) {
            $shop = $order ? $order->get_meta('_ds_shop_type') : '';
            echo esc_html($shop === 'uzleti' ? __('Business', 'licenceland') : __('Consumer', 'licenceland'));
        } elseif ('szamlaszam' === $column) {
            echo $order ? esc_html($order->get_meta('_szamlaszam', true)) : '';
        }
    }
    
    /**
     * Add invoice number field
     */
    public function add_szamlaszam_field($order) {
        woocommerce_wp_text_input([
            'id' => '_szamlaszam',
            'label' => __('Invoice Number', 'licenceland'),
            'wrapper_class' => 'form-field-wide',
            'value' => $order->get_meta('_szamlaszam', true),
        ]);
    }
    
    /**
     * Save invoice number field
     */
    public function save_szamlaszam_field($order_id) {
        if (isset($_POST['_szamlaszam'])) {
            $order = wc_get_order($order_id);
            $order->update_meta_data('_szamlaszam', sanitize_text_field($_POST['_szamlaszam']));
            $order->save();
        }
    }
    
    /**
     * Override Elementor document
     */
    public function override_elementor_document($doc, $loc) {
        if (!class_exists('\Elementor\Plugin')) {
            return $doc;
        }
        
        $shop = $_COOKIE['ds_shop_type'] ?? 'lakossagi';
        $map = [
            'lakossagi' => ['header' => 'ds_lak_header_id', 'footer' => 'ds_lak_footer_id'],
            'uzleti' => ['header' => 'ds_uzl_header_id', 'footer' => 'ds_uzl_footer_id'],
        ];
        
        if (isset($map[$shop][$loc])) {
            $id = get_option($map[$shop][$loc]);
            $custom = \Elementor\Plugin::instance()->documents->get(absint($id));
            if ($custom) {
                return $custom;
            }
        }
        
        return $doc;
    }
    
    /**
     * Output Elementor header
     */
    public function output_elementor_header() {
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }
        
        $shop = $_COOKIE['ds_shop_type'] ?? 'lakossagi';
        $map = ['lakossagi' => 'ds_lak_header_id', 'uzleti' => 'ds_uzl_header_id'];
        $id = get_option($map[$shop]);
        
        if ($id) {
            echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display(absint($id));
        }
    }
    
    /**
     * Output Elementor footer
     */
    public function output_elementor_footer() {
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }
        
        $shop = $_COOKIE['ds_shop_type'] ?? 'lakossagi';
        $map = ['lakossagi' => 'ds_lak_footer_id', 'uzleti' => 'ds_uzl_footer_id'];
        $id = get_option($map[$shop]);
        
        if ($id) {
            echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display(absint($id));
        }
    }
    
    /**
     * Enqueue shop styles
     */
    public function enqueue_shop_styles() {
        $shop = $_COOKIE['ds_shop_type'] ?? 'lakossagi';
        $map = [
            'lakossagi' => [
                'header' => get_option('ds_lak_header_id'),
                'footer' => get_option('ds_lak_footer_id'),
            ],
            'uzleti' => [
                'header' => get_option('ds_uzl_header_id'),
                'footer' => get_option('ds_uzl_footer_id'),
            ],
        ];
        
        foreach (['header', 'footer'] as $part) {
            $id = isset($map[$shop][$part]) ? absint($map[$shop][$part]) : 0;
            if ($id) {
                $css_file = WP_CONTENT_DIR . "/uploads/elementor/css/post-$id.css";
                $css_url = content_url("/uploads/elementor/css/post-$id.css");
                
                if (file_exists($css_file)) {
                    wp_enqueue_style(
                        "elementor-{$part}-{$shop}-css",
                        $css_url,
                        [],
                        filemtime($css_file)
                    );
                }
            }
        }
    }
    
    /**
     * Add shop body class
     */
    public function add_shop_body_class($classes) {
        $shop = $_COOKIE['ds_shop_type'] ?? 'lakossagi';
        $classes[] = 'shop-' . $shop;
        return $classes;
    }
    
    /**
     * Update cart fragments
     */
    public function update_cart_fragments($fragments) {
        ob_start();
        woocommerce_mini_cart();
        $fragments['div.widget_shopping_cart_content'] = ob_get_clean();
        return $fragments;
    }
    
    /**
     * Block by IP
     */
    public function block_by_ip() {
        $banned_raw = get_option('ds_banned_ips', '');
        $banned_ips = array_filter(array_map('trim', preg_split('/[\r\n]+/', $banned_raw)));
        $user_ip = WC_Geolocation::get_ip_address();
        
        if (in_array($user_ip, $banned_ips, true)) {
            wc_add_notice(__('Orders are not allowed from this IP address.', 'licenceland'), 'error');
        }
    }
    
    /**
     * Block by email
     */
    public function block_by_email() {
        if (isset($_POST['billing_email'])) {
            $banned_raw = get_option('ds_banned_emails', '');
            $banned_emails = array_filter(array_map('trim', preg_split('/[\r\n]+/', $banned_raw)));
            $email = sanitize_email(wp_unslash($_POST['billing_email']));
            
            if (in_array($email, $banned_emails, true)) {
                wc_add_notice(__('Sorry, this email address cannot be used for purchases.', 'licenceland'), 'error');
            }
        }
    }
    
    /**
     * Disable gateways for IP
     */
    public function disable_gateways_for_ip($gateways) {
        $banned_raw = get_option('ds_banned_ips', '');
        $banned_ips = array_filter(array_map('trim', preg_split('/[\r\n]+/', $banned_raw)));
        $user_ip = WC_Geolocation::get_ip_address();
        
        if (in_array($user_ip, $banned_ips, true)) {
            return [];
        }
        
        return $gateways;
    }
    
    /**
     * Disable gateways for email
     */
    public function disable_gateways_for_email($gateways) {
        $banned_raw = get_option('ds_banned_emails', '');
        $banned_emails = array_filter(array_map('trim', preg_split('/[\r\n]+/', $banned_raw)));
        $email = WC()->checkout()->get_value('billing_email');
        
        if (in_array($email, $banned_emails, true)) {
            return [];
        }
        
        return $gateways;
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        if (!get_option('ds_lak_payments')) {
            update_option('ds_lak_payments', []);
        }
        
        if (!get_option('ds_uzl_payments')) {
            update_option('ds_uzl_payments', []);
        }
        
        if (!get_option('ds_banned_ips')) {
            update_option('ds_banned_ips', '');
        }
        
        if (!get_option('ds_banned_emails')) {
            update_option('ds_banned_emails', '');
        }
    }
}