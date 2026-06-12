<?php
/**
 * POD Content Manager
 * 
 * Manages pages and blocks content via API.
 * Supports Flatsome UX Builder, Gutenberg, and classic editor.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class POD_Content_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get all pages
        register_rest_route( 'sac/v1', '/content/pages', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'api_get_pages' ),
            'permission_callback' => 'sac_verify_simple',
        ) );
        
        // Get single page with full content
        register_rest_route( 'sac/v1', '/content/pages/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'api_get_page' ),
            'permission_callback' => 'sac_verify_simple',
        ) );
        
        // Update page content
        register_rest_route( 'sac/v1', '/content/pages/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'api_update_page' ),
            'permission_callback' => 'sac_verify',
        ) );
        
        // Get all UX Blocks (Flatsome)
        register_rest_route( 'sac/v1', '/content/blocks', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'api_get_blocks' ),
            'permission_callback' => 'sac_verify_simple',
        ) );
        
        // Get single block
        register_rest_route( 'sac/v1', '/content/blocks/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'api_get_block' ),
            'permission_callback' => 'sac_verify_simple',
        ) );
        
        // Update block content
        register_rest_route( 'sac/v1', '/content/blocks/(?P<id>\d+)', array(
            'methods'             => 'PUT',
            'callback'            => array( $this, 'api_update_block' ),
            'permission_callback' => 'sac_verify',
        ) );
        
        // Bulk search and replace
        register_rest_route( 'sac/v1', '/content/search-replace', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'api_search_replace' ),
            'permission_callback' => 'sac_verify',
        ) );
        
        // Get content stats
        register_rest_route( 'sac/v1', '/content/stats', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'api_get_stats' ),
            'permission_callback' => 'sac_verify_simple',
        ) );
        
        // SEO Check - Get all pages with SEO status
        register_rest_route( 'sac/v1', '/content/seo-check', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'api_seo_check' ),
            'permission_callback' => 'sac_verify_simple',
        ) );
        
        // Get SEO meta for a specific page
        register_rest_route( 'sac/v1', '/content/pages/(?P<id>\d+)/seo-meta', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'api_get_seo_meta' ),
            'permission_callback' => 'sac_verify_simple',
        ) );
        
        // Update SEO meta for a page
        register_rest_route( 'sac/v1', '/content/pages/(?P<id>\d+)/seo-meta', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'api_update_seo_meta' ),
            'permission_callback' => 'sac_verify',
        ) );
        
        // Upload media (base64)
        register_rest_route( 'sac/v1', '/content/upload-media', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'api_upload_media' ),
            'permission_callback' => 'sac_verify',
        ) );
    }
    
    /**
     * API: Get all pages
     */
    public function api_get_pages( $request ) {
        $per_page = $request->get_param( 'per_page' ) ?: 50;
        $page = $request->get_param( 'page' ) ?: 1;
        $status = $request->get_param( 'status' ) ?: 'any';
        $search = $request->get_param( 'search' ) ?: '';
        
        $args = array(
            'post_type'      => 'page',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => $status,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );
        
        if ( $search ) {
            $args['s'] = $search;
        }
        
        $query = new WP_Query( $args );
        $pages = array();
        
        foreach ( $query->posts as $post ) {
            $pages[] = $this->format_page( $post );
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'pages'   => $pages,
            'total'   => $query->found_posts,
            'pages_count' => $query->max_num_pages,
        ) );
    }
    
    /**
     * API: Get single page with full content
     */
    public function api_get_page( $request ) {
        $page_id = intval( $request->get_param( 'id' ) );
        $post = get_post( $page_id );
        
        if ( ! $post || $post->post_type !== 'page' ) {
            return new WP_Error( 'not_found', 'Page not found', array( 'status' => 404 ) );
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'page'    => $this->format_page( $post, true ),
        ) );
    }
    
    /**
     * API: Update page content
     */
    public function api_update_page( $request ) {
        $page_id = intval( $request->get_param( 'id' ) );
        $params = $request->get_json_params();
        
        $post = get_post( $page_id );
        if ( ! $post || $post->post_type !== 'page' ) {
            return new WP_Error( 'not_found', 'Page not found', array( 'status' => 404 ) );
        }
        
        $update_data = array( 'ID' => $page_id );
        
        if ( isset( $params['title'] ) ) {
            $update_data['post_title'] = sanitize_text_field( $params['title'] );
        }
        if ( isset( $params['content'] ) ) {
            $update_data['post_content'] = wp_kses_post( $params['content'] );
        }
        if ( isset( $params['status'] ) ) {
            $update_data['post_status'] = sanitize_text_field( $params['status'] );
        }
        
        $result = wp_update_post( $update_data, true );
        
        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'update_failed', $result->get_error_message(), array( 'status' => 500 ) );
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'page'    => $this->format_page( get_post( $page_id ), true ),
        ) );
    }
    
    /**
     * API: Get all UX Blocks (Flatsome)
     */
    public function api_get_blocks( $request ) {
        $per_page = $request->get_param( 'per_page' ) ?: 50;
        $page = $request->get_param( 'page' ) ?: 1;
        $search = $request->get_param( 'search' ) ?: '';
        
        // Check if Flatsome UX Blocks post type exists
        $post_type = post_type_exists( 'blocks' ) ? 'blocks' : 'wp_block';
        
        $args = array(
            'post_type'      => $post_type,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'any',
            'orderby'        => 'title',
            'order'          => 'ASC',
        );
        
        if ( $search ) {
            $args['s'] = $search;
        }
        
        $query = new WP_Query( $args );
        $blocks = array();
        
        foreach ( $query->posts as $post ) {
            $blocks[] = $this->format_block( $post );
        }
        
        return rest_ensure_response( array(
            'success'     => true,
            'blocks'      => $blocks,
            'total'       => $query->found_posts,
            'block_type'  => $post_type,
            'is_flatsome' => post_type_exists( 'blocks' ),
        ) );
    }
    
    /**
     * API: Get single block
     */
    public function api_get_block( $request ) {
        $block_id = intval( $request->get_param( 'id' ) );
        $post = get_post( $block_id );
        
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Block not found', array( 'status' => 404 ) );
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'block'   => $this->format_block( $post, true ),
        ) );
    }
    
    /**
     * API: Update block content
     */
    public function api_update_block( $request ) {
        $block_id = intval( $request->get_param( 'id' ) );
        $params = $request->get_json_params();
        
        $post = get_post( $block_id );
        if ( ! $post ) {
            return new WP_Error( 'not_found', 'Block not found', array( 'status' => 404 ) );
        }
        
        $update_data = array( 'ID' => $block_id );
        
        if ( isset( $params['title'] ) ) {
            $update_data['post_title'] = sanitize_text_field( $params['title'] );
        }
        if ( isset( $params['content'] ) ) {
            $update_data['post_content'] = wp_kses_post( $params['content'] );
        }
        
        $result = wp_update_post( $update_data, true );
        
        if ( is_wp_error( $result ) ) {
            return new WP_Error( 'update_failed', $result->get_error_message(), array( 'status' => 500 ) );
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'block'   => $this->format_block( get_post( $block_id ), true ),
        ) );
    }
    
    /**
     * API: Bulk search and replace in content
     */
    public function api_search_replace( $request ) {
        $params = $request->get_json_params();
        $search = isset( $params['search'] ) ? $params['search'] : '';
        $replace = isset( $params['replace'] ) ? $params['replace'] : '';
        $post_types = isset( $params['post_types'] ) ? $params['post_types'] : array( 'page', 'blocks' );
        $dry_run = isset( $params['dry_run'] ) ? (bool) $params['dry_run'] : true;
        
        if ( empty( $search ) ) {
            return rest_ensure_response( array( 'success' => false, 'error' => 'Search term is required' ) );
        }
        
        $results = array(
            'matched'  => array(),
            'replaced' => 0,
            'dry_run'  => $dry_run,
        );
        
        foreach ( $post_types as $post_type ) {
            if ( ! post_type_exists( $post_type ) ) continue;
            
            $posts = get_posts( array(
                'post_type'      => $post_type,
                'posts_per_page' => -1,
                'post_status'    => 'any',
            ) );
            
            foreach ( $posts as $post ) {
                $content = $post->post_content;
                $title = $post->post_title;
                
                $content_matches = substr_count( $content, $search );
                $title_matches = substr_count( $title, $search );
                
                if ( $content_matches > 0 || $title_matches > 0 ) {
                    $results['matched'][] = array(
                        'id'              => $post->ID,
                        'title'           => $title,
                        'type'            => $post_type,
                        'content_matches' => $content_matches,
                        'title_matches'   => $title_matches,
                    );
                    
                    if ( ! $dry_run ) {
                        $new_content = str_replace( $search, $replace, $content );
                        $new_title = str_replace( $search, $replace, $title );
                        
                        wp_update_post( array(
                            'ID'           => $post->ID,
                            'post_title'   => $new_title,
                            'post_content' => $new_content,
                        ) );
                        
                        $results['replaced'] += $content_matches + $title_matches;
                    }
                }
            }
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'results' => $results,
        ) );
    }
    
    /**
     * API: Get content stats
     */
    public function api_get_stats( $request ) {
        $stats = array(
            'pages'  => wp_count_posts( 'page' ),
            'posts'  => wp_count_posts( 'post' ),
            'blocks' => post_type_exists( 'blocks' ) ? wp_count_posts( 'blocks' ) : null,
            'wp_blocks' => wp_count_posts( 'wp_block' ),
            'is_flatsome' => post_type_exists( 'blocks' ),
            'theme' => wp_get_theme()->get( 'Name' ),
        );
        
        return rest_ensure_response( array(
            'success' => true,
            'stats'   => $stats,
        ) );
    }
    
    /**
     * Format page data for API response
     */
    private function format_page( $post, $include_content = false ) {
        $data = array(
            'id'         => $post->ID,
            'title'      => $post->post_title,
            'slug'       => $post->post_name,
            'status'     => $post->post_status,
            'permalink'  => get_permalink( $post->ID ),
            'modified'   => $post->post_modified,
            'parent'     => $post->post_parent,
            'menu_order' => $post->menu_order,
            'template'   => get_page_template_slug( $post->ID ),
        );
        
        if ( $include_content ) {
            $data['content'] = $post->post_content;
            $data['content_rendered'] = apply_filters( 'the_content', $post->post_content );
            $data['has_shortcodes'] = has_shortcode( $post->post_content, 'ux_banner' ) || 
                                      has_shortcode( $post->post_content, 'section' ) ||
                                      has_shortcode( $post->post_content, 'row' ) ||
                                      has_shortcode( $post->post_content, 'col' );
            $data['has_blocks'] = has_blocks( $post->post_content );
            
            // Extract shortcodes used
            $data['shortcodes_used'] = $this->extract_shortcodes( $post->post_content );
        }
        
        return $data;
    }
    
    /**
     * Format block data for API response
     */
    private function format_block( $post, $include_content = false ) {
        $data = array(
            'id'       => $post->ID,
            'title'    => $post->post_title,
            'slug'     => $post->post_name,
            'status'   => $post->post_status,
            'modified' => $post->post_modified,
            'type'     => $post->post_type,
        );
        
        if ( $include_content ) {
            $data['content'] = $post->post_content;
            $data['content_rendered'] = apply_filters( 'the_content', $post->post_content );
            $data['shortcodes_used'] = $this->extract_shortcodes( $post->post_content );
        }
        
        return $data;
    }
    
    /**
     * Extract shortcodes from content
     */
    private function extract_shortcodes( $content ) {
        $shortcodes = array();
        
        // Match all shortcodes
        preg_match_all( '/\[([a-zA-Z0-9_-]+)/', $content, $matches );
        
        if ( ! empty( $matches[1] ) ) {
            $shortcodes = array_unique( $matches[1] );
        }
        
        return array_values( $shortcodes );
    }
    
    /**
     * API: Check SEO status for all pages
     */
    public function api_seo_check( $request ) {
        $pages = get_posts( array(
            'post_type'      => 'page',
            'post_status'    => array( 'publish', 'draft' ),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        
        $results = array();
        
        foreach ( $pages as $page ) {
            $page_id = $page->ID;
            
            // Get current meta (try Yoast first, then custom)
            $og_title = get_post_meta( $page_id, '_yoast_wpseo_opengraph-title', true );
            if ( empty( $og_title ) ) {
                $og_title = get_post_meta( $page_id, '_og_title', true );
            }
            
            $og_description = get_post_meta( $page_id, '_yoast_wpseo_opengraph-description', true );
            if ( empty( $og_description ) ) {
                $og_description = get_post_meta( $page_id, '_og_description', true );
            }
            
            $og_image = get_post_meta( $page_id, '_yoast_wpseo_opengraph-image', true );
            if ( empty( $og_image ) ) {
                $og_image = get_post_meta( $page_id, '_og_image', true );
            }
            
            // Get featured image as fallback
            $featured_image = get_the_post_thumbnail_url( $page_id, 'large' );
            
            // Extract first image from content
            $first_image = null;
            if ( has_post_thumbnail( $page_id ) ) {
                $first_image = $featured_image;
            } else {
                preg_match( '/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $page->post_content, $matches );
                if ( ! empty( $matches[1] ) ) {
                    $first_image = $matches[1];
                }
            }
            
            // Check if SEO is complete
            $has_seo = ! empty( $og_title ) && 
                       ! empty( $og_description ) && 
                       ! empty( $og_image ) &&
                       strlen( $og_title ) > 10 &&
                       strlen( $og_description ) > 50;
            
            // Determine missing fields
            $missing_fields = array();
            if ( empty( $og_title ) || strlen( $og_title ) < 10 ) {
                $missing_fields[] = 'og:title';
            }
            if ( empty( $og_description ) || strlen( $og_description ) < 50 ) {
                $missing_fields[] = 'og:description';
            }
            if ( empty( $og_image ) ) {
                $missing_fields[] = 'og:image';
            }
            
            $results[] = array(
                'id'            => $page_id,
                'title'         => $page->post_title,
                'slug'          => $page->post_name,
                'permalink'     => get_permalink( $page_id ),
                'status'        => $page->post_status,
                'hasSEO'        => $has_seo,
                'missingFields' => $missing_fields,
                'currentMeta'   => array(
                    'og_title'       => $og_title,
                    'og_description' => $og_description,
                    'og_image'       => $og_image,
                ),
                'featuredImage' => $featured_image,
                'firstImage'    => $first_image,
            );
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'pages'   => $results,
        ) );
    }
    
    /**
     * API: Get SEO meta for a specific page
     */
    public function api_get_seo_meta( $request ) {
        $page_id = intval( $request->get_param( 'id' ) );
        
        // Try Yoast SEO first, then custom meta
        $og_title = get_post_meta( $page_id, '_yoast_wpseo_opengraph-title', true );
        if ( empty( $og_title ) ) {
            $og_title = get_post_meta( $page_id, '_og_title', true );
        }
        
        $og_description = get_post_meta( $page_id, '_yoast_wpseo_opengraph-description', true );
        if ( empty( $og_description ) ) {
            $og_description = get_post_meta( $page_id, '_og_description', true );
        }
        
        $og_image = get_post_meta( $page_id, '_yoast_wpseo_opengraph-image', true );
        if ( empty( $og_image ) ) {
            $og_image = get_post_meta( $page_id, '_og_image', true );
        }
        
        $og_url = get_permalink( $page_id );
        
        $twitter_card = get_post_meta( $page_id, '_twitter_card', true );
        if ( empty( $twitter_card ) ) {
            $twitter_card = 'summary_large_image';
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'meta'    => array(
                'og_title'           => $og_title,
                'og_description'     => $og_description,
                'og_image'           => $og_image,
                'og_url'             => $og_url,
                'twitter_card'       => $twitter_card,
                'twitter_title'      => $og_title,
                'twitter_description' => $og_description,
                'twitter_image'      => $og_image,
            ),
        ) );
    }
    
    /**
     * API: Update SEO meta for a page
     */
    public function api_update_seo_meta( $request ) {
        $page_id = intval( $request->get_param( 'id' ) );
        $params = $request->get_json_params();
        $meta = isset( $params['meta'] ) ? $params['meta'] : array();
        
        if ( empty( $meta ) ) {
            return new WP_Error( 'no_meta', 'No meta data provided', array( 'status' => 400 ) );
        }
        
        // Clean and sanitize meta values
        if ( isset( $meta['og_title'] ) ) {
            $clean_title = sanitize_text_field( $meta['og_title'] );
            update_post_meta( $page_id, '_og_title', $clean_title );
            // Also update Yoast if installed
            if ( defined( 'WPSEO_VERSION' ) ) {
                update_post_meta( $page_id, '_yoast_wpseo_opengraph-title', $clean_title );
            }
        }
        
        if ( isset( $meta['og_description'] ) ) {
            // Strip shortcodes and clean description
            $clean_desc = strip_shortcodes( $meta['og_description'] );
            $clean_desc = wp_strip_all_tags( $clean_desc );
            $clean_desc = sanitize_textarea_field( $clean_desc );
            // Limit to 160 chars
            if ( strlen( $clean_desc ) > 160 ) {
                $clean_desc = substr( $clean_desc, 0, 157 ) . '...';
            }
            
            update_post_meta( $page_id, '_og_description', $clean_desc );
            if ( defined( 'WPSEO_VERSION' ) ) {
                update_post_meta( $page_id, '_yoast_wpseo_opengraph-description', $clean_desc );
            }
        }
        
        if ( isset( $meta['og_image'] ) ) {
            $clean_image = esc_url_raw( $meta['og_image'] );
            update_post_meta( $page_id, '_og_image', $clean_image );
            if ( defined( 'WPSEO_VERSION' ) ) {
                update_post_meta( $page_id, '_yoast_wpseo_opengraph-image', $clean_image );
            }
        }
        
        if ( isset( $meta['twitter_card'] ) ) {
            // Ensure single value, not array
            $twitter_card = is_array( $meta['twitter_card'] ) ? $meta['twitter_card'][0] : $meta['twitter_card'];
            update_post_meta( $page_id, '_twitter_card', sanitize_text_field( $twitter_card ) );
        }
        
        // Add action hook for theme integration
        do_action( 'pod_seo_meta_updated', $page_id, $meta );
        
        return rest_ensure_response( array(
            'success' => true,
            'message' => 'SEO meta updated successfully',
        ) );
    }
    /**
     * API: Upload media (base64 image)
     */
    public function api_upload_media( $request ) {
        $params    = $request->get_json_params();
        $base64    = isset( $params['base64'] ) ? $params['base64'] : '';
        $filename  = isset( $params['filename'] ) ? sanitize_file_name( $params['filename'] ) : 'uploaded-image.webp';
        $mime_type = isset( $params['mime_type'] ) ? sanitize_text_field( $params['mime_type'] ) : 'image/webp';
        
        if ( empty( $base64 ) ) {
            return new WP_Error( 'no_data', 'No base64 data provided', array( 'status' => 400 ) );
        }
        
        // Decode base64
        $decoded = base64_decode( $base64 );
        if ( false === $decoded ) {
            return new WP_Error( 'decode_failed', 'Failed to decode base64 data', array( 'status' => 400 ) );
        }
        
        // Prepare upload directory
        $upload_dir = wp_upload_dir();
        $upload_path = $upload_dir['path'] . '/' . $filename;
        
        // Ensure unique filename
        $upload_path = wp_unique_filename( $upload_dir['path'], $filename );
        $upload_path = $upload_dir['path'] . '/' . $upload_path;
        
        // Write file
        $written = file_put_contents( $upload_path, $decoded );
        if ( false === $written ) {
            return new WP_Error( 'write_failed', 'Failed to write file', array( 'status' => 500 ) );
        }
        
        // Create attachment
        $attachment = array(
            'post_mime_type' => $mime_type,
            'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $upload_path ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );
        
        $attach_id = wp_insert_attachment( $attachment, $upload_path );
        if ( is_wp_error( $attach_id ) ) {
            @unlink( $upload_path );
            return new WP_Error( 'attachment_failed', 'Failed to create attachment', array( 'status' => 500 ) );
        }
        
        // Generate metadata
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $upload_path );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        
        $url = wp_get_attachment_url( $attach_id );
        
        return rest_ensure_response( array(
            'success' => true,
            'url'     => $url,
            'id'      => $attach_id,
        ) );
    }
}

// Initialize
POD_Content_Manager::get_instance();
