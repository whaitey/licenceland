<?php
/**
 * Settings Management for LicenceLand
 * 
 * @package LicenceLand
 * @since 1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class LicenceLand_Settings {
    
    const OPTION_GROUP = 'licenceland_options';
    const MENU_SLUG = 'licenceland-settings';
    
    public function init() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
    }
    
    public function activate() {
        // Set default options
        $this->set_default_options();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('LicenceLand Settings', 'licenceland'),
            __('LicenceLand', 'licenceland'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'settings_page_html'],
            'dashicons-store',
            56
        );
        
        // Add submenu pages
        add_submenu_page(
            self::MENU_SLUG,
            __('General Settings', 'licenceland'),
            __('General', 'licenceland'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'settings_page_html']
        );
        
        add_submenu_page(
            self::MENU_SLUG,
            __('CD Keys Settings', 'licenceland'),
            __('CD Keys', 'licenceland'),
            'manage_options',
            'licenceland-cd-keys',
            [$this, 'cd_keys_settings_page']
        );
        
        add_submenu_page(
            self::MENU_SLUG,
            __('Dual Shop Settings', 'licenceland'),
            __('Dual Shop', 'licenceland'),
            'manage_options',
            'licenceland-dual-shop',
            [$this, 'dual_shop_settings_page']
        );
        
        add_submenu_page(
            self::MENU_SLUG,
            __('IP Search', 'licenceland'),
            __('IP Search', 'licenceland'),
            'manage_woocommerce',
            'licenceland-ip-search',
            [$this, 'ip_search_page']
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // General settings
        register_setting(self::OPTION_GROUP, 'licenceland_cd_keys_enabled');
        register_setting(self::OPTION_GROUP, 'licenceland_dual_shop_enabled');
        register_setting(self::OPTION_GROUP, 'licenceland_default_shop_type');
        register_setting(self::OPTION_GROUP, 'licenceland_github_token');
        register_setting(self::OPTION_GROUP, 'licenceland_payment_based_orders');
        
        // CD Keys settings
        register_setting(self::OPTION_GROUP, 'licenceland_cd_keys_default_threshold');
        register_setting(self::OPTION_GROUP, 'licenceland_cd_keys_auto_assign_default');
        
        // Dual Shop settings
        register_setting(self::OPTION_GROUP, 'ds_lak_header_id');
        register_setting(self::OPTION_GROUP, 'ds_uzl_header_id');
        register_setting(self::OPTION_GROUP, 'ds_lak_footer_id');
        register_setting(self::OPTION_GROUP, 'ds_uzl_footer_id');
        register_setting(self::OPTION_GROUP, 'ds_lak_product_id');
        register_setting(self::OPTION_GROUP, 'ds_uzl_product_id');
        
        register_setting(self::OPTION_GROUP, 'ds_lak_payments', [
            'type' => 'array',
            'sanitize_callback' => function($value) {
                return array_map('sanitize_text_field', (array) $value);
            },
        ]);
        
        register_setting(self::OPTION_GROUP, 'ds_uzl_payments', [
            'type' => 'array',
            'sanitize_callback' => function($value) {
                return array_map('sanitize_text_field', (array) $value);
            },
        ]);
        
        register_setting(self::OPTION_GROUP, 'ds_banned_ips', [
            'type' => 'string',
            'sanitize_callback' => function($value) {
                return preg_replace('/[^\d\.\:\n\r ]/', '', $value);
            },
        ]);
        
        register_setting(self::OPTION_GROUP, 'ds_banned_emails', [
            'type' => 'string',
            'sanitize_callback' => function($value) {
                return preg_replace('/[^\w\.@\-\+\_\n\r ]/', '', $value);
            },
        ]);
    }
    
    /**
     * Admin scripts
     */
    public function admin_scripts($hook) {
        if (strpos($hook, 'licenceland') === false) {
            return;
        }
        
        wp_enqueue_style(
            'licenceland-admin-settings',
            LICENCELAND_PLUGIN_URL . 'assets/css/admin-settings.css',
            [],
            LICENCELAND_VERSION
        );
        
        wp_enqueue_script(
            'licenceland-admin-settings',
            LICENCELAND_PLUGIN_URL . 'assets/js/admin-settings.js',
            ['jquery'],
            LICENCELAND_VERSION,
            true
        );
    }
    
    /**
     * Main settings page
     */
    public function settings_page_html() {
        ?>
        <div class="wrap">
            <h1><?php _e('LicenceLand Settings', 'licenceland'); ?></h1>
            
            <div class="licenceland-dashboard">
                <div class="licenceland-welcome">
                    <h2><?php _e('Welcome to LicenceLand', 'licenceland'); ?></h2>
                    <p><?php _e('Comprehensive e-commerce solution featuring CD Key management, dual shop functionality, and advanced WooCommerce integration.', 'licenceland'); ?></p>
                </div>
                
                <div class="licenceland-status">
                    <h3><?php _e('System Status', 'licenceland'); ?></h3>
                    <table class="widefat">
                        <tr>
                            <td><?php _e('Plugin Version:', 'licenceland'); ?></td>
                            <td><?php echo esc_html(LICENCELAND_VERSION); ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('WooCommerce:', 'licenceland'); ?></td>
                            <td><?php echo class_exists('WooCommerce') ? '<span class="status-ok">✓ Active</span>' : '<span class="status-error">✗ Not Active</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('CD Keys Feature:', 'licenceland'); ?></td>
                            <td><?php echo LicenceLand_Core::is_feature_enabled('cd_keys') ? '<span class="status-ok">✓ Enabled</span>' : '<span class="status-warning">⚠ Disabled</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Dual Shop Feature:', 'licenceland'); ?></td>
                            <td><?php echo LicenceLand_Core::is_feature_enabled('dual_shop') ? '<span class="status-ok">✓ Enabled</span>' : '<span class="status-warning">⚠ Disabled</span>'; ?></td>
                        </tr>
                        <tr>
                            <td><?php _e('Payment-Based Orders:', 'licenceland'); ?></td>
                            <td><?php echo get_option('licenceland_payment_based_orders', 'yes') === 'yes' ? '<span class="status-ok">✓ Enabled</span>' : '<span class="status-warning">⚠ Disabled</span>'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>
                
                <h2><?php _e('General Settings', 'licenceland'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="licenceland_cd_keys_enabled"><?php _e('Enable CD Keys', 'licenceland'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="licenceland_cd_keys_enabled" name="licenceland_cd_keys_enabled" value="yes" <?php checked(get_option('licenceland_cd_keys_enabled', 'yes'), 'yes'); ?>>
                            <p class="description"><?php _e('Enable CD Key management functionality.', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="licenceland_dual_shop_enabled"><?php _e('Enable Dual Shop', 'licenceland'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="licenceland_dual_shop_enabled" name="licenceland_dual_shop_enabled" value="yes" <?php checked(get_option('licenceland_dual_shop_enabled', 'yes'), 'yes'); ?>>
                            <p class="description"><?php _e('Enable dual shop functionality (Consumer/Business).', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="licenceland_default_shop_type"><?php _e('Default Shop Type', 'licenceland'); ?></label>
                        </th>
                        <td>
                            <select id="licenceland_default_shop_type" name="licenceland_default_shop_type">
                                <option value="lakossagi" <?php selected(get_option('licenceland_default_shop_type', 'lakossagi'), 'lakossagi'); ?>><?php _e('Consumer (Lakossági)', 'licenceland'); ?></option>
                                <option value="uzleti" <?php selected(get_option('licenceland_default_shop_type', 'lakossagi'), 'uzleti'); ?>><?php _e('Business (Üzleti)', 'licenceland'); ?></option>
                            </select>
                            <p class="description"><?php _e('Default shop type for new visitors.', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="licenceland_github_token"><?php _e('GitHub Token', 'licenceland'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="licenceland_github_token" name="licenceland_github_token" value="<?php echo esc_attr(get_option('licenceland_github_token', '')); ?>" class="regular-text">
                            <p class="description"><?php _e('GitHub personal access token for private repository updates. <a href="https://github.com/settings/tokens" target="_blank">Get token here</a>', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="licenceland_payment_based_orders"><?php _e('Payment-Based Order Creation', 'licenceland'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="licenceland_payment_based_orders" name="licenceland_payment_based_orders" value="yes" <?php checked(get_option('licenceland_payment_based_orders', 'yes'), 'yes'); ?>>
                            <p class="description"><?php _e('Only create orders after successful payment completion. Prevents orders from being created before payment is processed.', 'licenceland'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * CD Keys settings page
     */
    public function cd_keys_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('CD Keys Settings', 'licenceland'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>
                
                <h2><?php _e('CD Keys Configuration', 'licenceland'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="licenceland_cd_keys_default_threshold"><?php _e('Default Stock Alert Threshold', 'licenceland'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="licenceland_cd_keys_default_threshold" name="licenceland_cd_keys_default_threshold" value="<?php echo esc_attr(get_option('licenceland_cd_keys_default_threshold', 5)); ?>" min="0" step="1">
                            <p class="description"><?php _e('Default threshold for stock alerts when creating new products.', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="licenceland_cd_keys_auto_assign_default"><?php _e('Default Auto-assign Setting', 'licenceland'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="licenceland_cd_keys_auto_assign_default" name="licenceland_cd_keys_auto_assign_default" value="yes" <?php checked(get_option('licenceland_cd_keys_auto_assign_default', 'yes'), 'yes'); ?>>
                            <p class="description"><?php _e('Default setting for auto-assigning CD keys to pending orders.', 'licenceland'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Dual Shop settings page
     */
    public function dual_shop_settings_page() {
        $templates = get_posts([
            'post_type' => 'elementor_library',
            'numberposts' => -1,
        ]);
        
        $all_gateways = WC()->payment_gateways()->payment_gateways();
        $gateways = [];
        foreach ($all_gateways as $id => $gw) {
            $settings = get_option('woocommerce_' . $id . '_settings', []);
            if (isset($settings['enabled']) && 'yes' === $settings['enabled']) {
                $gateways[$id] = $gw;
            }
        }
        
        $lak_pay = get_option('ds_lak_payments', []);
        $uzl_pay = get_option('ds_uzl_payments', []);
        $banned_ips = get_option('ds_banned_ips', '');
        $banned_emails = get_option('ds_banned_emails', '');
        ?>
        
        <div class="wrap">
            <h1><?php _e('Dual Shop Settings', 'licenceland'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>
                
                <h2><?php _e('Header/Footer Configuration', 'licenceland'); ?></h2>
                <table class="form-table">
                    <?php
                    $fields = [
                        'ds_lak_header_id' => __('Consumer Header', 'licenceland'),
                        'ds_lak_footer_id' => __('Consumer Footer', 'licenceland'),
                        'ds_uzl_header_id' => __('Business Header', 'licenceland'),
                        'ds_uzl_footer_id' => __('Business Footer', 'licenceland'),
                    ];
                    foreach ($fields as $opt => $label) :
                        $current = get_option($opt);
                    ?>
                        <tr>
                            <th><label for="<?php echo esc_attr($opt); ?>"><?php echo esc_html($label); ?></label></th>
                            <td>
                                <select name="<?php echo esc_attr($opt); ?>" id="<?php echo esc_attr($opt); ?>">
                                    <option value=""><?php esc_html_e('— Select —', 'licenceland'); ?></option>
                                    <?php foreach ($templates as $tpl) : ?>
                                        <option value="<?php echo esc_attr($tpl->ID); ?>" <?php selected($current, $tpl->ID); ?>><?php echo esc_html($tpl->post_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                
                <h2><?php _e('Product Page Templates', 'licenceland'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="ds_lak_product_id"><?php esc_html_e('Consumer Product Template', 'licenceland'); ?></label></th>
                        <td>
                            <select name="ds_lak_product_id" id="ds_lak_product_id">
                                <option value=""><?php esc_html_e('— Select —', 'licenceland'); ?></option>
                                <?php foreach ($templates as $tpl) : ?>
                                    <option value="<?php echo esc_attr($tpl->ID); ?>" <?php selected(get_option('ds_lak_product_id'), $tpl->ID); ?>>
                                        <?php echo esc_html($tpl->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ds_uzl_product_id"><?php esc_html_e('Business Product Template', 'licenceland'); ?></label></th>
                        <td>
                            <select name="ds_uzl_product_id" id="ds_uzl_product_id">
                                <option value=""><?php esc_html_e('— Select —', 'licenceland'); ?></option>
                                <?php foreach ($templates as $tpl) : ?>
                                    <option value="<?php echo esc_attr($tpl->ID); ?>" <?php selected(get_option('ds_uzl_product_id'), $tpl->ID); ?>>
                                        <?php echo esc_html($tpl->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Payment Methods', 'licenceland'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e('Payment Method', 'licenceland'); ?></th>
                        <th><?php esc_html_e('Consumer', 'licenceland'); ?></th>
                        <th><?php esc_html_e('Business', 'licenceland'); ?></th>
                    </tr>
                    <?php foreach ($gateways as $id => $gateway) : ?>
                        <tr>
                            <th><?php echo esc_html($gateway->get_title()); ?></th>
                            <td><input type="checkbox" name="ds_lak_payments[]" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, $lak_pay, true)); ?>></td>
                            <td><input type="checkbox" name="ds_uzl_payments[]" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, $uzl_pay, true)); ?>></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                
                <h2><?php _e('Banned IP Addresses', 'licenceland'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="ds_banned_ips"><?php _e('Banned IPs', 'licenceland'); ?></label></th>
                        <td>
                            <textarea name="ds_banned_ips" id="ds_banned_ips" rows="6" cols="50" class="large-text"><?php echo esc_textarea($banned_ips); ?></textarea>
                            <p class="description"><?php _e('Enter one IP address per line (e.g., 1.2.3.4 or CIDR notation).', 'licenceland'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Banned Email Addresses', 'licenceland'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="ds_banned_emails"><?php _e('Banned Emails', 'licenceland'); ?></label></th>
                        <td>
                            <textarea name="ds_banned_emails" id="ds_banned_emails" rows="6" cols="50" class="large-text"><?php echo esc_textarea($banned_emails); ?></textarea>
                            <p class="description"><?php _e('Enter one email address per line.', 'licenceland'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * IP Search page
     */
    public function ip_search_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('WooCommerce Order Search by IP Address', 'licenceland'); ?></h1>
            
            <form method="get" action="">
                <input type="hidden" name="page" value="licenceland-ip-search">
                <input type="text" name="customer_ip" placeholder="<?php esc_attr_e('e.g., 192.168.0.1', 'licenceland'); ?>" value="<?php echo isset($_GET['customer_ip']) ? esc_attr($_GET['customer_ip']) : ''; ?>" style="width: 220px;" />
                <button type="submit" class="button button-primary"><?php _e('Search', 'licenceland'); ?></button>
            </form>
            
            <hr>
            
            <?php
            if (!empty($_GET['customer_ip'])) {
                $ip = sanitize_text_field($_GET['customer_ip']);
                $args = [
                    'limit' => 100,
                    'type' => 'shop_order',
                    'meta_key' => '_ds_customer_ip',
                    'meta_value' => $ip,
                    'meta_compare' => 'LIKE',
                    'orderby' => 'date',
                    'order' => 'DESC'
                ];
                
                $orders = wc_get_orders($args);
                
                if ($orders) {
                    echo '<h3>' . __('Results:', 'licenceland') . '</h3>';
                    echo '<table class="widefat"><thead><tr>
                            <th>' . __('Order', 'licenceland') . '</th>
                            <th>' . __('Date', 'licenceland') . '</th>
                            <th>' . __('Customer', 'licenceland') . '</th>
                            <th>' . __('IP Address', 'licenceland') . '</th>
                            <th>' . __('Status', 'licenceland') . '</th>
                        </tr></thead><tbody>';
                    
                    foreach ($orders as $order) {
                        echo '<tr>
                            <td><a href="' . esc_url(get_edit_post_link($order->get_id())) . '">#' . $order->get_order_number() . '</a></td>
                            <td>' . esc_html($order->get_date_created()->date('Y-m-d H:i')) . '</td>
                            <td>' . esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . '</td>
                            <td>' . esc_html($order->get_meta('_ds_customer_ip')) . '</td>
                            <td>' . wc_get_order_status_name($order->get_status()) . '</td>
                        </tr>';
                    }
                    
                    echo '</tbody></table>';
                } else {
                    echo '<div style="color:red;">' . __('No results found for this IP address.', 'licenceland') . '</div>';
                }
            }
            ?>
        </div>
        <?php
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        if (!get_option('licenceland_cd_keys_enabled')) {
            update_option('licenceland_cd_keys_enabled', 'yes');
        }
        
        if (!get_option('licenceland_dual_shop_enabled')) {
            update_option('licenceland_dual_shop_enabled', 'yes');
        }
        
        if (!get_option('licenceland_default_shop_type')) {
            update_option('licenceland_default_shop_type', 'lakossagi');
        }
        
        if (!get_option('licenceland_cd_keys_default_threshold')) {
            update_option('licenceland_cd_keys_default_threshold', 5);
        }
        
        if (!get_option('licenceland_cd_keys_auto_assign_default')) {
            update_option('licenceland_cd_keys_auto_assign_default', 'yes');
        }
    }
}