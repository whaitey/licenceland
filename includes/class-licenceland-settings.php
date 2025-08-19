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
            __('Abandoned Cart Settings', 'licenceland'),
            __('Abandoned Cart', 'licenceland'),
            'manage_options',
            'licenceland-abandoned-cart-settings',
            [$this, 'abandoned_cart_settings_page']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Sync Settings', 'licenceland'),
            __('Sync', 'licenceland'),
            'manage_options',
            'licenceland-sync-settings',
            [$this, 'sync_settings_page']
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
        register_setting(self::OPTION_GROUP, 'licenceland_payment_based_orders');
        register_setting(self::OPTION_GROUP, 'licenceland_abandoned_cart_enabled');
        register_setting(self::OPTION_GROUP, 'licenceland_abandoned_cart_reminder_delay');
        register_setting(self::OPTION_GROUP, 'licenceland_abandoned_cart_max_reminders');
        register_setting(self::OPTION_GROUP, 'licenceland_abandoned_cart_email_subject');
        register_setting(self::OPTION_GROUP, 'licenceland_abandoned_cart_email_template');
        
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

        // Sync
        register_setting(self::OPTION_GROUP, 'll_sync_mode');
        register_setting(self::OPTION_GROUP, 'll_sync_site_id');
        register_setting(self::OPTION_GROUP, 'll_sync_remote_url');
        register_setting(self::OPTION_GROUP, 'll_sync_shared_secret');
        register_setting(self::OPTION_GROUP, 'll_sync_products');
        register_setting(self::OPTION_GROUP, 'll_sync_orders');
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
     * Abandoned Cart Settings page
     */
    public function abandoned_cart_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Abandoned Cart Settings', 'licenceland'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::OPTION_GROUP);
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="licenceland_abandoned_cart_enabled"><?php _e('Enable Abandoned Cart Reminders', 'licenceland'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="licenceland_abandoned_cart_enabled" name="licenceland_abandoned_cart_enabled" value="yes" <?php checked(get_option('licenceland_abandoned_cart_enabled', 'yes'), 'yes'); ?>>
                            <p class="description"><?php _e('Send reminder emails for abandoned carts', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="licenceland_abandoned_cart_reminder_delay"><?php _e('Reminder Delay (hours)', 'licenceland'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="licenceland_abandoned_cart_reminder_delay" name="licenceland_abandoned_cart_reminder_delay" value="<?php echo esc_attr(get_option('licenceland_abandoned_cart_reminder_delay', 24)); ?>" min="1" max="168" class="small-text">
                            <p class="description"><?php _e('How many hours to wait before sending the first reminder (1-168 hours)', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="licenceland_abandoned_cart_max_reminders"><?php _e('Maximum Reminders', 'licenceland'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="licenceland_abandoned_cart_max_reminders" name="licenceland_abandoned_cart_max_reminders" value="<?php echo esc_attr(get_option('licenceland_abandoned_cart_max_reminders', 3)); ?>" min="1" max="10" class="small-text">
                            <p class="description"><?php _e('Maximum number of reminder emails to send (1-10)', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="licenceland_abandoned_cart_email_subject"><?php _e('Email Subject', 'licenceland'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="licenceland_abandoned_cart_email_subject" name="licenceland_abandoned_cart_email_subject" value="<?php echo esc_attr(get_option('licenceland_abandoned_cart_email_subject', __('You left something in your cart!', 'licenceland'))); ?>" class="regular-text">
                            <p class="description"><?php _e('Subject line for reminder emails', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="licenceland_abandoned_cart_email_template"><?php _e('Email Template', 'licenceland'); ?></label>
                        </th>
                        <td>
                            <textarea id="licenceland_abandoned_cart_email_template" name="licenceland_abandoned_cart_email_template" rows="20" cols="80" class="large-text code"><?php echo esc_textarea(get_option('licenceland_abandoned_cart_email_template', $this->get_default_abandoned_cart_template())); ?></textarea>
                            <p class="description">
                                <?php _e('Email template. Use placeholders:', 'licenceland'); ?>
                                <br><code>{customer_name}</code> - <?php _e('Customer name', 'licenceland'); ?>
                                <br><code>{cart_items}</code> - <?php _e('Cart items table', 'licenceland'); ?>
                                <br><code>{cart_total}</code> - <?php _e('Cart total', 'licenceland'); ?>
                                <br><code>{checkout_url}</code> - <?php _e('Checkout URL', 'licenceland'); ?>
                                <br><code>{shop_name}</code> - <?php _e('Shop name', 'licenceland'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Sync settings page
     */
    public function sync_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Sync Settings', 'licenceland'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="ll_sync_mode"><?php _e('Mode', 'licenceland'); ?></label></th>
                        <td>
                            <select id="ll_sync_mode" name="ll_sync_mode">
                                <option value="primary" <?php selected(get_option('ll_sync_mode', 'primary'), 'primary'); ?>><?php _e('Primary (push products)', 'licenceland'); ?></option>
                                <option value="secondary" <?php selected(get_option('ll_sync_mode', 'primary'), 'secondary'); ?>><?php _e('Secondary', 'licenceland'); ?></option>
                            </select>
                            <p class="description"><?php _e('Primary pushes products to the remote site. Secondary primarily receives.', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ll_sync_site_id"><?php _e('This Site ID', 'licenceland'); ?></label></th>
                        <td>
                            <input type="text" id="ll_sync_site_id" name="ll_sync_site_id" value="<?php echo esc_attr(get_option('ll_sync_site_id', home_url())); ?>" class="regular-text" />
                            <p class="description"><?php _e('Identifier sent in sync requests (e.g., the site URL).', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ll_sync_remote_url"><?php _e('Remote Site URL', 'licenceland'); ?></label></th>
                        <td>
                            <input type="url" id="ll_sync_remote_url" name="ll_sync_remote_url" value="<?php echo esc_attr(get_option('ll_sync_remote_url', '')); ?>" class="regular-text" />
                            <p class="description"><?php _e('Base URL of the other site (e.g., https://example.com).', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ll_sync_shared_secret"><?php _e('Shared Secret', 'licenceland'); ?></label></th>
                        <td>
                            <input type="text" id="ll_sync_shared_secret" name="ll_sync_shared_secret" value="<?php echo esc_attr(get_option('ll_sync_shared_secret', '')); ?>" class="regular-text" />
                            <p class="description"><?php _e('Used to sign sync requests (HMAC-SHA256). Keep this secret the same on both sites.', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ll_sync_products"><?php _e('Sync Products', 'licenceland'); ?></label></th>
                        <td>
                            <input type="checkbox" id="ll_sync_products" name="ll_sync_products" value="yes" <?php checked(get_option('ll_sync_products', 'yes'), 'yes'); ?> />
                            <p class="description"><?php _e('When enabled on Primary, product create/update/delete pushes to Secondary.', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ll_sync_orders"><?php _e('Sync Orders (Secondary → Primary)', 'licenceland'); ?></label></th>
                        <td>
                            <input type="checkbox" id="ll_sync_orders" name="ll_sync_orders" value="yes" <?php checked(get_option('ll_sync_orders', 'yes'), 'yes'); ?> />
                            <p class="description"><?php _e('On Secondary site, mirror orders to Primary for CD key assignment and central visibility.', 'licenceland'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function get_default_abandoned_cart_template() {
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