<?php
/**
 * Plugin Name: LicenceLand - Unified E-commerce Solution
 * Description: Comprehensive e-commerce solution featuring CD Key management, dual shop functionality (Lakossági/Üzleti), and advanced WooCommerce integration.
 * Version: 1.0.15
 * Author: ZeusWeb
 * Text Domain: licenceland
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LICENCELAND_VERSION', '1.0.15');
define('LICENCELAND_PLUGIN_FILE', __FILE__);
define('LICENCELAND_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LICENCELAND_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LICENCELAND_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Plugin Update Checker
require_once __DIR__ . '/load-v5p6.php';
use YahnisElsts\PluginUpdateChecker\v5p6\PucFactory;

$licencelandUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/whaitey/licenceland',
    __FILE__,
    'licenceland'
);

// Configure the update checker
$licencelandUpdateChecker->setBranch('main');
$licencelandUpdateChecker->getVcsApi()->enableReleaseAssets();

// Set custom update checker options
$licencelandUpdateChecker->setCheckPeriod(6); // Check every 6 hours for faster detection

// Add custom headers for better GitHub API compatibility
$licencelandUpdateChecker->getVcsApi()->setHttpFilter(function($url, $options) {
    $options['headers']['User-Agent'] = 'LicenceLand-Plugin-Update-Checker/1.0';
    return $options;
});

// Core classes
require_once LICENCELAND_PLUGIN_DIR . 'includes/class-licenceland-core.php';
require_once LICENCELAND_PLUGIN_DIR . 'includes/class-licenceland-cd-keys.php';
require_once LICENCELAND_PLUGIN_DIR . 'includes/class-licenceland-dual-shop.php';
require_once LICENCELAND_PLUGIN_DIR . 'includes/class-licenceland-settings.php';
require_once LICENCELAND_PLUGIN_DIR . 'includes/class-licenceland-abandoned-cart.php';
require_once LICENCELAND_PLUGIN_DIR . 'includes/class-licenceland-order-resend.php';

/**
 * Main LicenceLand Plugin Class
 */
class LicenceLand {
    
    private static $instance = null;
    public $core;
    public $cd_keys;
    public $dual_shop;
    public $settings;
    public $abandoned_cart;
    public $order_resend;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
        $this->load_components();
    }
    
    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'init'], 0);
        add_action('init', [$this, 'load_textdomain']);
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    private function load_components() {
        // Initialize core components
        $this->core = new LicenceLand_Core();
        $this->cd_keys = new LicenceLand_CD_Keys();
        $this->dual_shop = new LicenceLand_Dual_Shop();
        $this->settings = new LicenceLand_Settings();
        $this->abandoned_cart = new LicenceLand_Abandoned_Cart();
        $this->order_resend = new LicenceLand_Order_Resend();
    }
    
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'woocommerce_missing_notice']);
            return;
        }
        
        // Initialize components
        $this->core->init();
        $this->cd_keys->init();
        $this->dual_shop->init();
        $this->settings->init();
        $this->abandoned_cart->init();
        $this->order_resend->init();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain(
            'licenceland',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    public function activate() {
        // Create necessary database tables and options
        $this->core->activate();
        $this->cd_keys->activate();
        $this->dual_shop->activate();
        $this->settings->activate();
        $this->abandoned_cart->activate();
        $this->order_resend->activate();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Cleanup if necessary
        flush_rewrite_rules();
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('LicenceLand requires WooCommerce to be installed and activated.', 'licenceland'); ?></p>
        </div>
        <?php
    }
    
    public function get_version() {
        return LICENCELAND_VERSION;
    }
}

// Initialize the plugin
function licenceland() {
    return LicenceLand::get_instance();
}

// Start the plugin
licenceland();