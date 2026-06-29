<?php
/**
 * POD LLMS.txt Generator
 * Generate llms.txt file for AI crawlers (ChatGPT, Claude, Perplexity, Gemini)
 * 
 * @package POD_AI_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class POD_LLMS_Txt {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register REST API endpoints
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        
        // Serve llms.txt at root
        add_action( 'init', array( $this, 'register_rewrite_rules' ) );
        add_action( 'template_redirect', array( $this, 'serve_llms_txt' ) );
        
        // Admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }
    
    /**
     * Register rewrite rules for llms.txt
     */
    public function register_rewrite_rules() {
        add_rewrite_rule( '^llms\.txt$', 'index.php?llms_txt=1', 'top' );
        add_filter( 'query_vars', function( $vars ) {
            $vars[] = 'llms_txt';
            return $vars;
        } );
    }
    
    /**
     * Serve llms.txt file
     */
    public function serve_llms_txt() {
        if ( get_query_var( 'llms_txt' ) ) {
            header( 'Content-Type: text/plain; charset=utf-8' );
            header( 'X-Robots-Tag: noindex' );
            echo $this->generate_llms_txt();
            exit;
        }
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route( 'sac/v1', '/llms-txt/generate', array(
            'methods' => 'POST',
            'callback' => array( $this, 'api_generate_llms_txt' ),
            'permission_callback' => array( $this, 'check_api_permission' ),
        ) );
        
        register_rest_route( 'sac/v1', '/llms-txt/preview', array(
            'methods' => 'GET',
            'callback' => array( $this, 'api_preview_llms_txt' ),
            'permission_callback' => array( $this, 'check_api_permission' ),
        ) );
        
        register_rest_route( 'sac/v1', '/llms-txt/settings', array(
            'methods' => 'GET',
            'callback' => array( $this, 'api_get_settings' ),
            'permission_callback' => array( $this, 'check_api_permission' ),
        ) );
        
        register_rest_route( 'sac/v1', '/llms-txt/settings', array(
            'methods' => 'POST',
            'callback' => array( $this, 'api_save_settings' ),
            'permission_callback' => array( $this, 'check_api_permission' ),
        ) );
    }
    
    /**
     * Check API permission
     */
    public function check_api_permission() {
        // Check nonce from header
        $nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? $_SERVER['HTTP_X_WP_NONCE'] : '';
        if ( wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return true;
        }
        
        // Allow if user is logged in as admin
        return current_user_can( 'manage_options' );
    }
    
    /**
     * API: Generate llms.txt
     */
    public function api_generate_llms_txt( $request ) {
        $params = $request->get_json_params();
        $content = $this->generate_llms_txt( $params );
        
        // Optionally save to file
        if ( ! empty( $params['save_to_file'] ) ) {
            $result = $this->save_llms_txt_file( $content );
            if ( is_wp_error( $result ) ) {
                return new WP_REST_Response( array(
                    'success' => false,
                    'message' => $result->get_error_message(),
                ), 500 );
            }
        }
        
        return new WP_REST_Response( array(
            'success' => true,
            'content' => $content,
            'url' => home_url( '/llms.txt' ),
        ) );
    }
    
    /**
     * API: Preview llms.txt
     */
    public function api_preview_llms_txt( $request ) {
        $content = $this->generate_llms_txt();
        
        return new WP_REST_Response( array(
            'success' => true,
            'content' => $content,
            'url' => home_url( '/llms.txt' ),
        ) );
    }
    
    /**
     * API: Get settings
     */
    public function api_get_settings( $request ) {
        return new WP_REST_Response( array(
            'success' => true,
            'settings' => $this->get_settings(),
        ) );
    }
    
    /**
     * API: Save settings
     */
    public function api_save_settings( $request ) {
        $params = $request->get_json_params();
        
        if ( isset( $params['site_description'] ) ) {
            update_option( 'pod_llms_site_description', sanitize_textarea_field( $params['site_description'] ) );
        }
        if ( isset( $params['include_products'] ) ) {
            update_option( 'pod_llms_include_products', (bool) $params['include_products'] );
        }
        if ( isset( $params['include_categories'] ) ) {
            update_option( 'pod_llms_include_categories', (bool) $params['include_categories'] );
        }
        if ( isset( $params['include_pages'] ) ) {
            update_option( 'pod_llms_include_pages', (bool) $params['include_pages'] );
        }
        if ( isset( $params['include_posts'] ) ) {
            update_option( 'pod_llms_include_posts', (bool) $params['include_posts'] );
        }
        if ( isset( $params['max_products'] ) ) {
            update_option( 'pod_llms_max_products', absint( $params['max_products'] ) );
        }
        if ( isset( $params['custom_sections'] ) ) {
            update_option( 'pod_llms_custom_sections', sanitize_textarea_field( $params['custom_sections'] ) );
        }
        if ( isset( $params['contact_info'] ) ) {
            update_option( 'pod_llms_contact_info', sanitize_textarea_field( $params['contact_info'] ) );
        }
        
        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'Settings saved',
        ) );
    }
    
    /**
     * Get settings
     */
    private function get_settings() {
        return array(
            'site_description' => get_option( 'pod_llms_site_description', '' ),
            'include_products' => get_option( 'pod_llms_include_products', true ),
            'include_categories' => get_option( 'pod_llms_include_categories', true ),
            'include_pages' => get_option( 'pod_llms_include_pages', true ),
            'include_posts' => get_option( 'pod_llms_include_posts', false ),
            'max_products' => get_option( 'pod_llms_max_products', 50 ),
            'custom_sections' => get_option( 'pod_llms_custom_sections', '' ),
            'contact_info' => get_option( 'pod_llms_contact_info', '' ),
        );
    }
    
    /**
     * Generate llms.txt content
     */
    public function generate_llms_txt( $params = array() ) {
        $settings = $this->get_settings();
        $settings = wp_parse_args( $params, $settings );
        
        $site_name = get_bloginfo( 'name' );
        $site_url = home_url();
        $site_description = ! empty( $settings['site_description'] ) 
            ? $settings['site_description'] 
            : get_bloginfo( 'description' );
        
        $output = "# {$site_name}\n\n";
        $output .= "> {$site_description}\n\n";
        
        // About section
        $output .= $this->generate_about_section( $settings );
        
        // Products section
        if ( $settings['include_products'] && function_exists( 'wc_get_products' ) ) {
            $output .= $this->generate_products_section( $settings );
        }
        
        // Categories section
        if ( $settings['include_categories'] && function_exists( 'wc_get_products' ) ) {
            $output .= $this->generate_categories_section();
        }
        
        // Pages section
        if ( $settings['include_pages'] ) {
            $output .= $this->generate_pages_section();
        }
        
        // Blog posts section (optional)
        if ( $settings['include_posts'] ) {
            $output .= $this->generate_posts_section();
        }
        
        // Custom sections
        if ( ! empty( $settings['custom_sections'] ) ) {
            $output .= "\n" . $settings['custom_sections'] . "\n";
        }
        
        // Contact info
        if ( ! empty( $settings['contact_info'] ) ) {
            $output .= "\n## Contact\n\n" . $settings['contact_info'] . "\n";
        }
        
        return $output;
    }
    
    /**
     * Generate About section
     */
    private function generate_about_section( $settings ) {
        $output = "## About\n\n";
        $output .= "- [Homepage](" . home_url() . ")\n";
        
        // Shop page
        if ( function_exists( 'wc_get_page_id' ) ) {
            $shop_page_id = wc_get_page_id( 'shop' );
            if ( $shop_page_id > 0 ) {
                $output .= "- [Shop](" . get_permalink( $shop_page_id ) . ")\n";
            }
        }
        
        return $output . "\n";
    }
    
    /**
     * Generate Products section
     */
    private function generate_products_section( $settings ) {
        $max_products = isset( $settings['max_products'] ) ? absint( $settings['max_products'] ) : 50;
        
        $products = wc_get_products( array(
            'status' => 'publish',
            'limit' => $max_products,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );
        
        if ( empty( $products ) ) {
            return '';
        }
        
        $output = "## Products\n\n";
        
        foreach ( $products as $product ) {
            $name = $product->get_name();
            $url = get_permalink( $product->get_id() );
            $price = $product->get_price();
            $currency = get_woocommerce_currency_symbol();
            
            // Get short description or truncate description
            $desc = $product->get_short_description();
            if ( empty( $desc ) ) {
                $desc = wp_strip_all_tags( $product->get_description() );
            }
            $desc = wp_trim_words( $desc, 20, '...' );
            
            $output .= "- [{$name}]({$url})";
            if ( $price ) {
                $output .= " - {$currency}{$price}";
            }
            if ( $desc ) {
                $output .= ": {$desc}";
            }
            $output .= "\n";
        }
        
        return $output . "\n";
    }
    
    /**
     * Generate Categories section
     */
    private function generate_categories_section() {
        $categories = get_terms( array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'parent' => 0, // Top-level only
        ) );
        
        if ( empty( $categories ) || is_wp_error( $categories ) ) {
            return '';
        }
        
        $output = "## Product Categories\n\n";
        
        foreach ( $categories as $cat ) {
            $url = get_term_link( $cat );
            $count = $cat->count;
            $output .= "- [{$cat->name}]({$url}) ({$count} products)\n";
            
            // Get subcategories
            $subcats = get_terms( array(
                'taxonomy' => 'product_cat',
                'hide_empty' => true,
                'parent' => $cat->term_id,
            ) );
            
            if ( ! empty( $subcats ) && ! is_wp_error( $subcats ) ) {
                foreach ( $subcats as $subcat ) {
                    $sub_url = get_term_link( $subcat );
                    $output .= "  - [{$subcat->name}]({$sub_url}) ({$subcat->count} products)\n";
                }
            }
        }
        
        return $output . "\n";
    }
    
    /**
     * Generate Pages section
     */
    private function generate_pages_section() {
        $pages = get_pages( array(
            'post_status' => 'publish',
            'sort_column' => 'menu_order',
            'number' => 20,
        ) );
        
        if ( empty( $pages ) ) {
            return '';
        }
        
        // Filter out WooCommerce system pages
        $exclude_slugs = array( 'cart', 'checkout', 'my-account', 'shop' );
        
        $output = "## Pages\n\n";
        
        foreach ( $pages as $page ) {
            if ( in_array( $page->post_name, $exclude_slugs ) ) {
                continue;
            }
            
            $url = get_permalink( $page->ID );
            $output .= "- [{$page->post_title}]({$url})\n";
        }
        
        return $output . "\n";
    }
    
    /**
     * Generate Posts section (optional)
     */
    private function generate_posts_section() {
        $posts = get_posts( array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
        ) );
        
        if ( empty( $posts ) ) {
            return '';
        }
        
        $output = "## Optional\n\n";
        $output .= "### Recent Blog Posts\n\n";
        
        foreach ( $posts as $post ) {
            $url = get_permalink( $post->ID );
            $date = get_the_date( 'Y-m-d', $post->ID );
            $output .= "- [{$post->post_title}]({$url}) ({$date})\n";
        }
        
        return $output . "\n";
    }
    
    /**
     * Save llms.txt to file
     */
    private function save_llms_txt_file( $content ) {
        $file_path = ABSPATH . 'llms.txt';
        
        if ( ! is_writable( ABSPATH ) ) {
            return new WP_Error( 'not_writable', 'Root directory is not writable' );
        }
        
        $result = file_put_contents( $file_path, $content );
        
        if ( $result === false ) {
            return new WP_Error( 'write_failed', 'Failed to write llms.txt file' );
        }
        
        return true;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'pod-ai-connector',
            __( 'LLMS.txt', 'pod-ai-connector' ),
            __( 'LLMS.txt', 'pod-ai-connector' ),
            'manage_options',
            'pod-llms-txt',
            array( $this, 'render_admin_page' )
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'pod_llms_settings', 'pod_llms_site_description' );
        register_setting( 'pod_llms_settings', 'pod_llms_include_products' );
        register_setting( 'pod_llms_settings', 'pod_llms_include_categories' );
        register_setting( 'pod_llms_settings', 'pod_llms_include_pages' );
        register_setting( 'pod_llms_settings', 'pod_llms_include_posts' );
        register_setting( 'pod_llms_settings', 'pod_llms_max_products' );
        register_setting( 'pod_llms_settings', 'pod_llms_custom_sections' );
        register_setting( 'pod_llms_settings', 'pod_llms_contact_info' );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $settings = $this->get_settings();
        $preview = $this->generate_llms_txt();
        $llms_url = home_url( '/llms.txt' );
        ?>
        <div class="wrap">
            <h1><?php _e( 'LLMS.txt Generator', 'pod-ai-connector' ); ?></h1>
            <p><?php _e( 'Generate llms.txt file to help AI systems (ChatGPT, Claude, Perplexity, Gemini) understand your website.', 'pod-ai-connector' ); ?></p>
            
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <!-- Settings Form -->
                <div style="flex: 1; min-width: 400px;">
                    <div class="card" style="padding: 20px;">
                        <h2><?php _e( 'Settings', 'pod-ai-connector' ); ?></h2>
                        
                        <form method="post" action="options.php">
                            <?php settings_fields( 'pod_llms_settings' ); ?>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php _e( 'Site Description', 'pod-ai-connector' ); ?></th>
                                    <td>
                                        <textarea name="pod_llms_site_description" rows="3" class="large-text"><?php echo esc_textarea( $settings['site_description'] ); ?></textarea>
                                        <p class="description"><?php _e( 'Brief description of your website for AI systems.', 'pod-ai-connector' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e( 'Include Sections', 'pod-ai-connector' ); ?></th>
                                    <td>
                                        <label><input type="checkbox" name="pod_llms_include_products" value="1" <?php checked( $settings['include_products'] ); ?> /> <?php _e( 'Products', 'pod-ai-connector' ); ?></label><br>
                                        <label><input type="checkbox" name="pod_llms_include_categories" value="1" <?php checked( $settings['include_categories'] ); ?> /> <?php _e( 'Product Categories', 'pod-ai-connector' ); ?></label><br>
                                        <label><input type="checkbox" name="pod_llms_include_pages" value="1" <?php checked( $settings['include_pages'] ); ?> /> <?php _e( 'Pages', 'pod-ai-connector' ); ?></label><br>
                                        <label><input type="checkbox" name="pod_llms_include_posts" value="1" <?php checked( $settings['include_posts'] ); ?> /> <?php _e( 'Blog Posts (Optional section)', 'pod-ai-connector' ); ?></label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e( 'Max Products', 'pod-ai-connector' ); ?></th>
                                    <td>
                                        <input type="number" name="pod_llms_max_products" value="<?php echo esc_attr( $settings['max_products'] ); ?>" min="10" max="200" class="small-text" />
                                        <p class="description"><?php _e( 'Maximum number of products to include.', 'pod-ai-connector' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e( 'Contact Info', 'pod-ai-connector' ); ?></th>
                                    <td>
                                        <textarea name="pod_llms_contact_info" rows="3" class="large-text"><?php echo esc_textarea( $settings['contact_info'] ); ?></textarea>
                                        <p class="description"><?php _e( 'Contact information (email, phone, address).', 'pod-ai-connector' ); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php _e( 'Custom Sections', 'pod-ai-connector' ); ?></th>
                                    <td>
                                        <textarea name="pod_llms_custom_sections" rows="5" class="large-text" placeholder="## Shipping&#10;&#10;- Free shipping on orders over $50&#10;- Ships within 2-3 business days"><?php echo esc_textarea( $settings['custom_sections'] ); ?></textarea>
                                        <p class="description"><?php _e( 'Add custom markdown sections (shipping, returns, FAQs, etc.).', 'pod-ai-connector' ); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <?php submit_button( __( 'Save Settings', 'pod-ai-connector' ) ); ?>
                        </form>
                    </div>
                    
                    <div class="card" style="padding: 20px; margin-top: 20px;">
                        <h2><?php _e( 'File URL', 'pod-ai-connector' ); ?></h2>
                        <code style="display: block; padding: 10px; background: #f0f0f0; word-break: break-all;">
                            <?php echo esc_url( $llms_url ); ?>
                        </code>
                        <p style="margin-top: 10px;">
                            <a href="<?php echo esc_url( $llms_url ); ?>" target="_blank" class="button"><?php _e( 'View llms.txt', 'pod-ai-connector' ); ?></a>
                            <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_url( $llms_url ); ?>'); alert('Copied!');"><?php _e( 'Copy URL', 'pod-ai-connector' ); ?></button>
                        </p>
                    </div>
                </div>
                
                <!-- Preview -->
                <div style="flex: 1; min-width: 400px;">
                    <div class="card" style="padding: 20px;">
                        <h2><?php _e( 'Preview', 'pod-ai-connector' ); ?></h2>
                        <pre style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 5px; overflow: auto; max-height: 600px; font-size: 12px; line-height: 1.5;"><?php echo esc_html( $preview ); ?></pre>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialize
new POD_LLMS_Txt();
