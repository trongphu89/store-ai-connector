<?php
/**
 * Plugin Name: Store AI Connector
 * Plugin URI:  https://github.com/YOUR_GITHUB/store-ai-connector
 * Description: REST API connector for multi-store order management and GMC setup.
 * Version:     2.0.0
 * Author:      Your Name
 * License:     GPL v2 or later
 * Text Domain: store-ai-connector
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SAC_VERSION',           '2.0.0' );
define( 'SAC_PLUGIN_DIR',        plugin_dir_path( __FILE__ ) );
define( 'SAC_API_KEY_OPTION',    'sac_api_key' );
define( 'SAC_API_SECRET_OPTION', 'sac_api_secret' );

/**
 * Include Custom Fields class
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-custom-fields.php';

/**
 * Include POD Variations class
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-variations.php';

/**
 * Include POD Variations Settings (Admin)
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-variations-settings.php';

/**
 * Include Site Variables (Shortcode Variables System)
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-site-variables.php';

/**
 * Include Content Manager (Pages & Blocks)
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-content-manager.php';

/**
 * Include SEO Output (Open Graph & Twitter Cards)
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-seo-output.php';

/**
 * Include LLMS.txt Generator (AI Crawlers)
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-llms-txt.php';

/**
 * Include GeneratePress child-theme generator support
 *
 * Filesystem state machine + Customizer migrator are loaded here so they
 * are available to the endpoints class. The endpoints class itself is
 * loaded later (after `sac_verify` is defined) because its
 * route callbacks use that verifier as their permission_callback.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-theme-filesystem.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-theme-mod-migrator.php';

// Note: GMC Compliance is loaded later after verify functions are defined

/**
 * Enqueue frontend scripts for custom fields
 */
function sac_enqueue_scripts() {
    if ( is_product() ) {
        wp_enqueue_script(
            'pod-custom-fields-js',
            plugin_dir_url( __FILE__ ) . 'assets/js/custom-fields.js',
            array( 'jquery' ),
            SAC_VERSION,
            true
        );
        
        wp_localize_script( 'pod-custom-fields-js', 'pod_custom_fields', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
        ) );
    }
}
add_action( 'wp_enqueue_scripts', 'sac_enqueue_scripts' );

/**
 * Activation hook
 */
function sac_activate() {
    $existing_key = get_option( SAC_API_KEY_OPTION );
    if ( empty( $existing_key ) ) {
        $api_key = 'pod_' . wp_generate_password( 32, false );
        $api_secret = wp_generate_password( 64, false );
        update_option( SAC_API_KEY_OPTION, $api_key );
        update_option( SAC_API_SECRET_OPTION, $api_secret );
    }
    
    // Force Classic Editor for all posts and pages
    sac_force_classic_editor();
}
register_activation_hook( __FILE__, 'sac_activate' );

/**
 * Force Classic Editor (disable Gutenberg)
 */
function sac_force_classic_editor() {
    // Set Classic Editor as default for all post types
    update_option( 'classic-editor-replace', 'classic' );
    update_option( 'classic-editor-allow-users', 'disallow' );
    
    // Disable Gutenberg for posts and pages
    add_filter( 'use_block_editor_for_post', '__return_false', 10 );
    add_filter( 'use_block_editor_for_post_type', '__return_false', 10 );
}
add_action( 'init', 'sac_force_classic_editor' );

/**
 * Admin notice to install Classic Editor plugin
 */
function sac_classic_editor_notice() {
    // Check if Classic Editor plugin is installed
    if ( ! function_exists( 'classic_editor_init_actions' ) ) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>POD AI Connector:</strong> Để sử dụng tốt nhất, vui lòng cài đặt plugin <strong>Classic Editor</strong>.</p>
            <p>
                <a href="<?php echo admin_url( 'plugin-install.php?s=classic+editor&tab=search&type=term' ); ?>" class="button button-primary">
                    Cài đặt Classic Editor
                </a>
            </p>
        </div>
        <?php
    }
}
add_action( 'admin_notices', 'sac_classic_editor_notice' );

/**
 * Add CORS headers for development
 */
function sac_add_cors_headers() {
    // Only add CORS headers for POD Connector endpoints
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( strpos( $request_uri, '/sac/v1/' ) !== false ) {
        header( 'Access-Control-Allow-Origin: *' );
        header( 'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type, X-POD-API-Key, X-POD-Signature, X-POD-Signature-NB, X-POD-Timestamp, Authorization' );
        header( 'Access-Control-Allow-Credentials: true' );
        
        // Handle preflight OPTIONS request
        if ( $_SERVER['REQUEST_METHOD'] === 'OPTIONS' ) {
            status_header( 200 );
            exit;
        }
    }
}
add_action( 'rest_api_init', 'sac_add_cors_headers', 0 );

/**
 * Register REST routes
 */
function sac_register_routes() {
    register_rest_route( 'sac/v1', '/health', array(
        'methods'             => 'GET',
        'callback'            => 'sac_health',
        'permission_callback' => '__return_true',
    ) );

    register_rest_route( 'sac/v1', '/bulk-fetch', array(
        'methods'             => 'POST',
        'callback'            => 'sac_bulk_fetch',
        'permission_callback' => 'sac_verify',
    ) );

    register_rest_route( 'sac/v1', '/bulk-upload', array(
        'methods'             => 'POST',
        'callback'            => 'sac_bulk_upload',
        'permission_callback' => 'sac_verify',
    ) );

    register_rest_route( 'sac/v1', '/bulk-products', array(
        'methods'             => 'POST',
        'callback'            => 'sac_bulk_products',
        'permission_callback' => 'sac_verify',
    ) );

    register_rest_route( 'sac/v1', '/bulk-update', array(
        'methods'             => array( 'PUT', 'POST' ),
        'callback'            => 'sac_bulk_update',
        'permission_callback' => 'sac_verify',
    ) );

    register_rest_route( 'sac/v1', '/bulk-delete', array(
        'methods'             => 'DELETE',
        'callback'            => 'sac_bulk_delete',
        'permission_callback' => 'sac_verify',
    ) );

    register_rest_route( 'sac/v1', '/bulk-posts', array(
        'methods'             => 'POST',
        'callback'            => 'sac_bulk_posts',
        'permission_callback' => 'sac_verify',
    ) );

    // Single post endpoints
    register_rest_route( 'sac/v1', '/post/(?P<id>\d+)', array(
        'methods'             => 'GET',
        'callback'            => 'sac_get_post',
        'permission_callback' => 'sac_verify_simple',
    ) );

    register_rest_route( 'sac/v1', '/update-post', array(
        'methods'             => 'POST',
        'callback'            => 'sac_update_post',
        'permission_callback' => 'sac_verify',
    ) );

    register_rest_route( 'sac/v1', '/upload-media', array(
        'methods'             => 'POST',
        'callback'            => 'sac_upload_media',
        'permission_callback' => 'sac_verify_simple',
    ) );

    // Create category endpoint
    register_rest_route( 'sac/v1', '/create-category', array(
        'methods'             => 'POST',
        'callback'            => 'sac_create_category',
        'permission_callback' => 'sac_verify',
    ) );

    // Create variable product endpoint
    register_rest_route( 'sac/v1', '/create-variable-product', array(
        'methods'             => 'POST',
        'callback'            => 'sac_create_variable_product',
        'permission_callback' => 'sac_verify',
    ) );

    // Custom Fields endpoints
    register_rest_route( 'sac/v1', '/assign-custom-fields', array(
        'methods'             => 'POST',
        'callback'            => 'sac_assign_custom_fields',
        'permission_callback' => 'sac_verify',
    ) );

    register_rest_route( 'sac/v1', '/remove-custom-fields', array(
        'methods'             => 'POST',
        'callback'            => 'sac_remove_custom_fields',
        'permission_callback' => 'sac_verify',
    ) );

    // POD Variations endpoints
    register_rest_route( 'sac/v1', '/assign-pod-variations', array(
        'methods'             => 'POST',
        'callback'            => 'sac_assign_pod_variations',
        'permission_callback' => 'sac_verify',
    ) );

    register_rest_route( 'sac/v1', '/remove-pod-variations', array(
        'methods'             => 'POST',
        'callback'            => 'sac_remove_pod_variations',
        'permission_callback' => 'sac_verify',
    ) );

    // Product Gallery endpoints
    register_rest_route( 'sac/v1', '/update-product-gallery', array(
        'methods'             => 'POST',
        'callback'            => 'sac_update_product_gallery',
        'permission_callback' => 'sac_verify',
    ) );

    register_rest_route( 'sac/v1', '/bulk-update-galleries', array(
        'methods'             => 'POST',
        'callback'            => 'sac_bulk_update_galleries',
        'permission_callback' => 'sac_verify',
    ) );

    register_rest_route( 'sac/v1', '/clear-product-gallery', array(
        'methods'             => 'POST',
        'callback'            => 'sac_clear_product_gallery',
        'permission_callback' => 'sac_verify',
    ) );

    // Page Builder endpoint
    register_rest_route( 'sac/v1', '/create-page', array(
        'methods'             => 'POST',
        'callback'            => 'sac_create_page',
        'permission_callback' => 'sac_verify',
    ) );

    // Load order class
    require_once SAC_PLUGIN_DIR . 'includes/class-sac-orders.php';

    // ORDER MANAGEMENT ROUTES
    register_rest_route('sac/v1', '/orders', ['methods'=>'GET','callback'=>['SAC_Orders','get_orders'],'permission_callback'=>'sac_verify']);
    register_rest_route('sac/v1', '/order/(?P<id>\d+)', ['methods'=>'GET','callback'=>['SAC_Orders','get_order'],'permission_callback'=>'sac_verify']);
    register_rest_route('sac/v1', '/update-order-status', ['methods'=>'POST','callback'=>['SAC_Orders','update_order_status'],'permission_callback'=>'sac_verify']);
    register_rest_route('sac/v1', '/add-tracking', ['methods'=>'POST','callback'=>['SAC_Orders','add_tracking'],'permission_callback'=>'sac_verify']);
    register_rest_route('sac/v1', '/order-stats', ['methods'=>'GET','callback'=>['SAC_Orders','get_order_stats'],'permission_callback'=>'sac_verify']);
    register_rest_route('sac/v1', '/bulk-update-orders', ['methods'=>'POST','callback'=>['SAC_Orders','bulk_update_orders'],'permission_callback'=>'sac_verify']);
    register_rest_route('sac/v1', '/get-woo-page-ids', ['methods'=>'GET','callback'=>['SAC_Orders','get_woo_page_ids'],'permission_callback'=>'sac_verify']);
}
add_action( 'rest_api_init', 'sac_register_routes' );

/**
 * Create product category
 */
function sac_create_category( $request ) {
    $params = $request->get_json_params();
    $name = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
    
    if ( empty( $name ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'Category name is required' ) );
    }
    
    // Check if WooCommerce is active
    if ( ! taxonomy_exists( 'product_cat' ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'WooCommerce not active' ) );
    }
    
    // Check if category already exists
    $existing = get_term_by( 'name', $name, 'product_cat' );
    if ( $existing ) {
        return rest_ensure_response( array(
            'success' => true,
            'id' => $existing->term_id,
            'name' => $existing->name,
            'slug' => $existing->slug,
            'existed' => true
        ) );
    }
    
    // Create new category
    $slug = isset( $params['slug'] ) ? sanitize_title( $params['slug'] ) : sanitize_title( $name );
    $parent = isset( $params['parent'] ) ? intval( $params['parent'] ) : 0;
    $description = isset( $params['description'] ) ? sanitize_textarea_field( $params['description'] ) : '';
    
    $result = wp_insert_term( $name, 'product_cat', array(
        'slug' => $slug,
        'parent' => $parent,
        'description' => $description
    ) );
    
    if ( is_wp_error( $result ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => $result->get_error_message() ) );
    }
    
    return rest_ensure_response( array(
        'success' => true,
        'id' => $result['term_id'],
        'name' => $name,
        'slug' => $slug,
        'existed' => false
    ) );
}

/**
 * Verify HMAC signature
 */
function sac_verify( $request ) {
    $api_key   = $request->get_header( 'X-POD-API-Key' );
    $signature = $request->get_header( 'X-POD-Signature' );
    $timestamp = $request->get_header( 'X-POD-Timestamp' );

    // Debug log
    error_log( '[POD Connector] Verifying request: ' . $request->get_method() . ' ' . $request->get_route() );

    if ( empty( $api_key ) || empty( $signature ) || empty( $timestamp ) ) {
        error_log( '[POD Connector] Missing headers - key:' . ($api_key ? 'yes' : 'no') . ' sig:' . ($signature ? 'yes' : 'no') . ' ts:' . ($timestamp ? 'yes' : 'no') );
        return new WP_Error( 'missing_auth', 'Missing authentication headers', array( 'status' => 401 ) );
    }

    $time_diff = abs( time() - intval( $timestamp ) );
    if ( $time_diff > 300 ) {
        error_log( '[POD Connector] Request expired, diff: ' . $time_diff );
        return new WP_Error( 'expired', 'Request expired', array( 'status' => 401 ) );
    }

    $stored_key = get_option( SAC_API_KEY_OPTION );
    if ( $api_key !== $stored_key ) {
        error_log( '[POD Connector] Invalid API key' );
        return new WP_Error( 'invalid_key', 'Invalid API key', array( 'status' => 401 ) );
    }

    $stored_secret = get_option( SAC_API_SECRET_OPTION );
    $method        = $request->get_method();
    $route         = $request->get_route();

    // Strategy 1: Verify with $request->get_body()
    $body           = $request->get_body();
    $string_to_sign = $timestamp . $method . $route . $body;
    $expected_sig   = hash_hmac( 'sha256', $string_to_sign, $stored_secret );

    if ( hash_equals( $expected_sig, $signature ) ) {
        error_log( '[POD Connector] Auth OK (strategy 1: request body)' );
        return true;
    }

    error_log( '[POD Connector] Strategy 1 failed. Body length: ' . strlen( $body ) );

    // Strategy 2: Verify with raw php://input (some servers/WAFs modify $request->get_body())
    $raw_body = file_get_contents( 'php://input' );
    if ( $raw_body !== false && $raw_body !== $body ) {
        $string_to_sign_raw = $timestamp . $method . $route . $raw_body;
        $expected_sig_raw   = hash_hmac( 'sha256', $string_to_sign_raw, $stored_secret );

        if ( hash_equals( $expected_sig_raw, $signature ) ) {
            error_log( '[POD Connector] Auth OK (strategy 2: raw php://input)' );
            return true;
        }
        error_log( '[POD Connector] Strategy 2 failed. Raw body length: ' . strlen( $raw_body ) );
    }

    // Strategy 3: Verify using bodyless signature header (X-POD-Signature-NB)
    // Frontend sends a separate signature computed without body, for hosting that strips/modifies body
    $signature_nb = $request->get_header( 'X-POD-Signature-NB' );
    $string_no_body = $timestamp . $method . $route;
    $expected_no_body = hash_hmac( 'sha256', $string_no_body, $stored_secret );

    if ( ! empty( $signature_nb ) && hash_equals( $expected_no_body, $signature_nb ) ) {
        error_log( '[POD Connector] Auth OK (strategy 3: bodyless signature - hosting modified body)' );
        return true;
    }

    // Strategy 4: Last resort - main signature matches bodyless (for old clients that don't send NB header)
    if ( hash_equals( $expected_no_body, $signature ) ) {
        error_log( '[POD Connector] Auth OK (strategy 4: main sig matches bodyless)' );
        return true;
    }

    // All strategies failed - log debug info
    error_log( '[POD Connector] ALL auth strategies failed!' );
    error_log( '[POD Connector] Method: ' . $method . ', Route: ' . $route );
    error_log( '[POD Connector] Body (first 200): ' . substr( $body, 0, 200 ) );
    error_log( '[POD Connector] Expected sig (s1): ' . $expected_sig );
    error_log( '[POD Connector] Received sig: ' . $signature );
    error_log( '[POD Connector] Has NB header: ' . ($signature_nb ? 'yes' : 'no') );

    return new WP_Error( 'invalid_sig', 'Invalid signature', array( 'status' => 401 ) );
}

/**
 * Health check
 */
function sac_health( $request ) {
    $has_woo = class_exists( 'WooCommerce' );
    return rest_ensure_response( array(
        'status'      => 'ok',
        'version'     => SAC_VERSION,
        'site'        => get_bloginfo( 'name' ),
        'woocommerce' => $has_woo,
        'timestamp'   => time(),
    ) );
}


/**
 * Bulk fetch
 */
function sac_bulk_fetch( $request ) {
    $params   = $request->get_json_params();
    $response = array();

    // Fetch products
    if ( ! empty( $params['products'] ) && function_exists( 'wc_get_products' ) ) {
        $defaults = array( 'limit' => 100, 'page' => 1, 'status' => 'any' );
        $args     = wp_parse_args( $params['products'], $defaults );
        
        // Get total count first
        $total_args = array(
            'status' => $args['status'],
            'return' => 'ids',
            'limit'  => -1
        );
        $all_product_ids = wc_get_products( $total_args );
        $total_products = is_array( $all_product_ids ) ? count( $all_product_ids ) : 0;
        
        // Get products for current page
        $products = wc_get_products( array(
            'limit'  => $args['limit'],
            'page'   => $args['page'],
            'status' => $args['status'],
        ) );

        $response['products'] = array();
        foreach ( $products as $product ) {
            // Get all images in WooCommerce API format
            $images = array();
            $image_id = $product->get_image_id();
            if ( $image_id ) {
                $images[] = array(
                    'id'  => $image_id,
                    'src' => wp_get_attachment_url( $image_id ),
                );
            }
            // Gallery images
            $gallery_ids = $product->get_gallery_image_ids();
            foreach ( $gallery_ids as $gid ) {
                $images[] = array(
                    'id'  => $gid,
                    'src' => wp_get_attachment_url( $gid ),
                );
            }
            
            // Get categories
            $categories = array();
            $cat_ids = $product->get_category_ids();
            foreach ( $cat_ids as $cat_id ) {
                $term = get_term( $cat_id, 'product_cat' );
                if ( $term && ! is_wp_error( $term ) ) {
                    $categories[] = array(
                        'id'   => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    );
                }
            }
            
            $response['products'][] = array(
                'id'          => $product->get_id(),
                'name'        => $product->get_name(),
                'permalink'   => $product->get_permalink(),
                'status'      => $product->get_status(),
                'price'       => $product->get_price(),
                'regular_price' => $product->get_regular_price(),
                'description' => $product->get_description(),
                'short_description' => $product->get_short_description(),
                'sku'         => $product->get_sku(),
                'images'      => $images,
                'categories'  => $categories,
                'has_custom_fields' => ! empty( get_post_meta( $product->get_id(), '_pod_custom_fields', true ) ),
                'custom_field_preset' => sac_get_custom_field_preset_name( $product->get_id() ),
                'has_pod_variations' => ! empty( get_post_meta( $product->get_id(), '_pod_variations', true ) ),
                'pod_variation_preset' => sac_get_pod_variation_preset_name( $product->get_id() ),
            );
        }
        $response['products_total'] = $total_products;
    }

    // Fetch posts
    if ( ! empty( $params['posts'] ) ) {
        $defaults = array( 'per_page' => 100, 'page' => 1, 'status' => 'any' );
        $args     = wp_parse_args( $params['posts'], $defaults );
        $query    = new WP_Query( array(
            'post_type'      => 'post',
            'posts_per_page' => $args['per_page'],
            'paged'          => $args['page'],
            'post_status'    => $args['status'],
        ) );

        $response['posts'] = array();
        foreach ( $query->posts as $post ) {
            $response['posts'][] = array(
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'permalink' => get_permalink( $post->ID ),
                'link'      => get_permalink( $post->ID ),
                'status'    => $post->post_status,
                'date'      => $post->post_date,
                'excerpt'   => $post->post_excerpt,
                'featured_media' => get_post_thumbnail_id( $post->ID ),
            );
        }
        $response['posts_total'] = $query->found_posts;
    }

    // Fetch categories
    if ( ! empty( $params['categories'] ) && taxonomy_exists( 'product_cat' ) ) {
        $terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
        $response['categories'] = array();
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $response['categories'][] = array(
                    'id'    => $term->term_id,
                    'name'  => $term->name,
                    'slug'  => $term->slug,
                    'count' => $term->count,
                );
            }
        }
    }

    return rest_ensure_response( $response );
}

/**
 * Bulk upload images - with batching to prevent memory issues
 */
function sac_bulk_upload( $request ) {
    // Increase limits for large uploads
    @ini_set( 'memory_limit', '1024M' );
    @ini_set( 'max_execution_time', 600 );
    @set_time_limit( 600 );
    
    // Force garbage collection
    if ( function_exists( 'gc_collect_cycles' ) ) {
        gc_collect_cycles();
    }
    
    $params  = $request->get_json_params();
    $images  = isset( $params['images'] ) ? $params['images'] : array();
    $results = array();

    if ( empty( $images ) ) {
        return rest_ensure_response( array( 'results' => array(), 'error' => 'No images provided' ) );
    }

    // Log upload request
    error_log( '[POD Connector] Bulk upload: ' . count( $images ) . ' images' );

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    // Process images one by one to minimize memory usage
    foreach ( $images as $index => $image ) {
        // Force garbage collection every 5 images
        if ( $index > 0 && $index % 5 === 0 ) {
            if ( function_exists( 'gc_collect_cycles' ) ) {
                gc_collect_cycles();
            }
        }
        
        try {
            $base64   = isset( $image['data'] ) ? $image['data'] : '';
            $filename = isset( $image['filename'] ) ? sanitize_file_name( $image['filename'] ) : 'image-' . time() . '-' . $index . '.webp';

            if ( empty( $base64 ) ) {
                $results[] = array( 'success' => false, 'error' => 'No data' );
                continue;
            }

            // Remove data URL prefix if present
            $base64_clean = preg_replace( '#^data:image/\w+;base64,#i', '', $base64 );
            
            // Clear original base64 from memory
            unset( $image['data'] );
            unset( $base64 );
            
            $decoded = base64_decode( $base64_clean, true );
            
            // Clear base64_clean from memory
            unset( $base64_clean );

            if ( false === $decoded || empty( $decoded ) ) {
                $results[] = array( 'success' => false, 'error' => 'Invalid base64' );
                continue;
            }

            $upload_dir = wp_upload_dir();
            if ( ! empty( $upload_dir['error'] ) ) {
                unset( $decoded );
                $results[] = array( 'success' => false, 'error' => 'Upload dir error: ' . $upload_dir['error'] );
                continue;
            }
            
            $file_path = $upload_dir['path'] . '/' . $filename;
            $written   = file_put_contents( $file_path, $decoded );
            
            // Free memory immediately after writing
            unset( $decoded );
            
            if ( false === $written ) {
                $results[] = array( 'success' => false, 'error' => 'Failed to write file' );
                continue;
            }

            $file_type = wp_check_filetype( $filename );
            $mime_type = ! empty( $file_type['type'] ) ? $file_type['type'] : 'image/webp';

            $attachment = array(
                'post_mime_type' => $mime_type,
                'post_title'     => pathinfo( $filename, PATHINFO_FILENAME ),
                'post_status'    => 'inherit',
            );

            $attach_id = wp_insert_attachment( $attachment, $file_path );

            if ( is_wp_error( $attach_id ) ) {
                $results[] = array( 'success' => false, 'error' => $attach_id->get_error_message() );
                continue;
            }

            // Generate metadata (thumbnails) - this can be memory intensive
            $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
            wp_update_attachment_metadata( $attach_id, $attach_data );
            unset( $attach_data );

            if ( ! empty( $image['metadata']['alt'] ) ) {
                update_post_meta( $attach_id, '_wp_attachment_image_alt', sanitize_text_field( $image['metadata']['alt'] ) );
            }

            $results[] = array(
                'success' => true,
                'id'      => $attach_id,
                'url'     => wp_get_attachment_url( $attach_id ),
            );
            
            error_log( '[POD Connector] Uploaded image ' . ($index + 1) . '/' . count( $images ) . ': ' . $filename );
            
        } catch ( Exception $e ) {
            error_log( '[POD Connector] Upload error: ' . $e->getMessage() );
            $results[] = array( 'success' => false, 'error' => $e->getMessage() );
        }
    }

    error_log( '[POD Connector] Bulk upload complete: ' . count( $results ) . ' results' );
    return rest_ensure_response( array( 'results' => $results ) );
}


/**
 * Bulk create products
 */
function sac_bulk_products( $request ) {
    // Increase limits for bulk operations
    @ini_set( 'memory_limit', '1024M' );
    @ini_set( 'max_execution_time', 600 );
    @set_time_limit( 600 );
    
    error_log('[POD Connector] bulk_products called');
    
    if ( ! class_exists( 'WC_Product_Simple' ) ) {
        error_log('[POD Connector] WooCommerce not found!');
        return new WP_Error( 'no_woo', 'WooCommerce not installed', array( 'status' => 400 ) );
    }

    $params   = $request->get_json_params();
    $products = isset( $params['products'] ) ? $params['products'] : array();
    
    error_log('[POD Connector] Creating ' . count($products) . ' products');
    
    $results  = array();

    foreach ( $products as $index => $data ) {
        try {
            error_log('[POD Connector] Creating product ' . ($index + 1) . '/' . count($products) . ': ' . (isset($data['name']) ? $data['name'] : 'No name'));
            
            $product = new WC_Product_Simple();

            if ( ! empty( $data['name'] ) ) {
                $product->set_name( $data['name'] );
            }
            if ( ! empty( $data['slug'] ) ) {
                $product->set_slug( sanitize_title( $data['slug'] ) );
            }
            if ( ! empty( $data['description'] ) ) {
                $product->set_description( $data['description'] );
            }
            if ( ! empty( $data['short_description'] ) ) {
                $product->set_short_description( $data['short_description'] );
            }
            if ( ! empty( $data['regular_price'] ) ) {
                $product->set_regular_price( $data['regular_price'] );
            }
            if ( ! empty( $data['status'] ) ) {
                $product->set_status( $data['status'] );
            }

            if ( ! empty( $data['image_ids'] ) && is_array( $data['image_ids'] ) ) {
                $ids = array_map( 'intval', $data['image_ids'] );
                $product->set_image_id( $ids[0] );
                if ( count( $ids ) > 1 ) {
                    $product->set_gallery_image_ids( array_slice( $ids, 1 ) );
                }
            }

            if ( ! empty( $data['category_ids'] ) && is_array( $data['category_ids'] ) ) {
                $product->set_category_ids( array_map( 'intval', $data['category_ids'] ) );
            }

            $product_id = $product->save();
            
            error_log('[POD Connector] Product saved successfully: ID=' . $product_id);
            
            // Handle tags
            if ( ! empty( $data['tags'] ) && is_array( $data['tags'] ) ) {
                wp_set_object_terms( $product_id, $data['tags'], 'product_tag' );
            }
            
            // Handle meta_data (SEO fields, FAQ, etc.)
            if ( ! empty( $data['meta_data'] ) && is_array( $data['meta_data'] ) ) {
                foreach ( $data['meta_data'] as $meta ) {
                    if ( isset( $meta['key'] ) && isset( $meta['value'] ) ) {
                        update_post_meta( $product_id, sanitize_key( $meta['key'] ), $meta['value'] );
                    }
                }
            }
            
            $results[]  = array(
                'success'   => true,
                'id'        => $product_id,
                'permalink' => get_permalink( $product_id ),
            );
        } catch ( Exception $e ) {
            error_log('[POD Connector] Product creation failed: ' . $e->getMessage());
            error_log('[POD Connector] Stack trace: ' . $e->getTraceAsString());
            $results[] = array( 'success' => false, 'error' => $e->getMessage() );
        }
    }

    error_log('[POD Connector] bulk_products completed: ' . count($results) . ' results');
    return rest_ensure_response( array( 'results' => $results ) );
}

/**
 * Bulk update products/posts
 */
function sac_bulk_update( $request ) {
    $params  = $request->get_json_params();
    $results = array();

    // Update products
    if ( ! empty( $params['products'] ) && function_exists( 'wc_get_product' ) ) {
        foreach ( $params['products'] as $data ) {
            try {
                $product_id = isset( $data['id'] ) ? intval( $data['id'] ) : 0;
                if ( ! $product_id ) {
                    $results[] = array( 'success' => false, 'id' => 0, 'error' => 'Missing product ID' );
                    continue;
                }

                $product = wc_get_product( $product_id );
                if ( ! $product ) {
                    $results[] = array( 'success' => false, 'id' => $product_id, 'error' => 'Product not found' );
                    continue;
                }

                // Update fields
                if ( isset( $data['name'] ) ) {
                    $product->set_name( sanitize_text_field( $data['name'] ) );
                }
                if ( isset( $data['description'] ) ) {
                    $product->set_description( wp_kses_post( $data['description'] ) );
                }
                if ( isset( $data['short_description'] ) ) {
                    $product->set_short_description( wp_kses_post( $data['short_description'] ) );
                }
                if ( isset( $data['regular_price'] ) ) {
                    $product->set_regular_price( sanitize_text_field( $data['regular_price'] ) );
                }
                if ( isset( $data['sale_price'] ) ) {
                    $product->set_sale_price( sanitize_text_field( $data['sale_price'] ) );
                }
                if ( isset( $data['status'] ) ) {
                    $product->set_status( sanitize_text_field( $data['status'] ) );
                }
                if ( isset( $data['sku'] ) ) {
                    $product->set_sku( sanitize_text_field( $data['sku'] ) );
                }
                if ( ! empty( $data['category_ids'] ) && is_array( $data['category_ids'] ) ) {
                    $product->set_category_ids( array_map( 'intval', $data['category_ids'] ) );
                } elseif ( ! empty( $data['category_name'] ) && taxonomy_exists( 'product_cat' ) ) {
                    // Support category_name: look up or create category
                    $cat_name = sanitize_text_field( $data['category_name'] );
                    $existing = get_term_by( 'name', $cat_name, 'product_cat' );
                    if ( $existing ) {
                        $product->set_category_ids( array( $existing->term_id ) );
                    } else {
                        $new_cat = wp_insert_term( $cat_name, 'product_cat' );
                        if ( ! is_wp_error( $new_cat ) ) {
                            $product->set_category_ids( array( $new_cat['term_id'] ) );
                        }
                    }
                }

                $product->save();
                $results[] = array( 'success' => true, 'id' => $product_id );
            } catch ( Exception $e ) {
                $results[] = array( 'success' => false, 'id' => isset( $data['id'] ) ? $data['id'] : 0, 'error' => $e->getMessage() );
            }
        }
    }

    // Update posts
    if ( ! empty( $params['posts'] ) ) {
        foreach ( $params['posts'] as $data ) {
            try {
                $post_id = isset( $data['id'] ) ? intval( $data['id'] ) : 0;
                if ( ! $post_id ) {
                    $results[] = array( 'success' => false, 'id' => 0, 'error' => 'Missing post ID' );
                    continue;
                }

                $post_data = array( 'ID' => $post_id );

                if ( isset( $data['title'] ) ) {
                    $post_data['post_title'] = sanitize_text_field( $data['title'] );
                }
                if ( isset( $data['content'] ) ) {
                    $post_data['post_content'] = wp_kses_post( $data['content'] );
                }
                if ( isset( $data['status'] ) ) {
                    $post_data['post_status'] = sanitize_text_field( $data['status'] );
                }
                if ( isset( $data['excerpt'] ) ) {
                    $post_data['post_excerpt'] = wp_kses_post( $data['excerpt'] );
                }
                if ( isset( $data['slug'] ) ) {
                    $post_data['post_name'] = sanitize_title( $data['slug'] );
                }

                $updated = wp_update_post( $post_data, true );

                if ( is_wp_error( $updated ) ) {
                    $results[] = array( 'success' => false, 'id' => $post_id, 'error' => $updated->get_error_message() );
                } else {
                    // Update featured image if provided
                    if ( isset( $data['featured_media'] ) ) {
                        $media_id = intval( $data['featured_media'] );
                        if ( $media_id > 0 ) {
                            set_post_thumbnail( $post_id, $media_id );
                        } else {
                            delete_post_thumbnail( $post_id );
                        }
                    }
                    $results[] = array( 'success' => true, 'id' => $post_id );
                }
            } catch ( Exception $e ) {
                $results[] = array( 'success' => false, 'id' => isset( $data['id'] ) ? $data['id'] : 0, 'error' => $e->getMessage() );
            }
        }
    }

    return rest_ensure_response( array( 'results' => $results ) );
}

/**
 * Bulk delete
 */
function sac_bulk_delete( $request ) {
    $params  = $request->get_json_params();
    $results = array();

    // Delete products
    if ( ! empty( $params['product_ids'] ) && function_exists( 'wc_get_product' ) ) {
        $results['products'] = array();
        foreach ( $params['product_ids'] as $id ) {
            $product = wc_get_product( $id );
            if ( $product ) {
                $image_id = $product->get_image_id();
                $gallery  = $product->get_gallery_image_ids();
                $product->delete( true );

                if ( $image_id ) {
                    wp_delete_attachment( $image_id, true );
                }
                foreach ( $gallery as $gid ) {
                    wp_delete_attachment( $gid, true );
                }
                $results['products'][] = array( 'id' => $id, 'success' => true );
            } else {
                $results['products'][] = array( 'id' => $id, 'success' => false );
            }
        }
    }

    // Delete posts
    if ( ! empty( $params['post_ids'] ) ) {
        $results['posts'] = array();
        foreach ( $params['post_ids'] as $id ) {
            $deleted = wp_delete_post( $id, true );
            $results['posts'][] = array( 'id' => $id, 'success' => (bool) $deleted );
        }
    }

    return rest_ensure_response( array( 'results' => $results ) );
}

/**
 * Bulk create posts (for blog automation)
 */
function sac_bulk_posts( $request ) {
    $params  = $request->get_json_params();
    $posts   = isset( $params['posts'] ) ? $params['posts'] : array();
    $results = array();

    foreach ( $posts as $data ) {
        $post_data = array(
            'post_type'    => 'post',
            'post_status'  => isset( $data['status'] ) ? $data['status'] : 'draft',
        );

        if ( ! empty( $data['title'] ) ) {
            $post_data['post_title'] = $data['title'];
        }
        if ( ! empty( $data['content'] ) ) {
            $post_data['post_content'] = $data['content'];
        }
        if ( ! empty( $data['excerpt'] ) ) {
            $post_data['post_excerpt'] = $data['excerpt'];
        }
        if ( ! empty( $data['slug'] ) ) {
            $post_data['post_name'] = $data['slug'];
        }

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            $results[] = array( 'success' => false, 'error' => $post_id->get_error_message() );
            continue;
        }

        // Set featured image
        if ( ! empty( $data['featured_image_id'] ) ) {
            set_post_thumbnail( $post_id, intval( $data['featured_image_id'] ) );
        }

        // Set categories
        if ( ! empty( $data['category_ids'] ) && is_array( $data['category_ids'] ) ) {
            wp_set_post_categories( $post_id, array_map( 'intval', $data['category_ids'] ) );
        }

        // Set tags
        if ( ! empty( $data['tags'] ) && is_array( $data['tags'] ) ) {
            wp_set_post_tags( $post_id, $data['tags'] );
        }

        // Set meta data (SEO fields)
        if ( ! empty( $data['meta_data'] ) && is_array( $data['meta_data'] ) ) {
            foreach ( $data['meta_data'] as $meta ) {
                if ( ! empty( $meta['key'] ) && isset( $meta['value'] ) ) {
                    update_post_meta( $post_id, $meta['key'], $meta['value'] );
                }
            }
        }

        $results[] = array(
            'success'   => true,
            'id'        => $post_id,
            'permalink' => get_permalink( $post_id ),
        );
    }

    return rest_ensure_response( array( 'results' => $results ) );
}

/**
 * Get single post with full content
 */
function sac_get_post( $request ) {
    $post_id = intval( $request->get_param( 'id' ) );
    $post = get_post( $post_id );
    
    if ( ! $post ) {
        return new WP_Error( 'not_found', 'Post not found', array( 'status' => 404 ) );
    }
    
    $featured_image_url = null;
    $featured_media_id = get_post_thumbnail_id( $post_id );
    if ( $featured_media_id ) {
        $featured_image_url = wp_get_attachment_url( $featured_media_id );
    }
    
    return rest_ensure_response( array(
        'success' => true,
        'post' => array(
            'id' => $post->ID,
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'slug' => $post->post_name,
            'status' => $post->post_status,
            'date' => $post->post_date,
            'permalink' => get_permalink( $post_id ),
            'featured_media' => $featured_media_id,
            'featured_image_url' => $featured_image_url,
        )
    ) );
}

/**
 * Update single post
 */
function sac_update_post( $request ) {
    $params = $request->get_json_params();
    $post_id = isset( $params['post_id'] ) ? intval( $params['post_id'] ) : 0;
    
    if ( ! $post_id ) {
        return new WP_Error( 'missing_id', 'Missing post ID', array( 'status' => 400 ) );
    }
    
    $post = get_post( $post_id );
    if ( ! $post ) {
        return new WP_Error( 'not_found', 'Post not found', array( 'status' => 404 ) );
    }
    
    $post_data = array( 'ID' => $post_id );
    
    if ( isset( $params['title'] ) ) {
        $post_data['post_title'] = sanitize_text_field( $params['title'] );
    }
    if ( isset( $params['content'] ) ) {
        $post_data['post_content'] = wp_kses_post( $params['content'] );
    }
    if ( isset( $params['slug'] ) ) {
        $post_data['post_name'] = sanitize_title( $params['slug'] );
    }
    if ( isset( $params['status'] ) ) {
        $post_data['post_status'] = sanitize_text_field( $params['status'] );
    }
    
    $updated = wp_update_post( $post_data, true );
    
    if ( is_wp_error( $updated ) ) {
        return new WP_Error( 'update_failed', $updated->get_error_message(), array( 'status' => 500 ) );
    }
    
    // Update featured image
    if ( isset( $params['featured_media'] ) ) {
        $media_id = intval( $params['featured_media'] );
        if ( $media_id > 0 ) {
            set_post_thumbnail( $post_id, $media_id );
        } else {
            delete_post_thumbnail( $post_id );
        }
    }
    
    return rest_ensure_response( array(
        'success' => true,
        'id' => $post_id,
        'permalink' => get_permalink( $post_id )
    ) );
}

/**
 * Upload single media file
 */
function sac_upload_media( $request ) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    
    $files = $request->get_file_params();
    
    if ( empty( $files['file'] ) ) {
        return new WP_Error( 'no_file', 'No file uploaded', array( 'status' => 400 ) );
    }
    
    $file = $files['file'];
    
    // Check for upload errors
    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        return new WP_Error( 'upload_error', 'Upload error: ' . $file['error'], array( 'status' => 400 ) );
    }
    
    // Handle the upload
    $upload = wp_handle_upload( $file, array( 'test_form' => false ) );
    
    if ( isset( $upload['error'] ) ) {
        return new WP_Error( 'upload_failed', $upload['error'], array( 'status' => 500 ) );
    }
    
    // Create attachment
    $attachment = array(
        'post_mime_type' => $upload['type'],
        'post_title' => pathinfo( $file['name'], PATHINFO_FILENAME ),
        'post_status' => 'inherit',
    );
    
    $attach_id = wp_insert_attachment( $attachment, $upload['file'] );
    
    if ( is_wp_error( $attach_id ) ) {
        return new WP_Error( 'attachment_failed', $attach_id->get_error_message(), array( 'status' => 500 ) );
    }
    
    // Generate metadata
    $attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
    wp_update_attachment_metadata( $attach_id, $attach_data );
    
    return rest_ensure_response( array(
        'success' => true,
        'media_id' => $attach_id,
        'url' => wp_get_attachment_url( $attach_id )
    ) );
}

/**
 * Simple API key verification (no HMAC) for file uploads
 */
function sac_verify_simple( $request ) {
    $api_key = $request->get_header( 'X-POD-API-Key' );
    
    if ( empty( $api_key ) ) {
        return new WP_Error( 'missing_key', 'Missing API key', array( 'status' => 401 ) );
    }
    
    $stored_key = get_option( SAC_API_KEY_OPTION );
    if ( $api_key !== $stored_key ) {
        return new WP_Error( 'invalid_key', 'Invalid API key', array( 'status' => 401 ) );
    }
    
    return true;
}

/**
 * Include GMC Compliance Scanner (after verify functions are defined)
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-gmc-compliance.php';

/**
 * Include GMC Product Feed
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-gmc-feed.php';

/**
 * Include Telegram Sales Notifications
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-telegram-notify.php';

/**
 * Include POD SEO Lite (Sitemap, Meta Tags, IndexNow)
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-seo.php';

/**
 * Include Theme Endpoints (GeneratePress child-theme push / activate / status / mods).
 *
 * Loaded after `sac_verify` is defined because the endpoints
 * class registers `sac_verify` as its permission_callback.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-pod-theme-endpoints.php';

/**
 * Admin menu - Create main menu with submenus
 */
function sac_admin_menu() {
    // Main menu
    add_menu_page(
        'POD AI Connector',
        'POD AI Connector',
        'manage_options',
        'pod-ai-connector',
        'sac_settings_page',
        'dashicons-admin-generic',
        80
    );
    
    // Submenu: Settings (same as main)
    add_submenu_page(
        'pod-ai-connector',
        'Settings',
        'Settings',
        'manage_options',
        'pod-ai-connector',
        'sac_settings_page'
    );
}
add_action( 'admin_menu', 'sac_admin_menu' );

/**
 * AJAX regenerate keys
 */
function sac_ajax_regenerate() {
    check_ajax_referer( 'sac_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $api_key    = 'pod_' . wp_generate_password( 32, false );
    $api_secret = wp_generate_password( 64, false );
    update_option( SAC_API_KEY_OPTION, $api_key );
    update_option( SAC_API_SECRET_OPTION, $api_secret );

    wp_send_json_success( array( 'api_key' => $api_key, 'api_secret' => $api_secret ) );
}
add_action( 'wp_ajax_pod_regenerate_keys', 'sac_ajax_regenerate' );


/**
 * Settings page
 */
function sac_settings_page() {
    $api_key    = get_option( SAC_API_KEY_OPTION, '' );
    $api_secret = get_option( SAC_API_SECRET_OPTION, '' );
    $site_url   = get_site_url();
    $nonce      = wp_create_nonce( 'sac_nonce' );
    $has_woo    = class_exists( 'WooCommerce' );
    ?>
    <div class="wrap">
        <h1>POD AI Designer Connector</h1>
        <p>Kết nối với POD AI Designer Pro app.</p>

        <div style="background:#fff;padding:20px;margin:20px 0;border:1px solid #ccd0d4;border-radius:8px;">
            <h2>API Credentials</h2>
            <table class="form-table">
                <tr>
                    <th>Site URL</th>
                    <td><code id="pod-url"><?php echo esc_html( $site_url ); ?></code></td>
                </tr>
                <tr>
                    <th>API Key</th>
                    <td><code id="pod-key"><?php echo esc_html( $api_key ); ?></code></td>
                </tr>
                <tr>
                    <th>API Secret</th>
                    <td>
                        <code id="pod-secret" style="filter:blur(4px)"><?php echo esc_html( $api_secret ); ?></code>
                        <button type="button" class="button" id="toggle-secret">Show/Hide</button>
                    </td>
                </tr>
            </table>
            <p>
                <button type="button" class="button button-primary" id="copy-all">Copy All</button>
                <button type="button" class="button" id="regen-keys">Regenerate Keys</button>
            </p>
        </div>

        <div style="background:#fff;padding:20px;margin:20px 0;border:1px solid #ccd0d4;border-radius:8px;">
            <h2>Status</h2>
            <ul>
                <li><strong>Version:</strong> <?php echo esc_html( SAC_VERSION ); ?></li>
                <li><strong>WooCommerce:</strong> <?php echo $has_woo ? 'Yes' : 'No'; ?></li>
            </ul>
        </div>
    </div>

    <script>
    document.getElementById('toggle-secret').addEventListener('click', function() {
        var el = document.getElementById('pod-secret');
        el.style.filter = el.style.filter ? '' : 'blur(4px)';
    });

    document.getElementById('copy-all').addEventListener('click', function() {
        var data = {
            siteUrl: document.getElementById('pod-url').textContent,
            apiKey: document.getElementById('pod-key').textContent,
            apiSecret: document.getElementById('pod-secret').textContent
        };
        navigator.clipboard.writeText(JSON.stringify(data, null, 2));
        alert('Copied!');
    });

    document.getElementById('regen-keys').addEventListener('click', function() {
        if (!confirm('Generate new keys? Old keys will stop working.')) return;

        var formData = new FormData();
        formData.append('action', 'pod_regenerate_keys');
        formData.append('nonce', '<?php echo esc_js( $nonce ); ?>');

        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                document.getElementById('pod-key').textContent = data.data.api_key;
                document.getElementById('pod-secret').textContent = data.data.api_secret;
                alert('Keys regenerated!');
            } else {
                alert('Error: ' + (data.data || 'Unknown'));
            }
        });
    });
    </script>
    <?php
}

/**
 * Create variable product with variations
 */
function sac_create_variable_product( $request ) {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'WooCommerce not active' ) );
    }

    $params = $request->get_json_params();
    
    $name = isset( $params['name'] ) ? sanitize_text_field( $params['name'] ) : '';
    $slug = isset( $params['slug'] ) ? sanitize_title( $params['slug'] ) : '';
    $description = isset( $params['description'] ) ? wp_kses_post( $params['description'] ) : '';
    $short_description = isset( $params['short_description'] ) ? wp_kses_post( $params['short_description'] ) : '';
    $status = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : 'draft';
    $image_ids = isset( $params['image_ids'] ) ? array_map( 'intval', $params['image_ids'] ) : array();
    $category_ids = isset( $params['category_ids'] ) ? array_map( 'intval', $params['category_ids'] ) : array();
    $sizes = isset( $params['sizes'] ) ? $params['sizes'] : array(); // [{ name: 'S', price: '19.99' }, ...]
    $colors = isset( $params['colors'] ) ? $params['colors'] : array(); // ['Black', 'White', ...]
    $meta_data = isset( $params['meta_data'] ) ? $params['meta_data'] : array(); // [{ key: '...', value: '...' }, ...]
    
    if ( empty( $name ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'Product name is required' ) );
    }
    
    if ( empty( $sizes ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'At least one size is required' ) );
    }

    try {
        // Create variable product
        $product = new WC_Product_Variable();
        $product->set_name( $name );
        if ( ! empty( $slug ) ) {
            $product->set_slug( $slug );
        }
        $product->set_description( $description );
        $product->set_short_description( $short_description );
        $product->set_status( $status );
        
        // Set images
        if ( ! empty( $image_ids ) ) {
            $product->set_image_id( $image_ids[0] );
            if ( count( $image_ids ) > 1 ) {
                $product->set_gallery_image_ids( array_slice( $image_ids, 1 ) );
            }
        }
        
        // Set categories
        if ( ! empty( $category_ids ) ) {
            $product->set_category_ids( $category_ids );
        }
        
        // Create/get Size attribute
        $size_values = array_map( function( $s ) { return $s['name']; }, $sizes );
        $size_attribute = new WC_Product_Attribute();
        $size_attribute->set_name( 'Size' );
        $size_attribute->set_options( $size_values );
        $size_attribute->set_visible( true );
        $size_attribute->set_variation( true );
        
        $attributes = array( $size_attribute );
        
        // Create Color attribute if provided
        if ( ! empty( $colors ) ) {
            $color_attribute = new WC_Product_Attribute();
            $color_attribute->set_name( 'Color' );
            $color_attribute->set_options( $colors );
            $color_attribute->set_visible( true );
            $color_attribute->set_variation( true );
            $attributes[] = $color_attribute;
        }
        
        $product->set_attributes( $attributes );
        $product_id = $product->save();
        
        if ( ! $product_id ) {
            return rest_ensure_response( array( 'success' => false, 'error' => 'Failed to create product' ) );
        }
        
        // Create variations
        $variation_count = 0;
        $size_price_map = array();
        foreach ( $sizes as $size ) {
            $size_price_map[ $size['name'] ] = $size['price'];
        }
        
        if ( ! empty( $colors ) ) {
            // Size + Color variations
            foreach ( $sizes as $size ) {
                foreach ( $colors as $color ) {
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id( $product_id );
                    $variation->set_attributes( array(
                        'size' => $size['name'],
                        'color' => $color
                    ) );
                    $variation->set_regular_price( $size['price'] );
                    $variation->set_status( 'publish' );
                    $variation->set_manage_stock( false );
                    $variation->set_stock_status( 'instock' );
                    $variation->save();
                    $variation_count++;
                }
            }
        } else {
            // Size only variations
            foreach ( $sizes as $size ) {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id( $product_id );
                $variation->set_attributes( array(
                    'size' => $size['name']
                ) );
                $variation->set_regular_price( $size['price'] );
                $variation->set_status( 'publish' );
                $variation->set_manage_stock( false );
                $variation->set_stock_status( 'instock' );
                $variation->save();
                $variation_count++;
            }
        }
        
        // Sync variations
        WC_Product_Variable::sync( $product_id );
        
        // Save meta data (SEO fields, FAQ, etc.)
        if ( ! empty( $meta_data ) ) {
            foreach ( $meta_data as $meta ) {
                if ( isset( $meta['key'] ) && isset( $meta['value'] ) ) {
                    update_post_meta( $product_id, sanitize_key( $meta['key'] ), $meta['value'] );
                }
            }
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'id' => $product_id,
            'permalink' => get_permalink( $product_id ),
            'variations_created' => $variation_count
        ) );
        
    } catch ( Exception $e ) {
        return rest_ensure_response( array( 'success' => false, 'error' => $e->getMessage() ) );
    }
}

/**
 * Get custom field preset name(s) from product
 * Supports both legacy single-preset and new multi-preset format
 */
function sac_get_custom_field_preset_name( $product_id ) {
    $custom_fields = get_post_meta( $product_id, '_pod_custom_fields', true );
    if ( empty( $custom_fields ) || ! is_array( $custom_fields ) ) {
        return null;
    }
    
    // New multi-preset format: has 'presets' array
    if ( ! empty( $custom_fields['presets'] ) && is_array( $custom_fields['presets'] ) ) {
        $names = array_map( function( $p ) {
            return isset( $p['preset_name'] ) ? $p['preset_name'] : '';
        }, $custom_fields['presets'] );
        $names = array_filter( $names );
        return ! empty( $names ) ? implode( ' + ', $names ) : null;
    }
    
    // Legacy single-preset format
    if ( isset( $custom_fields['preset_name'] ) ) {
        return $custom_fields['preset_name'];
    }
    return null;
}

/**
 * Assign custom fields to products
 * Supports multi-preset: new presets are MERGED with existing ones
 * If the same preset_id is sent again, it replaces that preset only
 */
function sac_assign_custom_fields( $request ) {
    $params = $request->get_json_params();
    $product_ids = isset( $params['product_ids'] ) ? array_map( 'intval', $params['product_ids'] ) : array();
    $preset = isset( $params['preset'] ) ? $params['preset'] : null;
    
    if ( empty( $product_ids ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'No product IDs provided' ) );
    }
    
    if ( empty( $preset ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'No preset provided' ) );
    }
    
    $results = array();
    
    foreach ( $product_ids as $product_id ) {
        try {
            // Verify product exists
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                $results[] = array( 'id' => $product_id, 'success' => false, 'error' => 'Product not found' );
                continue;
            }
            
            // Build this preset's data
            $new_preset = array(
                'preset_id'        => isset( $preset['preset_id'] ) ? sanitize_text_field( $preset['preset_id'] ) : '',
                'preset_name'      => isset( $preset['preset_name'] ) ? sanitize_text_field( $preset['preset_name'] ) : '',
                'fields'           => isset( $preset['fields'] ) ? $preset['fields'] : array(),
                'enable_extra_fee' => isset( $preset['enable_extra_fee'] ) ? (bool) $preset['enable_extra_fee'] : false,
                'extra_fee'        => isset( $preset['extra_fee'] ) ? sanitize_text_field( $preset['extra_fee'] ) : '0',
                'fee_label'        => isset( $preset['fee_label'] ) ? sanitize_text_field( $preset['fee_label'] ) : 'Personalization Fee',
                'is_optional'      => isset( $preset['is_optional'] ) ? (bool) $preset['is_optional'] : false,
                'optional_label'   => isset( $preset['optional_label'] ) ? sanitize_text_field( $preset['optional_label'] ) : 'Add Personalization',
            );
            
            // Load existing data
            $existing = get_post_meta( $product_id, '_pod_custom_fields', true );
            $presets_list = array();
            
            if ( ! empty( $existing ) && is_array( $existing ) ) {
                if ( ! empty( $existing['presets'] ) && is_array( $existing['presets'] ) ) {
                    // Already multi-preset format
                    $presets_list = $existing['presets'];
                } elseif ( ! empty( $existing['preset_id'] ) ) {
                    // Migrate legacy single-preset format to multi-preset
                    $presets_list = array( $existing );
                }
            }
            
            // Merge: replace if same preset_id, otherwise append
            $found = false;
            foreach ( $presets_list as $idx => $p ) {
                if ( isset( $p['preset_id'] ) && $p['preset_id'] === $new_preset['preset_id'] ) {
                    $presets_list[ $idx ] = $new_preset;
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) {
                $presets_list[] = $new_preset;
            }
            
            // Merge all fields from all presets
            $merged_fields = array();
            $total_extra_fee = 0;
            $any_fee_enabled = false;
            $fee_labels = array();
            $any_optional = false;
            $optional_labels = array();
            
            foreach ( $presets_list as $p ) {
                if ( ! empty( $p['fields'] ) && is_array( $p['fields'] ) ) {
                    $merged_fields = array_merge( $merged_fields, $p['fields'] );
                }
                if ( ! empty( $p['enable_extra_fee'] ) ) {
                    $any_fee_enabled = true;
                    $total_extra_fee += floatval( isset( $p['extra_fee'] ) ? $p['extra_fee'] : 0 );
                    if ( ! empty( $p['fee_label'] ) ) {
                        $fee_labels[] = $p['fee_label'];
                    }
                }
                if ( ! empty( $p['is_optional'] ) ) {
                    $any_optional = true;
                    if ( ! empty( $p['optional_label'] ) ) {
                        $optional_labels[] = $p['optional_label'];
                    }
                }
            }
            
            // Save combined data (backwards-compatible with renderer)
            $combined = array(
                'presets'          => $presets_list,
                'preset_id'        => $new_preset['preset_id'], // Last assigned (legacy compat)
                'preset_name'      => implode( ' + ', array_filter( array_map( function( $p ) {
                    return isset( $p['preset_name'] ) ? $p['preset_name'] : '';
                }, $presets_list ) ) ),
                'fields'           => $merged_fields,
                'enable_extra_fee' => $any_fee_enabled,
                'extra_fee'        => strval( $total_extra_fee ),
                'fee_label'        => ! empty( $fee_labels ) ? implode( ' + ', array_unique( $fee_labels ) ) : 'Personalization Fee',
                'is_optional'      => $any_optional,
                'optional_label'   => ! empty( $optional_labels ) ? $optional_labels[0] : 'Add Personalization',
            );
            
            update_post_meta( $product_id, '_pod_custom_fields', $combined );
            
            $results[] = array( 'id' => $product_id, 'success' => true );
            
        } catch ( Exception $e ) {
            $results[] = array( 'id' => $product_id, 'success' => false, 'error' => $e->getMessage() );
        }
    }
    
    return rest_ensure_response( array( 'success' => true, 'results' => $results ) );
}

/**
 * Remove custom fields from products
 */
function sac_remove_custom_fields( $request ) {
    $params = $request->get_json_params();
    $product_ids = isset( $params['product_ids'] ) ? array_map( 'intval', $params['product_ids'] ) : array();
    
    if ( empty( $product_ids ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'No product IDs provided' ) );
    }
    
    $results = array();
    
    foreach ( $product_ids as $product_id ) {
        try {
            // Verify product exists
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                $results[] = array( 'id' => $product_id, 'success' => false, 'error' => 'Product not found' );
                continue;
            }
            
            // Delete custom fields meta
            delete_post_meta( $product_id, '_pod_custom_fields' );
            
            $results[] = array( 'id' => $product_id, 'success' => true );
            
        } catch ( Exception $e ) {
            $results[] = array( 'id' => $product_id, 'success' => false, 'error' => $e->getMessage() );
        }
    }
    
    return rest_ensure_response( array( 'success' => true, 'results' => $results ) );
}


/**
 * Get POD variation preset name from product
 */
function sac_get_pod_variation_preset_name( $product_id ) {
    $pod_variations = get_post_meta( $product_id, '_pod_variations', true );
    if ( ! empty( $pod_variations ) && isset( $pod_variations['preset_name'] ) ) {
        return $pod_variations['preset_name'];
    }
    return null;
}

/**
 * Assign POD variations to products
 */
function sac_assign_pod_variations( $request ) {
    $params = $request->get_json_params();
    $product_ids = isset( $params['product_ids'] ) ? array_map( 'intval', $params['product_ids'] ) : array();
    $preset = isset( $params['preset'] ) ? $params['preset'] : null;
    
    if ( empty( $product_ids ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'No product IDs provided' ) );
    }
    
    if ( empty( $preset ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'No preset provided' ) );
    }
    
    $results = array();
    
    foreach ( $product_ids as $product_id ) {
        try {
            // Verify product exists
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                $results[] = array( 'id' => $product_id, 'success' => false, 'error' => 'Product not found' );
                continue;
            }
            
            // Save POD variations config to product meta
            $pod_variations_data = array(
                'preset_id' => isset( $preset['preset_id'] ) ? sanitize_text_field( $preset['preset_id'] ) : '',
                'preset_name' => isset( $preset['preset_name'] ) ? sanitize_text_field( $preset['preset_name'] ) : '',
                'useSharedSizesColors' => isset( $preset['useSharedSizesColors'] ) ? (bool) $preset['useSharedSizesColors'] : true,
                'styles' => isset( $preset['styles'] ) ? $preset['styles'] : array(),
                'sizes' => isset( $preset['sizes'] ) ? $preset['sizes'] : array(),
                'colors' => isset( $preset['colors'] ) ? $preset['colors'] : array(),
            );
            
            update_post_meta( $product_id, '_pod_variations', $pod_variations_data );
            
            // Clear any cache for this product
            if ( function_exists( 'clean_post_cache' ) ) {
                clean_post_cache( $product_id );
            }
            if ( function_exists( 'wc_delete_product_transients' ) ) {
                wc_delete_product_transients( $product_id );
            }
            
            $results[] = array( 'id' => $product_id, 'success' => true );
            
        } catch ( Exception $e ) {
            $results[] = array( 'id' => $product_id, 'success' => false, 'error' => $e->getMessage() );
        }
    }
    
    return rest_ensure_response( array( 'success' => true, 'results' => $results ) );
}

/**
 * Remove POD variations from products
 */
function sac_remove_pod_variations( $request ) {
    $params = $request->get_json_params();
    $product_ids = isset( $params['product_ids'] ) ? array_map( 'intval', $params['product_ids'] ) : array();
    
    if ( empty( $product_ids ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'No product IDs provided' ) );
    }
    
    $results = array();
    
    foreach ( $product_ids as $product_id ) {
        try {
            // Verify product exists
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                $results[] = array( 'id' => $product_id, 'success' => false, 'error' => 'Product not found' );
                continue;
            }
            
            // Delete POD variations meta
            delete_post_meta( $product_id, '_pod_variations' );
            
            $results[] = array( 'id' => $product_id, 'success' => true );
            
        } catch ( Exception $e ) {
            $results[] = array( 'id' => $product_id, 'success' => false, 'error' => $e->getMessage() );
        }
    }
    
    return rest_ensure_response( array( 'success' => true, 'results' => $results ) );
}

/**
 * Update product gallery images
 */
function sac_update_product_gallery( $request ) {
    $params = $request->get_json_params();
    $product_id = isset( $params['product_id'] ) ? intval( $params['product_id'] ) : 0;
    $gallery_image_ids = isset( $params['gallery_image_ids'] ) ? array_map( 'intval', $params['gallery_image_ids'] ) : array();
    $append = isset( $params['append'] ) ? (bool) $params['append'] : true;
    
    // Debug logging
    error_log( 'POD Gallery Update - Product ID: ' . $product_id );
    error_log( 'POD Gallery Update - Gallery IDs received: ' . print_r( $gallery_image_ids, true ) );
    error_log( 'POD Gallery Update - Append mode: ' . ( $append ? 'true' : 'false' ) );
    
    if ( empty( $product_id ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'Product ID is required' ) );
    }
    
    $product = wc_get_product( $product_id );
    if ( ! $product ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'Product not found' ) );
    }
    
    try {
        if ( $append ) {
            // Append to existing gallery
            $existing_gallery = $product->get_gallery_image_ids();
            error_log( 'POD Gallery Update - Existing gallery: ' . print_r( $existing_gallery, true ) );
            $new_gallery = array_unique( array_merge( $existing_gallery, $gallery_image_ids ) );
        } else {
            // Replace gallery
            $new_gallery = $gallery_image_ids;
        }
        
        error_log( 'POD Gallery Update - New gallery to save: ' . print_r( $new_gallery, true ) );
        
        // Validate that all IDs are valid attachments
        $valid_ids = array();
        foreach ( $new_gallery as $img_id ) {
            if ( wp_attachment_is_image( $img_id ) ) {
                $valid_ids[] = $img_id;
            } else {
                error_log( 'POD Gallery Update - Invalid attachment ID: ' . $img_id );
            }
        }
        
        error_log( 'POD Gallery Update - Valid IDs after validation: ' . print_r( $valid_ids, true ) );
        
        $product->set_gallery_image_ids( $valid_ids );
        $product->save();
        
        // Verify the save
        $saved_gallery = $product->get_gallery_image_ids();
        error_log( 'POD Gallery Update - Gallery after save: ' . print_r( $saved_gallery, true ) );
        
        // Clear product cache to ensure changes are visible immediately
        wc_delete_product_transients( $product_id );
        wp_cache_delete( 'product-' . $product_id, 'products' );
        
        return rest_ensure_response( array(
            'success' => true,
            'gallery_count' => count( $valid_ids ),
            'gallery_ids' => $valid_ids,
            'debug' => array(
                'received' => count( $gallery_image_ids ),
                'valid' => count( $valid_ids ),
                'saved' => count( $saved_gallery )
            )
        ) );
        
    } catch ( Exception $e ) {
        error_log( 'POD Gallery Update - Error: ' . $e->getMessage() );
        return rest_ensure_response( array( 'success' => false, 'error' => $e->getMessage() ) );
    }
}

/**
 * Bulk update product galleries
 */
function sac_bulk_update_galleries( $request ) {
    $params = $request->get_json_params();
    $updates = isset( $params['updates'] ) ? $params['updates'] : array();
    $append = isset( $params['append'] ) ? (bool) $params['append'] : true;
    
    error_log( 'POD Bulk Gallery Update - Total updates: ' . count( $updates ) );
    error_log( 'POD Bulk Gallery Update - Append mode: ' . ( $append ? 'true' : 'false' ) );
    
    if ( empty( $updates ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'No updates provided' ) );
    }
    
    $results = array();
    
    foreach ( $updates as $update ) {
        $product_id = isset( $update['product_id'] ) ? intval( $update['product_id'] ) : 0;
        $gallery_image_ids = isset( $update['gallery_image_ids'] ) ? array_map( 'intval', $update['gallery_image_ids'] ) : array();
        
        error_log( 'POD Bulk Gallery Update - Product ' . $product_id . ' - Gallery IDs: ' . print_r( $gallery_image_ids, true ) );
        
        if ( empty( $product_id ) ) {
            $results[] = array( 'id' => $product_id, 'success' => false, 'error' => 'Invalid product ID' );
            continue;
        }
        
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            $results[] = array( 'id' => $product_id, 'success' => false, 'error' => 'Product not found' );
            continue;
        }
        
        try {
            if ( $append ) {
                $existing_gallery = $product->get_gallery_image_ids();
                error_log( 'POD Bulk Gallery Update - Product ' . $product_id . ' - Existing: ' . print_r( $existing_gallery, true ) );
                
                // Validate existing gallery too (clean invalid IDs)
                $valid_existing = array();
                foreach ( $existing_gallery as $img_id ) {
                    if ( wp_attachment_is_image( $img_id ) ) {
                        $valid_existing[] = $img_id;
                    } else {
                        error_log( 'POD Bulk Gallery Update - Product ' . $product_id . ' - Removing invalid existing ID: ' . $img_id );
                    }
                }
                
                $new_gallery = array_unique( array_merge( $valid_existing, $gallery_image_ids ) );
            } else {
                $new_gallery = $gallery_image_ids;
            }
            
            // Validate that all IDs are valid attachments
            $valid_ids = array();
            foreach ( $new_gallery as $img_id ) {
                if ( wp_attachment_is_image( $img_id ) ) {
                    $valid_ids[] = $img_id;
                } else {
                    error_log( 'POD Bulk Gallery Update - Product ' . $product_id . ' - Invalid attachment ID: ' . $img_id );
                }
            }
            
            error_log( 'POD Bulk Gallery Update - Product ' . $product_id . ' - Valid IDs: ' . print_r( $valid_ids, true ) );
            
            $product->set_gallery_image_ids( $valid_ids );
            $product->save();
            
            // Verify the save
            $saved_gallery = $product->get_gallery_image_ids();
            error_log( 'POD Bulk Gallery Update - Product ' . $product_id . ' - Saved gallery: ' . print_r( $saved_gallery, true ) );
            
            // Clear product cache to ensure changes are visible immediately
            wc_delete_product_transients( $product_id );
            wp_cache_delete( 'product-' . $product_id, 'products' );
            
            $results[] = array( 
                'id' => $product_id, 
                'success' => true, 
                'gallery_count' => count( $valid_ids ),
                'debug' => array(
                    'received' => count( $gallery_image_ids ),
                    'valid' => count( $valid_ids ),
                    'saved' => count( $saved_gallery )
                )
            );
            
        } catch ( Exception $e ) {
            error_log( 'POD Bulk Gallery Update - Product ' . $product_id . ' - Error: ' . $e->getMessage() );
            $results[] = array( 'id' => $product_id, 'success' => false, 'error' => $e->getMessage() );
        }
    }
    
    return rest_ensure_response( array( 'success' => true, 'results' => $results ) );
}


/**
 * Clear product gallery images
 */
function sac_clear_product_gallery( $request ) {
    $params = $request->get_json_params();
    $product_ids = isset( $params['product_ids'] ) ? array_map( 'intval', $params['product_ids'] ) : array();
    
    if ( empty( $product_ids ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'No product IDs provided' ) );
    }
    
    $results = array();
    
    foreach ( $product_ids as $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            $results[] = array( 'id' => $product_id, 'success' => false, 'error' => 'Product not found' );
            continue;
        }
        
        try {
            // Clear gallery
            $product->set_gallery_image_ids( array() );
            $product->save();
            
            $results[] = array( 'id' => $product_id, 'success' => true );
            
        } catch ( Exception $e ) {
            $results[] = array( 'id' => $product_id, 'success' => false, 'error' => $e->getMessage() );
        }
    }
    
    return rest_ensure_response( array( 'success' => true, 'results' => $results ) );
}

/**
 * Create WordPress page (for AI Page Builder)
 */
function sac_create_page( $request ) {
    global $wpdb;
    
    $params = $request->get_json_params();
    
    $title = isset( $params['title'] ) ? sanitize_text_field( $params['title'] ) : '';
    $content = isset( $params['content'] ) ? $params['content'] : '';
    $slug = isset( $params['slug'] ) ? sanitize_title( $params['slug'] ) : '';
    $status = isset( $params['status'] ) ? $params['status'] : 'draft';
    $meta_description = isset( $params['meta_description'] ) ? sanitize_text_field( $params['meta_description'] ) : '';
    
    if ( empty( $title ) || empty( $content ) ) {
        return rest_ensure_response( array( 'success' => false, 'error' => 'Title and content are required' ) );
    }
    
    // Remove all WordPress content filters to prevent shortcode encoding
    remove_all_filters( 'content_save_pre' );
    remove_all_filters( 'wp_insert_post_data' );
    
    // Temporarily disable kses filtering
    kses_remove_filters();
    
    // Create page with minimal data first
    $page_data = array(
        'post_type'    => 'page',
        'post_title'   => $title,
        'post_content' => '',  // Empty first to avoid filters
        'post_status'  => $status,
        'post_name'    => $slug,
        'post_author'  => get_current_user_id(),
    );
    
    $page_id = wp_insert_post( $page_data, true );
    
    if ( is_wp_error( $page_id ) ) {
        kses_init_filters();
        return rest_ensure_response( array( 'success' => false, 'error' => $page_id->get_error_message() ) );
    }
    
    // Now update content directly in database to bypass ALL WordPress filters
    $wpdb->update(
        $wpdb->posts,
        array( 'post_content' => $content ),
        array( 'ID' => $page_id ),
        array( '%s' ),
        array( '%d' )
    );
    
    // Re-enable kses filtering
    kses_init_filters();
    
    // Set meta description for SEO
    if ( ! empty( $meta_description ) ) {
        update_post_meta( $page_id, '_yoast_wpseo_metadesc', $meta_description );
    }
    
    // Set page template to use Flatsome UX Builder
    update_post_meta( $page_id, '_wp_page_template', 'default' );
    
    // Force Classic Editor for this page
    update_post_meta( $page_id, '_flatsome_use_builder', 'yes' );
    update_post_meta( $page_id, 'classic-editor-remember', 'classic-editor' );
    
    // Clear cache
    clean_post_cache( $page_id );
    
    return rest_ensure_response( array(
        'success' => true,
        'id'      => $page_id,
        'link'    => get_permalink( $page_id ),
    ) );
}
