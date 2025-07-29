<?php
/**
 * Plugin Update Checker Integration for LicenceLand
 * 
 * @package LicenceLand
 * @since 1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Include the Plugin Update Checker library
require_once LICENCELAND_PLUGIN_DIR . 'includes/plugin-update-checker/load-v5p6.php';

use YahnisElsts\PluginUpdateChecker\v5p6\PucFactory;

class LicenceLand_Updater {
    
    private $update_checker;
    
    public function init() {
        // Initialize the Plugin Update Checker
        $this->update_checker = PucFactory::buildUpdateChecker(
            'https://github.com/whaitey/licenceland', // GitHub repository URL
            LICENCELAND_PLUGIN_FILE, // Plugin file path
            'licenceland' // Plugin slug
        );
        
        // Set the branch that contains the stable release
        $this->update_checker->setBranch('main');
        
        // Optional: Add custom filters for the update info
        add_filter('puc_pre_inject_update-' . $this->update_checker->getUniqueName('puc'), [$this, 'inject_update_info'], 10, 2);
    }
    
    /**
     * Inject custom update information
     */
    public function inject_update_info($update, $api) {
        if ($update !== null) {
            // Add custom information to the update object
            $update->tested = '6.4';
            $update->requires = '5.0';
            $update->requires_php = '7.4';
            $update->last_updated = current_time('mysql');
            
            // Add custom sections if available
            if (isset($update->sections)) {
                $update->sections['installation'] = 'Upload the plugin files to the `/wp-content/plugins/licenceland` directory, or install the plugin through the WordPress plugins screen directly. Then activate the plugin through the \'Plugins\' screen in WordPress.';
                $update->sections['support'] = 'For support, please visit the GitHub repository or contact ZeusWeb.';
            }
        }
        
        return $update;
    }
    
    /**
     * Get the update checker instance (for external access if needed)
     */
    public function get_update_checker() {
        return $this->update_checker;
    }
}