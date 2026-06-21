<?php
/**
 * POD AI Connector - GMC Compliance Scanner
 * Scan and fix site for Google Merchant Center compliance
 * 
 * @package suspended POD_AI_Connector
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class POD_GMC_Compliance {
    
    /**
     * Required pages for GMC compliance
     */
    private $required_pages = array(
        'about' => array(
            'slugs' => array('about', 'about-us', 'about-me', 'who-we-are'),
            'title' => 'About Us',
            'required' => true
        ),
        'contact' => array(
            'slugs' => array('contact', 'contact-us', 'get-in-touch'),
            'title' => 'Contact Us',
            'required' => true
        ),
        'privacy' => array(
            'slugs' => array('privacy', 'privacy-policy', 'privacy-notice'),
            'title' => 'Privacy Policy',
            'required' => true
        ),
        'terms' => array(
            'slugs' => array('terms', 'terms-of-service', 'terms-and-conditions', 'tos'),
            'title' => 'Terms of Service',
            'required' => true
        ),
        'shipping' => array(
            'slugs' => array('shipping', 'shipping-policy', 'delivery', 'delivery-policy'),
            'title' => 'Shipping Policy',
            'required' => true
        ),
        'returns' => array(
            'slugs' => array('returns', 'return-policy', 'refund', 'refund-policy', 'returns-refunds'),
            'title' => 'Return & Refund Policy',
            'required' => true
        ),
        'faq' => array(
            'slugs' => array('faq', 'faqs', 'frequently-asked-questions', 'help'),
            'title' => 'FAQ',
            'required' => false
        )
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('sac/v1', '/gmc-scan', array(
            'methods' => 'GET',
            'callback' => array($this, 'scan_site'),
            'permission_callback' => 'sac_verify_simple'
        ));
        
        register_rest_route('sac/v1', '/gmc-create-page', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_page'),
            'permission_callback' => 'sac_verify'
        ));
        
        register_rest_route('sac/v1', '/gmc-update-products', array(
            'methods' => 'POST',
            'callback' => array($this, 'bulk_update_products'),
            'permission_callback' => 'sac_verify'
        ));
    }
    
    /**
     * Check API permission - REMOVED, using global sac_verify functions
     */
    
    /**
     * Scan site for GMC compliance
     */
    public function scan_site($request) {
        $result = array(
            'pages' => $this->scan_pages(),
            'products' => $this->scan_products(),
            'site_info' => $this->scan_site_info(),
            'score' => 0,
            'scanned_at' => current_time('c')
        );
        
        // Calculate score
        $result['score'] = $this->calculate_score($result);
        
        return rest_ensure_response($result);
    }
    
    /**
     * Scan required pages
     */
    private function scan_pages() {
        $pages_status = array();
        
        foreach ($this->required_pages as $key => $config) {
            $found_page = null;
            
            // Search by slug
            foreach ($config['slugs'] as $slug) {
                $page = get_page_by_path($slug);
                if ($page && $page->post_status === 'publish') {
                    $found_page = $page;
                    break;
                }
            }
            
            // If not found by slug, search by title
            if (!$found_page) {
                $pages = get_posts(array(
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'title' => $config['title'],
                    'posts_per_page' => 1
                ));
                if (!empty($pages)) {
                    $found_page = $pages[0];
                }
            }
            
            // Also search with partial title match
            if (!$found_page) {
                global $wpdb;
                $like_title = '%' . $wpdb->esc_like($key) . '%';
                $page_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} 
                    WHERE post_type = 'page' 
                    AND post_status = 'publish' 
                    AND (post_title LIKE %s OR post_name LIKE %s)
                    LIMIT 1",
                    $like_title, $like_title
                ));
                if ($page_id) {
                    $found_page = get_post($page_id);
                }
            }
            
            $pages_status[$key] = array(
                'key' => $key,
                'title' => $config['title'],
                'required' => $config['required'],
                'found' => $found_page !== null,
                'page_id' => $found_page ? $found_page->ID : null,
                'page_title' => $found_page ? $found_page->post_title : null,
                'page_url' => $found_page ? get_permalink($found_page->ID) : null,
                'content_length' => $found_page ? strlen($found_page->post_content) : 0
            );
        }
        
        return $pages_status;
    }
    
    /**
     * Scan products for compliance
     */
    private function scan_products() {
        if (!class_exists('WooCommerce')) {
            return array(
                'total' => 0,
                'woocommerce_active' => false
            );
        }
        
        $products = wc_get_products(array(
            'status' => 'publish',
            'limit' => -1,
            'return' => 'ids'
        ));
        
        $total = count($products);
        $missing_description = array();
        $invalid_price = array();
        $low_quality_images = array();
        $with_description = 0;
        $with_valid_price = 0;
        $with_quality_images = 0;
        
        foreach ($products as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;
            
            // Check description
            $description = $product->get_description();
            $short_description = $product->get_short_description();
            if (strlen($description) > 50 || strlen($short_description) > 30) {
                $with_description++;
            } else {
                $missing_description[] = array(
                    'id' => $product_id,
                    'name' => $product->get_name(),
                    'description_length' => strlen($description),
                    'short_description_length' => strlen($short_description)
                );
            }
            
            // Check price
            $price = $product->get_price();
            if ($price && floatval($price) > 0) {
                $with_valid_price++;
            } else {
                $invalid_price[] = array(
                    'id' => $product_id,
                    'name' => $product->get_name(),
                    'price' => $price
                );
            }
            
            // Check image quality
            $image_id = $product->get_image_id();
            if ($image_id) {
                $image_data = wp_get_attachment_image_src($image_id, 'full');
                if ($image_data && $image_data[1] >= 500 && $image_data[2] >= 500) {
                    $with_quality_images++;
                } else {
                    $low_quality_images[] = array(
                        'id' => $product_id,
                        'name' => $product->get_name(),
                        'image_width' => $image_data ? $image_data[1] : 0,
                        'image_height' => $image_data ? $image_data[2] : 0
                    );
                }
            } else {
                $low_quality_images[] = array(
                    'id' => $product_id,
                    'name' => $product->get_name(),
                    'image_width' => 0,
                    'image_height' => 0,
                    'no_image' => true
                );
            }
        }
        
        return array(
            'woocommerce_active' => true,
            'total' => $total,
            'with_description' => $with_description,
            'with_valid_price' => $with_valid_price,
            'with_quality_images' => $with_quality_images,
            'missing_description' => array_slice($missing_description, 0, 50), // Limit to 50
            'missing_description_count' => count($missing_description),
            'invalid_price' => array_slice($invalid_price, 0, 50),
            'invalid_price_count' => count($invalid_price),
            'low_quality_images' => array_slice($low_quality_images, 0, 50),
            'low_quality_images_count' => count($low_quality_images)
        );
    }
    
    /**
     * Scan site info
     */
    private function scan_site_info() {
        $site_url = get_site_url();
        $is_ssl = is_ssl() || strpos($site_url, 'https://') === 0;
        
        // Get site variables if available
        $site_variables = get_option('pod_site_variables', array());
        
        return array(
            'ssl' => $is_ssl,
            'site_url' => $site_url,
            'site_name' => get_bloginfo('name'),
            'site_description' => get_bloginfo('description'),
            'admin_email' => get_option('admin_email'),
            'site_variables' => $site_variables,
            'has_shop_name' => !empty($site_variables['shop_name']),
            'has_email' => !empty($site_variables['email']) || !empty(get_option('admin_email')),
            'has_phone' => !empty($site_variables['phone']),
            'has_address' => !empty($site_variables['address'])
        );
    }
    
    /**
     * Calculate compliance score
     */
    private function calculate_score($result) {
        $score = 0;
        $max_score = 100;
        
        // Pages score (60 points total)
        $required_pages = 0;
        $found_required = 0;
        foreach ($result['pages'] as $page) {
            if ($page['required']) {
                $required_pages++;
                if ($page['found']) {
                    $found_required++;
                }
            }
        }
        if ($required_pages > 0) {
            $score += ($found_required / $required_pages) * 60;
        }
        
        // Products score (30 points total)
        if ($result['products']['total'] > 0) {
            $desc_ratio = $result['products']['with_description'] / $result['products']['total'];
            $price_ratio = $result['products']['with_valid_price'] / $result['products']['total'];
            $image_ratio = $result['products']['with_quality_images'] / $result['products']['total'];
            
            $score += $desc_ratio * 10;
            $score += $price_ratio * 10;
            $score += $image_ratio * 10;
        } else {
            $score += 30; // No products = full score for this section
        }
        
        // Site info score (10 points total)
        if ($result['site_info']['ssl']) $score += 4;
        if ($result['site_info']['has_email']) $score += 2;
        if ($result['site_info']['has_phone']) $score += 2;
        if ($result['site_info']['has_address']) $score += 2;
        
        return round($score);
    }
    
    /**
     * Create a new page
     */
    public function create_page($request) {
        $params = $request->get_json_params();
        
        $title = sanitize_text_field($params['title'] ?? '');
        $content = wp_kses_post($params['content'] ?? '');
        $slug = sanitize_title($params['slug'] ?? $title);
        $status = in_array($params['status'] ?? 'draft', array('draft', 'publish')) ? $params['status'] : 'draft';
        
        if (empty($title) || empty($content)) {
            return new WP_Error('missing_data', 'Title and content are required', array('status' => 400));
        }
        
        // Check if page with slug already exists
        $existing = get_page_by_path($slug);
        if ($existing) {
            return new WP_Error('page_exists', 'A page with this slug already exists', array('status' => 400));
        }
        
        $page_id = wp_insert_post(array(
            'post_title' => $title,
            'post_content' => $content,
            'post_name' => $slug,
            'post_status' => $status,
            'post_type' => 'page',
            'post_author' => 1
        ));
        
        if (is_wp_error($page_id)) {
            return $page_id;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'page_id' => $page_id,
            'page_url' => get_permalink($page_id),
            'edit_url' => admin_url('post.php?post=' . $page_id . '&action=edit')
        ));
    }
    
    /**
     * Bulk update products
     */
    public function bulk_update_products($request) {
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_not_active', 'WooCommerce is not active', array('status' => 400));
        }
        
        $params = $request->get_json_params();
        $products = $params['products'] ?? array();
        
        $updated = 0;
        $errors = array();
        
        foreach ($products as $product_data) {
            $product_id = intval($product_data['id'] ?? 0);
            if (!$product_id) continue;
            
            $product = wc_get_product($product_id);
            if (!$product) {
                $errors[] = "Product {$product_id} not found";
                continue;
            }
            
            // Update description
            if (isset($product_data['description'])) {
                $product->set_description(wp_kses_post($product_data['description']));
            }
            
            // Update short description
            if (isset($product_data['short_description'])) {
                $product->set_short_description(wp_kses_post($product_data['short_description']));
            }
            
            $product->save();
            $updated++;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'updated' => $updated,
            'errors' => $errors
        ));
    }
}

// Initialize
new POD_GMC_Compliance();
