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

        // Product change hooks (create/update/delete)
        add_action('save_post_product', [$this, 'maybe_push_product'], 20, 3);
        add_action('before_delete_post', [$this, 'maybe_push_product_delete'], 10, 1);

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
    }

    private function get_setting(string $key, $default = '') {
        return get_option($key, $default);
    }

    private function is_primary(): bool {
        return $this->get_setting('ll_sync_mode', 'primary') === 'primary';
    }

    private function is_products_enabled(): bool {
        return $this->get_setting('ll_sync_products', 'yes') === 'yes';
    }

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
        $secret = $this->get_setting('ll_sync_shared_secret', '');
        if ($secret === '') {
            return false;
        }
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'POST';
        $path = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $body = $request instanceof WP_REST_Request ? $request->get_body() : file_get_contents('php://input');
        $payload = $method . "\n" . $path . "\n" . $ts . "\n" . (string)$body;
        $calc = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        if (!hash_equals($calc, $sig)) {
            return false;
        }
        self::$isSyncRequest = true;
        return true;
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

        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id) {
            // Create
            $post_id = wp_insert_post([
                'post_title' => wp_strip_all_tags((string)($data['name'] ?? $sku)),
                'post_status' => in_array($data['status'] ?? 'publish', ['publish','draft','pending','private'], true) ? $data['status'] : 'publish',
                'post_type' => 'product',
                'post_content' => (string)($data['description'] ?? ''),
                'post_excerpt' => (string)($data['short_description'] ?? ''),
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
                'post_content' => (string)($data['description'] ?? ''),
                'post_excerpt' => (string)($data['short_description'] ?? ''),
            ]);
        }

        // Prices & stock
        if (isset($data['regular_price'])) {
            update_post_meta($product_id, '_regular_price', (string)$data['regular_price']);
        }
        if (isset($data['sale_price'])) {
            update_post_meta($product_id, '_sale_price', (string)$data['sale_price']);
        }
        $price = isset($data['sale_price']) && $data['sale_price'] !== '' ? (string)$data['sale_price'] : (string)($data['regular_price'] ?? '');
        if ($price !== '') {
            update_post_meta($product_id, '_price', $price);
        }
        if (isset($data['stock_quantity'])) {
            wc_update_product_stock($product_id, (int)$data['stock_quantity']);
            update_post_meta($product_id, '_manage_stock', 'yes');
            update_post_meta($product_id, '_stock_status', ((int)$data['stock_quantity'] > 0) ? 'instock' : 'outofstock');
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
    public function maybe_push_product($post_id, $post, $update) {
        if (self::$isSyncRequest) {
            return; // Prevent loops
        }
        if (!$this->is_primary() || !$this->is_products_enabled()) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if ($post->post_type !== 'product') {
            return;
        }

        $sku = get_post_meta($post_id, '_sku', true);
        if ($sku === '') {
            return; // Only sync products with SKU identity
        }

        $payload = $this->build_product_payload($post_id);
        $this->send_to_remote('POST', '/wp-json/licenceland/v1/sync/product', json_encode($payload));
    }

    public function maybe_push_product_delete($post_id) {
        if (self::$isSyncRequest) {
            return;
        }
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'product') {
            return;
        }
        if (!$this->is_primary() || !$this->is_products_enabled()) {
            return;
        }
        $sku = get_post_meta($post_id, '_sku', true);
        if ($sku === '') {
            return;
        }
        $path = '/wp-json/licenceland/v1/sync/product/' . rawurlencode($sku);
        $this->send_to_remote('DELETE', $path, '');
    }

    private function build_product_payload(int $product_id): array {
        $product = wc_get_product($product_id);
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
            'stock_quantity' => $product->get_stock_quantity(),
        ];
        return $payload;
    }

    private function send_to_remote(string $method, string $path, string $body): void {
        $remote = rtrim((string)$this->get_setting('ll_sync_remote_url', ''), '/');
        $secret = (string)$this->get_setting('ll_sync_shared_secret', '');
        $siteId = (string)$this->get_setting('ll_sync_site_id', home_url());
        if ($remote === '' || $secret === '') {
            return;
        }
        $ts = (string) time();
        $payload = strtoupper($method) . "\n" . $path . "\n" . $ts . "\n" . $body;
        $sig = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        $args = [
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-LL-Id' => $siteId,
                'X-LL-Timestamp' => $ts,
                'X-LL-Signature' => $sig,
                'X-LL-Sync' => '1',
            ],
            'body' => $body,
        ];

        $url = $remote . $path;
        if ($method === 'POST') {
            wp_remote_post($url, $args);
        } elseif ($method === 'DELETE') {
            $args['method'] = 'DELETE';
            wp_remote_request($url, $args);
        }
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
}


