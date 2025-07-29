<?php
/**
 * Uninstall script for LicenceLand
 * 
 * This file is executed when the plugin is deleted from WordPress.
 * It removes all plugin data, options, and database tables.
 * 
 * @package LicenceLand
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// UNINSTALL CLEANUP DISABLED - All data will be preserved
// Uncomment the code below if you want to enable cleanup on plugin deletion

/*
// Remove all plugin options
$options_to_delete = [
    // General options
    'licenceland_cd_keys_enabled',
    'licenceland_dual_shop_enabled',
    'licenceland_default_shop_type',
    'licenceland_version',
    'licenceland_activated',
    'licenceland_github_token',
    'licenceland_payment_based_orders',
    
    // CD Keys options
    'licenceland_cd_keys_default_threshold',
    'licenceland_cd_keys_auto_assign_default',
    
    // Dual Shop options
    'ds_lak_header_id',
    'ds_uzl_header_id',
    'ds_lak_footer_id',
    'ds_uzl_footer_id',
    'ds_lak_product_id',
    'ds_uzl_product_id',
    'ds_lak_payments',
    'ds_uzl_payments',
    'ds_banned_ips',
    'ds_banned_emails',
];

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Remove transients
delete_transient('licenceland_github_data');

// Remove database tables
global $wpdb;

$tables_to_drop = [
    $wpdb->prefix . 'licenceland_cd_keys_usage',
    $wpdb->prefix . 'licenceland_backorders',
];

foreach ($tables_to_drop as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Remove product meta data
$meta_keys_to_delete = [
    '_cd_keys',
    '_cd_key_stock_threshold',
    '_cd_key_auto_assign',
    '_cd_email_template',
    '_ds_available_lakossagi',
    '_ds_available_uzleti',
    '_ds_business_price',
    '_backorder_enabled',
    '_backorder_notification_sent',
    '_licenceland_actual_order_id',
    '_licenceland_order_creation_method',
    '_licenceland_checkout_data_stored',
];

foreach ($meta_keys_to_delete as $meta_key) {
    $wpdb->delete($wpdb->postmeta, ['meta_key' => $meta_key]);
}

// Remove order meta data
$order_meta_keys_to_delete = [
    '_ds_shop_type',
    '_ds_customer_ip',
    '_szamlaszam',
];

foreach ($order_meta_keys_to_delete as $meta_key) {
    $wpdb->delete($wpdb->postmeta, ['meta_key' => $meta_key]);
}

// Remove order item meta data
$order_item_meta_keys_to_delete = [
    '_cd_key',
];

foreach ($order_item_meta_keys_to_delete as $meta_key) {
    $wpdb->delete($wpdb->prefix . 'woocommerce_order_itemmeta', ['meta_key' => $meta_key]);
}

// Clear any cached data
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Log uninstall for debugging
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('LicenceLand plugin uninstalled - all data removed');
}
*/

// Log that cleanup was skipped
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('LicenceLand plugin uninstalled - cleanup disabled, data preserved');
}