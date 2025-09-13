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
            __('CD Keys Manager', 'licenceland'),
            __('CD Keys Manager', 'licenceland'),
            'manage_options',
            'licenceland-keys-manager',
            [$this, 'keys_manager_page']
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
        $yesNoKeep = function(string $name, string $def){
            return function($v) use ($name, $def){
                if ($v === null || $v === '') { return get_option($name, $def); }
                $s = is_scalar($v)?(string)$v:''; return ($s==='yes')?'yes':'no';
            };
        };
        $intKeep = function(string $name, int $def){
            return function($v) use ($name, $def){
                if ($v === null || $v === '') { return (int) get_option($name, $def); }
                return max(0, (int)$v);
            };
        };
        $textKeep = function(string $name, string $def){
            return function($v) use ($name, $def){
                if ($v === null) { return (string) get_option($name, $def); }
                return sanitize_text_field(is_scalar($v)?(string)$v:'');
            };
        };
        $htmlKeep = function(string $name, string $def){
            return function($v) use ($name, $def){
                if ($v === null) { return (string) get_option($name, $def); }
                return wp_kses_post(is_scalar($v)?(string)$v:'');
            };
        };

        register_setting(self::OPTION_GROUP, 'licenceland_cd_keys_enabled', [ 'type'=>'string', 'sanitize_callback'=>$yesNoKeep('licenceland_cd_keys_enabled','yes'), 'default'=>'yes' ]);
        register_setting(self::OPTION_GROUP, 'licenceland_dual_shop_enabled', [ 'type'=>'string', 'sanitize_callback'=>$yesNoKeep('licenceland_dual_shop_enabled','yes'), 'default'=>'yes' ]);
        register_setting(self::OPTION_GROUP, 'licenceland_default_shop_type', [ 'type'=>'string', 'sanitize_callback'=>function($v){ $s=is_scalar($v)?(string)$v:''; return in_array($s,['lakossagi','uzleti'],true)?$s:'lakossagi'; }, 'default'=>'lakossagi' ]);
        register_setting(self::OPTION_GROUP, 'licenceland_payment_based_orders', [ 'type'=>'string', 'sanitize_callback'=>$yesNoKeep('licenceland_payment_based_orders','yes'), 'default'=>'yes' ]);
        register_setting(self::OPTION_GROUP, 'licenceland_abandoned_cart_enabled', [ 'type'=>'string', 'sanitize_callback'=>$yesNoKeep('licenceland_abandoned_cart_enabled','yes'), 'default'=>'yes' ]);
        register_setting(self::OPTION_GROUP, 'licenceland_abandoned_cart_reminder_delay', [ 'type'=>'integer', 'sanitize_callback'=>$intKeep('licenceland_abandoned_cart_reminder_delay',24), 'default'=>24 ]);
        register_setting(self::OPTION_GROUP, 'licenceland_abandoned_cart_max_reminders', [ 'type'=>'integer', 'sanitize_callback'=>$intKeep('licenceland_abandoned_cart_max_reminders',3), 'default'=>3 ]);
        register_setting(self::OPTION_GROUP, 'licenceland_abandoned_cart_email_subject', [ 'type'=>'string', 'sanitize_callback'=>$textKeep('licenceland_abandoned_cart_email_subject',__('You left something in your cart!','licenceland')), 'default'=>__('You left something in your cart!','licenceland') ]);
        register_setting(self::OPTION_GROUP, 'licenceland_abandoned_cart_email_template', [ 'type'=>'string', 'sanitize_callback'=>$htmlKeep('licenceland_abandoned_cart_email_template',$this->get_default_abandoned_cart_template()), 'default'=>$this->get_default_abandoned_cart_template() ]);
        // Update checker settings
        register_setting(self::OPTION_GROUP, 'licenceland_github_token', [ 'type'=>'string', 'sanitize_callback'=>function($v){ $s = is_scalar($v)?(string)$v:''; return trim($s); } ]);
        register_setting(self::OPTION_GROUP, 'licenceland_use_release_assets', [ 'type'=>'string', 'sanitize_callback'=>$yesNoKeep('licenceland_use_release_assets','no'), 'default'=>'no' ]);
        
        // CD Keys settings
        register_setting(self::OPTION_GROUP, 'licenceland_cd_keys_default_threshold', [ 'type'=>'integer', 'sanitize_callback'=>$intKeep('licenceland_cd_keys_default_threshold',5), 'default'=>5 ]);
        register_setting(self::OPTION_GROUP, 'licenceland_cd_keys_auto_assign_default', [ 'type'=>'string', 'sanitize_callback'=>$yesNoKeep('licenceland_cd_keys_auto_assign_default','yes'), 'default'=>'yes' ]);
        
        // Dual Shop settings (register under both the main group and a dedicated group to avoid cross-form resets)
        $dual_shop_group = 'licenceland_dual_shop';
        foreach ([self::OPTION_GROUP, $dual_shop_group] as $grp) {
            register_setting($grp, 'ds_lak_header_id', [ 'type'=>'integer', 'sanitize_callback'=>function($v){ return absint($v); }, 'default'=>0 ]);
            register_setting($grp, 'ds_uzl_header_id', [ 'type'=>'integer', 'sanitize_callback'=>function($v){ return absint($v); }, 'default'=>0 ]);
            register_setting($grp, 'ds_lak_footer_id', [ 'type'=>'integer', 'sanitize_callback'=>function($v){ return absint($v); }, 'default'=>0 ]);
            register_setting($grp, 'ds_uzl_footer_id', [ 'type'=>'integer', 'sanitize_callback'=>function($v){ return absint($v); }, 'default'=>0 ]);
            register_setting($grp, 'ds_lak_product_id', [ 'type'=>'integer', 'sanitize_callback'=>function($v){ return absint($v); }, 'default'=>0 ]);
            register_setting($grp, 'ds_uzl_product_id', [ 'type'=>'integer', 'sanitize_callback'=>function($v){ return absint($v); }, 'default'=>0 ]);
        }
        
        $sanitize_payments = function($value){
            // Allow clearing to empty array (when hidden field posts empty) and filter empties
            $arr = (array) $value;
            $arr = array_map('sanitize_text_field', $arr);
            $arr = array_values(array_filter($arr, function($v){ return $v !== '' && $v !== null; }));
            return $arr;
        };
        register_setting(self::OPTION_GROUP, 'ds_lak_payments', [ 'type' => 'array', 'sanitize_callback' => $sanitize_payments ]);
        register_setting($dual_shop_group, 'ds_lak_payments', [ 'type' => 'array', 'sanitize_callback' => $sanitize_payments ]);
        
        register_setting(self::OPTION_GROUP, 'ds_uzl_payments', [ 'type' => 'array', 'sanitize_callback' => $sanitize_payments ]);
        register_setting($dual_shop_group, 'ds_uzl_payments', [ 'type' => 'array', 'sanitize_callback' => $sanitize_payments ]);
        
        register_setting(self::OPTION_GROUP, 'ds_banned_ips', [ 'type' => 'string', 'sanitize_callback' => function($value) { $str = is_scalar($value) ? (string)$value : ''; return preg_replace('/[^\d\.\:\n\r ]/', '', $str); } ]);
        register_setting($dual_shop_group, 'ds_banned_ips', [ 'type' => 'string', 'sanitize_callback' => function($value) { $str = is_scalar($value) ? (string)$value : ''; return preg_replace('/[^\d\.\:\n\r ]/', '', $str); } ]);
        
        register_setting(self::OPTION_GROUP, 'ds_banned_emails', [ 'type' => 'string', 'sanitize_callback' => function($value) { $str = is_scalar($value) ? (string)$value : ''; return preg_replace('/[^\w\.@\-\+\_\n\r ]/', '', $str); } ]);
        register_setting($dual_shop_group, 'ds_banned_emails', [ 'type' => 'string', 'sanitize_callback' => function($value) { $str = is_scalar($value) ? (string)$value : ''; return preg_replace('/[^\w\.@\-\+\_\n\r ]/', '', $str); } ]);

        // Sync
        register_setting(self::OPTION_GROUP, 'll_sync_mode', [
            'type' => 'string',
            'sanitize_callback' => function($v){ $s = is_scalar($v)?(string)$v:''; return in_array($s, ['primary','secondary'], true)?$s:'primary'; }
        ]);
        register_setting(self::OPTION_GROUP, 'll_sync_site_id', [
            'type' => 'string',
            'sanitize_callback' => function($v){
                if ($v === null || $v === '') {
                    return get_option('ll_sync_site_id', home_url());
                }
                return sanitize_text_field(is_scalar($v)?(string)$v:'');
            }
        ]);
        register_setting(self::OPTION_GROUP, 'll_sync_remote_url', [
            'type' => 'string',
            'sanitize_callback' => function($v){
                if ($v === null || $v === '') {
                    return get_option('ll_sync_remote_url', '');
                }
                return esc_url_raw(is_scalar($v)?(string)$v:'');
            }
        ]);
        register_setting(self::OPTION_GROUP, 'll_sync_shared_secret', [
            'type' => 'string',
            'sanitize_callback' => function($v){
                if ($v === null || $v === '') {
                    return get_option('ll_sync_shared_secret', '');
                }
                return sanitize_text_field(is_scalar($v)?(string)$v:'');
            }
        ]);
        $yesNoKeep = function(string $name, string $def){
            return function($v) use ($name, $def){
                if ($v === null || $v === '') {
                    // Keep previous value if field not present in submission
                    return get_option($name, $def);
                }
                $s = is_scalar($v) ? (string)$v : '';
                return ($s === 'yes') ? 'yes' : 'no';
            };
        };
        // Deprecated toggles removed in favor of role-based behavior

        // Primary: multiple remotes URL list (one per line)
        register_setting(self::OPTION_GROUP, 'll_sync_remote_urls', [
            'type' => 'array',
            'sanitize_callback' => function($v){
                $raw = is_scalar($v)?(string)$v:'';
                // Support textarea POST coming as string
                if (is_string($raw)) {
                    $lines = preg_split('/[\r\n]+/', $raw) ?: [];
                } else if (is_array($v)) {
                    $lines = $v;
                } else {
                    $lines = [];
                }
                $urls = array_values(array_unique(array_filter(array_map(function($u){ return esc_url_raw(trim((string)$u)); }, $lines))));
                return $urls;
            },
            'default' => []
        ]);
    }

    public function keys_manager_page() {
        if (!current_user_can('manage_options')) { return; }
        $skus = isset($_POST['skus']) ? sanitize_text_field((string) $_POST['skus']) : '';
        $product_ids = isset($_POST['product_ids']) ? array_map('absint', (array) $_POST['product_ids']) : [];
        $keys = isset($_POST['keys']) ? (string) $_POST['keys'] : '';
        $mode = isset($_POST['mode']) ? sanitize_text_field((string) $_POST['mode']) : 'append';
        $paged = isset($_GET['ll_paged']) ? max(1, (int) $_GET['ll_paged']) : 1;
        $per_page = 20;
        $search = isset($_GET['ll_search']) ? sanitize_text_field((string) $_GET['ll_search']) : '';
        $ran = false;
        $result = '';
        if (!empty($_POST['ll_keys_nonce']) && wp_verify_nonce($_POST['ll_keys_nonce'], 'll_keys_manage')) {
            $ran = true;
            $isPrimary = function_exists('licenceland') && licenceland()->sync && method_exists(licenceland()->sync, 'is_primary_site') ? licenceland()->sync->is_primary_site() : false;
            $idsToUpdate = $product_ids;
            if ($skus !== '') {
                $skuListFromText = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,\s]+/', $skus)))));
                foreach ($skuListFromText as $skuVal) {
                    $pidBySku = wc_get_product_id_by_sku($skuVal);
                    if ($pidBySku) { $idsToUpdate[] = (int) $pidBySku; }
                }
            }
            $idsToUpdate = array_values(array_unique(array_filter(array_map('absint', $idsToUpdate))));
            if (!empty($idsToUpdate) && $isPrimary) {
                $keysList = array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n]+/', $keys)))));
                $totalAffected = 0;
                foreach ($idsToUpdate as $pid) {
                    if ($mode === 'replace') {
                        update_post_meta($pid, '_cd_keys', $keysList);
                    } else {
                        $existing = get_post_meta($pid, '_cd_keys', true);
                        if (is_string($existing)) { $existing = preg_split('/[\r\n]+/', $existing) ?: []; }
                        $existing = is_array($existing) ? $existing : [];
                        $merged = array_values(array_unique(array_merge($existing, $keysList)));
                        update_post_meta($pid, '_cd_keys', $merged);
                    }
                    $totalAffected++;
                }
                $result = sprintf(__('Updated %d products on Primary. Keys now set/merged.', 'licenceland'), $totalAffected);
            } elseif (!empty($idsToUpdate) && function_exists('licenceland') && licenceland()->sync) {
                $path = ($mode === 'replace') ? '/wp-json/licenceland/v1/sync/keys/replace' : '/wp-json/licenceland/v1/sync/keys/append';
                $okCount = 0; $failCount = 0; $lastErr = '';
                foreach ($idsToUpdate as $pid) {
                    $skuVal = get_post_meta($pid, '_sku', true);
                    if ($skuVal === '') { $failCount++; continue; }
                    $payload = json_encode(['sku' => (string)$skuVal, 'keys' => preg_split('/[\r\n]+/', (string)$keys)]);
                    $res = licenceland()->sync->send_to_remote_public('POST', $path, (string)$payload);
                    if (!empty($res['ok'])) { $okCount++; } else { $failCount++; $lastErr = (string)($res['error'] ?? 'unknown'); }
                }
                $result = sprintf(__('Pushed updates for %d products to Primary. Failures: %d %s', 'licenceland'), $okCount, $failCount, $lastErr ? '(' . $lastErr . ')' : '');
            }
        }

        // Query products for listing
        $args = [
            'post_type' => 'product',
            'post_status' => ['publish','draft','pending','private'],
            'fields' => 'ids',
            'posts_per_page' => $per_page,
            'paged' => $paged,
        ];
        if ($search !== '') {
            $args['s'] = $search;
            $args['meta_query'] = [
                'relation' => 'OR',
                [ 'key' => '_sku', 'value' => $search, 'compare' => 'LIKE' ],
            ];
        }
        $q = new WP_Query($args);
        $ids = $q->posts ?: [];
        $max_pages = (int) $q->max_num_pages;
        $base_url = remove_query_arg(['ll_paged'], $_SERVER['REQUEST_URI']);
        $next_url = add_query_arg(['ll_paged' => $paged + 1, 'll_search' => $search], $base_url);
        $prev_url = add_query_arg(['ll_paged' => max(1, $paged - 1), 'll_search' => $search], $base_url);
        ?>
        <div class="wrap">
            <h1><?php _e('CD Keys Manager', 'licenceland'); ?></h1>
            <?php if ($ran) : ?>
                <div class="notice notice-success"><p><?php echo esc_html($result); ?></p></div>
            <?php endif; ?>
            <form method="get" style="margin-bottom:12px;">
                <input type="hidden" name="page" value="licenceland-keys-manager" />
                <input type="search" name="ll_search" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search by title or SKU', 'licenceland'); ?>" />
                <button class="button"><?php _e('Filter', 'licenceland'); ?></button>
            </form>
            <form method="post">
                <?php wp_nonce_field('ll_keys_manage', 'll_keys_nonce'); ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width:28px;"><input type="checkbox" id="ll-select-all" /></th>
                            <th><?php _e('Product', 'licenceland'); ?></th>
                            <th><?php _e('SKU', 'licenceland'); ?></th>
                            <th><?php _e('Keys', 'licenceland'); ?></th>
                            <th><?php _e('ID', 'licenceland'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($ids)) : ?>
                        <tr><td colspan="4"><?php _e('No products found.', 'licenceland'); ?></td></tr>
                    <?php else : foreach ($ids as $pid) : $skuVal = get_post_meta($pid, '_sku', true); $keysMeta = get_post_meta($pid, '_cd_keys', true); if (is_string($keysMeta)) { $keysMeta = array_filter(array_map('trim', preg_split('/[\r\n]+/',$keysMeta))); } $keysArr = is_array($keysMeta)?array_values($keysMeta):[]; $keysCount = count($keysArr); $keysPreview = $keysCount>0?implode(', ', array_slice($keysArr,0,3)):''; ?>
                        <tr>
                            <td><input type="checkbox" name="product_ids[]" value="<?php echo esc_attr($pid); ?>" /></td>
                            <td><a href="<?php echo esc_url(get_edit_post_link($pid)); ?>" target="_blank"><?php echo esc_html(get_the_title($pid)); ?></a></td>
                            <td><code><?php echo esc_html($skuVal ?: '-'); ?></code></td>
                            <td>
                                <span><?php echo (int)$keysCount; ?> <?php _e('keys', 'licenceland'); ?></span>
                                <?php if ($keysCount>0): ?>
                                    <a href="#" class="ll-view-keys" data-keys="<?php echo esc_attr(implode("\n", $keysArr)); ?>" style="margin-left:8px;"><?php _e('View', 'licenceland'); ?></a>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) $pid; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <p style="margin-top:8px;">
                    <?php if ($paged > 1) : ?><a class="button" href="<?php echo esc_url($prev_url); ?>">&laquo; <?php _e('Prev', 'licenceland'); ?></a><?php endif; ?>
                    <?php if ($paged < $max_pages) : ?><a class="button" href="<?php echo esc_url($next_url); ?>"><?php _e('Next', 'licenceland'); ?> &raquo;</a><?php endif; ?>
                    <span style="margin-left:10px; color:#666;"><?php printf(__('Page %d of %d', 'licenceland'), $paged, max(1, $max_pages)); ?></span>
                </p>
                <table class="form-table">
                    <tr>
                        <th><label for="skus"><?php _e('Product SKUs (optional)', 'licenceland'); ?></label></th>
                        <td><input type="text" id="skus" name="skus" class="regular-text" placeholder="<?php esc_attr_e('You can also select products above', 'licenceland'); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="keys"><?php _e('CD Keys (one per line)', 'licenceland'); ?></label></th>
                        <td><textarea id="keys" name="keys" rows="8" class="large-text code"></textarea></td>
                    </tr>
                    <tr>
                        <th><?php _e('Mode', 'licenceland'); ?></th>
                        <td>
                            <label><input type="radio" name="mode" value="append" checked> <?php _e('Append', 'licenceland'); ?></label>
                            &nbsp;&nbsp;
                            <label><input type="radio" name="mode" value="replace"> <?php _e('Replace', 'licenceland'); ?></label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Apply', 'licenceland')); ?>
            </form>
        </div>
        <script>
        jQuery(function($){
            $('#ll-select-all').on('change', function(){
                $('input[name="product_ids[]"]').prop('checked', $(this).is(':checked'));
            });
            // Prefill keys textarea when exactly 1 product is selected
            var nonceGet = '<?php echo wp_create_nonce('licenceland_nonce'); ?>';
            function loadKeysForSelected(){
                var checked = $('input[name="product_ids[]"]:checked');
                if (checked.length === 1) {
                    var pid = checked.val();
                    $.post(ajaxurl, { action: 'licenceland_get_cd_keys', nonce: nonceGet, product_id: pid })
                        .done(function(resp){ if (resp && resp.success && Array.isArray(resp.data)) { $('#keys').val(resp.data.join('\n')); } });
                }
            }
            $(document).on('change', 'input[name="product_ids[]"]', loadKeysForSelected);
            // Inline view of keys from table link
            $(document).on('click', '.ll-view-keys', function(e){
                e.preventDefault();
                var keys = $(this).data('keys') || '';
                var pre = $('#ll-keys-preview');
                if (!pre.length) { pre = $('<pre id="ll-keys-preview" style="background:#f7f7f7; padding:8px; white-space:pre-wrap; border:1px solid #ddd; margin-top:10px;"></pre>').insertAfter('table.widefat.striped'); }
                pre.text(keys);
            });
        });
        </script>
        <?php
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
                    <tr>
                        <th scope="row">
                            <label for="licenceland_github_token"><?php _e('GitHub Token (for updates)', 'licenceland'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="licenceland_github_token" name="licenceland_github_token" value="<?php echo esc_attr(get_option('licenceland_github_token', '')); ?>" class="regular-text" />
                            <p class="description"><?php _e('Optional. Increases GitHub API limits for the built-in update checker. Fine-grained: grant read access to this repo.', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="licenceland_use_release_assets"><?php _e('Use GitHub Release Assets', 'licenceland'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="licenceland_use_release_assets" name="licenceland_use_release_assets" value="yes" <?php checked(get_option('licenceland_use_release_assets', 'no'), 'yes'); ?>>
                            <p class="description"><?php _e('Enable if you publish release ZIPs on GitHub. Leave off if using branch/tag downloads.', 'licenceland'); ?></p>
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
        $remote_url = get_option('ll_sync_remote_url', '');
        $shared_secret = get_option('ll_sync_shared_secret', '');
        ?>
        
        <div class="wrap">
            <h1><?php _e('Dual Shop Settings', 'licenceland'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('licenceland_dual_shop'); ?>
                
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
                    <input type="hidden" name="ds_lak_payments[]" value="" />
                    <input type="hidden" name="ds_uzl_payments[]" value="" />
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
                                <option value="primary" <?php selected(get_option('ll_sync_mode', 'primary'), 'primary'); ?>><?php _e('Primary (controller)', 'licenceland'); ?></option>
                                <option value="secondary" <?php selected(get_option('ll_sync_mode', 'primary'), 'secondary'); ?>><?php _e('Secondary (receiver)', 'licenceland'); ?></option>
                            </select>
                            <p class="description"><?php _e('Primary pushes products to all configured remotes. Secondary mirrors orders to Primary. CD keys are mirrored automatically.', 'licenceland'); ?></p>
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
                        <th><label for="ll_sync_remote_url"><?php _e('Primary/Single Remote URL', 'licenceland'); ?></label></th>
                        <td>
                            <input type="url" id="ll_sync_remote_url" name="ll_sync_remote_url" value="<?php echo esc_attr(get_option('ll_sync_remote_url', '')); ?>" class="regular-text" />
                            <p class="description"><?php _e('For Secondary: set Primary URL. For single-remote Primary, you can use this as a fallback.', 'licenceland'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ll_sync_shared_secret"><?php _e('Shared Secret', 'licenceland'); ?></label></th>
                        <td>
                            <input type="text" id="ll_sync_shared_secret" name="ll_sync_shared_secret" value="<?php echo esc_attr(get_option('ll_sync_shared_secret', '')); ?>" class="regular-text" />
                            <p class="description"><?php _e('Used to sign sync requests (HMAC-SHA256). For multi-remote, put secrets in the JSON list below.', 'licenceland'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>
            <h2><?php _e('Remote Payments Control (Push to Secondary)', 'licenceland'); ?></h2>
            <p><?php _e('Choose which payment methods should be enabled on the remote site and push the configuration.', 'licenceland'); ?></p>

            <?php
            $all_gateways = WC()->payment_gateways()->payment_gateways();
            $lak_pay = get_option('ds_lak_payments', []);
            $uzl_pay = get_option('ds_uzl_payments', []);
            ?>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e('Payment Method', 'licenceland'); ?></th>
                    <th><?php esc_html_e('Consumer (Remote)', 'licenceland'); ?></th>
                    <th><?php esc_html_e('Business (Remote)', 'licenceland'); ?></th>
                </tr>
                <?php foreach ($all_gateways as $id => $gw) : ?>
                    <tr>
                        <th><?php echo esc_html($gw->get_title()); ?> <code><?php echo esc_html($id); ?></code></th>
                        <td><input type="checkbox" class="ll-remote-lak" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, (array)$lak_pay, true)); ?>></td>
                        <td><input type="checkbox" class="ll-remote-uzl" value="<?php echo esc_attr($id); ?>" <?php checked(in_array($id, (array)$uzl_pay, true)); ?>></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p>
                <button type="button" class="button button-primary" id="ll-push-remote-payments"><?php _e('Push to Remote', 'licenceland'); ?></button>
                <span id="ll-push-remote-payments-result" style="margin-left:10px;"></span>
            </p>

            <?php if (function_exists('licenceland') && licenceland()->sync && licenceland()->sync->is_primary_site()) : ?>
            <hr>
            <h2><?php _e('Primary: Push All Products to Remotes', 'licenceland'); ?></h2>
            <p><?php _e('Batch-pushes the full product catalog (title, content, pricing, taxonomies, images, CD keys config) to all configured Secondary sites.', 'licenceland'); ?></p>
            <p>
                <button type="button" class="button" id="ll-push-all-products"><?php _e('Push All Products', 'licenceland'); ?></button>
                <span id="ll-push-all-products-result" style="margin-left:10px;"></span>
            </p>
            <?php endif; ?>

            <hr>
            <h2><?php _e('Primary: Secondary Remotes (URLs)', 'licenceland'); ?></h2>
            <p><?php _e('On the Primary site, list all Secondary site URLs below (one per line). All remotes use the Shared Secret above.', 'licenceland'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields(self::OPTION_GROUP); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="ll_sync_remote_urls"><?php _e('Remote URLs', 'licenceland'); ?></label></th>
                        <td>
                            <textarea id="ll_sync_remote_urls" name="ll_sync_remote_urls" rows="6" class="large-text code"><?php echo esc_textarea(implode("\n", (array) get_option('ll_sync_remote_urls', []))); ?></textarea>
                            <p class="description"><?php _e('Example: https://site-b.example', 'licenceland'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save Remotes', 'licenceland')); ?>
            </form>
        </div>

        <script>
        jQuery(function($){
            $('#ll-push-remote-payments').on('click', function(){
                var lak = [], uzl = [];
                $('.ll-remote-lak:checked').each(function(){ lak.push($(this).val()); });
                $('.ll-remote-uzl:checked').each(function(){ uzl.push($(this).val()); });
                var result = $('#ll-push-remote-payments-result');
                result.text('<?php echo esc_js(__('Pushing...', 'licenceland')); ?>').removeClass('ok err');
                $.post(ajaxurl, {
                    action: 'licenceland_push_remote_payments',
                    nonce: '<?php echo wp_create_nonce('licenceland_sync'); ?>',
                    lak: lak,
                    uzl: uzl
                }).done(function(resp){
                    if (resp && resp.success) { result.text('<?php echo esc_js(__('Pushed successfully.', 'licenceland')); ?>').addClass('ok'); }
                    else { result.text((resp && resp.data) ? resp.data : '<?php echo esc_js(__('Push failed.', 'licenceland')); ?>').addClass('err'); }
                }).fail(function(){ result.text('<?php echo esc_js(__('Push failed.', 'licenceland')); ?>').addClass('err'); });
            });

            $('#ll-push-all-products').on('click', function(){
                var btn = $(this), res = $('#ll-push-all-products-result');
                var page = 1, total = 0;
                res.text('<?php echo esc_js(__('Starting...', 'licenceland')); ?>').removeClass('ok err');
                btn.prop('disabled', true);
                function step(){
                    $.post(ajaxurl, {
                        action: 'licenceland_push_all_products',
                        nonce: '<?php echo wp_create_nonce('licenceland_sync'); ?>',
                        page: page,
                        per_page: 50
                    }).done(function(resp){
                        if (resp && resp.success) {
                            total += (resp.data && resp.data.pushed) ? resp.data.pushed : 0;
                            if (resp.data && resp.data.has_more) {
                                page += 1;
                                res.text('<?php echo esc_js(__('Pushed page', 'licenceland')); ?> ' + (page-1) + ' / ' + resp.data.total_pages + ' ...');
                                step();
                            } else {
                                res.text('<?php echo esc_js(__('All products pushed. Total:', 'licenceland')); ?> ' + total).addClass('ok');
                                btn.prop('disabled', false);
                            }
                        } else {
                            res.text((resp && resp.data) ? resp.data : '<?php echo esc_js(__('Push failed.', 'licenceland')); ?>').addClass('err');
                            btn.prop('disabled', false);
                        }
                    }).fail(function(){
                        res.text('<?php echo esc_js(__('Push failed.', 'licenceland')); ?>').addClass('err');
                        btn.prop('disabled', false);
                    });
                }
                step();
            });
        });
        </script>
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