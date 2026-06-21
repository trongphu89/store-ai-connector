<?php
/**
 * POD Variations - Frontend Display & Cart Integration
 * Thay thế WooCommerce Variable Products bằng Simple Product + Meta
 * 
 * @package POD_AI_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class POD_Variations {
    
    /**
     * SVG Icons for product types - Modern & Professional Design
     */
    private static $product_icons = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize icons
        self::init_icons();
        
        // Add body class for products with POD Variations
        add_filter( 'body_class', array( $this, 'add_body_class' ) );
        
        // Frontend hooks
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_variation_form' ), 15 );
        
        // Price display hooks - Show "From $X" for products with POD Variations
        add_filter( 'woocommerce_get_price_html', array( $this, 'modify_price_html' ), 10, 2 );
        
        // Cart hooks
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'calculate_totals' ), 20 );
        
        // Order hooks
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_meta' ), 10, 4 );
        add_action( 'woocommerce_admin_order_item_headers', array( $this, 'admin_order_headers' ) );
        add_action( 'woocommerce_admin_order_item_values', array( $this, 'admin_order_values' ), 10, 3 );
        add_action( 'woocommerce_order_item_meta_end', array( $this, 'order_item_meta_display' ), 10, 4 );
        
        // Assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_shop_assets' ) );
        
        // Validation
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );
        
        // Schema JSON-LD for GMC compliance
        add_action( 'wp_head', array( $this, 'output_schema_jsonld' ), 99 );
    }
    
    /**
     * Initialize SVG icons - Modern Professional Design
     */
    private static function init_icons() {
        self::$product_icons = array(
            // ============ APPAREL ============
            't-shirt' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22 5L26 8L24 12H22V27H10V12H8L6 8L10 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 5C10 5 11 8 16 8C21 8 22 5 22 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M13 8V10M19 8V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'tshirt' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22 5L26 8L24 12H22V27H10V12H8L6 8L10 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 5C10 5 11 8 16 8C21 8 22 5 22 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M13 8V10M19 8V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'hoodie' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 28V12L8 8C8 8 10 4 16 4C22 4 24 8 24 8L26 12V28H22V25H10V28H6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 8C8 8 9 6 16 6C23 6 24 8 24 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 8L16 4L22 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 10V15M19 10V15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M11 18H21V23H11V18Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 25H10M22 25H26" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'sweatshirt' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 28V12L4 10L8 6H24L28 10L26 12V28H6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><ellipse cx="16" cy="8" rx="5" ry="2" stroke="currentColor" stroke-width="1.5"/><path d="M11 8C11 8 12 10 16 10C20 10 21 8 21 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M6 25H26" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M6 22H10V28M26 22H22V28" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            
            'tank-top' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H21L22 8V28H10V8L11 4Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 4C9 4 7 6 7 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M21 4C23 4 25 6 25 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14 4C14 4 14 7 16 7C18 7 18 4 18 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'tanktop' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H21L22 8V28H10V8L11 4Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 4C9 4 7 6 7 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M21 4C23 4 25 6 25 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14 4C14 4 14 7 16 7C18 7 18 4 18 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'long-sleeve' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22 5L28 9L26 26H23L22 14V27H10V14L9 26H6L4 9L10 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 5C10 5 11 8 16 8C21 8 22 5 22 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M6 26H9M23 26H26" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'longsleeve' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22 5L28 9L26 26H23L22 14V27H10V14L9 26H6L4 9L10 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 5C10 5 11 8 16 8C21 8 22 5 22 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M6 26H9M23 26H26" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'polo' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22 5L26 8L24 12H22V27H10V12H8L6 8L10 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M13 5V11M19 5V11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M13 5H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="16" cy="7" r="0.75" fill="currentColor"/><circle cx="16" cy="9.5" r="0.75" fill="currentColor"/></svg>',
            
            'v-neck' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22 5L26 8L24 12H22V27H10V12H8L6 8L10 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 5L16 12L20 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            
            'crop-top' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M22 5L26 8L24 12H22V18H10V12H8L6 8L10 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 5C10 5 11 8 16 8C21 8 22 5 22 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 18H22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
            
            'baby-onesie' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H21L25 8L22 11V18L19 26H17L16 20L15 26H13L10 18V11L7 8L11 4Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="14" cy="13" r="1" fill="currentColor"/><circle cx="18" cy="13" r="1" fill="currentColor"/><circle cx="16" cy="16" r="1" fill="currentColor"/></svg>',
            
            'onesie' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H21L25 8L22 11V18L19 26H17L16 20L15 26H13L10 18V11L7 8L11 4Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="14" cy="13" r="1" fill="currentColor"/><circle cx="18" cy="13" r="1" fill="currentColor"/><circle cx="16" cy="16" r="1" fill="currentColor"/></svg>',
            
            'leggings' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4H20V10L19 28H17L16 16L15 28H13L12 10V4Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 4H20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 7H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'shorts' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 6H23V12L20 22H18L16 14L14 22H12L9 12V6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 6H23" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M9 9H23" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'dress' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13 4H19L21 9L18 12L23 28H9L14 12L11 9L13 4Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 4C14 4 14 7 16 7C18 7 18 4 18 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 20H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-dasharray="2 2"/></svg>',
            
            'socks' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4V16L8 22C6 25 7 28 10 29H16C19 28 20 25 18 22L16 16V4H12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 4H16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 8H16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 11H16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',

            'pajamas' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 4H22V10H10V4Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 10V28" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M20 10V28" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 4L7 7V10L10 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 4L25 7V10L22 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="14" cy="7" r="0.75" fill="currentColor"/><circle cx="18" cy="7" r="0.75" fill="currentColor"/></svg>',

            // ============ HOME & LIVING ============
            'pillow' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="9" width="24" height="14" rx="4" stroke="currentColor" stroke-width="1.5"/><path d="M9 9V23" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M23 9V23" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 12C9 12 11 14 16 14C21 14 23 12 23 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'cushion' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="9" width="24" height="14" rx="4" stroke="currentColor" stroke-width="1.5"/><path d="M9 9V23" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M23 9V23" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 12C9 12 11 14 16 14C21 14 23 12 23 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'blanket' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 8H26V24C26 25.1 25.1 26 24 26H8C6.9 26 6 25.1 6 24V8Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 8C6 6.9 6.9 6 8 6H24C25.1 6 26 6.9 26 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 13H22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 17H22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 21H18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'canvas' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="5" y="5" width="22" height="22" rx="1" stroke="currentColor" stroke-width="1.5"/><path d="M5 10H27" stroke="currentColor" stroke-width="1.5"/><path d="M10 5V27" stroke="currentColor" stroke-width="1.5"/><circle cx="19" cy="18" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M14 23L17 20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'poster' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="7" y="4" width="18" height="24" rx="1" stroke="currentColor" stroke-width="1.5"/><path d="M11 10H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M11 14H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M11 18H17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><rect x="11" y="21" width="10" height="4" rx="0.5" stroke="currentColor" stroke-width="1.5"/></svg>',
            
            'flag' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 4V28" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M7 6H24L21 12L24 18H7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 10H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M11 14H17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'doormat' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="11" width="26" height="10" rx="1" stroke="currentColor" stroke-width="1.5"/><path d="M7 14H25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 18H25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M3 21L5 23M29 21L27 23" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'tapestry' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 4H23V28H9V4Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="13" r="4" stroke="currentColor" stroke-width="1.5"/><path d="M12 21H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 4L7 2M23 4L25 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M6 2H8M24 2H26" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'ornament' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="16" cy="18" r="10" stroke="currentColor" stroke-width="1.5"/><path d="M16 4V8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><rect x="14" y="5" width="4" height="3" rx="0.5" stroke="currentColor" stroke-width="1.5"/><path d="M12 14C12 14 14 16 16 16C18 16 20 14 20 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="16" cy="20" r="2" stroke="currentColor" stroke-width="1.5"/></svg>',
            
            'candle' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="10" y="14" width="12" height="14" rx="1" stroke="currentColor" stroke-width="1.5"/><path d="M13 14V11C13 10 14 9 16 9C18 9 19 10 19 11V14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M16 4V9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><ellipse cx="16" cy="4" rx="1.5" ry="2" fill="currentColor"/><path d="M10 20H22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',

            // ============ DRINKWARE ============
            'mug' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 8H21V24C21 25.1 20.1 26 19 26H9C7.9 26 7 25.1 7 24V8Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12H24C25.1 12 26 12.9 26 14V18C26 19.1 25.1 20 24 20H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 8H21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M10 14H18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'tumbler' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 4H22L20 28H12L10 4Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 4H22" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M11 10H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 18H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><ellipse cx="16" cy="4" rx="6" ry="1" stroke="currentColor" stroke-width="1.5"/></svg>',
            
            'water-bottle' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13 10H19V13L21 16V26C21 27.1 20.1 28 19 28H13C11.9 28 11 27.1 11 26V16L13 13V10Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="14" y="4" width="4" height="6" rx="0.5" stroke="currentColor" stroke-width="1.5"/><path d="M11 20H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14 4H18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
            
            'waterbottle' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13 10H19V13L21 16V26C21 27.1 20.1 28 19 28H13C11.9 28 11 27.1 11 26V16L13 13V10Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="14" y="4" width="4" height="6" rx="0.5" stroke="currentColor" stroke-width="1.5"/><path d="M11 20H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14 4H18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
            
            'beer-stein' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 6H19V24C19 25.1 18.1 26 17 26H9C7.9 26 7 25.1 7 24V6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 10H22C23.1 10 24 10.9 24 12V18C24 19.1 23.1 20 22 20H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 6C7 4.9 7.9 4 9 4H17C18.1 4 19 4.9 19 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 6H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M10 12V18M13 11V19M16 12V18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'beerstein' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 6H19V24C19 25.1 18.1 26 17 26H9C7.9 26 7 25.1 7 24V6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 10H22C23.1 10 24 10.9 24 12V18C24 19.1 23.1 20 22 20H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 6C7 4.9 7.9 4 9 4H17C18.1 4 19 4.9 19 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 6H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M10 12V18M13 11V19M16 12V18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'wine-glass' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11 4H21L20 14C20 17 18 20 16 20C14 20 12 17 12 14L11 4Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 20V26" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 28H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M11 4H21" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 10H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',


            // ============ ACCESSORIES ============
            'tote-bag' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 12H24V26C24 27.1 23.1 28 22 28H10C8.9 28 8 27.1 8 26V12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 12V9C12 6 13.5 4 16 4C18.5 4 20 6 20 9V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M8 12H24" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 18H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'totebag' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 12H24V26C24 27.1 23.1 28 22 28H10C8.9 28 8 27.1 8 26V12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 12V9C12 6 13.5 4 16 4C18.5 4 20 6 20 9V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M8 12H24" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 18H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'phone-case' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="9" y="3" width="14" height="26" rx="3" stroke="currentColor" stroke-width="1.5"/><path d="M14 7H18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="16" cy="25" r="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="11" y="10" width="10" height="11" rx="1" stroke="currentColor" stroke-width="1.5"/></svg>',
            
            'phonecase' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="9" y="3" width="14" height="26" rx="3" stroke="currentColor" stroke-width="1.5"/><path d="M14 7H18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="16" cy="25" r="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="11" y="10" width="10" height="11" rx="1" stroke="currentColor" stroke-width="1.5"/></svg>',
            
            'backpack' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 12H24V26C24 27.1 23.1 28 22 28H10C8.9 28 8 27.1 8 26V12Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 12V9C12 6 13.5 4 16 4C18.5 4 20 6 20 9V12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><rect x="11" y="17" width="10" height="6" rx="1" stroke="currentColor" stroke-width="1.5"/><path d="M8 16H6V24H8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M24 16H26V24H24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            
            'laptop-sleeve' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="8" width="24" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M9 13H23" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 17H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M4 12H28" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'laptopsleeve' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="8" width="24" height="16" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M9 13H23" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 17H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M4 12H28" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'hat' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 20C6 14 10 10 16 10C22 10 26 14 26 20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M4 20H28V23C28 24 25 26 16 26C7 26 4 24 4 23V20Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 20L2 22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><ellipse cx="16" cy="20" rx="8" ry="2" stroke="currentColor" stroke-width="1.5"/></svg>',
            
            'cap' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 20C6 14 10 10 16 10C22 10 26 14 26 20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M4 20H28V23C28 24 25 26 16 26C7 26 4 24 4 23V20Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 20L2 22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><ellipse cx="16" cy="20" rx="8" ry="2" stroke="currentColor" stroke-width="1.5"/></svg>',
            
            'beanie' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 20C7 14 10 8 16 8C22 8 25 14 25 20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 20V24C7 25 10 27 16 27C22 27 25 25 25 24V20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="5" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M7 22H25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'face-mask' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 14C6 11 10 8 16 8C22 8 26 11 26 14V18C26 21 22 24 16 24C10 24 6 21 6 18V14Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 14C4 14 3 15 3 16C3 17 4 18 6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M26 14C28 14 29 15 29 16C29 17 28 18 26 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 15H22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 19H22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'facemask' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 14C6 11 10 8 16 8C22 8 26 11 26 14V18C26 21 22 24 16 24C10 24 6 21 6 18V14Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 14C4 14 3 15 3 16C3 17 4 18 6 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M26 14C28 14 29 15 29 16C29 17 28 18 26 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 15H22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 19H22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'bandana' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16 6L6 16L16 26L26 16L16 6Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="16" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M11 11L13 13M21 11L19 13M11 21L13 19M21 21L19 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',

            // ============ STATIONERY ============
            'sticker' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="16" cy="16" r="11" stroke="currentColor" stroke-width="1.5"/><path d="M16 5C16 5 22 7 25 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M25 12L28 9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 13L20 13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 17L18 17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'badge' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="16" cy="14" r="10" stroke="currentColor" stroke-width="1.5"/><circle cx="16" cy="14" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M16 24V28" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 28L16 26L20 28" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            
            'button-pin' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="16" cy="14" r="10" stroke="currentColor" stroke-width="1.5"/><circle cx="16" cy="14" r="6" stroke="currentColor" stroke-width="1.5"/><path d="M16 24V28" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 28L16 26L20 28" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            
            'notebook' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="7" y="4" width="18" height="24" rx="1" stroke="currentColor" stroke-width="1.5"/><path d="M12 4V28" stroke="currentColor" stroke-width="1.5"/><path d="M15 10H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M15 14H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M15 18H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="9.5" cy="8" r="0.75" fill="currentColor"/><circle cx="9.5" cy="12" r="0.75" fill="currentColor"/><circle cx="9.5" cy="16" r="0.75" fill="currentColor"/></svg>',
            
            'journal' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="7" y="4" width="18" height="24" rx="1" stroke="currentColor" stroke-width="1.5"/><path d="M12 4V28" stroke="currentColor" stroke-width="1.5"/><path d="M15 10H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M15 14H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M15 18H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="9.5" cy="8" r="0.75" fill="currentColor"/><circle cx="9.5" cy="12" r="0.75" fill="currentColor"/><circle cx="9.5" cy="16" r="0.75" fill="currentColor"/></svg>',
            
            'keychain' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="7" stroke="currentColor" stroke-width="1.5"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M17 17L26 26" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M23 29L29 23" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M21 21L23 23" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'magnet' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 10V20C8 24 11 27 16 27C21 27 24 24 24 20V10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><rect x="6" y="6" width="6" height="6" rx="0.5" stroke="currentColor" stroke-width="1.5"/><rect x="20" y="6" width="6" height="6" rx="0.5" stroke="currentColor" stroke-width="1.5"/><path d="M6 10H12M20 10H26" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'greeting-card' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="7" width="24" height="18" rx="1" stroke="currentColor" stroke-width="1.5"/><path d="M4 13H28" stroke="currentColor" stroke-width="1.5"/><path d="M9 18H15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 21H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><rect x="18" y="16" width="6" height="6" rx="0.5" stroke="currentColor" stroke-width="1.5"/></svg>',
            
            'greetingcard' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="7" width="24" height="18" rx="1" stroke="currentColor" stroke-width="1.5"/><path d="M4 13H28" stroke="currentColor" stroke-width="1.5"/><path d="M9 18H15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 21H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><rect x="18" y="16" width="6" height="6" rx="0.5" stroke="currentColor" stroke-width="1.5"/></svg>',
            
            'mouse-pad' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="9" width="24" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><ellipse cx="16" cy="16" rx="4" ry="5" stroke="currentColor" stroke-width="1.5"/><path d="M16 13V15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'mousepad' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="9" width="24" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><ellipse cx="16" cy="16" rx="4" ry="5" stroke="currentColor" stroke-width="1.5"/><path d="M16 13V15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'phone-grip' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="16" cy="16" r="9" stroke="currentColor" stroke-width="1.5"/><circle cx="16" cy="16" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M16 7V5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M16 27V25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 16H5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M27 16H25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'phonegrip' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="16" cy="16" r="9" stroke="currentColor" stroke-width="1.5"/><circle cx="16" cy="16" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M16 7V5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M16 27V25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 16H5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M27 16H25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'popsocket' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="16" cy="16" r="9" stroke="currentColor" stroke-width="1.5"/><circle cx="16" cy="16" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M16 7V5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M16 27V25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M7 16H5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M27 16H25" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',


            // ============ PET SUPPLIES ============
            'pet-tag' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16 5L26 10V20L16 27L6 20V10L16 5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="8" r="1.5" fill="currentColor"/><path d="M12 16H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M13 20H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'pettag' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16 5L26 10V20L16 27L6 20V10L16 5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="8" r="1.5" fill="currentColor"/><path d="M12 16H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M13 20H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'pet-bowl' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="16" cy="16" rx="12" ry="5" stroke="currentColor" stroke-width="1.5"/><path d="M4 16C4 22 9 26 16 26C23 26 28 22 28 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M8 16V19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M24 16V19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><ellipse cx="16" cy="14" rx="6" ry="2" stroke="currentColor" stroke-width="1.5"/></svg>',
            
            'petbowl' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="16" cy="16" rx="12" ry="5" stroke="currentColor" stroke-width="1.5"/><path d="M4 16C4 22 9 26 16 26C23 26 28 22 28 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M8 16V19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M24 16V19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><ellipse cx="16" cy="14" rx="6" ry="2" stroke="currentColor" stroke-width="1.5"/></svg>',
            
            'pet-bed' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="16" cy="22" rx="12" ry="6" stroke="currentColor" stroke-width="1.5"/><path d="M4 22V18C4 13 9 9 16 9C23 9 28 13 28 18V22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 18C10 16 12 14 16 14C20 14 22 16 22 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'petbed' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="16" cy="22" rx="12" ry="6" stroke="currentColor" stroke-width="1.5"/><path d="M4 22V18C4 13 9 9 16 9C23 9 28 13 28 18V22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 18C10 16 12 14 16 14C20 14 22 16 22 18" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'pet-bandana' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 8H26L16 26L6 8Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 8C6 6 7 5 9 5H23C25 5 26 6 26 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 12H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="16" cy="17" r="2" stroke="currentColor" stroke-width="1.5"/></svg>',
            
            'petbandana' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 8H26L16 26L6 8Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 8C6 6 7 5 9 5H23C25 5 26 6 26 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M12 12H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="16" cy="17" r="2" stroke="currentColor" stroke-width="1.5"/></svg>',
            
            'pet-collar' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="16" cy="16" rx="11" ry="6" stroke="currentColor" stroke-width="1.5"/><ellipse cx="16" cy="16" rx="7" ry="3" stroke="currentColor" stroke-width="1.5"/><circle cx="16" cy="22" r="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M16 19V22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            'petcollar' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="16" cy="16" rx="11" ry="6" stroke="currentColor" stroke-width="1.5"/><ellipse cx="16" cy="16" rx="7" ry="3" stroke="currentColor" stroke-width="1.5"/><circle cx="16" cy="22" r="2.5" stroke="currentColor" stroke-width="1.5"/><path d="M16 19V22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            
            // ============ DEFAULT ============
            'default' => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="5" y="5" width="22" height="22" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M5 12H27" stroke="currentColor" stroke-width="1.5"/><path d="M12 5V27" stroke="currentColor" stroke-width="1.5"/><circle cx="19.5" cy="19.5" r="4.5" stroke="currentColor" stroke-width="1.5"/></svg>',
        );
    }
    
    /**
     * Get SVG icon for a style name
     * @param string $style_name The style name to look up
     * @param string $custom_svg Optional custom SVG from product meta
     */
    public static function get_icon( $style_name, $custom_svg = '' ) {
        // Priority 1: Custom SVG from product meta
        if ( ! empty( $custom_svg ) ) {
            return $custom_svg;
        }
        
        // Priority 2: Predefined icon lookup
        // Normalize the name: lowercase, replace spaces with hyphens
        $key = strtolower( trim( $style_name ) );
        $key = preg_replace( '/\s+/', '-', $key );
        $key = preg_replace( '/[^a-z0-9\-]/', '', $key );
        
        // Try exact match first
        if ( isset( self::$product_icons[ $key ] ) ) {
            return self::$product_icons[ $key ];
        }
        
        // Try without hyphens
        $key_no_hyphen = str_replace( '-', '', $key );
        if ( isset( self::$product_icons[ $key_no_hyphen ] ) ) {
            return self::$product_icons[ $key_no_hyphen ];
        }
        
        // Try partial match
        foreach ( self::$product_icons as $icon_key => $icon_svg ) {
            if ( strpos( $key, $icon_key ) !== false || strpos( $icon_key, $key ) !== false ) {
                return $icon_svg;
            }
        }
        
        // Return default icon
        return self::$product_icons['default'];
    }
    
    /**
     * Add body class for products with POD Variations
     */
    public function add_body_class( $classes ) {
        if ( ! is_product() ) {
            return $classes;
        }
        
        global $post;
        $pod_variations = get_post_meta( $post->ID, '_pod_variations', true );
        
        if ( ! empty( $pod_variations ) ) {
            $classes[] = 'pod-variations-active';
        }
        
        return $classes;
    }
    
    /**
     * Modify price HTML to show "From $X" for products with price variations
     * Only show "From" if there's actually a price difference possible
     */
    public function modify_price_html( $price_html, $product ) {
        // Skip if in admin or cart/checkout
        if ( is_admin() || is_cart() || is_checkout() ) {
            return $price_html;
        }
        
        $product_id = $product->get_id();
        $show_from = false;
        $base_price = floatval( $product->get_price() );
        $has_pod_variations = false;
        
        // Check Custom Fields - only if has extra fee enabled with amount > 0
        $custom_fields = get_post_meta( $product_id, '_pod_custom_fields', true );
        if ( ! empty( $custom_fields ) ) {
            $cf_data = is_string( $custom_fields ) ? json_decode( $custom_fields, true ) : $custom_fields;
            if ( ! empty( $cf_data['enableExtraFee'] ) && ! empty( $cf_data['extraFee'] ) ) {
                $extra_fee = floatval( $cf_data['extraFee'] );
                if ( $extra_fee > 0 ) {
                    $show_from = true;
                }
            }
        }
        
        // Check POD Variations
        $pod_variations = get_post_meta( $product_id, '_pod_variations', true );
        if ( ! empty( $pod_variations ) ) {
            $has_pod_variations = true;
            $pv_data = is_string( $pod_variations ) ? json_decode( $pod_variations, true ) : $pod_variations;
            
            // Check styles for price variations
            if ( ! empty( $pv_data['styles'] ) ) {
                // Get all style prices
                $style_prices = array();
                foreach ( $pv_data['styles'] as $style ) {
                    $style_price = isset( $style['basePrice'] ) ? floatval( $style['basePrice'] ) : 0;
                    if ( $style_price > 0 ) {
                        $style_prices[] = $style_price;
                    }
                }
                
                // Use minimum style price as base
                if ( ! empty( $style_prices ) ) {
                    $base_price = min( $style_prices );
                    
                    // Check if styles have different prices
                    if ( count( array_unique( $style_prices ) ) > 1 ) {
                        $show_from = true;
                    }
                }
                
                // Check for per-style size upcharges (when NOT using shared sizes)
                if ( ! $show_from && isset( $pv_data['useSharedSizesColors'] ) && ! $pv_data['useSharedSizesColors'] ) {
                    foreach ( $pv_data['styles'] as $style ) {
                        if ( ! empty( $style['sizes'] ) ) {
                            foreach ( $style['sizes'] as $size ) {
                                if ( isset( $size['upcharge'] ) && floatval( $size['upcharge'] ) > 0 ) {
                                    $show_from = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
            
            // Check for shared size upcharges - OUTSIDE styles block so it works even without styles
            if ( ! $show_from && ! empty( $pv_data['sizes'] ) ) {
                foreach ( $pv_data['sizes'] as $size ) {
                    if ( isset( $size['upcharge'] ) && floatval( $size['upcharge'] ) > 0 ) {
                        $show_from = true;
                        break;
                    }
                }
            }
        }
        
        // Only show "From" if there's actually a price variation
        if ( $show_from && $base_price > 0 ) {
            $from_text = apply_filters( 'pod_variations_from_text', __( 'From', 'pod-ai-connector' ) );
            $price_html = '<span class="pod-var-from-price">'
                        . '<span class="pod-var-from-label">' . esc_html( $from_text ) . '</span> '
                        . wc_price( $base_price )
                        . '</span>';
        } elseif ( $has_pod_variations && $base_price > 0 ) {
            // Has POD Variations but no price variation - just show the base price
            $price_html = wc_price( $base_price );
        }
        // If base_price is 0 or no POD variations, return original price_html
        
        return $price_html;
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        if ( ! is_product() ) {
            return;
        }
        
        global $post;
        $pod_variations = get_post_meta( $post->ID, '_pod_variations', true );
        
        if ( empty( $pod_variations ) ) {
            return;
        }
        
        wp_enqueue_style(
            'pod-variations-css',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/pod-variations.css',
            array(),
            SAC_VERSION
        );
        
        // Add inline CSS to forcefully hide original price - ONLY in main product summary, NOT related products
        $inline_css = '
            body.pod-variations-active div.product > .summary > .price,
            body.pod-variations-active div.product > .summary > p.price,
            body.pod-variations-active div.product > .entry-summary > .price,
            body.pod-variations-active div.product > .entry-summary > p.price,
            body.pod-variations-active div.product > .summary p.price:first-of-type,
            body.pod-variations-active div.product > .entry-summary p.price:first-of-type {
                display: none !important;
            }
            body.pod-variations-active .pod-var-price-amount .woocommerce-Price-amount {
                display: inline !important;
            }
            /* Ensure related products show prices */
            body.pod-variations-active .related.products .woocommerce-Price-amount,
            body.pod-variations-active .upsells.products .woocommerce-Price-amount,
            body.pod-variations-active .cross-sells .woocommerce-Price-amount,
            body.pod-variations-active section.related .woocommerce-Price-amount,
            body.pod-variations-active section.upsells .woocommerce-Price-amount {
                display: inline !important;
            }
        ';
        wp_add_inline_style( 'pod-variations-css', $inline_css );
        
        wp_enqueue_script(
            'pod-variations-js',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/pod-variations.js',
            array( 'jquery' ),
            SAC_VERSION . '.5',  // Force reload v5 - fix auto-select timing
            true
        );
    }

    /**
     * Enqueue assets for shop/category pages (for "From $X" styling)
     */
    public function enqueue_shop_assets() {
        // Load on shop, category, tag, and search pages
        if ( is_shop() || is_product_category() || is_product_tag() || is_search() ) {
            wp_enqueue_style(
                'pod-variations-shop-css',
                plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/pod-variations.css',
                array(),
                SAC_VERSION
            );
        }
    }
    
    /**
     * Render variation form on product page
     */
    public function render_variation_form() {
        global $product;
        
        if ( ! $product ) {
            return;
        }
        
        $pod_variations = get_post_meta( $product->get_id(), '_pod_variations', true );
        
        if ( empty( $pod_variations ) ) {
            return;
        }
        
        $styles = isset( $pod_variations['styles'] ) ? $pod_variations['styles'] : array();
        $use_shared = isset( $pod_variations['useSharedSizesColors'] ) ? $pod_variations['useSharedSizesColors'] : true;
        
        // Get sizes/colors based on mode
        if ( $use_shared ) {
            $sizes = isset( $pod_variations['sizes'] ) ? $pod_variations['sizes'] : array();
            $colors = isset( $pod_variations['colors'] ) ? $pod_variations['colors'] : array();
        } else {
            // Per-style: get from first style
            $sizes = ! empty( $styles[0]['sizes'] ) ? $styles[0]['sizes'] : array();
            $colors = ! empty( $styles[0]['colors'] ) ? $styles[0]['colors'] : array();
        }
        
        // Get first style's base price for initial display
        // If style basePrice is 0 or empty, fallback to simple product price
        $style_base_price = ! empty( $styles ) ? floatval( $styles[0]['basePrice'] ) : 0;
        $simple_product_price = floatval( $product->get_price() );
        $initial_price = ( $style_base_price > 0 ) ? $style_base_price : $simple_product_price;
        $initial_style_name = ! empty( $styles ) ? $styles[0]['name'] : '';
        
        // Prepare per-style data for JavaScript (when not using shared)
        $per_style_data = array();
        if ( ! $use_shared ) {
            foreach ( $styles as $style ) {
                $style_id = isset( $style['id'] ) ? $style['id'] : '';
                $per_style_data[ $style_id ] = array(
                    'sizes' => isset( $style['sizes'] ) ? $style['sizes'] : array(),
                    'colors' => isset( $style['colors'] ) ? $style['colors'] : array(),
                );
            }
        }
        
        // Debug: uncomment to see data
        // error_log('POD Variations - useShared: ' . ($use_shared ? 'true' : 'false'));
        // error_log('POD Variations - perStyleData: ' . print_r($per_style_data, true));
        // error_log('POD Variations - styles: ' . print_r($styles, true));
        ?>
        <div class="pod-variations-form" 
             data-product-id="<?php echo esc_attr( $product->get_id() ); ?>"
             data-use-shared="<?php echo $use_shared ? 'true' : 'false'; ?>"
             data-per-style='<?php echo esc_attr( json_encode( $per_style_data ) ); ?>'
             data-simple-price="<?php echo esc_attr( $simple_product_price ); ?>">
            
            <?php if ( ! empty( $styles ) ) : ?>
            <!-- Style Selector -->
            <div class="pod-var-section pod-var-styles">
                <label class="pod-var-label">Style: <span class="pod-var-style-name-display"><?php 
                    // Use displayName if available, otherwise use name
                    $initial_display_name = ! empty( $styles[0]['displayName'] ) ? $styles[0]['displayName'] : $initial_style_name;
                    echo esc_html( $initial_display_name ); 
                ?></span></label>
                <div class="pod-var-swatches">
                    <?php foreach ( $styles as $index => $style ) : 
                        $is_selected = $index === 0;
                        // Get custom SVG from style data
                        $custom_svg = isset( $style['customSvg'] ) ? $style['customSvg'] : '';
                        $icon_svg = self::get_icon( $style['name'], $custom_svg );
                        // Check if icon is default (no predefined icon found and no custom SVG)
                        $is_default_icon = empty( $custom_svg ) && ( $icon_svg === self::$product_icons['default'] );
                        // Use displayName if available
                        $display_name = ! empty( $style['displayName'] ) ? $style['displayName'] : $style['name'];
                        // Get initials from display name
                        $words = preg_split('/[\s\-_]+/', $display_name);
                        $initials = '';
                        foreach ($words as $word) {
                            if (!empty($word)) {
                                $initials .= strtoupper(substr($word, 0, 1));
                            }
                        }
                        $initials = substr($initials, 0, 2); // Max 2 characters
                    ?>
                    <div class="pod-var-swatch pod-var-style <?php echo $is_selected ? 'selected' : ''; ?> <?php echo $is_default_icon ? 'pod-var-style-letter' : ''; ?>" 
                         data-id="<?php echo esc_attr( $style['id'] ); ?>"
                         data-name="<?php echo esc_attr( $display_name ); ?>"
                         data-price="<?php echo esc_attr( floatval( $style['basePrice'] ) > 0 ? $style['basePrice'] : $simple_product_price ); ?>"
                         title="<?php echo esc_attr( $display_name ); ?>">
                        <?php if ( $is_default_icon ) : ?>
                            <span class="pod-var-style-letter-text"><?php echo esc_html( $initials ); ?></span>
                        <?php else : ?>
                            <?php echo $icon_svg; ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ( ! empty( $sizes ) || ! $use_shared ) : ?>
            <!-- Size Selector -->
            <div class="pod-var-section pod-var-sizes" <?php echo empty( $sizes ) ? 'style="display:none;"' : ''; ?>>
                <label class="pod-var-label">Size</label>
                <div class="pod-var-swatches">
                    <?php foreach ( $sizes as $index => $size ) : 
                        $is_selected = $index === 0;
                    ?>
                    <div class="pod-var-swatch pod-var-size <?php echo $is_selected ? 'selected' : ''; ?>" 
                         data-id="<?php echo esc_attr( $size['id'] ); ?>"
                         data-name="<?php echo esc_attr( $size['name'] ); ?>"
                         data-upcharge="<?php echo esc_attr( $size['upcharge'] ); ?>"
                         title="<?php echo esc_attr( $size['name'] ); ?>">
                        <?php echo esc_html( $size['name'] ); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ( ! empty( $colors ) || ! $use_shared ) : ?>
            <!-- Color Selector -->
            <div class="pod-var-section pod-var-colors" <?php echo empty( $colors ) ? 'style="display:none;"' : ''; ?>>
                <label class="pod-var-label">Color: <span class="pod-var-color-name-display"><?php echo ! empty( $colors ) ? esc_html( $colors[0]['name'] ) : ''; ?></span></label>
                <div class="pod-var-swatches">
                    <?php foreach ( $colors as $index => $color ) : 
                        $is_selected = $index === 0;
                    ?>
                    <div class="pod-var-swatch pod-var-color <?php echo $is_selected ? 'selected' : ''; ?>" 
                         data-id="<?php echo esc_attr( $color['id'] ); ?>"
                         data-name="<?php echo esc_attr( $color['name'] ); ?>"
                         data-hex="<?php echo esc_attr( $color['hex'] ); ?>"
                         style="background-color: <?php echo esc_attr( $color['hex'] ); ?>;"
                         title="<?php echo esc_attr( $color['name'] ); ?>">
                        <span class="sr-only"><?php echo esc_html( $color['name'] ); ?></span>
                        <span class="pod-var-color-check">✓</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Hidden Inputs -->
            <input type="hidden" name="pod_var_style_id" value="<?php echo ! empty( $styles ) ? esc_attr( $styles[0]['id'] ) : ''; ?>">
            <input type="hidden" name="pod_var_style_name" value="<?php echo ! empty( $styles ) ? esc_attr( $styles[0]['name'] ) : ''; ?>">
            <input type="hidden" name="pod_var_style_price" value="<?php echo ! empty( $styles ) && floatval( $styles[0]['basePrice'] ) > 0 ? esc_attr( $styles[0]['basePrice'] ) : esc_attr( $simple_product_price ); ?>">
            <input type="hidden" name="pod_var_size_id" value="<?php echo ! empty( $sizes ) ? esc_attr( $sizes[0]['id'] ) : ''; ?>">
            <input type="hidden" name="pod_var_size_name" value="<?php echo ! empty( $sizes ) ? esc_attr( $sizes[0]['name'] ) : ''; ?>">
            <input type="hidden" name="pod_var_size_upcharge" value="<?php echo ! empty( $sizes ) ? esc_attr( $sizes[0]['upcharge'] ) : '0'; ?>">
            <input type="hidden" name="pod_var_color_id" value="<?php echo ! empty( $colors ) ? esc_attr( $colors[0]['id'] ) : ''; ?>">
            <input type="hidden" name="pod_var_color_name" value="<?php echo ! empty( $colors ) ? esc_attr( $colors[0]['name'] ) : ''; ?>">
            <input type="hidden" name="pod_var_color_hex" value="<?php echo ! empty( $colors ) ? esc_attr( $colors[0]['hex'] ) : ''; ?>">
            <input type="hidden" name="pod_var_calculated_price" value="<?php echo esc_attr( $initial_price ); ?>">
            
            <!-- Dynamic Price Display -->
            <div class="pod-var-price-display">
                <span class="pod-var-price-label">Price:</span>
                <span class="pod-var-price-amount" data-currency="<?php echo esc_attr( get_woocommerce_currency_symbol() ); ?>"><?php echo wp_kses_post( wc_price( $initial_price ) ); ?></span>
            </div>
        </div>
        <?php
    }

    
    /**
     * Validate add to cart
     */
    public function validate_add_to_cart( $passed, $product_id, $quantity ) {
        $pod_variations = get_post_meta( $product_id, '_pod_variations', true );
        
        if ( empty( $pod_variations ) ) {
            return $passed;
        }
        
        // Check if style is selected (if styles exist)
        if ( ! empty( $pod_variations['styles'] ) && empty( $_POST['pod_var_style_id'] ) ) {
            wc_add_notice( __( 'Please select a style.', 'pod-connector' ), 'error' );
            return false;
        }
        
        // Check if size is selected (if sizes exist)
        if ( ! empty( $pod_variations['sizes'] ) && empty( $_POST['pod_var_size_id'] ) ) {
            wc_add_notice( __( 'Please select a size.', 'pod-connector' ), 'error' );
            return false;
        }
        
        // Check if color is selected (if colors exist)
        if ( ! empty( $pod_variations['colors'] ) && empty( $_POST['pod_var_color_id'] ) ) {
            wc_add_notice( __( 'Please select a color.', 'pod-connector' ), 'error' );
            return false;
        }
        
        return $passed;
    }
    
    /**
     * Add variation data to cart item
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        $pod_variations = get_post_meta( $product_id, '_pod_variations', true );
        
        if ( empty( $pod_variations ) ) {
            return $cart_item_data;
        }
        
        $pod_var_data = array();
        
        // Style
        if ( ! empty( $_POST['pod_var_style_id'] ) ) {
            $pod_var_data['style'] = array(
                'id' => sanitize_text_field( $_POST['pod_var_style_id'] ),
                'name' => sanitize_text_field( $_POST['pod_var_style_name'] ),
                'price' => sanitize_text_field( $_POST['pod_var_style_price'] ),
            );
        }
        
        // Size
        if ( ! empty( $_POST['pod_var_size_id'] ) ) {
            $pod_var_data['size'] = array(
                'id' => sanitize_text_field( $_POST['pod_var_size_id'] ),
                'name' => sanitize_text_field( $_POST['pod_var_size_name'] ),
                'upcharge' => sanitize_text_field( $_POST['pod_var_size_upcharge'] ),
            );
        }
        
        // Color
        if ( ! empty( $_POST['pod_var_color_id'] ) ) {
            $pod_var_data['color'] = array(
                'id' => sanitize_text_field( $_POST['pod_var_color_id'] ),
                'name' => sanitize_text_field( $_POST['pod_var_color_name'] ),
                'hex' => sanitize_text_field( $_POST['pod_var_color_hex'] ),
            );
        }
        
        // Calculated price
        if ( ! empty( $_POST['pod_var_calculated_price'] ) ) {
            $pod_var_data['calculated_price'] = floatval( $_POST['pod_var_calculated_price'] );
        }
        
        if ( ! empty( $pod_var_data ) ) {
            $cart_item_data['pod_variation'] = $pod_var_data;
        }
        
        return $cart_item_data;
    }
    
    /**
     * Display variation data in cart
     */
    public function display_cart_item_data( $item_data, $cart_item ) {
        if ( empty( $cart_item['pod_variation'] ) ) {
            return $item_data;
        }
        
        $pod_var = $cart_item['pod_variation'];
        
        if ( ! empty( $pod_var['style'] ) ) {
            $item_data[] = array(
                'key' => __( 'Style', 'pod-connector' ),
                'value' => $pod_var['style']['name'],
            );
        }
        
        if ( ! empty( $pod_var['size'] ) ) {
            $item_data[] = array(
                'key' => __( 'Size', 'pod-connector' ),
                'value' => $pod_var['size']['name'],
            );
        }
        
        if ( ! empty( $pod_var['color'] ) ) {
            $item_data[] = array(
                'key' => __( 'Color', 'pod-connector' ),
                'value' => $pod_var['color']['name'],
            );
        }
        
        return $item_data;
    }
    
    /**
     * Calculate cart totals with POD variation prices
     */
    public function calculate_totals( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }
        
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( ! empty( $cart_item['pod_variation']['calculated_price'] ) ) {
                $cart_item['data']->set_price( $cart_item['pod_variation']['calculated_price'] );
            }
        }
    }
    
    /**
     * Save variation data to order item meta
     */
    public function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['pod_variation'] ) ) {
            return;
        }
        
        $pod_var = $values['pod_variation'];
        
        if ( ! empty( $pod_var['style'] ) ) {
            $item->add_meta_data( '_pod_variation_style', $pod_var['style']['name'], true );
        }
        
        if ( ! empty( $pod_var['size'] ) ) {
            $item->add_meta_data( '_pod_variation_size', $pod_var['size']['name'], true );
        }
        
        if ( ! empty( $pod_var['color'] ) ) {
            $item->add_meta_data( '_pod_variation_color', $pod_var['color']['name'], true );
        }
        
        if ( ! empty( $pod_var['calculated_price'] ) ) {
            $item->add_meta_data( '_pod_variation_price', $pod_var['calculated_price'], true );
        }
    }
    
    /**
     * Admin order headers
     */
    public function admin_order_headers() {
        echo '<th class="pod-variation-col">' . esc_html__( 'POD Variation', 'pod-connector' ) . '</th>';
    }
    
    /**
     * Admin order values
     */
    public function admin_order_values( $product, $item, $item_id ) {
        $style = $item->get_meta( '_pod_variation_style' );
        $size = $item->get_meta( '_pod_variation_size' );
        $color = $item->get_meta( '_pod_variation_color' );
        
        echo '<td class="pod-variation-col">';
        if ( $style || $size || $color ) {
            $parts = array();
            if ( $style ) $parts[] = 'Style: ' . esc_html( $style );
            if ( $size ) $parts[] = 'Size: ' . esc_html( $size );
            if ( $color ) $parts[] = 'Color: ' . esc_html( $color );
            echo implode( '<br>', $parts );
        } else {
            echo '—';
        }
        echo '</td>';
    }
    
    /**
     * Display variation info in order emails
     */
    public function order_item_meta_display( $item_id, $item, $order, $plain_text ) {
        $style = $item->get_meta( '_pod_variation_style' );
        $size = $item->get_meta( '_pod_variation_size' );
        $color = $item->get_meta( '_pod_variation_color' );
        
        if ( ! $style && ! $size && ! $color ) {
            return;
        }
        
        if ( $plain_text ) {
            if ( $style ) echo "\nStyle: " . $style;
            if ( $size ) echo "\nSize: " . $size;
            if ( $color ) echo "\nColor: " . $color;
        } else {
            echo '<div class="pod-variation-order-meta" style="margin-top: 8px; font-size: 12px; color: #666;">';
            if ( $style ) echo '<div><strong>Style:</strong> ' . esc_html( $style ) . '</div>';
            if ( $size ) echo '<div><strong>Size:</strong> ' . esc_html( $size ) . '</div>';
            if ( $color ) echo '<div><strong>Color:</strong> ' . esc_html( $color ) . '</div>';
            echo '</div>';
        }
    }
    
    /**
     * Output Schema JSON-LD for GMC compliance
     * Supports both ProductGroup (all variants) and single Product (specific variant via URL params)
     */
    public function output_schema_jsonld() {
        if ( ! is_product() ) {
            return;
        }
        
        global $post;
        if ( ! $post || ! function_exists( 'wc_get_product' ) ) {
            return;
        }
        
        $product = wc_get_product( $post->ID );
        if ( ! $product ) {
            return;
        }
        
        $product_id = $product->get_id();
        $pod_variations = get_post_meta( $product_id, '_pod_variations', true );
        
        if ( empty( $pod_variations ) ) {
            return;
        }
        
        $pv_data = is_string( $pod_variations ) ? json_decode( $pod_variations, true ) : $pod_variations;
        if ( empty( $pv_data ) || ! is_array( $pv_data ) ) {
            return;
        }
        
        $styles = isset( $pv_data['styles'] ) ? $pv_data['styles'] : array();
        $use_shared = isset( $pv_data['useSharedSizesColors'] ) ? $pv_data['useSharedSizesColors'] : true;
        $shared_sizes = isset( $pv_data['sizes'] ) ? $pv_data['sizes'] : array();
        
        // Check URL params for specific variant
        $url_style = isset( $_GET['pod-style'] ) ? sanitize_text_field( $_GET['pod-style'] ) : '';
        $url_size = isset( $_GET['pod-size'] ) ? sanitize_text_field( $_GET['pod-size'] ) : '';
        
        $base_url = get_permalink( $product_id );
        $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        
        // If specific variant requested via URL params
        if ( ! empty( $url_style ) || ! empty( $url_size ) ) {
            $schema = $this->get_single_variant_schema( $product, $pv_data, $url_style, $url_size, $base_url, $currency );
        } else {
            // Output ProductGroup with all variants
            $schema = $this->get_product_group_schema( $product, $pv_data, $base_url, $currency );
        }
        
        if ( ! empty( $schema ) ) {
            echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
        }
    }
    
    /**
     * Get Schema for a single variant (when URL params present)
     */
    private function get_single_variant_schema( $product, $pv_data, $url_style, $url_size, $base_url, $currency ) {
        $styles = isset( $pv_data['styles'] ) ? $pv_data['styles'] : array();
        $use_shared = isset( $pv_data['useSharedSizesColors'] ) ? $pv_data['useSharedSizesColors'] : true;
        $shared_sizes = isset( $pv_data['sizes'] ) ? $pv_data['sizes'] : array();
        
        // Find matching style
        $selected_style = null;
        $style_price = floatval( $product->get_price() );
        $style_name = '';
        
        if ( ! empty( $styles ) ) {
            foreach ( $styles as $style ) {
                $style_slug = sanitize_title( $style['name'] );
                if ( $style_slug === $url_style || $style['id'] === $url_style ) {
                    $selected_style = $style;
                    $style_price = floatval( $style['basePrice'] );
                    $style_name = $style['name'];
                    break;
                }
            }
            // Default to first style if not found
            if ( ! $selected_style && ! empty( $styles ) ) {
                $selected_style = $styles[0];
                $style_price = floatval( $styles[0]['basePrice'] );
                $style_name = $styles[0]['name'];
            }
        }
        
        // Find matching size and upcharge
        $size_upcharge = 0;
        $size_name = '';
        $sizes_to_check = $use_shared ? $shared_sizes : ( $selected_style && isset( $selected_style['sizes'] ) ? $selected_style['sizes'] : array() );
        
        if ( ! empty( $url_size ) && ! empty( $sizes_to_check ) ) {
            foreach ( $sizes_to_check as $size ) {
                $size_slug = sanitize_title( $size['name'] );
                if ( $size_slug === $url_size || $size['id'] === $url_size ) {
                    $size_upcharge = floatval( $size['upcharge'] );
                    $size_name = $size['name'];
                    break;
                }
            }
        }
        
        $final_price = $style_price + $size_upcharge;
        
        // Build variant name
        $variant_parts = array( $product->get_name() );
        if ( $style_name ) $variant_parts[] = $style_name;
        if ( $size_name ) $variant_parts[] = $size_name;
        $variant_name = implode( ' - ', $variant_parts );
        
        // Build URL with params
        $variant_url = $base_url;
        $params = array();
        if ( $url_style ) $params['pod-style'] = $url_style;
        if ( $url_size ) $params['pod-size'] = $url_size;
        if ( ! empty( $params ) ) {
            $variant_url = add_query_arg( $params, $base_url );
        }
        
        // Get product SKU with fallback
        $product_sku = $product->get_sku();
        if ( empty( $product_sku ) ) {
            $product_sku = 'product-' . $product->get_id();
        }
        
        // Get description with fallback
        $description = wp_strip_all_tags( $product->get_short_description() );
        if ( empty( $description ) ) {
            $description = wp_strip_all_tags( $product->get_description() );
        }
        if ( empty( $description ) ) {
            $description = $variant_name; // Use product name as fallback
        }
        $description = substr( $description, 0, 5000 ); // GMC limit
        
        // Get image URL - try multiple sources
        $image_url = '';
        $image_id = $product->get_image_id();
        if ( $image_id ) {
            $image_url = wp_get_attachment_url( $image_id );
        }
        if ( empty( $image_url ) ) {
            // Try gallery images
            $gallery_ids = $product->get_gallery_image_ids();
            if ( ! empty( $gallery_ids ) ) {
                $image_url = wp_get_attachment_url( $gallery_ids[0] );
            }
        }
        if ( empty( $image_url ) ) {
            // Use placeholder
            $image_url = wc_placeholder_img_src( 'full' );
        }
        
        // Get brand with fallback
        $brand = $product->get_attribute( 'brand' );
        if ( empty( $brand ) ) {
            $brand = get_bloginfo( 'name' ); // Use site name as fallback
        }
        
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $variant_name,
            'description' => $description,
            'url' => $variant_url,
            'sku' => $product_sku . ( $url_style ? '-' . $url_style : '' ) . ( $url_size ? '-' . $url_size : '' ),
            'image' => $image_url,
            'brand' => array(
                '@type' => 'Brand',
                'name' => $brand,
            ),
            'offers' => array(
                '@type' => 'Offer',
                'price' => number_format( $final_price, 2, '.', '' ),
                'priceCurrency' => $currency,
                'priceValidUntil' => date( 'Y-m-d', strtotime( '+1 year' ) ),
                'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => $variant_url,
            ),
        );
        
        // Add inventory level only if tracking stock
        $stock_qty = $product->get_stock_quantity();
        if ( $product->get_manage_stock() && $stock_qty !== null ) {
            $schema['offers']['inventoryLevel'] = max( 0, intval( $stock_qty ) );
        }
        
        // Add shipping details to offers
        $shipping = $this->get_shipping_schema();
        if ( $shipping ) {
            $schema['offers']['shippingDetails'] = $shipping;
        }
        
        // Add return policy to offers
        $return_policy = $this->get_return_policy_schema();
        if ( $return_policy ) {
            $schema['offers']['hasMerchantReturnPolicy'] = $return_policy;
        }
        
        // Add size attribute
        if ( $size_name ) {
            $schema['size'] = $size_name;
        }
        
        // Add aggregateRating if product has reviews
        $rating_data = $this->get_product_rating_data( $product );
        if ( $rating_data ) {
            $schema['aggregateRating'] = $rating_data['aggregateRating'];
            if ( ! empty( $rating_data['reviews'] ) ) {
                $schema['review'] = $rating_data['reviews'];
            }
        }
        
        return $schema;
    }
    
    /**
     * Get ProductGroup Schema with all variants
     */
    private function get_product_group_schema( $product, $pv_data, $base_url, $currency ) {
        $styles = isset( $pv_data['styles'] ) ? $pv_data['styles'] : array();
        $use_shared = isset( $pv_data['useSharedSizesColors'] ) ? $pv_data['useSharedSizesColors'] : true;
        $shared_sizes = isset( $pv_data['sizes'] ) ? $pv_data['sizes'] : array();
        
        $variants = array();
        $all_prices = array();
        
        // Get product image for variants
        $product_image = '';
        $image_id = $product->get_image_id();
        if ( $image_id ) {
            $product_image = wp_get_attachment_url( $image_id );
        }
        if ( empty( $product_image ) ) {
            // Try gallery images
            $gallery_ids = $product->get_gallery_image_ids();
            if ( ! empty( $gallery_ids ) ) {
                $product_image = wp_get_attachment_url( $gallery_ids[0] );
            }
        }
        
        // Get brand for variants
        $brand = $product->get_attribute( 'brand' );
        if ( empty( $brand ) ) {
            $brand = get_bloginfo( 'name' );
        }
        
        // Get description for variants
        $description = wp_strip_all_tags( $product->get_short_description() );
        if ( empty( $description ) ) {
            $description = wp_strip_all_tags( $product->get_description() );
        }
        if ( empty( $description ) ) {
            $description = $product->get_name();
        }
        $description = substr( $description, 0, 5000 );
        
        // Get shipping schema
        $shipping = $this->get_shipping_schema();
        
        // Get return policy schema
        $return_policy = $this->get_return_policy_schema();
        
        // Price valid until (1 year from now)
        $price_valid_until = date( 'Y-m-d', strtotime( '+1 year' ) );
        
        // Inventory level (for POD, use actual stock or omit if not tracking)
        // GMC accepts high numbers for made-to-order products
        $inventory_level = null;
        $stock_quantity = $product->get_stock_quantity();
        if ( ! empty( $stock_quantity ) && $stock_quantity > 0 ) {
            // Use actual stock if product tracks inventory
            $inventory_level = $stock_quantity;
        } elseif ( $product->get_manage_stock() ) {
            // If managing stock but quantity is 0 or negative, use 0
            $inventory_level = 0;
        }
        // If not managing stock (POD products), leave null - GMC will ignore this field
        
        // Generate variants
        if ( ! empty( $styles ) ) {
            foreach ( $styles as $style ) {
                $style_price = floatval( $style['basePrice'] );
                $style_slug = sanitize_title( $style['name'] );
                $sizes_to_use = $use_shared ? $shared_sizes : ( isset( $style['sizes'] ) ? $style['sizes'] : array() );
                
                if ( ! empty( $sizes_to_use ) ) {
                    foreach ( $sizes_to_use as $size ) {
                        $size_upcharge = floatval( $size['upcharge'] );
                        $final_price = $style_price + $size_upcharge;
                        $all_prices[] = $final_price;
                        $size_slug = sanitize_title( $size['name'] );
                        
                        $variant_url = add_query_arg( array(
                            'pod-style' => $style_slug,
                            'pod-size' => $size_slug,
                        ), $base_url );
                        
                        $variant_offer = array(
                            '@type' => 'Offer',
                            'price' => number_format( $final_price, 2, '.', '' ),
                            'priceCurrency' => $currency,
                            'priceValidUntil' => $price_valid_until,
                            'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                            'url' => $variant_url,
                        );
                        
                        // Add inventory level only if tracking stock
                        if ( $inventory_level !== null ) {
                            $variant_offer['inventoryLevel'] = $inventory_level;
                        }
                        
                        // Add shipping to variant offer
                        if ( $shipping ) {
                            $variant_offer['shippingDetails'] = $shipping;
                        }
                        
                        // Add return policy to variant offer
                        if ( $return_policy ) {
                            $variant_offer['hasMerchantReturnPolicy'] = $return_policy;
                        }
                        
                        $variant_data = array(
                            '@type' => 'Product',
                            'name' => $product->get_name() . ' - ' . $style['name'] . ' - ' . $size['name'],
                            'sku' => $product->get_sku() . '-' . $style_slug . '-' . $size_slug,
                            'size' => $size['name'],
                            'url' => $variant_url,
                            'description' => $description,
                            'offers' => $variant_offer,
                        );
                        if ( $product_image ) {
                            $variant_data['image'] = $product_image;
                        }
                        if ( $brand ) {
                            $variant_data['brand'] = array( '@type' => 'Brand', 'name' => $brand );
                        }
                        $variants[] = $variant_data;
                    }
                } else {
                    // Style only, no sizes
                    $all_prices[] = $style_price;
                    $variant_url = add_query_arg( 'pod-style', $style_slug, $base_url );
                    
                    $variant_offer = array(
                        '@type' => 'Offer',
                        'price' => number_format( $style_price, 2, '.', '' ),
                        'priceCurrency' => $currency,
                        'priceValidUntil' => $price_valid_until,
                        'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                        'url' => $variant_url,
                    );
                    
                    // Add inventory level only if tracking stock
                    if ( $inventory_level !== null ) {
                        $variant_offer['inventoryLevel'] = $inventory_level;
                    }
                    
                    // Add shipping to variant offer
                    if ( $shipping ) {
                        $variant_offer['shippingDetails'] = $shipping;
                    }
                    
                    // Add return policy to variant offer
                    if ( $return_policy ) {
                        $variant_offer['hasMerchantReturnPolicy'] = $return_policy;
                    }
                    
                    $variant_data = array(
                        '@type' => 'Product',
                        'name' => $product->get_name() . ' - ' . $style['name'],
                        'sku' => $product->get_sku() . '-' . $style_slug,
                        'url' => $variant_url,
                        'description' => $description,
                        'offers' => $variant_offer,
                    );
                    if ( $product_image ) {
                        $variant_data['image'] = $product_image;
                    }
                    if ( $brand ) {
                        $variant_data['brand'] = array( '@type' => 'Brand', 'name' => $brand );
                    }
                    $variants[] = $variant_data;
                }
            }
        } elseif ( ! empty( $shared_sizes ) ) {
            // No styles, only sizes
            $base_price = floatval( $product->get_price() );
            foreach ( $shared_sizes as $size ) {
                $size_upcharge = floatval( $size['upcharge'] );
                $final_price = $base_price + $size_upcharge;
                $all_prices[] = $final_price;
                $size_slug = sanitize_title( $size['name'] );
                
                $variant_url = add_query_arg( 'pod-size', $size_slug, $base_url );
                
                $variant_offer = array(
                    '@type' => 'Offer',
                    'price' => number_format( $final_price, 2, '.', '' ),
                    'priceCurrency' => $currency,
                    'priceValidUntil' => $price_valid_until,
                    'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url' => $variant_url,
                );
                
                // Add inventory level only if tracking stock
                if ( $inventory_level !== null ) {
                    $variant_offer['inventoryLevel'] = $inventory_level;
                }
                
                // Add shipping to variant offer
                if ( $shipping ) {
                    $variant_offer['shippingDetails'] = $shipping;
                }
                
                // Add return policy to variant offer
                if ( $return_policy ) {
                    $variant_offer['hasMerchantReturnPolicy'] = $return_policy;
                }
                
                $variant_data = array(
                    '@type' => 'Product',
                    'name' => $product->get_name() . ' - ' . $size['name'],
                    'sku' => $product->get_sku() . '-' . $size_slug,
                    'size' => $size['name'],
                    'url' => $variant_url,
                    'description' => $description,
                    'offers' => $variant_offer,
                );
                if ( $product_image ) {
                    $variant_data['image'] = $product_image;
                }
                if ( $brand ) {
                    $variant_data['brand'] = array( '@type' => 'Brand', 'name' => $brand );
                }
                $variants[] = $variant_data;
            }
        }
        
        if ( empty( $variants ) ) {
            return array();
        }
        
        // Build ProductGroup schema
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'ProductGroup',
            'name' => $product->get_name(),
            'url' => $base_url,
            'productGroupID' => $product->get_sku() ?: 'product-' . $product->get_id(),
            'description' => $description,
            'variesBy' => array( 'https://schema.org/size' ),
            'hasVariant' => $variants,
        );
        
        // Add image to ProductGroup
        if ( $product_image ) {
            $schema['image'] = $product_image;
        }
        
        // Add brand to ProductGroup
        if ( $brand ) {
            $schema['brand'] = array( '@type' => 'Brand', 'name' => $brand );
        }
        
        // Add aggregate offer
        if ( ! empty( $all_prices ) ) {
            $schema['offers'] = array(
                '@type' => 'AggregateOffer',
                'lowPrice' => number_format( min( $all_prices ), 2, '.', '' ),
                'highPrice' => number_format( max( $all_prices ), 2, '.', '' ),
                'priceCurrency' => $currency,
                'offerCount' => count( $variants ),
                'availability' => $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            );
            
            // Add shipping details to offers
            $shipping = $this->get_shipping_schema();
            if ( $shipping ) {
                $schema['offers']['shippingDetails'] = $shipping;
            }
            
            // Add return policy to offers
            $return_policy = $this->get_return_policy_schema();
            if ( $return_policy ) {
                $schema['offers']['hasMerchantReturnPolicy'] = $return_policy;
            }
        }
        
        // Add aggregateRating if product has reviews
        $rating_data = $this->get_product_rating_data( $product );
        if ( $rating_data ) {
            $schema['aggregateRating'] = $rating_data['aggregateRating'];
            if ( ! empty( $rating_data['reviews'] ) ) {
                $schema['review'] = $rating_data['reviews'];
            }
        }
        
        return $schema;
    }
    
    /**
     * Get product rating and reviews data for Schema
     */
    private function get_product_rating_data( $product ) {
        $review_count = $product->get_review_count();
        $average_rating = $product->get_average_rating();
        
        // No reviews - return null
        if ( $review_count < 1 || floatval( $average_rating ) <= 0 ) {
            return null;
        }
        
        $data = array(
            'aggregateRating' => array(
                '@type' => 'AggregateRating',
                'ratingValue' => number_format( floatval( $average_rating ), 1, '.', '' ),
                'reviewCount' => intval( $review_count ),
                'bestRating' => '5',
                'worstRating' => '1',
            ),
            'reviews' => array(),
        );
        
        // Get actual reviews (max 5 for schema)
        $comments = get_comments( array(
            'post_id' => $product->get_id(),
            'status' => 'approve',
            'type' => 'review',
            'number' => 5,
            'orderby' => 'comment_date_gmt',
            'order' => 'DESC',
        ) );
        
        if ( ! empty( $comments ) ) {
            foreach ( $comments as $comment ) {
                $rating = get_comment_meta( $comment->comment_ID, 'rating', true );
                if ( ! $rating ) {
                    $rating = 5; // Default to 5 if no rating meta
                }
                
                $review = array(
                    '@type' => 'Review',
                    'author' => array(
                        '@type' => 'Person',
                        'name' => $comment->comment_author ?: 'Anonymous',
                    ),
                    'datePublished' => date( 'Y-m-d', strtotime( $comment->comment_date ) ),
                    'reviewBody' => wp_strip_all_tags( $comment->comment_content ),
                    'reviewRating' => array(
                        '@type' => 'Rating',
                        'ratingValue' => intval( $rating ),
                        'bestRating' => '5',
                        'worstRating' => '1',
                    ),
                );
                
                $data['reviews'][] = $review;
            }
        }
        
        return $data;
    }
    
    /**
     * Get shipping details for Schema
     */
    private function get_shipping_schema() {
        $country = get_option( 'pod_gmc_shipping_country', '' );
        $service = get_option( 'pod_gmc_shipping_service', 'Standard Shipping' );
        $price = get_option( 'pod_gmc_shipping_price', '0.00' );
        
        if ( empty( $country ) ) {
            return null;
        }
        
        $currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        
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
     * Get merchant return policy for Schema
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
}

// Initialize
new POD_Variations();
