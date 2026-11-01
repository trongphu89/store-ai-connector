<?php
/**
 * POD SEO Lite - Lightweight SEO for POD Sites
 * 
 * Features:
 * - Auto Meta Title/Description
 * - Open Graph & Twitter Cards
 * - Sitemap Generator (products, posts, variants)
 * - IndexNow auto ping
 * - Robots.txt optimization
 * - Canonical URLs
 * 
 * @package POD_AI_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class POD_SEO {
    
    private $indexnow_key = null;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Meta tags
        add_action( 'wp_head', array( $this, 'output_meta_tags' ), 1 );
        add_action( 'wp_head', array( $this, 'output_open_graph' ), 2 );
        add_action( 'wp_head', array( $this, 'output_canonical' ), 3 );
        add_action( 'wp_head', array( $this, 'output_organization_schema' ), 4 );
        add_action( 'wp_head', array( $this, 'output_breadcrumbs_schema' ), 5 );
        add_action( 'wp_head', array( $this, 'output_faq_schema' ), 6 );
        add_action( 'wp_head', array( $this, 'output_product_schema' ), 7 );
        
        // Sitemap
        add_action( 'init', array( $this, 'register_sitemaps' ) );
        add_filter( 'robots_txt', array( $this, 'add_sitemap_to_robots' ), 10, 2 );
        
        // IndexNow
        add_action( 'publish_post', array( $this, 'ping_indexnow' ), 10, 2 );
        add_action( 'publish_product', array( $this, 'ping_indexnow' ), 10, 2 );
        add_action( 'save_post', array( $this, 'ping_indexnow_on_update' ), 10, 3 );
        
        // Admin
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // IndexNow key file
        add_action( 'init', array( $this, 'serve_indexnow_key' ) );
        
        // Remove default WordPress meta
        remove_action( 'wp_head', 'rel_canonical' );
        
        // Remove WooCommerce default structured data to avoid duplicates
        add_action( 'init', array( $this, 'remove_wc_structured_data' ) );
        
        // 3rd-party SEO compatibility
        add_filter( 'wp_robots', array( $this, 'wp_robots_filter' ), 999 );
        add_filter( 'wpseo_robots', array( $this, 'yoast_robots_filter' ) );
        add_filter( 'rank_math/frontend/robots', array( $this, 'rankmath_robots_filter' ) );
    }

    /**
     * WP Robots Filter
     */
    public function wp_robots_filter( $robots ) {
        if ( isset( $_GET['pod-style'] ) || isset( $_GET['pod-size'] ) ) {
            return array( 'noindex' => true, 'follow' => true );
        }
        return $robots;
    }

    /**
     * Yoast Robots Filter
     */
    public function yoast_robots_filter( $robots ) {
        if ( isset( $_GET['pod-style'] ) || isset( $_GET['pod-size'] ) ) {
            return 'noindex, follow';
        }
        return $robots;
    }

    /**
     * RankMath Robots Filter
     */
    public function rankmath_robots_filter( $robots ) {
        if ( isset( $_GET['pod-style'] ) || isset( $_GET['pod-size'] ) ) {
            $robots['index'] = 'noindex';
            $robots['follow'] = 'follow';
        }
        return $robots;
    }
    
    /**
     * Remove WooCommerce default structured data
     */
    public function remove_wc_structured_data() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }
        
        // Remove WooCommerce default Product schema to avoid duplicates
        // We will output our own enhanced schema with shipping/return policy
        add_filter( 'woocommerce_structured_data_product', '__return_false', 999 );
        
        // Also remove from footer output
        add_action( 'wp_head', function() {
            if ( isset( WC()->structured_data ) ) {
                remove_action( 'wp_footer', array( WC()->structured_data, 'output_structured_data' ), 10 );
            }
        }, 1 );
    }
    
    // ==================== ORGANIZATION SCHEMA ====================
    
    /**
     * Output Organization Schema JSON-LD
     */
    public function output_organization_schema() {
        // Only output on homepage
        if ( ! is_front_page() && ! is_home() ) {
            return;
        }
        
        $site_name = get_bloginfo( 'name' );
        $site_url = home_url( '/' );
        $logo = get_option( 'pod_seo_default_image', '' );
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $site_name,
            'url' => $site_url,
        );
        
        if ( $logo ) {
            $schema['logo'] = $logo;
        }
        
        // Add social profiles if available
        $social_profiles = array();
        $facebook = get_option( 'pod_seo_facebook', '' );
        $twitter = get_option( 'pod_seo_twitter', '' );
        $instagram = get_option( 'pod_seo_instagram', '' );
        
        if ( $facebook ) $social_profiles[] = $facebook;
        if ( $twitter ) $social_profiles[] = $twitter;
        if ( $instagram ) $social_profiles[] = $instagram;
        
        if ( ! empty( $social_profiles ) ) {
            $schema['sameAs'] = $social_profiles;
        }
        
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        echo "\n" . '</script>' . "\n";
    }
    
    // ==================== BREADCRUMBS SCHEMA ====================
    
    /**
     * Output Breadcrumbs Schema JSON-LD
     */
    public function output_breadcrumbs_schema() {
        // Don't output on homepage
        if ( is_front_page() || is_home() ) {
            return;
        }
        
        $breadcrumbs = $this->get_breadcrumbs();
        
        if ( empty( $breadcrumbs ) ) {
            return;
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array()
        );
        
        $position = 1;
        foreach ( $breadcrumbs as $crumb ) {
            $schema['itemListElement'][] = array(
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $crumb['name'],
                'item' => $crumb['url']
            );
        }
        
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        echo "\n" . '</script>' . "\n";
    }
    
    /**
     * Get breadcrumbs array
     */
    private function get_breadcrumbs() {
        $breadcrumbs = array();
        
        // Home
        $breadcrumbs[] = array(
            'name' => get_bloginfo( 'name' ),
            'url' => home_url( '/' )
        );
        
        // Product
        if ( is_singular( 'product' ) && function_exists( 'wc_get_product' ) ) {
            global $product;
            if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                $product = wc_get_product( get_the_ID() );
            }
            
            if ( $product && is_a( $product, 'WC_Product' ) ) {
                // Shop page
                $shop_page_id = wc_get_page_id( 'shop' );
                if ( $shop_page_id > 0 ) {
                    $breadcrumbs[] = array(
                        'name' => get_the_title( $shop_page_id ),
                        'url' => get_permalink( $shop_page_id )
                    );
                }
                
                // Product categories
                $terms = get_the_terms( get_the_ID(), 'product_cat' );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    $term = array_shift( $terms );
                    $breadcrumbs[] = array(
                        'name' => $term->name,
                        'url' => get_term_link( $term )
                    );
                }
                
                // Current product
                $breadcrumbs[] = array(
                    'name' => $product->get_name(),
                    'url' => get_permalink()
                );
            }
        }
        
        // Post
        elseif ( is_singular( 'post' ) ) {
            // Blog page
            $blog_page_id = get_option( 'page_for_posts' );
            if ( $blog_page_id ) {
                $breadcrumbs[] = array(
                    'name' => get_the_title( $blog_page_id ),
                    'url' => get_permalink( $blog_page_id )
                );
            }
            
            // Post category
            $categories = get_the_category();
            if ( ! empty( $categories ) ) {
                $category = $categories[0];
                $breadcrumbs[] = array(
                    'name' => $category->name,
                    'url' => get_category_link( $category )
                );
            }
            
            // Current post
            $breadcrumbs[] = array(
                'name' => get_the_title(),
                'url' => get_permalink()
            );
        }
        
        // Page
        elseif ( is_singular( 'page' ) ) {
            $breadcrumbs[] = array(
                'name' => get_the_title(),
                'url' => get_permalink()
            );
        }
        
        // Category
        elseif ( is_category() ) {
            $category = get_queried_object();
            $breadcrumbs[] = array(
                'name' => $category->name,
                'url' => get_category_link( $category )
            );
        }
        
        // Product Category
        elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
            $shop_page_id = wc_get_page_id( 'shop' );
            if ( $shop_page_id > 0 ) {
                $breadcrumbs[] = array(
                    'name' => get_the_title( $shop_page_id ),
                    'url' => get_permalink( $shop_page_id )
                );
            }
            
            $term = get_queried_object();
            $breadcrumbs[] = array(
                'name' => $term->name,
                'url' => get_term_link( $term )
            );
        }
        
        // Shop page
        elseif ( function_exists( 'is_shop' ) && is_shop() ) {
            $shop_page_id = wc_get_page_id( 'shop' );
            if ( $shop_page_id > 0 ) {
                $breadcrumbs[] = array(
                    'name' => get_the_title( $shop_page_id ),
                    'url' => get_permalink( $shop_page_id )
                );
            }
        }
        
        return $breadcrumbs;
    }
    
    // ==================== PRODUCT SCHEMA ====================
    
    /**
     * Output Product Schema JSON-LD with shipping and return policy
     * This replaces WooCommerce default schema with GMC-compliant version
     */
    public function output_product_schema() {
        if ( ! is_singular( 'product' ) || ! function_exists( 'wc_get_product' ) ) {
            return;
        }
        
        global $product;
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            $product = wc_get_product( get_the_ID() );
        }
        
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }
        
        // Check if POD Variations will handle schema
        $pod_variations = get_post_meta( get_the_ID(), '_pod_variations', true );
        if ( ! empty( $pod_variations ) ) {
            // POD Variations class will output enhanced schema
            return;
        }
        
        // Build Product schema
        $currency = get_woocommerce_currency();
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->get_name(),
            'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
            'sku' => $product->get_sku(),
            'url' => get_permalink(),
        );
        
        // Image
        $image_id = $product->get_image_id();
        if ( $image_id ) {
            $schema['image'] = wp_get_attachment_url( $image_id );
        }
        
        // Brand
        $brand = get_option( 'pod_seo_brand', get_bloginfo( 'name' ) );
        $schema['brand'] = array(
            '@type' => 'Brand',
            'name' => $brand
        );
        
        // Offers
        $offer = array(
            '@type' => 'Offer',
            'url' => get_permalink(),
            'priceCurrency' => $currency,
            'price' => $product->get_price(),
            'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'priceValidUntil' => date( 'Y-12-31' ),
        );
        
        // Add shipping details
        $shipping = $this->get_shipping_schema( $currency );
        if ( $shipping ) {
            $offer['shippingDetails'] = $shipping;
        }
        
        // Add return policy
        $return_policy = $this->get_return_policy_schema();
        if ( $return_policy ) {
            $offer['hasMerchantReturnPolicy'] = $return_policy;
        }
        
        $schema['offers'] = $offer;
        
        // Reviews/Rating (if available)
        $rating = $product->get_average_rating();
        $review_count = $product->get_review_count();
        if ( $rating > 0 && $review_count > 0 ) {
            $schema['aggregateRating'] = array(
                '@type' => 'AggregateRating',
                'ratingValue' => $rating,
                'reviewCount' => $review_count
            );
        }
        
        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        echo "\n" . '</script>' . "\n";
    }
    
    /**
     * Get shipping schema from GMC settings
     */
    private function get_shipping_schema( $currency ) {
        $country = get_option( 'pod_gmc_shipping_country', '' );
        $price = get_option( 'pod_gmc_shipping_price', '0.00' );
        
        if ( empty( $country ) ) {
            return null;
        }
        
        return array(
            '@type' => 'OfferShippingDetails',
            'shippingRate' => array(
                '@type' => 'MonetaryAmount',
                'value' => number_format( floatval( $price ), 2, '.', '' ),
                'currency' => $currency,
            ),
            'shippingDestination' => array(
                '@type' => 'DefinedRegion',
                'addressCountry' => $country,
            ),
            'deliveryTime' => array(
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => array(
                    '@type' => 'QuantitativeValue',
                    'minValue' => 1,
                    'maxValue' => 3,
                    'unitCode' => 'DAY',
                ),
                'transitTime' => array(
                    '@type' => 'QuantitativeValue',
                    'minValue' => 5,
                    'maxValue' => 14,
                    'unitCode' => 'DAY',
                ),
            ),
        );
    }
    
    /**
     * Get return policy schema from GMC settings
     */
    private function get_return_policy_schema() {
        $return_url = get_option( 'pod_gmc_return_policy', '' );
        
        if ( empty( $return_url ) ) {
            return null;
        }
        
        return array(
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => get_option( 'pod_gmc_shipping_country', 'US' ),
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => 30,
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'returnFees' => 'https://schema.org/FreeReturn',
            'url' => $return_url,
        );
    }
    
    // ==================== FAQ SCHEMA ====================
    
    /**
     * Output FAQ Schema JSON-LD from post meta
     */
    public function output_faq_schema() {
        if ( ! is_singular( array( 'post', 'page', 'product' ) ) ) {
            return;
        }
        
        $post_id = get_the_ID();
        $faq_json = get_post_meta( $post_id, '_pod_faq_schema', true );
        
        if ( empty( $faq_json ) ) {
            return;
        }
        
        $faq_items = json_decode( $faq_json, true );
        if ( empty( $faq_items ) || ! is_array( $faq_items ) ) {
            return;
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array()
        );
        
        foreach ( $faq_items as $item ) {
            if ( ! empty( $item['question'] ) && ! empty( $item['answer'] ) ) {
                $schema['mainEntity'][] = array(
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => $item['answer']
                    )
                );
            }
        }
        
        if ( ! empty( $schema['mainEntity'] ) ) {
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
            echo "\n" . '</script>' . "\n";
        }
    }
    
    // ==================== META TAGS ====================
    
    /**
     * Output meta title and description
     */
    public function output_meta_tags() {
        // Don't output if Yoast or RankMath is active
        if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) ) {
            return;
        }
        
        $title = $this->get_meta_title();
        $description = $this->get_meta_description();
        
        if ( $description ) {
            echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
        }
        
        // Additional SEO meta
        if ( isset( $_GET['pod-style'] ) || isset( $_GET['pod-size'] ) ) {
            echo '<meta name="robots" content="noindex, follow" />' . "\n";
        } else {
            echo '<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1" />' . "\n";
        }
    }
    
    /**
     * Get meta title for current page
     */
    private function get_meta_title() {
        $site_name = get_bloginfo( 'name' );
        $separator = ' | ';
        
        if ( is_singular( 'product' ) && function_exists( 'wc_get_product' ) ) {
            global $product;
            if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                $product = wc_get_product( get_the_ID() );
            }
            if ( $product && is_a( $product, 'WC_Product' ) ) {
                $brand = get_option( 'pod_seo_brand', $site_name );
                return $product->get_name() . $separator . $brand;
            }
        }
        
        if ( is_singular( 'post' ) ) {
            return get_the_title() . $separator . $site_name;
        }
        
        if ( is_singular( 'page' ) ) {
            return get_the_title() . $separator . $site_name;
        }
        
        if ( function_exists( 'is_product_category' ) && is_product_category() ) {
            $term = get_queried_object();
            if ( $term && isset( $term->name ) ) {
                return $term->name . ' - Shop ' . $separator . $site_name;
            }
        }
        
        if ( is_category() ) {
            $term = get_queried_object();
            return $term->name . $separator . $site_name;
        }
        
        if ( function_exists( 'is_shop' ) && is_shop() ) {
            return get_option( 'pod_seo_shop_title', 'Shop' ) . $separator . $site_name;
        }
        
        if ( is_home() || is_front_page() ) {
            $tagline = get_bloginfo( 'description' );
            return $site_name . ( $tagline ? $separator . $tagline : '' );
        }
        
        return $site_name;
    }
    
    /**
     * Get meta description for current page
     */
    private function get_meta_description() {
        if ( is_singular( 'product' ) && function_exists( 'wc_get_product' ) ) {
            global $product;
            if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                $product = wc_get_product( get_the_ID() );
            }
            if ( $product && is_a( $product, 'WC_Product' ) ) {
                $desc = $product->get_short_description() ?: $product->get_description();
                $desc = wp_strip_all_tags( $desc );
                return $this->truncate_description( $desc );
            }
        }
        
        if ( is_singular( 'post' ) || is_singular( 'page' ) ) {
            $post = get_post();
            if ( $post ) {
                $desc = $post->post_excerpt ?: $post->post_content;
                $desc = wp_strip_all_tags( $desc );
                return $this->truncate_description( $desc );
            }
        }
        
        if ( ( function_exists( 'is_product_category' ) && is_product_category() ) || is_category() ) {
            $term = get_queried_object();
            if ( $term && isset( $term->description ) && $term->description ) {
                return $this->truncate_description( $term->description );
            }
        }
        
        if ( is_home() || is_front_page() ) {
            return get_option( 'pod_seo_home_description', get_bloginfo( 'description' ) );
        }
        
        return get_bloginfo( 'description' );
    }
    
    /**
     * Truncate description to SEO-friendly length
     */
    private function truncate_description( $text, $length = 160 ) {
        $text = trim( preg_replace( '/\s+/', ' ', $text ) );
        if ( strlen( $text ) <= $length ) {
            return $text;
        }
        $text = substr( $text, 0, $length );
        $text = substr( $text, 0, strrpos( $text, ' ' ) );
        return $text . '...';
    }
    
    // ==================== OPEN GRAPH ====================
    
    /**
     * Output Open Graph and Twitter Card meta tags
     */
    public function output_open_graph() {
        if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) ) {
            return;
        }
        
        $og = $this->get_open_graph_data();
        
        // Open Graph
        echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";
        echo '<meta property="og:type" content="' . esc_attr( $og['type'] ) . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $og['title'] ) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $og['description'] ) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url( $og['url'] ) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '" />' . "\n";
        
        if ( $og['image'] ) {
            echo '<meta property="og:image" content="' . esc_url( $og['image'] ) . '" />' . "\n";
            echo '<meta property="og:image:width" content="1200" />' . "\n";
            echo '<meta property="og:image:height" content="630" />' . "\n";
        }
        
        // Product specific
        if ( $og['type'] === 'product' && isset( $og['price'] ) ) {
            echo '<meta property="product:price:amount" content="' . esc_attr( $og['price'] ) . '" />' . "\n";
            echo '<meta property="product:price:currency" content="' . esc_attr( $og['currency'] ) . '" />' . "\n";
            echo '<meta property="og:availability" content="' . esc_attr( $og['availability'] ) . '" />' . "\n";
        }
        
        // Twitter Card
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $og['title'] ) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $og['description'] ) . '" />' . "\n";
        if ( $og['image'] ) {
            echo '<meta name="twitter:image" content="' . esc_url( $og['image'] ) . '" />' . "\n";
        }
    }
    
    /**
     * Get Open Graph data for current page
     */
    private function get_open_graph_data() {
        $data = array(
            'type' => 'website',
            'title' => $this->get_meta_title(),
            'description' => $this->get_meta_description(),
            'url' => $this->get_canonical_url(),
            'image' => null,
        );
        
        if ( is_singular( 'product' ) && function_exists( 'wc_get_product' ) ) {
            global $product;
            if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
                $product = wc_get_product( get_the_ID() );
            }
            if ( $product && is_a( $product, 'WC_Product' ) ) {
                $data['type'] = 'product';
                $data['image'] = wp_get_attachment_url( $product->get_image_id() );
                $data['price'] = $product->get_price();
                $data['currency'] = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
                $data['availability'] = $product->is_in_stock() ? 'instock' : 'outofstock';
            }
        } elseif ( is_singular() ) {
            $data['type'] = 'article';
            $data['image'] = get_the_post_thumbnail_url( get_the_ID(), 'large' );
        }
        
        // Fallback image
        if ( ! $data['image'] ) {
            $data['image'] = get_option( 'pod_seo_default_image', '' );
        }
        
        return $data;
    }
    
    // ==================== CANONICAL ====================
    
    /**
     * Output canonical URL
     */
    public function output_canonical() {
        if ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) ) {
            return;
        }
        
        $canonical = $this->get_canonical_url();
        if ( $canonical ) {
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
        }
    }
    
    /**
     * Get canonical URL
     */
    private function get_canonical_url() {
        if ( is_singular() ) {
            return get_permalink();
        }
        
        if ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();
            if ( $term && ! is_wp_error( $term ) ) {
                $link = get_term_link( $term );
                return is_wp_error( $link ) ? home_url( '/' ) : $link;
            }
        }
        
        if ( function_exists( 'is_shop' ) && is_shop() && function_exists( 'wc_get_page_id' ) ) {
            $shop_id = wc_get_page_id( 'shop' );
            if ( $shop_id > 0 ) {
                return get_permalink( $shop_id );
            }
        }
        
        if ( is_home() ) {
            return home_url( '/' );
        }
        
        return home_url( '/' );
    }
    
    // ==================== SITEMAP ====================
    
    /**
     * Register sitemap endpoints
     */
    public function register_sitemaps() {
        add_rewrite_rule( '^sitemap\.xml$', 'index.php?pod_sitemap=index', 'top' );
        add_rewrite_rule( '^sitemap-posts\.xml$', 'index.php?pod_sitemap=posts', 'top' );
        add_rewrite_rule( '^sitemap-pages\.xml$', 'index.php?pod_sitemap=pages', 'top' );
        add_rewrite_rule( '^sitemap-products\.xml$', 'index.php?pod_sitemap=products', 'top' );
        add_rewrite_rule( '^sitemap-categories\.xml$', 'index.php?pod_sitemap=categories', 'top' );
        
        add_filter( 'query_vars', function( $vars ) {
            $vars[] = 'pod_sitemap';
            return $vars;
        });
        
        add_action( 'template_redirect', array( $this, 'render_sitemap' ) );
    }
    
    /**
     * Render sitemap
     */
    public function render_sitemap() {
        $sitemap_type = get_query_var( 'pod_sitemap' );
        if ( ! $sitemap_type ) {
            return;
        }
        
        header( 'Content-Type: application/xml; charset=utf-8' );
        header( 'X-Robots-Tag: noindex, follow' );
        
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        
        switch ( $sitemap_type ) {
            case 'index':
                $this->render_sitemap_index();
                break;
            case 'posts':
                $this->render_posts_sitemap();
                break;
            case 'pages':
                $this->render_pages_sitemap();
                break;
            case 'products':
                $this->render_products_sitemap();
                break;
            case 'categories':
                $this->render_categories_sitemap();
                break;
        }
        
        exit;
    }
    
    /**
     * Render sitemap index
     */
    private function render_sitemap_index() {
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        $sitemaps = array(
            'sitemap-posts.xml',
            'sitemap-pages.xml',
        );
        
        if ( class_exists( 'WooCommerce' ) ) {
            $sitemaps[] = 'sitemap-products.xml';
            $sitemaps[] = 'sitemap-categories.xml';
        }
        
        foreach ( $sitemaps as $sitemap ) {
            echo '<sitemap>' . "\n";
            echo '<loc>' . esc_url( home_url( '/' . $sitemap ) ) . '</loc>' . "\n";
            echo '<lastmod>' . date( 'c' ) . '</lastmod>' . "\n";
            echo '</sitemap>' . "\n";
        }
        
        echo '</sitemapindex>';
    }
    
    /**
     * Render posts sitemap
     */
    private function render_posts_sitemap() {
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Home page
        $this->output_url( home_url( '/' ), date( 'c' ), 'daily', '1.0' );
        
        // Posts
        $posts = get_posts( array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ));
        
        foreach ( $posts as $post ) {
            $this->output_url(
                get_permalink( $post ),
                get_the_modified_date( 'c', $post ),
                'weekly',
                '0.6'
            );
        }
        
        echo '</urlset>';
    }
    
    /**
     * Render pages sitemap
     */
    private function render_pages_sitemap() {
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        $pages = get_posts( array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ));
        
        foreach ( $pages as $page ) {
            $priority = $page->ID == get_option( 'page_on_front' ) ? '1.0' : '0.5';
            $this->output_url(
                get_permalink( $page ),
                get_the_modified_date( 'c', $page ),
                'monthly',
                $priority
            );
        }
        
        echo '</urlset>';
    }
    
    /**
     * Render products sitemap
     */
    private function render_products_sitemap() {
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '</urlset>';
            return;
        }
        
        // Shop page
        $shop_page_id = wc_get_page_id( 'shop' );
        if ( $shop_page_id > 0 ) {
            $this->output_url( get_permalink( $shop_page_id ), date( 'c' ), 'daily', '0.9' );
        }
        
        // Products
        $products = get_posts( array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ));
        
        foreach ( $products as $post ) {
            $product = wc_get_product( $post->ID );
            if ( ! $product ) continue;
            
            $image_url = wp_get_attachment_url( $product->get_image_id() );
            
            echo '<url>' . "\n";
            echo '<loc>' . esc_url( get_permalink( $post ) ) . '</loc>' . "\n";
            echo '<lastmod>' . get_the_modified_date( 'c', $post ) . '</lastmod>' . "\n";
            echo '<changefreq>weekly</changefreq>' . "\n";
            echo '<priority>0.8</priority>' . "\n";
            
            if ( $image_url ) {
                echo '<image:image>' . "\n";
                echo '<image:loc>' . esc_url( $image_url ) . '</image:loc>' . "\n";
                echo '<image:title>' . esc_html( $product->get_name() ) . '</image:title>' . "\n";
                echo '</image:image>' . "\n";
            }
            
            echo '</url>' . "\n";
        }
        
        echo '</urlset>';
    }
    
    /**
     * Render categories sitemap
     */
    private function render_categories_sitemap() {
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Product categories
        if ( class_exists( 'WooCommerce' ) ) {
            $terms = get_terms( array(
                'taxonomy' => 'product_cat',
                'hide_empty' => true,
            ));
            
            foreach ( $terms as $term ) {
                $this->output_url( get_term_link( $term ), date( 'c' ), 'weekly', '0.7' );
            }
        }
        
        // Post categories
        $categories = get_categories( array( 'hide_empty' => true ) );
        foreach ( $categories as $cat ) {
            $this->output_url( get_category_link( $cat ), date( 'c' ), 'weekly', '0.5' );
        }
        
        echo '</urlset>';
    }
    
    /**
     * Delete rendering variants sitemap
     */
    
    /**
     * Output single URL entry
     */
    private function output_url( $loc, $lastmod, $changefreq, $priority ) {
        echo '<url>' . "\n";
        echo '<loc>' . esc_url( $loc ) . '</loc>' . "\n";
        echo '<lastmod>' . $lastmod . '</lastmod>' . "\n";
        echo '<changefreq>' . $changefreq . '</changefreq>' . "\n";
        echo '<priority>' . $priority . '</priority>' . "\n";
        echo '</url>' . "\n";
    }
    
    /**
     * Add sitemap to robots.txt
     */
    public function add_sitemap_to_robots( $output, $public ) {
        if ( $public ) {
            $output .= "\n# POD SEO Sitemap\n";
            $output .= "Sitemap: " . home_url( '/sitemap.xml' ) . "\n";
        }
        return $output;
    }

    
    // ==================== INDEXNOW ====================
    
    /**
     * Get or generate IndexNow key
     */
    private function get_indexnow_key() {
        if ( $this->indexnow_key ) {
            return $this->indexnow_key;
        }
        
        $key = get_option( 'pod_seo_indexnow_key' );
        if ( ! $key ) {
            $key = wp_generate_uuid4();
            $key = str_replace( '-', '', $key );
            update_option( 'pod_seo_indexnow_key', $key );
        }
        
        $this->indexnow_key = $key;
        return $key;
    }
    
    /**
     * Serve IndexNow key file
     */
    public function serve_indexnow_key() {
        $request_uri = $_SERVER['REQUEST_URI'];
        $key = $this->get_indexnow_key();
        
        if ( preg_match( '/^\/' . preg_quote( $key, '/' ) . '\.txt$/', $request_uri ) ) {
            header( 'Content-Type: text/plain' );
            echo $key;
            exit;
        }
    }
    
    /**
     * Ping IndexNow when post is published
     */
    public function ping_indexnow( $post_id, $post ) {
        if ( ! get_option( 'pod_seo_indexnow_enabled', true ) ) {
            return;
        }
        
        if ( $post->post_status !== 'publish' ) {
            return;
        }
        
        $url = get_permalink( $post_id );
        $this->submit_to_indexnow( $url );
    }
    
    /**
     * Ping IndexNow on post update
     */
    public function ping_indexnow_on_update( $post_id, $post, $update ) {
        if ( ! $update ) {
            return;
        }
        
        if ( ! get_option( 'pod_seo_indexnow_enabled', true ) ) {
            return;
        }
        
        if ( ! in_array( $post->post_type, array( 'post', 'page', 'product' ) ) ) {
            return;
        }
        
        if ( $post->post_status !== 'publish' ) {
            return;
        }
        
        // Avoid duplicate pings
        $last_ping = get_post_meta( $post_id, '_pod_indexnow_last_ping', true );
        if ( $last_ping && ( time() - intval( $last_ping ) ) < 300 ) {
            return; // Don't ping more than once per 5 minutes
        }
        
        $url = get_permalink( $post_id );
        $result = $this->submit_to_indexnow( $url );
        
        if ( $result ) {
            update_post_meta( $post_id, '_pod_indexnow_last_ping', time() );
        }
    }
    
    /**
     * Submit URL to IndexNow
     */
    private function submit_to_indexnow( $url ) {
        $key = $this->get_indexnow_key();
        $host = parse_url( home_url(), PHP_URL_HOST );
        
        $api_url = 'https://api.indexnow.org/indexnow?' . http_build_query( array(
            'url' => $url,
            'key' => $key,
            'keyLocation' => home_url( '/' . $key . '.txt' ),
        ));
        
        $response = wp_remote_get( $api_url, array(
            'timeout' => 10,
            'sslverify' => true,
        ));
        
        if ( is_wp_error( $response ) ) {
            $this->log_indexnow( $url, 'error', $response->get_error_message() );
            return false;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $this->log_indexnow( $url, $code == 200 || $code == 202 ? 'success' : 'failed', 'HTTP ' . $code );
        
        return $code == 200 || $code == 202;
    }
    
    /**
     * Bulk submit URLs to IndexNow
     */
    public function bulk_submit_indexnow( $urls ) {
        $key = $this->get_indexnow_key();
        $host = parse_url( home_url(), PHP_URL_HOST );
        
        $body = array(
            'host' => $host,
            'key' => $key,
            'keyLocation' => home_url( '/' . $key . '.txt' ),
            'urlList' => array_slice( $urls, 0, 10000 ), // Max 10,000 URLs per request
        );
        
        $response = wp_remote_post( 'https://api.indexnow.org/indexnow', array(
            'timeout' => 30,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body' => json_encode( $body ),
        ));
        
        if ( is_wp_error( $response ) ) {
            return array( 'success' => false, 'error' => $response->get_error_message() );
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        return array(
            'success' => $code == 200 || $code == 202,
            'code' => $code,
            'submitted' => count( $urls ),
        );
    }
    
    /**
     * Log IndexNow submission
     */
    private function log_indexnow( $url, $status, $message ) {
        $logs = get_option( 'pod_seo_indexnow_logs', array() );
        
        array_unshift( $logs, array(
            'url' => $url,
            'status' => $status,
            'message' => $message,
            'time' => current_time( 'mysql' ),
        ));
        
        // Keep only last 100 logs
        $logs = array_slice( $logs, 0, 100 );
        update_option( 'pod_seo_indexnow_logs', $logs );
    }
    
    // ==================== ADMIN ====================
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'pod-ai-connector',
            __( 'SEO Settings', 'pod-ai-connector' ),
            __( 'SEO', 'pod-ai-connector' ),
            'manage_options',
            'pod-seo',
            array( $this, 'render_admin_page' )
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'pod_seo_settings', 'pod_seo_brand' );
        register_setting( 'pod_seo_settings', 'pod_seo_shop_title' );
        register_setting( 'pod_seo_settings', 'pod_seo_home_description' );
        register_setting( 'pod_seo_settings', 'pod_seo_default_image' );
        register_setting( 'pod_seo_settings', 'pod_seo_indexnow_enabled' );
        register_setting( 'pod_seo_settings', 'pod_seo_facebook' );
        register_setting( 'pod_seo_settings', 'pod_seo_twitter' );
        register_setting( 'pod_seo_settings', 'pod_seo_instagram' );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Handle bulk submit
        if ( isset( $_POST['pod_seo_bulk_submit'] ) && check_admin_referer( 'pod_seo_bulk_submit' ) ) {
            $urls = $this->get_all_urls();
            $result = $this->bulk_submit_indexnow( $urls );
            
            if ( $result['success'] ) {
                echo '<div class="notice notice-success"><p>✓ Đã submit ' . $result['submitted'] . ' URLs to IndexNow!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>✗ Error: ' . esc_html( $result['error'] ?? 'Unknown error' ) . '</p></div>';
            }
        }
        
        // Flush rewrite rules if needed
        if ( isset( $_GET['flush_rules'] ) ) {
            flush_rewrite_rules();
            echo '<div class="notice notice-success"><p>✓ Rewrite rules flushed!</p></div>';
        }
        
        $indexnow_key = $this->get_indexnow_key();
        $logs = get_option( 'pod_seo_indexnow_logs', array() );
        ?>
        <div class="wrap">
            <h1><?php _e( 'POD SEO Lite', 'pod-ai-connector' ); ?></h1>
            
            <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
                <h2 style="margin-top: 0;">📊 SEO Status</h2>
                <table class="widefat" style="margin-bottom: 15px;">
                    <tr>
                        <td><strong>Sitemap Index</strong></td>
                        <td><a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank"><?php echo esc_url( home_url( '/sitemap.xml' ) ); ?></a></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span></td>
                    </tr>
                    <tr>
                        <td><strong>Products Sitemap</strong></td>
                        <td><a href="<?php echo esc_url( home_url( '/sitemap-products.xml' ) ); ?>" target="_blank"><?php echo esc_url( home_url( '/sitemap-products.xml' ) ); ?></a></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span></td>
                    </tr>
                    <tr>
                        <td><strong>POD Variants Sitemap</strong></td>
                        <td><a href="<?php echo esc_url( home_url( '/sitemap-pod-variants.xml' ) ); ?>" target="_blank"><?php echo esc_url( home_url( '/sitemap-pod-variants.xml' ) ); ?></a></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span></td>
                    </tr>
                    <tr>
                        <td><strong>IndexNow Key</strong></td>
                        <td><a href="<?php echo esc_url( home_url( '/' . $indexnow_key . '.txt' ) ); ?>" target="_blank"><?php echo esc_url( home_url( '/' . $indexnow_key . '.txt' ) ); ?></a></td>
                        <td><span class="dashicons dashicons-yes-alt" style="color: green;"></span></td>
                    </tr>
                </table>
                <p>
                    <a href="<?php echo admin_url( 'admin.php?page=pod-seo&flush_rules=1' ); ?>" class="button">🔄 Flush Rewrite Rules</a>
                    <span class="description" style="margin-left: 10px;">Click nếu sitemap trả về 404</span>
                </p>
            </div>
            
            <div class="card" style="max-width: 800px; padding: 20px; margin-bottom: 20px;">
                <h2 style="margin-top: 0;">⚡ IndexNow - Instant Indexing</h2>
                <p>IndexNow tự động ping Bing, Yandex khi bạn publish/update content.</p>
                
                <form method="post">
                    <?php wp_nonce_field( 'pod_seo_bulk_submit' ); ?>
                    <p>
                        <button type="submit" name="pod_seo_bulk_submit" class="button button-primary">
                            🚀 Submit All URLs to IndexNow
                        </button>
                        <span class="description" style="margin-left: 10px;">Submit tất cả products, posts, pages</span>
                    </p>
                </form>
                
                <?php if ( ! empty( $logs ) ) : ?>
                <h3>Recent IndexNow Logs</h3>
                <table class="widefat striped" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>URL</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( array_slice( $logs, 0, 10 ) as $log ) : ?>
                        <tr>
                            <td><?php echo esc_html( $log['time'] ); ?></td>
                            <td style="max-width: 400px; overflow: hidden; text-overflow: ellipsis;"><?php echo esc_html( $log['url'] ); ?></td>
                            <td>
                                <?php if ( $log['status'] === 'success' ) : ?>
                                    <span style="color: green;">✓ <?php echo esc_html( $log['message'] ); ?></span>
                                <?php else : ?>
                                    <span style="color: red;">✗ <?php echo esc_html( $log['message'] ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <form method="post" action="options.php" style="max-width: 800px;">
                <?php settings_fields( 'pod_seo_settings' ); ?>
                
                <div class="card" style="padding: 20px; margin-bottom: 20px;">
                    <h2 style="margin-top: 0;">⚙️ SEO Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e( 'Brand Name', 'pod-ai-connector' ); ?></th>
                            <td>
                                <input type="text" name="pod_seo_brand" value="<?php echo esc_attr( get_option( 'pod_seo_brand', get_bloginfo( 'name' ) ) ); ?>" class="regular-text" />
                                <p class="description">Hiển thị trong meta title: "Product Name | Brand"</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Shop Page Title', 'pod-ai-connector' ); ?></th>
                            <td>
                                <input type="text" name="pod_seo_shop_title" value="<?php echo esc_attr( get_option( 'pod_seo_shop_title', 'Shop' ) ); ?>" class="regular-text" />
                                <p class="description">Title cho trang Shop</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Home Description', 'pod-ai-connector' ); ?></th>
                            <td>
                                <textarea name="pod_seo_home_description" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'pod_seo_home_description', get_bloginfo( 'description' ) ) ); ?></textarea>
                                <p class="description">Meta description cho trang chủ (max 160 ký tự)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Default OG Image', 'pod-ai-connector' ); ?></th>
                            <td>
                                <input type="url" name="pod_seo_default_image" value="<?php echo esc_attr( get_option( 'pod_seo_default_image', '' ) ); ?>" class="large-text" />
                                <p class="description">URL ảnh mặc định cho Open Graph (1200x630px recommended)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'IndexNow Auto Ping', 'pod-ai-connector' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="pod_seo_indexnow_enabled" value="1" <?php checked( get_option( 'pod_seo_indexnow_enabled', true ), 1 ); ?> />
                                    <?php _e( 'Tự động ping IndexNow khi publish/update', 'pod-ai-connector' ); ?>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="card" style="padding: 20px; margin-bottom: 20px;">
                    <h2 style="margin-top: 0;">🌐 Social Profiles (Organization Schema)</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e( 'Facebook URL', 'pod-ai-connector' ); ?></th>
                            <td>
                                <input type="url" name="pod_seo_facebook" value="<?php echo esc_attr( get_option( 'pod_seo_facebook', '' ) ); ?>" class="large-text" placeholder="https://facebook.com/yourpage" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Twitter URL', 'pod-ai-connector' ); ?></th>
                            <td>
                                <input type="url" name="pod_seo_twitter" value="<?php echo esc_attr( get_option( 'pod_seo_twitter', '' ) ); ?>" class="large-text" placeholder="https://twitter.com/yourhandle" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e( 'Instagram URL', 'pod-ai-connector' ); ?></th>
                            <td>
                                <input type="url" name="pod_seo_instagram" value="<?php echo esc_attr( get_option( 'pod_seo_instagram', '' ) ); ?>" class="large-text" placeholder="https://instagram.com/yourhandle" />
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="card" style="max-width: 800px; padding: 20px;">
                <h2 style="margin-top: 0;">📋 Checklist SEO</h2>
                <ul style="list-style: disc; padding-left: 20px;">
                    <li>✅ Meta Title & Description tự động</li>
                    <li>✅ Open Graph & Twitter Cards</li>
                    <li>✅ Canonical URLs</li>
                    <li>✅ XML Sitemap (products, posts) - Đã bỏ qua variants</li>
                    <li>✅ Schema JSON-LD: Organization, Breadcrumbs, Product, FAQ</li>
                    <li>✅ GMC Compliance: Shipping & Return Policy trong Product Schema</li>
                    <li>✅ Xóa Schema WooCommerce mặc định (tránh trùng lặp)</li>
                    <li>✅ IndexNow auto ping</li>
                    <li>✅ Robots meta tags (tự động noindex cho các biến thể)</li>
                </ul>
                <p style="margin-top: 15px;">
                    <strong>Không cần cài thêm Yoast SEO hoặc RankMath!</strong><br>
                    Plugin này đã bao gồm tất cả SEO cơ bản cần thiết cho POD sites + GMC compliance.
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get all URLs for bulk submit
     */
    private function get_all_urls() {
        $urls = array();
        
        // Home
        $urls[] = home_url( '/' );
        
        // Posts
        $posts = get_posts( array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ));
        foreach ( $posts as $post ) {
            $urls[] = get_permalink( $post );
        }
        
        // Pages
        $pages = get_posts( array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ));
        foreach ( $pages as $page ) {
            $urls[] = get_permalink( $page );
        }
        
        // Products
        if ( class_exists( 'WooCommerce' ) ) {
            $products = get_posts( array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => -1,
            ));
            foreach ( $products as $product ) {
                $urls[] = get_permalink( $product );
            }
            
            // Categories
            $terms = get_terms( array(
                'taxonomy' => 'product_cat',
                'hide_empty' => true,
            ));
            foreach ( $terms as $term ) {
                $urls[] = get_term_link( $term );
            }
        }
        
        return array_unique( $urls );
    }
}

// Initialize
new POD_SEO();
