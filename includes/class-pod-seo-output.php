<?php
/**
 * POD SEO Output
 * 
 * Outputs Open Graph and Twitter Card meta tags in <head>
 * Uses output buffering to REPLACE any existing theme OG/Twitter tags
 * so there are never duplicates, regardless of what theme is used.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class POD_SEO_Output {
    
    private static $instance = null;
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Use output buffering to intercept and replace OG tags in the entire page
        add_action( 'template_redirect', array( $this, 'start_output_buffer' ) );
        
        // Also hook into Yoast/Rank Math filters as fallback
        add_filter( 'wpseo_opengraph_title', array( $this, 'override_seo_title' ), 999 );
        add_filter( 'wpseo_opengraph_desc', array( $this, 'override_seo_description' ), 999 );
        add_filter( 'wpseo_opengraph_image', array( $this, 'override_seo_image' ), 999 );
        add_filter( 'wpseo_twitter_title', array( $this, 'override_seo_title' ), 999 );
        add_filter( 'wpseo_twitter_description', array( $this, 'override_seo_description' ), 999 );
        add_filter( 'wpseo_twitter_image', array( $this, 'override_seo_image' ), 999 );
        add_filter( 'rank_math/opengraph/facebook/og_title', array( $this, 'override_seo_title' ), 999 );
        add_filter( 'rank_math/opengraph/facebook/og_description', array( $this, 'override_seo_description' ), 999 );
        add_filter( 'rank_math/opengraph/facebook/og_image', array( $this, 'override_seo_image' ), 999 );
        add_filter( 'rank_math/opengraph/twitter/title', array( $this, 'override_seo_title' ), 999 );
        add_filter( 'rank_math/opengraph/twitter/description', array( $this, 'override_seo_description' ), 999 );
        add_filter( 'rank_math/opengraph/twitter/image', array( $this, 'override_seo_image' ), 999 );
    }
    
    /**
     * Start output buffering on singular pages that have custom SEO meta
     */
    public function start_output_buffer() {
        if ( ! is_singular() ) {
            return;
        }
        
        global $post;
        if ( ! $post ) {
            return;
        }
        
        // Only buffer if we have custom SEO meta saved
        $og_title = get_post_meta( $post->ID, '_og_title', true );
        if ( empty( $og_title ) ) {
            return;
        }
        
        ob_start( array( $this, 'process_output' ) );
    }
    
    /**
     * Process the full page HTML output:
     * 1. Strip ALL existing OG and Twitter meta tags from theme/plugins
     * 2. Inject our custom POD SEO meta tags into <head>
     */
    public function process_output( $html ) {
        if ( empty( $html ) ) {
            return $html;
        }
        
        global $post;
        if ( ! $post ) {
            return $html;
        }
        
        $page_id = $post->ID;
        
        // Get our custom meta
        $og_title       = get_post_meta( $page_id, '_og_title', true );
        $og_description = get_post_meta( $page_id, '_og_description', true );
        $og_image       = get_post_meta( $page_id, '_og_image', true );
        $og_url         = get_permalink( $page_id );
        $twitter_card   = get_post_meta( $page_id, '_twitter_card', true );
        
        // If no custom title, don't modify anything
        if ( empty( $og_title ) ) {
            return $html;
        }
        
        // Fallbacks
        if ( empty( $og_description ) ) {
            $og_description = wp_trim_words( strip_shortcodes( $post->post_content ), 30, '...' );
        }
        if ( empty( $og_image ) ) {
            $og_image = get_the_post_thumbnail_url( $page_id, 'large' );
        }
        if ( empty( $twitter_card ) ) {
            $twitter_card = 'summary_large_image';
        }
        
        // Step 1: Remove ALL existing OG and Twitter meta tags from the HTML
        $patterns = array(
            // Open Graph tags (property="og:*")
            '/<meta\s+property=["\']og:(title|description|image|image:width|image:height|image:alt|url|type|site_name)["\'][^>]*\/?>/i',
            '/<meta\s+[^>]*property=["\']og:(title|description|image|image:width|image:height|image:alt|url|type|site_name)["\'][^>]*\/?>/i',
            // Twitter Card tags (name="twitter:*")
            '/<meta\s+name=["\']twitter:(card|title|description|image|site|creator)["\'][^>]*\/?>/i',
            '/<meta\s+[^>]*name=["\']twitter:(card|title|description|image|site|creator)["\'][^>]*\/?>/i',
        );
        
        foreach ( $patterns as $pattern ) {
            $html = preg_replace( $pattern, '', $html );
        }
        
        // Also remove any existing POD AI Designer comment blocks
        $html = preg_replace( '/<!-- POD AI Designer.*?End POD AI Designer Meta Tags -->/s', '', $html );
        
        // Step 2: Build our clean meta tags block
        $meta_tags = "\n<!-- POD AI Designer - SEO Meta Tags -->\n";
        $meta_tags .= '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
        if ( ! empty( $og_description ) ) {
            $meta_tags .= '<meta property="og:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
        }
        if ( ! empty( $og_image ) ) {
            $meta_tags .= '<meta property="og:image" content="' . esc_url( $og_image ) . '" />' . "\n";
        }
        $meta_tags .= '<meta property="og:url" content="' . esc_url( $og_url ) . '" />' . "\n";
        $meta_tags .= '<meta property="og:type" content="website" />' . "\n";
        $meta_tags .= '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
        $meta_tags .= '<meta name="twitter:card" content="' . esc_attr( $twitter_card ) . '" />' . "\n";
        $meta_tags .= '<meta name="twitter:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
        if ( ! empty( $og_description ) ) {
            $meta_tags .= '<meta name="twitter:description" content="' . esc_attr( $og_description ) . '" />' . "\n";
        }
        if ( ! empty( $og_image ) ) {
            $meta_tags .= '<meta name="twitter:image" content="' . esc_url( $og_image ) . '" />' . "\n";
        }
        $meta_tags .= "<!-- End POD AI Designer Meta Tags -->\n";
        
        // Step 3: Inject our tags right after <head> or after <title>
        if ( preg_match( '/<\/title>/i', $html ) ) {
            $html = preg_replace( '/<\/title>/i', '</title>' . $meta_tags, $html, 1 );
        } elseif ( preg_match( '/<head[^>]*>/i', $html ) ) {
            $html = preg_replace( '/<head([^>]*)>/i', '<head$1>' . $meta_tags, $html, 1 );
        }
        
        return $html;
    }
    
    /**
     * Override SEO plugin title
     */
    public function override_seo_title( $title ) {
        if ( ! is_singular() ) return $title;
        global $post;
        $custom = get_post_meta( $post->ID, '_og_title', true );
        return ! empty( $custom ) ? $custom : $title;
    }
    
    /**
     * Override SEO plugin description
     */
    public function override_seo_description( $description ) {
        if ( ! is_singular() ) return $description;
        global $post;
        $custom = get_post_meta( $post->ID, '_og_description', true );
        return ! empty( $custom ) ? $custom : $description;
    }
    
    /**
     * Override SEO plugin image
     */
    public function override_seo_image( $image ) {
        if ( ! is_singular() ) return $image;
        global $post;
        $custom = get_post_meta( $post->ID, '_og_image', true );
        return ! empty( $custom ) ? $custom : $image;
    }
}

// Initialize
POD_SEO_Output::get_instance();
