<?php
/**
 * Cross-site Sync for LicenceLand
 *
 * @package LicenceLand
 * @since 1.1.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class LicenceLand_Sync {

    private static $isSyncRequest = false;

    public function init() {
        add_action('rest_api_init', [$this, 'register_routes']);

        // Product change hooks disabled: pushing is now manual via Sync UI

        // Order push (Secondary -> Primary)
        add_action('woocommerce_checkout_order_processed', [$this, 'maybe_push_order'], 20, 1);
        add_action('woocommerce_new_order', [$this, 'maybe_push_order'], 20, 1);

        // Push CD key changes immediately (Primary only)
        add_action('updated_post_meta', [$this, 'maybe_push_keys_on_change'], 10, 4);

        // Admin: add origin column to orders list
        add_filter('manage_edit-shop_order_columns', [$this, 'add_origin_column'], 30);
        add_action('manage_shop_order_posts_custom_column', [$this, 'render_origin_column'], 20, 2);
        add_filter('woocommerce_shop_order_list_table_columns', [$this, 'add_origin_column'], 30);
        add_action('woocommerce_shop_order_list_table_custom_column', [$this, 'render_origin_column'], 20, 2);
    }

    public function activate() {
        // Defaults
        if (!get_option('ll_sync_mode')) {
            update_option('ll_sync_mode', 'primary');
        }
        if (!get_option('ll_sync_products')) {
            update_option('ll_sync_products', 'yes');
        }
    }

    // REST API
    public function register_routes() {
        register_rest_route('licenceland/v1', '/sync/product', [
            'methods' => 'POST',
            'callback' => [$this, 'route_sync_product'],
            'permission_callback' => [$this, 'verify_hmac_request'],
        ]);

        register_rest_route('licenceland/v1', '/sync/product/(?P<sku>[^/]+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'route_delete_product'],
            'permission_callback' => [$this, 'verify_hmac_request'],
        ]);

        register_rest_route('licenceland/v1', '/sync/order', [
            'methods' => 'POST',
            'callback' => [$this, 'route_sync_order'],
            'permission_callback' => [$this, 'verify_hmac_request'],
        ]);

        register_rest_route('licenceland/v1', '/sync/order/resend', [
            'methods' => 'POST',
            'callback' => [$this, 'route_resend_remote_email'],
            'permission_callback' => [$this, 'verify_hmac_request'],
        ]);

        register_rest_route('licenceland/v1', '/sync/settings/payments', [
            'methods' => 'POST',
            'callback' => [$this, 'route_update_remote_payments'],
            'permission_callback' => [$this, 'verify_hmac_request'],
        ]);

        // CD Keys management (Primary): append/replace via SKU
        register_rest_route('licenceland/v1', '/sync/keys/append', [
            'methods' => 'POST',
            'callback' => [$this, 'route_keys_append'],
            'permission_callback' => [$this, 'verify_hmac_request'],
        ]);
        register_rest_route('licenceland/v1', '/sync/keys/replace', [
            'methods' => 'POST',
            'callback' => [$this, 'route_keys_replace'],
            'permission_callback' => [$this, 'verify_hmac_request'],
        ]);
    }

    private function get_setting(string $key, $default = '') {
        // Constants override options for zero-UI operation
        $map = [
            'll_sync_mode' => 'LICENCELAND_SYNC_MODE',
            'll_sync_site_id' => 'LICENCELAND_SYNC_SITE_ID',
            'll_sync_remote_url' => 'LICENCELAND_SYNC_REMOTE_URL',
            'll_sync_shared_secret' => 'LICENCELAND_SYNC_SECRET',
            'll_sync_products' => 'LICENCELAND_SYNC_PRODUCTS',
            'll_sync_orders' => 'LICENCELAND_SYNC_ORDERS',
            'll_sync_cd_keys' => 'LICENCELAND_SYNC_CD_KEYS',
        ];
        if (isset($map[$key]) && defined($map[$key])) {
            $val = constant($map[$key]);
            if ($key === 'll_sync_products' || $key === 'll_sync_orders' || $key === 'll_sync_cd_keys') {
                $s = is_string($val) ? strtolower($val) : (is_bool($val) ? ($val ? 'yes' : 'no') : '');
                return ($s === 'yes' || $s === 'true' || $s === '1') ? 'yes' : 'no';
            }
            return $val;
        }
        return get_option($key, $default);
    }

    private function normalize_keys_array($keys): array {
        if (is_string($keys)) {
            $keys = preg_split('/[\r\n]+/', $keys) ?: [];
        }
        $keys = array_map(function($k){ return sanitize_text_field((string)$k); }, (array)$keys);
        $keys = array_values(array_unique(array_filter(array_map('trim', $keys))));
        return $keys;
    }

    // Primary: Append keys to product by SKU
    public function route_keys_append(WP_REST_Request $request) {
        $data = json_decode($request->get_body(), true);
        if (!is_array($data)) {
            return new WP_REST_Response(['error' => 'invalid_body'], 400);
        }
        $sku = isset($data['sku']) ? (string)$data['sku'] : '';
        $keys = $this->normalize_keys_array($data['keys'] ?? []);
        if ($sku === '' || empty($keys)) {
            return new WP_REST_Response(['error' => 'missing_params'], 400);
        }
        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id) {
            return new WP_REST_Response(['error' => 'product_not_found'], 404);
        }
        $existing = get_post_meta($product_id, '_cd_keys', true);
        if (is_string($existing)) {
            $existing = preg_split('/[\r\n]+/', $existing) ?: [];
        }
        $existing = is_array($existing) ? $existing : [];
        $existing = $this->normalize_keys_array($existing);
        $merged = array_values(array_unique(array_merge($existing, $keys)));
        update_post_meta($product_id, '_cd_keys', $merged);
        // Fan-out from Primary by pushing full product payload
        $this->push_product((int)$product_id);
        return new WP_REST_Response(['ok' => true, 'added' => count($keys), 'total' => count($merged)], 200);
    }

    // Primary: Replace keys for product by SKU
    public function route_keys_replace(WP_REST_Request $request) {
        $data = json_decode($request->get_body(), true);
        if (!is_array($data)) {
            return new WP_REST_Response(['error' => 'invalid_body'], 400);
        }
        $sku = isset($data['sku']) ? (string)$data['sku'] : '';
        $keys = $this->normalize_keys_array($data['keys'] ?? []);
        if ($sku === '') {
            return new WP_REST_Response(['error' => 'missing_params'], 400);
        }
        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id) {
            return new WP_REST_Response(['error' => 'product_not_found'], 404);
        }
        update_post_meta($product_id, '_cd_keys', $keys);
        // Fan-out from Primary by pushing full product payload
        $this->push_product((int)$product_id);
        return new WP_REST_Response(['ok' => true, 'total' => count($keys)], 200);
    }

    private function is_primary(): bool {
        return $this->get_setting('ll_sync_mode', 'primary') === 'primary';
    }

    // Public wrappers for external checks/calls
    public function is_primary_site(): bool { return $this->is_primary(); }

    // Role-driven behavior: Primary pushes products; Secondary pushes orders. Keys always mirrored.
    private function is_products_enabled(): bool { return $this->is_primary(); }
    private function is_orders_enabled(): bool { return !$this->is_primary(); }

    // HMAC verification
    public function verify_hmac_request($request): bool {
        $id = $this->get_header('X-LL-Id');
        $ts = $this->get_header('X-LL-Timestamp');
        $sig = $this->get_header('X-LL-Signature');
        if (!$id || !$ts || !$sig) {
            return false;
        }
        // 5-minute skew
        if (abs(time() - (int)$ts) > 300) {
            return false;
        }
        // Find matching secret by incoming site id
        // Shared secret is global now
        $secret = (string) $this->get_setting('ll_sync_shared_secret', '');
        if ($secret === '') { return false; }
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'POST';
        $rawPath = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $route = ($request instanceof WP_REST_Request) ? (string)$request->get_route() : '';
        $body = $request instanceof WP_REST_Request ? $request->get_body() : file_get_contents('php://input');

        $candidates = array_values(array_unique(array_filter([
            $rawPath,
            '/wp-json' . $route,
            $route,
            '/index.php?rest_route=' . $route,
            '/?rest_route=' . $route,
        ])));

        foreach ($candidates as $path) {
            $payload = $method . "\n" . $path . "\n" . $ts . "\n" . (string)$body;
            $calc = base64_encode(hash_hmac('sha256', $payload, $secret, true));
            if (hash_equals($calc, $sig)) {
                self::$isSyncRequest = true;
                return true;
            }
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[LicenceLand Sync] HMAC verification failed for route ' . $route . ' rawPath ' . $rawPath);
        }
        return false;
    }

    private function get_header(string $name): string {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : '';
    }

    // Routes: product upsert
    public function route_sync_product(WP_REST_Request $request) {
        $data = json_decode($request->get_body(), true);
        if (!is_array($data)) {
            return new WP_REST_Response(['error' => 'invalid_body'], 400);
        }
        $sku = trim((string)($data['sku'] ?? ''));
        if ($sku === '') {
            return new WP_REST_Response(['error' => 'missing_sku'], 400);
        }

        $originSite = isset($data['origin_site']) ? (string)$data['origin_site'] : '';
        $originId = isset($data['origin_id']) ? (string)$data['origin_id'] : '';

        $product_id = $this->find_existing_product($sku, $originSite, $originId);
        if (!$product_id) {
            // Create
            $post_id = wp_insert_post([
                'post_title' => wp_strip_all_tags((string)($data['name'] ?? $sku)),
                'post_status' => in_array($data['status'] ?? 'publish', ['publish','draft','pending','private'], true) ? $data['status'] : 'publish',
                'post_type' => 'product',
                'post_content' => wp_kses_post((string)($data['description'] ?? '')),
                'post_excerpt' => wp_kses_post((string)($data['short_description'] ?? '')),
            ], true);
            if (is_wp_error($post_id)) {
                return new WP_REST_Response(['error' => $post_id->get_error_message()], 500);
            }
            $product_id = $post_id;
            update_post_meta($product_id, '_sku', $sku);
        } else {
            // Update basic post fields
            wp_update_post([
                'ID' => $product_id,
                'post_title' => wp_strip_all_tags((string)($data['name'] ?? '')),
                'post_status' => in_array($data['status'] ?? 'publish', ['publish','draft','pending','private'], true) ? $data['status'] : 'publish',
                'post_content' => wp_kses_post((string)($data['description'] ?? '')),
                'post_excerpt' => wp_kses_post((string)($data['short_description'] ?? '')),
            ]);
        }

        // Prices & stock
        if (isset($data['regular_price'])) {
            update_post_meta($product_id, '_regular_price', wc_format_decimal((string)$data['regular_price']));
        }
        if (isset($data['sale_price'])) {
            update_post_meta($product_id, '_sale_price', wc_format_decimal((string)$data['sale_price']));
        }
        $price = isset($data['sale_price']) && $data['sale_price'] !== '' ? (string)$data['sale_price'] : (string)($data['regular_price'] ?? '');
        if ($price !== '') {
            update_post_meta($product_id, '_price', wc_format_decimal($price));
        }
        if (isset($data['stock_quantity'])) {
            wc_update_product_stock($product_id, (int)$data['stock_quantity']);
            update_post_meta($product_id, '_manage_stock', 'yes');
            update_post_meta($product_id, '_stock_status', ((int)$data['stock_quantity'] > 0) ? 'instock' : 'outofstock');
        }

        // CD Keys related meta
        if (isset($data['cd_key_stock_threshold'])) {
            update_post_meta($product_id, '_cd_key_stock_threshold', (int)$data['cd_key_stock_threshold']);
        }
        if (isset($data['cd_key_auto_assign'])) {
            $val = is_string($data['cd_key_auto_assign']) ? strtolower((string)$data['cd_key_auto_assign']) : $data['cd_key_auto_assign'];
            $yn = ($val === 'yes' || $val === 'true' || $val === 1 || $val === '1') ? 'yes' : 'no';
            update_post_meta($product_id, '_cd_key_auto_assign', $yn);
        }
        if (isset($data['cd_email_template'])) {
            update_post_meta($product_id, '_cd_email_template', wp_kses_post((string)$data['cd_email_template']));
        }
        // Optionally sync raw CD keys (sensitive)
        if (isset($data['cd_keys']) && is_array($data['cd_keys'])) {
            $keys = array_values(array_unique(array_filter(array_map(function($k){ return sanitize_text_field((string)$k); }, $data['cd_keys']))));
            update_post_meta($product_id, '_cd_keys', $keys);
        }

        // Taxonomies: categories and tags (accept slugs)
        if (!empty($data['categories']) && is_array($data['categories'])) {
            $cats = array_filter(array_map('sanitize_title', $data['categories']));
            if (!empty($cats)) {
                wp_set_object_terms($product_id, $cats, 'product_cat');
            }
        }
        if (!empty($data['tags']) && is_array($data['tags'])) {
            $tags = array_filter(array_map('sanitize_title', $data['tags']));
            if (!empty($tags)) {
                wp_set_object_terms($product_id, $tags, 'product_tag');
            }
        }

        // Images: featured and gallery (sideload from absolute URLs)
        if (!empty($data['featured_image'])) {
            $featured_id = $this->sideload_image_by_url((string)$data['featured_image'], $product_id);
            if ($featured_id) {
                set_post_thumbnail($product_id, $featured_id);
                update_post_meta($product_id, '_ll_featured_src', esc_url_raw((string)$data['featured_image']));
            }
        }
        if (!empty($data['gallery']) && is_array($data['gallery'])) {
            $ids = [];
            foreach ($data['gallery'] as $imgUrl) {
                $aid = $this->sideload_image_by_url((string)$imgUrl, $product_id);
                if ($aid) { $ids[] = $aid; }
            }
            if (!empty($ids)) {
                update_post_meta($product_id, '_product_image_gallery', implode(',', array_map('intval', $ids)));
                update_post_meta($product_id, '_ll_gallery_srcs', array_map('esc_url_raw', $data['gallery']));
            }
        }

        // Origin markers
        if (!empty($data['origin_site'])) {
            update_post_meta($product_id, '_ll_origin_site', sanitize_text_field((string)$data['origin_site']));
        }
        if (!empty($data['origin_id'])) {
            update_post_meta($product_id, '_ll_origin_id', sanitize_text_field((string)$data['origin_id']));
        }
        if (!empty($data['sync_version'])) {
            update_post_meta($product_id, '_ll_sync_version', (int)$data['sync_version']);
        }

        // Ensure Dual Shop availability defaults exist if missing (treat as available by default)
        if (get_post_meta($product_id, '_ds_available_lakossagi', true) === '') {
            update_post_meta($product_id, '_ds_available_lakossagi', 'yes');
        }
        if (get_post_meta($product_id, '_ds_available_uzleti', true) === '') {
            update_post_meta($product_id, '_ds_available_uzleti', 'yes');
        }

        return new WP_REST_Response([
            'ok' => true,
            'product_id' => $product_id,
        ], 200);
    }

    // Routes: product delete
    public function route_delete_product(WP_REST_Request $request) {
        $sku = sanitize_text_field((string)$request['sku']);
        if ($sku === '') {
            return new WP_REST_Response(['error' => 'missing_sku'], 400);
        }
        $product_id = wc_get_product_id_by_sku($sku);
        if ($product_id) {
            wp_trash_post($product_id);
        }
        return new WP_REST_Response(['ok' => true], 200);
    }

    // Outbound push (Primary only)
    public function maybe_push_product($post_id, $post, $update) { /* disabled */ }

    public function maybe_push_product_delete($post_id) { /* disabled */ }

    // If the CD keys/config meta changes, push the product payload to propagate changes
    public function maybe_push_keys_on_change($meta_id, $object_id, $meta_key, $_meta_value) {
        if (self::$isSyncRequest) {
            return;
        }
        if ($meta_key !== '_cd_keys' && $meta_key !== '_cd_email_template' && $meta_key !== '_cd_key_stock_threshold' && $meta_key !== '_cd_key_auto_assign') {
            return;
        }
        $post = get_post($object_id);
        if ($post && $post->post_type === 'product') {
            if ($this->is_primary()) {
                // Primary fan-out
                $this->push_product((int)$object_id);
            } else {
                // Secondary -> push to Primary only
                $payload = json_encode($this->build_product_payload((int)$object_id));
                $this->send_to_remote('POST', '/wp-json/licenceland/v1/sync/product', (string)$payload);
            }
        }
    }

    // Outbound order push from Secondary to Primary
    public function maybe_push_order($order_id) {
        if (self::$isSyncRequest) {
            return;
        }
        // Only Secondary pushes orders to Primary to assign keys
        if ($this->is_primary() || !$this->is_orders_enabled()) {
            return;
        }
        $order = wc_get_order(is_numeric($order_id) ? (int)$order_id : $order_id);
        if (!$order) {
            return;
        }
        // Avoid resending
        if ($order->get_meta('_ll_pushed_to_primary')) {
            return;
        }

        $payload = [
            'origin_site' => $this->get_setting('ll_sync_site_id', home_url()),
            'order_id' => (string)$order->get_id(),
            // Assign keys only when Secondary pushes to Primary. If we're Primary pushing to Secondary, set false for visibility-only mirror.
            'assign_keys' => $this->is_primary() ? false : true,
            'shop_type' => isset($_COOKIE['ds_shop_type']) ? sanitize_text_field((string)$_COOKIE['ds_shop_type']) : 'lakossagi',
            'billing' => [
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'company' => $order->get_billing_company(),
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ],
            'shipping' => [
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'company' => $order->get_shipping_company(),
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country(),
            ],
            'line_items' => [],
        ];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) continue;
            $payload['line_items'][] = [
                'sku' => (string)$product->get_sku(),
                'quantity' => (int)$item->get_quantity(),
            ];
        }

        $res = $this->send_to_remote_public('POST', '/wp-json/licenceland/v1/sync/order', json_encode($payload));
        if (!empty($res['ok'])) {
            $order->update_meta_data('_ll_pushed_to_primary', 1);
            $order->save();
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[LicenceLand Sync] Order push failed: ' . ($res['error'] ?? 'unknown_error'));
            }
        }
    }
    private function build_product_payload(int $product_id): array {
        $product = wc_get_product($product_id);
        $keysMeta = get_post_meta($product_id, '_cd_keys', true);
        if (is_string($keysMeta)) {
            $keysMeta = array_filter(array_map('trim', explode("\n", $keysMeta)));
        }
        $cdKeysCount = is_array($keysMeta) ? count($keysMeta) : 0;

        // Terms (by slug)
        $cat_terms = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'all']);
        $cat_slugs = array_values(array_unique(array_map(function($t){ return (string)$t->slug; }, is_array($cat_terms) ? $cat_terms : [])));
        $tag_terms = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'all']);
        $tag_slugs = array_values(array_unique(array_map(function($t){ return (string)$t->slug; }, is_array($tag_terms) ? $tag_terms : [])));

        $product = wc_get_product($product_id);
        // Images for payload
        $featured_id = $product ? $product->get_image_id() : 0;
        $featured_url = $featured_id ? wp_get_attachment_url($featured_id) : '';
        $gallery_ids = $product ? $product->get_gallery_image_ids() : [];
        $gallery_urls = [];
        foreach ((array)$gallery_ids as $gid) {
            $u = wp_get_attachment_url($gid);
            if ($u) { $gallery_urls[] = $u; }
        }

        $payload = [
            'origin_site' => $this->get_setting('ll_sync_site_id', home_url()),
            'origin_id' => (string)$product_id,
            'sync_version' => (int) (get_post_modified_time('U', true, $product_id) ?? time()),
            'sku' => (string)$product->get_sku(),
            'name' => (string)$product->get_name(),
            'status' => get_post_status($product_id),
            'description' => (string)get_post_field('post_content', $product_id),
            'short_description' => (string)get_post_field('post_excerpt', $product_id),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            // Expose stock as remaining CD keys if available; fallback to Woo stock
            'stock_quantity' => ($cdKeysCount > 0) ? $cdKeysCount : $product->get_stock_quantity(),
            // CD Keys config
            'cd_keys_count' => $cdKeysCount,
            'cd_key_stock_threshold' => (int) get_post_meta($product_id, '_cd_key_stock_threshold', true),
            'cd_key_auto_assign' => (string) (get_post_meta($product_id, '_cd_key_auto_assign', true) ?: 'yes'),
            'cd_email_template' => (string) get_post_meta($product_id, '_cd_email_template', true),
            // Raw keys for full mirroring
            'cd_keys' => is_array($keysMeta) ? array_values($keysMeta) : [],
            // Taxonomies
            'categories' => $cat_slugs,
            'tags' => $tag_slugs,
            // Images
            'featured_image' => $featured_url,
            'gallery' => $gallery_urls,
        ];
        return $payload;
    }

    /**
     * Try to locate an existing product by origin mapping or SKU
     */
    private function find_existing_product(string $sku, string $originSite, string $originId): int {
        // Prefer origin mapping if provided
        if ($originSite !== '' && $originId !== '') {
            $q = new WP_Query([
                'post_type' => 'product',
                'post_status' => 'any',
                'fields' => 'ids',
                'posts_per_page' => 1,
                'meta_query' => [
                    'relation' => 'AND',
                    [ 'key' => '_ll_origin_site', 'value' => $originSite, 'compare' => '=' ],
                    [ 'key' => '_ll_origin_id', 'value' => $originId, 'compare' => '=' ],
                ],
            ]);
            if (!empty($q->posts)) {
                return (int)$q->posts[0];
            }
        }
        // Fallback by SKU (exact)
        if ($sku !== '') {
            $id = wc_get_product_id_by_sku($sku);
            if ($id) { return (int)$id; }
            // Case-insensitive fallback
            $q2 = new WP_Query([
                'post_type' => 'product',
                'post_status' => 'any',
                'fields' => 'ids',
                'posts_per_page' => 1,
                'meta_query' => [
                    [ 'key' => '_sku', 'value' => $sku, 'compare' => 'LIKE' ],
                ],
            ]);
            if (!empty($q2->posts)) {
                return (int)$q2->posts[0];
            }
        }
        return 0;
    }

    /**
     * Download and attach an image by URL, return attachment ID or 0
     */
    private function sideload_image_by_url(string $url, int $post_id): int {
        if ($url === '') { return 0; }
        // Avoid re-download if previously set
        $prev = get_post_meta($post_id, '_ll_featured_src', true);
        if ($prev === $url) {
            $thumb = get_post_thumbnail_id($post_id);
            if ($thumb) { return (int)$thumb; }
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        // media_sideload_image can return ID when context is 'id'
        $attach_id = 0;
        // Some WP versions: media_sideload_image($url, $post_id, null, 'id')
        $result = @media_sideload_image($url, $post_id, null, 'id');
        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[LicenceLand Sync] Failed to sideload image: ' . $url . ' - ' . $result->get_error_message());
            }
            return 0;
        }
        $attach_id = is_numeric($result) ? (int)$result : 0;
        return $attach_id;
    }

    private function send_to_remote(string $method, string $path, string $body): void {
        $siteId = (string)$this->get_setting('ll_sync_site_id', home_url());
        $urls = (array) get_option('ll_sync_remote_urls', []);
        // Fallback to single remote option if list empty
        if (empty($urls)) {
            $singleUrl = (string) $this->get_setting('ll_sync_remote_url', '');
            if ($singleUrl) { $urls = [$singleUrl]; }
        }
        $secret = (string) $this->get_setting('ll_sync_shared_secret', '');
        foreach ($urls as $remoteUrl) {
            $remoteUrl = rtrim((string)$remoteUrl, '/');
            if ($remoteUrl === '' || $secret === '') { continue; }
            $ts = (string) time();
            $payload = strtoupper($method) . "\n" . $path . "\n" . $ts . "\n" . $body;
            $sig = base64_encode(hash_hmac('sha256', $payload, $secret, true));
            $args = [
                'timeout' => 12,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-LL-Id' => $siteId,
                    'X-LL-Timestamp' => $ts,
                    'X-LL-Signature' => $sig,
                    'X-LL-Sync' => '1',
                ],
                'body' => $body,
                'redirection' => 2,
                'blocking' => true,
            ];
            $url = $remoteUrl . $path;
            $response = ($method === 'POST') ? wp_remote_post($url, $args) : wp_remote_request($url, $args + ['method' => $method]);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (is_wp_error($response)) {
                    error_log('[LicenceLand Sync] Push ' . $method . ' ' . $url . ' failed: ' . $response->get_error_message());
                } else if ($response) {
                    error_log('[LicenceLand Sync] Push ' . $method . ' ' . $url . ' -> ' . wp_remote_retrieve_response_code($response));
                }
            }
        }
    }

    private function push_product(int $product_id): void {
        if (!$this->is_primary() || !$this->is_products_enabled()) {
            return;
        }
        $payload = json_encode($this->build_product_payload($product_id));
        $this->send_to_remote('POST', '/wp-json/licenceland/v1/sync/product', (string)$payload);
    }

    public function push_product_public(int $product_id): bool {
        $this->push_product($product_id);
        return true;
    }

    // Public wrapper returning response body/ok for AJAX proxy needs
    public function send_to_remote_public(string $method, string $path, string $body): array {
        $remote = rtrim((string)$this->get_setting('ll_sync_remote_url', ''), '/');
        $secret = (string)$this->get_setting('ll_sync_shared_secret', '');
        $siteId = (string)$this->get_setting('ll_sync_site_id', home_url());
        if ($remote === '' || $secret === '') {
            return ['ok' => false, 'error' => 'not_configured'];
        }
        $ts = (string) time();
        $payload = strtoupper($method) . "\n" . $path . "\n" . $ts . "\n" . $body;
        $sig = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        $args = [
            'timeout' => 12,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-LL-Id' => $siteId,
                'X-LL-Timestamp' => $ts,
                'X-LL-Signature' => $sig,
                'X-LL-Sync' => '1',
            ],
            'body' => $body,
            'redirection' => 2,
            'blocking' => true,
        ];
        $url = $remote . $path;
        $response = ($method === 'POST') ? wp_remote_post($url, $args) : wp_remote_request($url, $args + ['method' => $method]);
        if (is_wp_error($response)) {
            return ['ok' => false, 'error' => $response->get_error_message()];
        }
        $code = wp_remote_retrieve_response_code($response);
        $bodyStr = wp_remote_retrieve_body($response);
        $json = json_decode($bodyStr, true);
        return ($code >= 200 && $code < 300) ? (is_array($json) ? $json + ['ok' => true] : ['ok' => true]) : ['ok' => false, 'error' => $bodyStr ?: 'http_' . $code];
    }

    // Remote email resend endpoint (runs on Secondary when invoked from Primary)
    public function route_resend_remote_email(WP_REST_Request $request) {
        $data = json_decode($request->get_body(), true);
        if (!is_array($data)) {
            return new WP_REST_Response(['error' => 'invalid_body'], 400);
        }
        $remoteOrderId = (int)($data['remote_order_id'] ?? 0);
        $emailType = (string)($data['email_type'] ?? '');
        if (!$remoteOrderId || $emailType === '') {
            return new WP_REST_Response(['error' => 'missing_params'], 400);
        }
        $order = wc_get_order($remoteOrderId);
        if (!$order) {
            return new WP_REST_Response(['error' => 'order_not_found'], 404);
        }
        if (!function_exists('licenceland') || !licenceland()->order_resend) {
            return new WP_REST_Response(['error' => 'resend_unavailable'], 500);
        }
        $res = licenceland()->order_resend->api_resend_email($remoteOrderId, $emailType);
        return new WP_REST_Response($res, $res['success'] ? 200 : 500);
    }

    // Update payment gateway allowlists on this site (called from the controller site)
    public function route_update_remote_payments(WP_REST_Request $request) {
        $data = json_decode($request->get_body(), true);
        if (!is_array($data)) {
            return new WP_REST_Response(['error' => 'invalid_body'], 400);
        }
        $lak = isset($data['ds_lak_payments']) ? (array)$data['ds_lak_payments'] : [];
        $uzl = isset($data['ds_uzl_payments']) ? (array)$data['ds_uzl_payments'] : [];
        $lak = array_values(array_unique(array_map('sanitize_text_field', $lak)));
        $uzl = array_values(array_unique(array_map('sanitize_text_field', $uzl)));
        update_option('ds_lak_payments', $lak);
        update_option('ds_uzl_payments', $uzl);
        return new WP_REST_Response(['ok' => true], 200);
    }

    // Admin column: Origin
    public function add_origin_column($columns) {
        if (!isset($columns['ll_origin'])) {
            $columns['ll_origin'] = __('Origin', 'licenceland');
        }
        return $columns;
    }

    public function render_origin_column($column, $post_id) {
        if ($column !== 'll_origin') {
            return;
        }
        $origin = get_post_meta($post_id, '_ll_origin_site', true);
        echo esc_html($origin ?: __('Local', 'licenceland'));
    }

    // ----- Incoming order from Secondary -> create mirror order on Primary, assign/send CD keys -----
    public function route_sync_order(WP_REST_Request $request) {
        $data = json_decode($request->get_body(), true);
        if (!is_array($data)) {
            return new WP_REST_Response(['error' => 'invalid_body'], 400);
        }

        $billing = (array)($data['billing'] ?? []);
        $shipping = (array)($data['shipping'] ?? []);
        $items = (array)($data['line_items'] ?? []);
        $remoteOrderId = (string)($data['order_id'] ?? '');
        $originSite = (string)($data['origin_site'] ?? 'secondary');

        if (empty($items)) {
            return new WP_REST_Response(['error' => 'no_items'], 400);
        }

        try {
            $order = wc_create_order();

            // Add items by SKU
            $addedCount = 0;
            foreach ($items as $li) {
                $sku = isset($li['sku']) ? (string)$li['sku'] : '';
                $qty = isset($li['quantity']) ? max(1, (int)$li['quantity']) : 1;
                if ($sku === '') {
                    continue;
                }
                $productId = wc_get_product_id_by_sku($sku);
                if (!$productId) {
                    continue;
                }
                $order->add_product(wc_get_product($productId), $qty);
                $addedCount++;
            }

            if ($addedCount === 0) {
                return new WP_REST_Response(['error' => 'items_not_found'], 400);
            }

            // Addresses
            if (!empty($billing)) {
                $order->set_address($billing, 'billing');
            }
            if (!empty($shipping)) {
                $order->set_address($shipping, 'shipping');
            }

            $order->update_meta_data('_ll_origin_site', $originSite);
            if (!empty($data['shop_type'])) {
                $order->update_meta_data('_ll_origin_shop_type', sanitize_text_field((string)$data['shop_type']));
            }
            if ($remoteOrderId !== '') {
                $order->update_meta_data('_ll_remote_order_id', $remoteOrderId);
            }
            $order->save();

            // Assign CD keys if requested (e.g., Secondary -> Primary). Skip for visibility-only mirrors.
            $shouldAssignKeys = !empty($data['assign_keys']);
            if ($shouldAssignKeys) {
                foreach ($order->get_items() as $itemId => $item) {
                    $this->assign_cd_keys_to_item($order, $itemId, $item);
                }
            }

            // Mark processing and save
            $order->set_status('processing');
            $order->save();

            return new WP_REST_Response(['ok' => true, 'order_id' => $order->get_id()], 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    private function assign_cd_keys_to_item(WC_Order $order, $itemId, $item): void {
        $productId = $item->get_product_id();
        $qty = max(1, (int)$item->get_quantity());
        $keys = get_post_meta($productId, '_cd_keys', true);
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
        for ($i = 0; $i < $qty; $i++) {
            if (!empty($keys)) {
                $assigned[] = array_shift($keys);
            }
        }
        if (empty($assigned)) {
            return;
        }

        $cdKeyValue = (count($assigned) === 1) ? $assigned[0] : implode(', ', $assigned);
        wc_update_order_item_meta($itemId, '_cd_key', $cdKeyValue);
        update_post_meta($productId, '_cd_keys', $keys);
        $this->log_cd_key_usage($assigned, $productId, $order->get_id(), $itemId);

        // Send email with product template
        $this->send_cd_key_email($order, $item, $cdKeyValue);
    }

    private function send_cd_key_email(WC_Order $order, $item, string $cdKey): void {
        $productId = $item->get_product_id();
        $template = get_post_meta($productId, '_cd_email_template', true);
        if ($template) {
            $output = str_replace('{cd_key}', esc_html($cdKey), $template);
        } else {
            $output = sprintf(__('Your CD key: %s', 'licenceland'), esc_html($cdKey));
        }
        $to = $order->get_billing_email();
        if (!$to) {
            return;
        }
        $subject = sprintf(__('CD Key for Order #%s', 'licenceland'), $order->get_order_number());
        wp_mail($to, $subject, wpautop($output));
    }

    private function log_cd_key_usage(array $keys, int $productId, int $orderId, int $orderItemId): void {
        global $wpdb;
        $table = $wpdb->prefix . 'licenceland_cd_keys_usage';
        foreach ($keys as $key) {
            $wpdb->insert(
                $table,
                [
                    'cd_key' => $key,
                    'product_id' => $productId,
                    'order_id' => $orderId,
                    'order_item_id' => $orderItemId,
                ],
                ['%s', '%d', '%d', '%d']
            );
        }
    }
}


