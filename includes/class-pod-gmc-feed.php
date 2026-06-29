<?php
/**
 * POD GMC Feed - Google Merchant Center Product Feed
 * Generates XML feed with all product variants for GMC compliance
 * 
 * Feed URL: yoursite.com/?feed={custom-slug} (default: gmc-products)
 * 
 * @package POD_AI_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class POD_GMC_Feed {

    /**
     * Sanitize text for XML output
     * Converts HTML entities (like &ndash; &rsquo; &mdash;) to UTF-8 characters
     * and removes XML-incompatible control characters
     */
    private function sanitize_for_xml( $text ) {
        // Decode ALL HTML entities to their UTF-8 characters
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        // Remove XML-incompatible control characters (keep tab, newline, carriage return)
        $text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text );
        return $text;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        // Register feed
        add_action( 'init', array( $this, 'register_feed' ) );
        
        // Add feed link to wp_head for discovery
        add_action( 'wp_head', array( $this, 'add_feed_link' ) );
        
        // Admin settings
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Handle category mapping save (custom POST handling)
        add_action( 'admin_post_pod_gmc_save_category_mapping', array( $this, 'save_category_mapping' ) );

        // Handle category filter save
        add_action( 'admin_post_pod_gmc_save_category_filter', array( $this, 'save_category_filter' ) );

        // Flush rewrite rules when feed slug changes
        add_action( 'update_option_pod_gmc_feed_slug', array( $this, 'flush_rewrite_on_slug_change' ), 10, 2 );
    }
    
    /**
     * Get feed slug from settings
     */
    public function get_feed_slug() {
        $slug = get_option( 'pod_gmc_feed_slug', 'gmc-products' );
        $slug = sanitize_title( $slug );
        return ! empty( $slug ) ? $slug : 'gmc-products';
    }

    /**
     * Register custom feed
     */
    public function register_feed() {
        add_feed( $this->get_feed_slug(), array( $this, 'render_feed' ) );
    }

    /**
     * Flush rewrite rules when feed slug changes
     */
    public function flush_rewrite_on_slug_change( $old_value, $new_value ) {
        if ( $old_value !== $new_value ) {
            // Need to re-register feed with new slug before flushing
            add_feed( sanitize_title( $new_value ), array( $this, 'render_feed' ) );
            flush_rewrite_rules();
        }
    }
    
    /**
     * Add feed link to head
     */
    public function add_feed_link() {
        if ( ! function_exists( 'is_shop' ) ) {
            return;
        }
        if ( is_shop() || is_front_page() ) {
            $feed_url = $this->get_feed_url();
            echo '<link rel="alternate" type="application/rss+xml" title="' . esc_attr( get_bloginfo( 'name' ) ) . ' - GMC Product Feed" href="' . esc_url( $feed_url ) . '" />' . "\n";
        }
    }
    
    /**
     * Get feed URL
     */
    public function get_feed_url() {
        return home_url( '/?feed=' . $this->get_feed_slug() );
    }

    /**
     * Get storefront (headless frontend) base URL.
     * Returns empty string if not configured.
     */
    public function get_storefront_url() {
        $url = get_option( 'pod_gmc_storefront_url', '' );
        return rtrim( $url, '/' );
    }

    /**
     * Rewrite a WordPress-generated URL to use the storefront domain.
     * Replaces the scheme+host part of $url with the configured storefront URL.
     * Falls back to the original URL if storefront URL is not set.
     *
     * Example:
     *   Input:  https://api.mmaclarksville.com/product/t-shirt/?pod-style=bella
     *   Output: https://www.mmaclarksville.com/product/t-shirt/?pod-style=bella
     */
    private function rewrite_to_storefront_url( $url ) {
        $storefront = $this->get_storefront_url();
        if ( empty( $storefront ) ) {
            return $url;
        }
        // Extract just the path + query from the original WordPress URL
        $parsed = wp_parse_url( $url );
        $path   = isset( $parsed['path'] ) ? $parsed['path'] : '/';
        $query  = isset( $parsed['query'] ) ? '?' . $parsed['query'] : '';
        return $storefront . $path . $query;
    }
    
    /**
     * Render the GMC feed
     */
    public function render_feed() {
        // Set XML header
        header( 'Content-Type: application/xml; charset=utf-8' );
        
        // Get settings
        $settings = $this->get_settings();
        
        // Start XML output
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
        echo '<channel>' . "\n";
        echo '<title>' . esc_html( $this->sanitize_for_xml( get_bloginfo( 'name' ) ) ) . ' - Product Feed</title>' . "\n";
        echo '<link>' . esc_url( home_url() ) . '</link>' . "\n";
        echo '<description>' . esc_html( $this->sanitize_for_xml( get_bloginfo( 'description' ) ) ) . '</description>' . "\n";
        
        // Get products
        $this->output_products( $settings );
        
        echo '</channel>' . "\n";
        echo '</rss>';
        
        exit;
    }
    
    /**
     * Output all products with variants
     */
    private function output_products( $settings ) {
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_pod_variations',
                    'compare' => 'EXISTS',
                ),
            ),
        );
        
        // Option to include all products or only POD Variations
        if ( ! empty( $settings['include_all_products'] ) ) {
            unset( $args['meta_query'] );
        }

        // Category filter: only include products from selected categories
        $included_cats = isset( $settings['included_categories'] ) ? $settings['included_categories'] : array();
        if ( ! empty( $included_cats ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => array_map( 'intval', $included_cats ),
                    'operator' => 'IN',
                ),
            );
        }
        
        $products = new WP_Query( $args );
        
        if ( $products->have_posts() ) {
            while ( $products->have_posts() ) {
                $products->the_post();
                global $product;
                
                if ( ! $product ) {
                    continue;
                }
                
                $this->output_product_items( $product, $settings );
            }
            wp_reset_postdata();
        }
    }
    
    /**
     * Output items for a single product (including all variants)
     */
    private function output_product_items( $product, $settings ) {
        $product_id    = $product->get_id();
        $pod_variations = get_post_meta( $product_id, '_pod_variations', true );
        // Rewrite permalink to storefront domain if headless setup is configured
        $base_url      = $this->rewrite_to_storefront_url( get_permalink( $product_id ) );
        $currency      = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        
        // Get product data
        $product_title = $this->sanitize_for_xml( $product->get_name() );
        $product_desc  = wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() );
        $product_desc  = $this->sanitize_for_xml( $product_desc );
        $product_desc  = substr( $product_desc, 0, 5000 ); // GMC limit
        $product_sku   = $product->get_sku() ?: 'product-' . $product_id;
        $product_image = wp_get_attachment_url( $product->get_image_id() );
        $product_stock = $product->is_in_stock() ? 'in_stock' : 'out_of_stock';

        // Get additional images from WooCommerce product gallery
        $additional_images = array();
        $include_additional = ! empty( $settings['include_additional_images'] );
        if ( $include_additional ) {
            $gallery_ids = $product->get_gallery_image_ids();
            if ( ! empty( $gallery_ids ) ) {
                // GMC allows up to 10 additional images
                $gallery_ids = array_slice( $gallery_ids, 0, 10 );
                foreach ( $gallery_ids as $img_id ) {
                    $img_url = wp_get_attachment_url( $img_id );
                    if ( $img_url ) {
                        $additional_images[] = $img_url;
                    }
                }
            }
        }
        
        // Get brand
        $brand = $product->get_attribute( 'brand' );
        if ( empty( $brand ) ) {
            $brand = isset( $settings['default_brand'] ) ? $settings['default_brand'] : get_bloginfo( 'name' );
        }
        
        // Get category for Google Product Category
        $google_category = $this->get_google_category( $product, $settings );
        
        // Get return policy URL
        $return_policy_url = isset( $settings['return_policy_url'] ) ? $settings['return_policy_url'] : '';
        
        // Check if has POD Variations
        if ( ! empty( $pod_variations ) ) {
            $pv_data = is_string( $pod_variations ) ? json_decode( $pod_variations, true ) : $pod_variations;
            $this->output_pod_variation_items( $product, $pv_data, $settings, array(
                'title'             => $product_title,
                'description'       => $product_desc,
                'sku'               => $product_sku,
                'image'             => $product_image,
                'additional_images' => $additional_images,
                'stock'             => $product_stock,
                'brand'             => $brand,
                'google_category'   => $google_category,
                'base_url'          => $base_url,
                'currency'          => $currency,
                'gender'            => $settings['default_gender'],
                'age_group'         => $settings['default_age_group'],
                'default_color'     => $settings['default_color'],
                'shipping'          => $this->get_shipping_data( $settings, $currency ),
                'return_policy_url' => $return_policy_url,
            ) );
        } else {
            // Simple product without POD Variations
            $product_price = floatval( $product->get_price() );
            
            // Skip if price is invalid for GMC
            if ( $product_price <= 0 ) {
                return;
            }
            
            $product_color = $product->get_attribute( 'color' );
            if ( empty( $product_color ) ) {
                $product_color = $product->get_attribute( 'pa_color' );
            }
            if ( empty( $product_color ) ) {
                $product_color = $settings['default_color'];
            }
            
            $this->output_item( array(
                'id'                     => $product_sku,
                'title'                  => $product_title,
                'description'            => $product_desc,
                'link'                   => $base_url,
                'image_link'             => $product_image,
                'additional_image_links'  => $additional_images,
                'price'                  => number_format( $product_price, 2, '.', '' ) . ' ' . $currency,
                'availability'           => $product_stock,
                'condition'              => 'new',
                'brand'                  => $brand,
                'google_product_category' => $google_category,
                'gender'                 => $settings['default_gender'],
                'age_group'              => $settings['default_age_group'],
                'color'                  => $product_color,
                'shipping'               => $this->get_shipping_data( $settings, $currency ),
                'return_policy_url'      => $return_policy_url,
            ) );
        }
    }

    
    /**
     * Output POD Variation items
     * Respects variation_mode: 'all' | 'first_only'
     */
    private function output_pod_variation_items( $product, $pv_data, $settings, $base_data ) {
        $styles        = isset( $pv_data['styles'] ) ? $pv_data['styles'] : array();
        $use_shared    = isset( $pv_data['useSharedSizesColors'] ) ? $pv_data['useSharedSizesColors'] : true;
        $shared_sizes  = isset( $pv_data['sizes'] ) ? $pv_data['sizes'] : array();
        $shared_colors = isset( $pv_data['colors'] ) ? $pv_data['colors'] : array();
        
        $item_group_id     = $base_data['sku'];
        $default_color     = isset( $base_data['default_color'] ) ? $base_data['default_color'] : 'Multicolor';
        $gender            = isset( $base_data['gender'] ) ? $base_data['gender'] : 'unisex';
        $age_group         = isset( $base_data['age_group'] ) ? $base_data['age_group'] : 'adult';
        $shipping          = isset( $base_data['shipping'] ) ? $base_data['shipping'] : null;
        $return_policy_url = isset( $base_data['return_policy_url'] ) ? $base_data['return_policy_url'] : '';
        
        // Fallback price from WooCommerce product
        $wc_product_price = floatval( $product->get_price() );
        
        // Variation mode
        $first_only = ( isset( $settings['variation_mode'] ) && $settings['variation_mode'] === 'first_only' );
        
        // Generate variants
        if ( ! empty( $styles ) ) {
            // When first_only: limit styles to just the first one
            $styles_to_loop = $first_only ? array( $styles[0] ) : $styles;

            foreach ( $styles_to_loop as $style ) {
                $style_price = floatval( $style['basePrice'] );
                // Fallback to WooCommerce product price if basePrice is 0 or empty
                if ( $style_price <= 0 ) {
                    $style_price = $wc_product_price;
                }
                $style_slug  = sanitize_title( $style['name'] );
                $style_name  = $this->sanitize_for_xml( $style['name'] );
                
                // Get sizes for this style
                $sizes_to_use   = $use_shared ? $shared_sizes : ( isset( $style['sizes'] ) ? $style['sizes'] : array() );
                // Get colors for this style
                $colors_to_use  = $use_shared ? $shared_colors : ( isset( $style['colors'] ) ? $style['colors'] : array() );
                
                // Determine color
                $item_color = $default_color;
                if ( ! empty( $colors_to_use ) ) {
                    if ( count( $colors_to_use ) > 1 ) {
                        $item_color = 'Multicolor';
                    } else {
                        $item_color = $colors_to_use[0]['name'];
                    }
                }
                
                if ( ! empty( $sizes_to_use ) ) {
                    // When first_only: limit sizes to just the first one
                    $sizes_to_loop = $first_only ? array( $sizes_to_use[0] ) : $sizes_to_use;

                    foreach ( $sizes_to_loop as $size ) {
                        $size_upcharge = floatval( $size['upcharge'] );
                        $final_price   = $style_price + $size_upcharge;
                        $size_slug     = sanitize_title( $size['name'] );
                        $size_name     = $this->sanitize_for_xml( $size['name'] );
                        
                        $variant_url   = add_query_arg( array(
                            'pod-style' => $style_slug,
                            'pod-size'  => $size_slug,
                        ), $base_data['base_url'] );
                        
                        $variant_id    = $base_data['sku'] . '-' . $style_slug . '-' . $size_slug;
                        $variant_title = $base_data['title'] . ' - ' . $style_name . ' - ' . $size_name;
                        
                        $this->output_item( array(
                            'id'                     => $variant_id,
                            'item_group_id'          => $item_group_id,
                            'title'                  => $variant_title,
                            'description'            => $base_data['description'],
                            'link'                   => $variant_url,
                            'image_link'             => $base_data['image'],
                            'additional_image_links'  => isset( $base_data['additional_images'] ) ? $base_data['additional_images'] : array(),
                            'price'                  => number_format( $final_price, 2, '.', '' ) . ' ' . $base_data['currency'],
                            'availability'           => $base_data['stock'],
                            'condition'              => 'new',
                            'brand'                  => $base_data['brand'],
                            'google_product_category' => $base_data['google_category'],
                            'size'                   => $size_name,
                            'color'                  => $item_color,
                            'gender'                 => $gender,
                            'age_group'              => $age_group,
                            'shipping'               => $shipping,
                            'return_policy_url'      => $return_policy_url,
                        ) );

                        // Stop after first size when first_only
                        if ( $first_only ) {
                            break;
                        }
                    }
                } else {
                    // Style only, no sizes
                    // Skip if style price is invalid
                    if ( $style_price <= 0 ) {
                        continue;
                    }
                    
                    $variant_url   = add_query_arg( 'pod-style', $style_slug, $base_data['base_url'] );
                    $variant_id    = $base_data['sku'] . '-' . $style_slug;
                    $variant_title = $base_data['title'] . ' - ' . $style_name;
                    
                    $this->output_item( array(
                        'id'                     => $variant_id,
                        'item_group_id'          => $item_group_id,
                        'title'                  => $variant_title,
                        'description'            => $base_data['description'],
                        'link'                   => $variant_url,
                        'image_link'             => $base_data['image'],
                        'additional_image_links'  => isset( $base_data['additional_images'] ) ? $base_data['additional_images'] : array(),
                        'price'                  => number_format( $style_price, 2, '.', '' ) . ' ' . $base_data['currency'],
                        'availability'           => $base_data['stock'],
                        'condition'              => 'new',
                        'brand'                  => $base_data['brand'],
                        'google_product_category' => $base_data['google_category'],
                        'color'                  => $item_color,
                        'gender'                 => $gender,
                        'age_group'              => $age_group,
                        'shipping'               => $shipping,
                        'return_policy_url'      => $return_policy_url,
                    ) );
                }

                // Stop after first style when first_only
                if ( $first_only ) {
                    break;
                }
            }

        } elseif ( ! empty( $shared_sizes ) ) {
            // No styles, only sizes
            $base_price = floatval( $product->get_price() );
            // Ensure base price is valid for GMC
            if ( $base_price <= 0 ) {
                // Skip this product if no valid price
                return;
            }
            
            $item_color = $default_color;
            if ( ! empty( $shared_colors ) ) {
                if ( count( $shared_colors ) > 1 ) {
                    $item_color = 'Multicolor';
                } else {
                    $item_color = $shared_colors[0]['name'];
                }
            }
            
            $sizes_to_loop = $first_only ? array( $shared_sizes[0] ) : $shared_sizes;
            foreach ( $sizes_to_loop as $size ) {
                $size_upcharge = floatval( $size['upcharge'] );
                $final_price   = $base_price + $size_upcharge;
                $size_slug     = sanitize_title( $size['name'] );
                $size_name     = $this->sanitize_for_xml( $size['name'] );
                
                $variant_url   = add_query_arg( 'pod-size', $size_slug, $base_data['base_url'] );
                $variant_id    = $base_data['sku'] . '-' . $size_slug;
                $variant_title = $base_data['title'] . ' - ' . $size_name;
                
                $this->output_item( array(
                    'id'                     => $variant_id,
                    'item_group_id'          => $item_group_id,
                    'title'                  => $variant_title,
                    'description'            => $base_data['description'],
                    'link'                   => $variant_url,
                    'image_link'             => $base_data['image'],
                    'additional_image_links'  => isset( $base_data['additional_images'] ) ? $base_data['additional_images'] : array(),
                    'price'                  => number_format( $final_price, 2, '.', '' ) . ' ' . $base_data['currency'],
                    'availability'           => $base_data['stock'],
                    'condition'              => 'new',
                    'brand'                  => $base_data['brand'],
                    'google_product_category' => $base_data['google_category'],
                    'size'                   => $size_name,
                    'color'                  => $item_color,
                    'gender'                 => $gender,
                    'age_group'              => $age_group,
                    'shipping'               => $shipping,
                    'return_policy_url'      => $return_policy_url,
                ) );
            }
        }
    }
    
    /**
     * Get shipping data for feed
     */
    private function get_shipping_data( $settings, $currency ) {
        $country = isset( $settings['shipping_country'] ) ? $settings['shipping_country'] : '';
        $service = isset( $settings['shipping_service'] ) ? $settings['shipping_service'] : '';
        $price   = isset( $settings['shipping_price'] ) ? $settings['shipping_price'] : '';
        
        if ( empty( $country ) ) {
            return null;
        }
        
        return array(
            'country' => $country,
            'service' => $service ?: 'Standard Shipping',
            'price'   => number_format( floatval( $price ), 2, '.', '' ) . ' ' . $currency,
        );
    }
    
    /**
     * Output a single item in XML format
     */
    private function output_item( $data ) {
        echo '<item>' . "\n";
        
        // Required fields
        echo '<g:id>' . esc_html( $data['id'] ) . '</g:id>' . "\n";
        echo '<title>' . esc_html( $this->sanitize_for_xml( $data['title'] ) ) . '</title>' . "\n";
        echo '<description><![CDATA[' . $this->sanitize_for_xml( $data['description'] ) . ']]></description>' . "\n";
        echo '<link>' . esc_url( $data['link'] ) . '</link>' . "\n";
        echo '<g:price>' . esc_html( $data['price'] ) . '</g:price>' . "\n";
        echo '<g:availability>' . esc_html( $data['availability'] ) . '</g:availability>' . "\n";
        echo '<g:condition>' . esc_html( $data['condition'] ) . '</g:condition>' . "\n";
        
        // Image
        if ( ! empty( $data['image_link'] ) ) {
            echo '<g:image_link>' . esc_url( $data['image_link'] ) . '</g:image_link>' . "\n";
        }
        
        // Additional Images (up to 10)
        if ( ! empty( $data['additional_image_links'] ) && is_array( $data['additional_image_links'] ) ) {
            foreach ( $data['additional_image_links'] as $additional_img ) {
                echo '<g:additional_image_link>' . esc_url( $additional_img ) . '</g:additional_image_link>' . "\n";
            }
        }
        
        // Brand
        if ( ! empty( $data['brand'] ) ) {
            echo '<g:brand>' . esc_html( $this->sanitize_for_xml( $data['brand'] ) ) . '</g:brand>' . "\n";
        }
        
        // Item group ID (for variants)
        if ( ! empty( $data['item_group_id'] ) ) {
            echo '<g:item_group_id>' . esc_html( $data['item_group_id'] ) . '</g:item_group_id>' . "\n";
        }
        
        // Google Product Category
        if ( ! empty( $data['google_product_category'] ) ) {
            echo '<g:google_product_category>' . esc_html( $this->sanitize_for_xml( $data['google_product_category'] ) ) . '</g:google_product_category>' . "\n";
        }
        
        // Size (for apparel)
        if ( ! empty( $data['size'] ) ) {
            echo '<g:size>' . esc_html( $this->sanitize_for_xml( $data['size'] ) ) . '</g:size>' . "\n";
        }
        
        // Color - required for apparel
        if ( ! empty( $data['color'] ) ) {
            echo '<g:color>' . esc_html( $this->sanitize_for_xml( $data['color'] ) ) . '</g:color>' . "\n";
        }
        
        // Gender - required for apparel
        if ( ! empty( $data['gender'] ) ) {
            echo '<g:gender>' . esc_html( $data['gender'] ) . '</g:gender>' . "\n";
        }
        
        // Age Group - required for apparel
        if ( ! empty( $data['age_group'] ) ) {
            echo '<g:age_group>' . esc_html( $data['age_group'] ) . '</g:age_group>' . "\n";
        }
        
        // Shipping
        if ( ! empty( $data['shipping'] ) ) {
            echo '<g:shipping>' . "\n";
            echo '  <g:country>' . esc_html( $data['shipping']['country'] ) . '</g:country>' . "\n";
            echo '  <g:service>' . esc_html( $data['shipping']['service'] ) . '</g:service>' . "\n";
            echo '  <g:price>' . esc_html( $data['shipping']['price'] ) . '</g:price>' . "\n";
            echo '</g:shipping>' . "\n";
            echo '<g:shipping_weight>1 lb</g:shipping_weight>' . "\n";
            echo '<g:shipping_length>12 in</g:shipping_length>' . "\n";
            echo '<g:shipping_width>10 in</g:shipping_width>' . "\n";
            echo '<g:shipping_height>2 in</g:shipping_height>' . "\n";
        }
        
        // Return Policy
        if ( ! empty( $data['return_policy_url'] ) ) {
            echo '<g:return_policy_label>standard_return</g:return_policy_label>' . "\n";
            echo '<g:return_address_label>default</g:return_address_label>' . "\n";
        }
        
        echo '</item>' . "\n";
    }
    
    /**
     * Get Google Product Category for a product
     * Priority: per-product meta > WC category mapping > default setting
     */
    private function get_google_category( $product, $settings ) {
        // 1. Check per-product meta first
        $google_cat = get_post_meta( $product->get_id(), '_pod_google_category', true );
        if ( ! empty( $google_cat ) ) {
            return $google_cat;
        }

        // 2. Check WC category mapping
        $mapping = isset( $settings['category_mapping'] ) ? $settings['category_mapping'] : array();
        if ( ! empty( $mapping ) ) {
            $terms = get_the_terms( $product->get_id(), 'product_cat' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    // Check by slug
                    if ( isset( $mapping[ $term->slug ] ) && ! empty( $mapping[ $term->slug ] ) ) {
                        return $mapping[ $term->slug ];
                    }
                    // Check by term_id as string
                    if ( isset( $mapping[ 'cat_' . $term->term_id ] ) && ! empty( $mapping[ 'cat_' . $term->term_id ] ) ) {
                        return $mapping[ 'cat_' . $term->term_id ];
                    }
                }
            }
            // Check for 'default' fallback in mapping
            if ( isset( $mapping['default'] ) && ! empty( $mapping['default'] ) ) {
                return $mapping['default'];
            }
        }
        
        // 3. Use global default from settings
        return isset( $settings['default_google_category'] ) ? $settings['default_google_category'] : 'Apparel & Accessories';
    }
    
    /**
     * Get feed settings
     */
    private function get_settings() {
        $category_mapping_raw = get_option( 'pod_gmc_category_mapping', '{}' );
        $category_mapping     = json_decode( $category_mapping_raw, true );
        if ( ! is_array( $category_mapping ) ) {
            $category_mapping = array();
        }

        $included_categories_raw = get_option( 'pod_gmc_included_categories', '[]' );
        $included_categories     = json_decode( $included_categories_raw, true );
        if ( ! is_array( $included_categories ) ) {
            $included_categories = array();
        }

        return array(
            'include_all_products'      => get_option( 'pod_gmc_include_all', false ),
            'include_additional_images' => get_option( 'pod_gmc_include_additional_images', false ),
            'variation_mode'            => get_option( 'pod_gmc_variation_mode', 'all' ), // 'all' | 'first_only'
            'included_categories'       => $included_categories, // [] = all categories
            'default_brand'             => get_option( 'pod_gmc_default_brand', get_bloginfo( 'name' ) ),
            'default_google_category'   => get_option( 'pod_gmc_default_category', 'Apparel & Accessories' ),
            'category_mapping'          => $category_mapping,
            'default_gender'            => get_option( 'pod_gmc_default_gender', 'unisex' ),
            'default_age_group'         => get_option( 'pod_gmc_default_age_group', 'adult' ),
            'default_color'             => get_option( 'pod_gmc_default_color', 'Multicolor' ),
            // Shipping
            'shipping_country'          => get_option( 'pod_gmc_shipping_country', 'US' ),
            'shipping_service'          => get_option( 'pod_gmc_shipping_service', 'Standard Shipping' ),
            'shipping_price'            => get_option( 'pod_gmc_shipping_price', '0.00' ),
            // Return policy
            'return_policy_url'         => get_option( 'pod_gmc_return_policy', '' ),
            // Headless storefront URL override
            'storefront_url'            => $this->get_storefront_url(),
        );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'pod-ai-connector',
            __( 'GMC Feed', 'pod-ai-connector' ),
            __( 'GMC Feed', 'pod-ai-connector' ),
            'manage_options',
            'pod-gmc-feed',
            array( $this, 'render_admin_page' )
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'pod_gmc_settings', 'pod_gmc_feed_slug', array(
            'sanitize_callback' => 'sanitize_title',
            'default'           => 'gmc-products',
        ) );
        register_setting( 'pod_gmc_settings', 'pod_gmc_include_all' );
        register_setting( 'pod_gmc_settings', 'pod_gmc_include_additional_images' );
        register_setting( 'pod_gmc_settings', 'pod_gmc_variation_mode' );
        register_setting( 'pod_gmc_settings', 'pod_gmc_included_categories' );
        register_setting( 'pod_gmc_settings', 'pod_gmc_default_brand' );
        register_setting( 'pod_gmc_settings', 'pod_gmc_default_category' );
        register_setting( 'pod_gmc_settings', 'pod_gmc_category_mapping' );
        register_setting( 'pod_gmc_settings', 'pod_gmc_default_gender' );
        register_setting( 'pod_gmc_settings', 'pod_gmc_default_age_group' );
        register_setting( 'pod_gmc_settings', 'pod_gmc_default_color' );
        // Shipping settings
        register_setting( 'pod_gmc_settings', 'pod_gmc_shipping_country' );
        register_setting( 'pod_gmc_settings', 'pod_gmc_shipping_service' );
        register_setting( 'pod_gmc_settings', 'pod_gmc_shipping_price' );
        register_setting( 'pod_gmc_settings', 'pod_gmc_free_shipping_threshold' );
        // Return policy
        register_setting( 'pod_gmc_settings', 'pod_gmc_return_policy' );
        // Storefront (headless frontend) URL override
        register_setting( 'pod_gmc_settings', 'pod_gmc_storefront_url', array(
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        ) );
    }

    /**
     * Save category mapping via custom POST action
     */
    public function save_category_mapping() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'pod_gmc_category_mapping_nonce' );

        $mapping = array();
        if ( isset( $_POST['pod_gmc_cat_map'] ) && is_array( $_POST['pod_gmc_cat_map'] ) ) {
            foreach ( $_POST['pod_gmc_cat_map'] as $slug => $google_cat ) {
                $mapping[ sanitize_key( $slug ) ] = sanitize_text_field( $google_cat );
            }
        }
        // Save default fallback
        if ( isset( $_POST['pod_gmc_cat_map_default'] ) ) {
            $mapping['default'] = sanitize_text_field( $_POST['pod_gmc_cat_map_default'] );
        }

        update_option( 'pod_gmc_category_mapping', wp_json_encode( $mapping ) );

        wp_redirect( admin_url( 'admin.php?page=pod-gmc-feed&updated=1' ) );
        exit;
    }

    /**
     * Save category filter (which categories to include in feed)
     */
    public function save_category_filter() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'pod_gmc_category_filter_nonce' );

        $selected = array();
        if ( isset( $_POST['pod_gmc_included_cats'] ) && is_array( $_POST['pod_gmc_included_cats'] ) ) {
            foreach ( $_POST['pod_gmc_included_cats'] as $term_id ) {
                $selected[] = absint( $term_id );
            }
        }
        // Empty array = feed all categories (no filter)
        update_option( 'pod_gmc_included_categories', wp_json_encode( $selected ) );

        wp_redirect( admin_url( 'admin.php?page=pod-gmc-feed&updated=1' ) );
        exit;
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $feed_url        = $this->get_feed_url();
        $feed_slug       = $this->get_feed_slug();
        $current_mode    = get_option( 'pod_gmc_variation_mode', 'all' );
        $mapping_raw     = get_option( 'pod_gmc_category_mapping', '{}' );
        $mapping         = json_decode( $mapping_raw, true );
        if ( ! is_array( $mapping ) ) {
            $mapping = array();
        }

        // Load saved category filter
        $included_cats_raw = get_option( 'pod_gmc_included_categories', '[]' );
        $included_cats     = json_decode( $included_cats_raw, true );
        if ( ! is_array( $included_cats ) ) {
            $included_cats = array();
        }

        // Get all WooCommerce product categories
        $product_cats = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
        ) );

        $updated = isset( $_GET['updated'] );
        ?>
        <div class="wrap">
            <h1><?php _e( 'GMC Product Feed', 'pod-ai-connector' ); ?></h1>

            <?php if ( $updated ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php _e( 'Settings saved.', 'pod-ai-connector' ); ?></p></div>
            <?php endif; ?>
            
            <!-- Feed URL Card -->
            <div class="card" style="max-width: 800px; padding: 20px;">
                <h2><?php _e( 'Feed URL', 'pod-ai-connector' ); ?></h2>
                <p><?php _e( 'Use this URL in Google Merchant Center to fetch your product feed:', 'pod-ai-connector' ); ?></p>
                <code id="pod-gmc-feed-url" style="display: block; padding: 15px; background: #f0f0f0; font-size: 14px; word-break: break-all;">
                    <?php echo esc_url( $feed_url ); ?>
                </code>
                <p style="margin-top: 15px;">
                    <a href="<?php echo esc_url( $feed_url ); ?>" target="_blank" class="button button-secondary" id="pod-gmc-preview-btn">
                        <?php _e( 'Preview Feed', 'pod-ai-connector' ); ?>
                    </a>
                    <button type="button" class="button" onclick="navigator.clipboard.writeText(document.getElementById('pod-gmc-feed-url').textContent.trim()); alert('Copied!');">
                        <?php _e( 'Copy URL', 'pod-ai-connector' ); ?>
                    </button>
                </p>
            </div>
            
            <!-- Main Settings Form -->
            <form method="post" action="options.php" style="max-width: 800px; margin-top: 20px;">
                <?php settings_fields( 'pod_gmc_settings' ); ?>
                
                <table class="form-table">
                    <!-- Feed URL Slug -->
                    <tr>
                        <th scope="row"><?php _e( 'Feed URL Slug', 'pod-ai-connector' ); ?></th>
                        <td>
                            <code><?php echo esc_html( home_url( '/?feed=' ) ); ?></code>
                            <input type="text" name="pod_gmc_feed_slug" value="<?php echo esc_attr( $feed_slug ); ?>" class="regular-text" style="width: 200px;" placeholder="gmc-products" />
                            <p class="description">
                                <?php _e( 'Tùy chỉnh đường dẫn feed. Mặc định: <code>gmc-products</code>. Chỉ dùng chữ thường, số và dấu gạch ngang.', 'pod-ai-connector' ); ?>
                            </p>
                            <p class="description" style="color: #d63638;">
                                <?php _e( '⚠ Khi thay đổi slug, nhớ cập nhật lại URL trong Google Merchant Center.', 'pod-ai-connector' ); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Storefront URL (Headless) -->
                    <tr>
                        <th scope="row"><?php _e( 'Storefront URL (Headless)', 'pod-ai-connector' ); ?></th>
                        <td>
                            <input type="url" name="pod_gmc_storefront_url" value="<?php echo esc_attr( get_option( 'pod_gmc_storefront_url', '' ) ); ?>" class="regular-text" placeholder="https://www.mmaclarksville.com" />
                            <p class="description">
                                <?php _e( '<strong>Headless setup:</strong> Nếu WordPress chạy tại domain API riêng (ví dụ: <code>api.mmaclarksville.com</code>), nhập URL frontend storefront tại đây (ví dụ: <code>https://www.mmaclarksville.com</code>). Plugin sẽ tự động thay thế domain trong tất cả link sản phẩm trong feed. Để trống nếu không dùng headless.', 'pod-ai-connector' ); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Include All Products -->
                    <tr>
                        <th scope="row"><?php _e( 'Include All Products', 'pod-ai-connector' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="pod_gmc_include_all" value="1" <?php checked( get_option( 'pod_gmc_include_all' ), 1 ); ?> />
                                <?php _e( 'Include products without POD Variations', 'pod-ai-connector' ); ?>
                            </label>
                            <p class="description"><?php _e( 'By default, only products with POD Variations are included.', 'pod-ai-connector' ); ?></p>
                        </td>
                    </tr>

                    <!-- Include Additional Images -->
                    <tr>
                        <th scope="row"><?php _e( 'Additional Images', 'pod-ai-connector' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="pod_gmc_include_additional_images" value="1" <?php checked( get_option( 'pod_gmc_include_additional_images' ), 1 ); ?> />
                                <?php _e( 'Include gallery images in feed', 'pod-ai-connector' ); ?>
                            </label>
                            <p class="description">
                                <?php _e( 'Xuất ảnh phụ từ Product Gallery lên GMC. Tối đa 10 ảnh phụ mỗi sản phẩm (<code>additional_image_link</code>).', 'pod-ai-connector' ); ?>
                            </p>
                        </td>
                    </tr>

                    <!-- Variation Feed Mode (NEW) -->
                    <tr>
                        <th scope="row"><?php _e( 'Variation Feed Mode', 'pod-ai-connector' ); ?></th>
                        <td>
                            <fieldset>
                                <label style="display:block; margin-bottom:8px;">
                                    <input type="radio" name="pod_gmc_variation_mode" value="all" <?php checked( $current_mode, 'all' ); ?> />
                                    <strong><?php _e( 'All Variations', 'pod-ai-connector' ); ?></strong>
                                    &nbsp;<span class="description"><?php _e( '— Feed mọi biến thể (style × size). File lớn, nhưng đầy đủ nhất.', 'pod-ai-connector' ); ?></span>
                                </label>
                                <label style="display:block;">
                                    <input type="radio" name="pod_gmc_variation_mode" value="first_only" <?php checked( $current_mode, 'first_only' ); ?> />
                                    <strong><?php _e( 'First Variation Only', 'pod-ai-connector' ); ?></strong>
                                    &nbsp;<span class="description"><?php _e( '— Chỉ feed 1 biến thể đầu tiên mỗi sản phẩm. Feed nhỏ gọn, phù hợp giới hạn GMC.', 'pod-ai-connector' ); ?></span>
                                </label>
                            </fieldset>
                        </td>
                    </tr>

                    <!-- Default Brand -->
                    <tr>
                        <th scope="row"><?php _e( 'Default Brand', 'pod-ai-connector' ); ?></th>
                        <td>
                            <input type="text" name="pod_gmc_default_brand" value="<?php echo esc_attr( get_option( 'pod_gmc_default_brand', get_bloginfo( 'name' ) ) ); ?>" class="regular-text" />
                            <p class="description"><?php _e( 'Used when product has no brand attribute.', 'pod-ai-connector' ); ?></p>
                        </td>
                    </tr>

                    <!-- Default Google Category -->
                    <tr>
                        <th scope="row"><?php _e( 'Default Google Category', 'pod-ai-connector' ); ?></th>
                        <td>
                            <input type="text" name="pod_gmc_default_category" value="<?php echo esc_attr( get_option( 'pod_gmc_default_category', 'Apparel & Accessories' ) ); ?>" class="regular-text" />
                            <p class="description">
                                <?php _e( 'Áp dụng khi sản phẩm không có mapping riêng bên dưới.', 'pod-ai-connector' ); ?>
                                <a href="https://support.google.com/merchants/answer/6324436" target="_blank"><?php _e( 'View categories', 'pod-ai-connector' ); ?></a>
                            </p>
                        </td>
                    </tr>

                    <!-- Default Gender -->
                    <tr>
                        <th scope="row"><?php _e( 'Default Gender', 'pod-ai-connector' ); ?></th>
                        <td>
                            <select name="pod_gmc_default_gender">
                                <?php
                                $current_gender = get_option( 'pod_gmc_default_gender', 'unisex' );
                                $genders = array(
                                    'unisex' => __( 'Unisex', 'pod-ai-connector' ),
                                    'male'   => __( 'Male', 'pod-ai-connector' ),
                                    'female' => __( 'Female', 'pod-ai-connector' ),
                                );
                                foreach ( $genders as $value => $label ) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr( $value ),
                                        selected( $current_gender, $value, false ),
                                        esc_html( $label )
                                    );
                                }
                                ?>
                            </select>
                            <p class="description"><?php _e( 'Default gender for all products. Required by GMC for apparel.', 'pod-ai-connector' ); ?></p>
                        </td>
                    </tr>

                    <!-- Default Age Group -->
                    <tr>
                        <th scope="row"><?php _e( 'Default Age Group', 'pod-ai-connector' ); ?></th>
                        <td>
                            <select name="pod_gmc_default_age_group">
                                <?php
                                $current_age = get_option( 'pod_gmc_default_age_group', 'adult' );
                                $age_groups  = array(
                                    'adult'   => __( 'Adult', 'pod-ai-connector' ),
                                    'kids'    => __( 'Kids', 'pod-ai-connector' ),
                                    'toddler' => __( 'Toddler', 'pod-ai-connector' ),
                                    'infant'  => __( 'Infant', 'pod-ai-connector' ),
                                    'newborn' => __( 'Newborn', 'pod-ai-connector' ),
                                );
                                foreach ( $age_groups as $value => $label ) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr( $value ),
                                        selected( $current_age, $value, false ),
                                        esc_html( $label )
                                    );
                                }
                                ?>
                            </select>
                            <p class="description"><?php _e( 'Default age group for all products. Required by GMC for apparel.', 'pod-ai-connector' ); ?></p>
                        </td>
                    </tr>

                    <!-- Default Color -->
                    <tr>
                        <th scope="row"><?php _e( 'Default Color', 'pod-ai-connector' ); ?></th>
                        <td>
                            <input type="text" name="pod_gmc_default_color" value="<?php echo esc_attr( get_option( 'pod_gmc_default_color', 'Multicolor' ) ); ?>" class="regular-text" />
                            <p class="description"><?php _e( 'Default color when product has no color attribute.', 'pod-ai-connector' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <!-- Shipping Settings -->
                <h2 style="margin-top: 30px;"><?php _e( 'Shipping Settings', 'pod-ai-connector' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e( 'Shipping Country', 'pod-ai-connector' ); ?></th>
                        <td>
                            <input type="text" name="pod_gmc_shipping_country" value="<?php echo esc_attr( get_option( 'pod_gmc_shipping_country', 'US' ) ); ?>" class="small-text" placeholder="US" />
                            <p class="description"><?php _e( 'ISO 3166-1 country code (e.g., US, VN, GB).', 'pod-ai-connector' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Shipping Service', 'pod-ai-connector' ); ?></th>
                        <td>
                            <input type="text" name="pod_gmc_shipping_service" value="<?php echo esc_attr( get_option( 'pod_gmc_shipping_service', 'Standard Shipping' ) ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Shipping Price', 'pod-ai-connector' ); ?></th>
                        <td>
                            <input type="text" name="pod_gmc_shipping_price" value="<?php echo esc_attr( get_option( 'pod_gmc_shipping_price', '0.00' ) ); ?>" class="small-text" placeholder="0.00" />
                            <span><?php echo function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD'; ?></span>
                            <p class="description"><?php _e( 'Use 0.00 for free shipping.', 'pod-ai-connector' ); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- Return Policy -->
                <h2 style="margin-top: 30px;"><?php _e( 'Return Policy', 'pod-ai-connector' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e( 'Return Policy URL', 'pod-ai-connector' ); ?></th>
                        <td>
                            <input type="url" name="pod_gmc_return_policy" value="<?php echo esc_attr( get_option( 'pod_gmc_return_policy', '' ) ); ?>" class="regular-text" placeholder="https://yoursite.com/return-policy" />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button( __( 'Save Settings', 'pod-ai-connector' ) ); ?>
            </form>

            <!-- Category Filter Section -->
            <div style="max-width: 800px; margin-top: 30px;">
                <h2><?php _e( 'Feed Category Filter', 'pod-ai-connector' ); ?></h2>
                <p class="description" style="margin-bottom:12px;">
                    <?php _e( 'Chọn các category sẽ được đưa vào feed. <strong>Bỏ chọn tất cả = feed toàn bộ category.</strong>', 'pod-ai-connector' ); ?>
                </p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="pod_gmc_save_category_filter" />
                    <?php wp_nonce_field( 'pod_gmc_category_filter_nonce' ); ?>

                    <div style="border:1px solid #ccd0d4; background:#fff; border-radius:4px; padding:16px; max-height:380px; overflow-y:auto;">
                        <?php if ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ) : ?>
                            <?php
                            // Build tree: top-level first, then children
                            $top_cats   = array();
                            $child_cats = array();
                            foreach ( $product_cats as $cat ) {
                                if ( $cat->parent == 0 ) {
                                    $top_cats[] = $cat;
                                } else {
                                    $child_cats[ $cat->parent ][] = $cat;
                                }
                            }
                            ?>
                            <p style="margin-top:0;">
                                <a href="#" onclick="document.querySelectorAll('[name=\'pod_gmc_included_cats[]\']').forEach(c=>c.checked=true);return false;" style="margin-right:12px;"><?php _e( 'Select All', 'pod-ai-connector' ); ?></a>
                                <a href="#" onclick="document.querySelectorAll('[name=\'pod_gmc_included_cats[]\']').forEach(c=>c.checked=false);return false;"><?php _e( 'Deselect All', 'pod-ai-connector' ); ?></a>
                            </p>
                            <?php foreach ( $top_cats as $cat ) : ?>
                                <?php $is_checked = empty( $included_cats ) || in_array( $cat->term_id, $included_cats ); ?>
                                <div style="margin-bottom:6px;">
                                    <label style="font-weight:600; font-size:13px;">
                                        <input type="checkbox"
                                               name="pod_gmc_included_cats[]"
                                               value="<?php echo esc_attr( $cat->term_id ); ?>"
                                               <?php checked( $is_checked ); ?>
                                        />
                                        <?php echo esc_html( $cat->name ); ?>
                                        <span style="color:#999; font-weight:normal;">(<?php echo intval( $cat->count ); ?> sp)</span>
                                    </label>
                                    <?php if ( isset( $child_cats[ $cat->term_id ] ) ) : ?>
                                        <div style="margin-left:24px; margin-top:4px;">
                                            <?php foreach ( $child_cats[ $cat->term_id ] as $child ) : ?>
                                                <?php $is_child_checked = empty( $included_cats ) || in_array( $child->term_id, $included_cats ); ?>
                                                <div style="margin-bottom:4px;">
                                                    <label style="font-size:13px;">
                                                        <input type="checkbox"
                                                               name="pod_gmc_included_cats[]"
                                                               value="<?php echo esc_attr( $child->term_id ); ?>"
                                                               <?php checked( $is_child_checked ); ?>
                                                        />
                                                        <?php echo esc_html( $child->name ); ?>
                                                        <span style="color:#999;">(<?php echo intval( $child->count ); ?> sp)</span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <em><?php _e( 'Không tìm thấy WooCommerce product categories.', 'pod-ai-connector' ); ?></em>
                        <?php endif; ?>
                    </div>

                    <?php submit_button( __( 'Save Category Filter', 'pod-ai-connector' ), 'primary', 'submit_cat_filter', false ); ?>
                </form>
            </div>

            <!-- Category Mapping Section (separate form) -->
            <div style="max-width: 800px; margin-top: 30px;">
                <h2><?php _e( 'Category → Google Category Mapping', 'pod-ai-connector' ); ?></h2>
                <p class="description" style="margin-bottom:12px;">
                    <?php _e( 'Map từng WooCommerce category sang Google Product Category tương ứng. Để trống = dùng Default Google Category ở trên.', 'pod-ai-connector' ); ?>
                    <a href="https://support.google.com/merchants/answer/6324436" target="_blank"><?php _e( 'Xem danh sách Google categories', 'pod-ai-connector' ); ?></a>
                </p>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="pod_gmc_save_category_mapping" />
                    <?php wp_nonce_field( 'pod_gmc_category_mapping_nonce' ); ?>

                    <table class="widefat fixed striped" style="margin-bottom: 16px;">
                        <thead>
                            <tr>
                                <th style="width: 35%;"><?php _e( 'WooCommerce Category', 'pod-ai-connector' ); ?></th>
                                <th><?php _e( 'Google Product Category', 'pod-ai-connector' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $product_cats ) && ! is_wp_error( $product_cats ) ) : ?>
                                <?php foreach ( $product_cats as $cat ) : ?>
                                    <?php
                                    $saved_value = isset( $mapping[ $cat->slug ] ) ? $mapping[ $cat->slug ] : '';
                                    $indent = str_repeat( '— ', $cat->parent ? 1 : 0 );
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html( $indent . $cat->name ); ?></strong>
                                            <br><code style="font-size:11px; color:#888;"><?php echo esc_html( $cat->slug ); ?></code>
                                            <span style="color:#aaa; font-size:11px;"> (<?php echo esc_html( $cat->count ); ?> sản phẩm)</span>
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                name="pod_gmc_cat_map[<?php echo esc_attr( $cat->slug ); ?>]"
                                                value="<?php echo esc_attr( $saved_value ); ?>"
                                                class="large-text"
                                                placeholder="<?php esc_attr_e( 'e.g. Apparel & Accessories > Clothing > Shirts & Tops', 'pod-ai-connector' ); ?>"
                                            />
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr><td colspan="2"><em><?php _e( 'Không tìm thấy WooCommerce product categories.', 'pod-ai-connector' ); ?></em></td></tr>
                            <?php endif; ?>

                            <!-- Default fallback row -->
                            <tr style="background: #fffbe6;">
                                <td>
                                    <strong><?php _e( 'Default (fallback)', 'pod-ai-connector' ); ?></strong>
                                    <br><span class="description" style="font-size:11px;"><?php _e( 'Áp dụng khi không match category nào.', 'pod-ai-connector' ); ?></span>
                                </td>
                                <td>
                                    <input
                                        type="text"
                                        name="pod_gmc_cat_map_default"
                                        value="<?php echo esc_attr( isset( $mapping['default'] ) ? $mapping['default'] : '' ); ?>"
                                        class="large-text"
                                        placeholder="<?php esc_attr_e( 'e.g. Apparel & Accessories', 'pod-ai-connector' ); ?>"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button( __( 'Save Category Mapping', 'pod-ai-connector' ), 'primary', 'submit_mapping' ); ?>
                </form>
            </div>

            <!-- How to use -->
            <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px;">
                <h2><?php _e( 'How to use in Google Merchant Center', 'pod-ai-connector' ); ?></h2>
                <ol>
                    <li><?php _e( 'Go to Google Merchant Center → Products → Feeds', 'pod-ai-connector' ); ?></li>
                    <li><?php _e( 'Click "Add primary feed"', 'pod-ai-connector' ); ?></li>
                    <li><?php _e( 'Select "Scheduled fetch"', 'pod-ai-connector' ); ?></li>
                    <li><?php _e( 'Paste the Feed URL above', 'pod-ai-connector' ); ?></li>
                    <li><?php _e( 'Set fetch frequency (daily recommended)', 'pod-ai-connector' ); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }
}

// Initialize
new POD_GMC_Feed();
