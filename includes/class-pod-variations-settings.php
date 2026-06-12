<?php
/**
 * POD Variations Settings - Admin Settings Page
 * Cho phép customize size của style/size/color swatches
 * 
 * @package POD_AI_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class POD_Variations_Settings {
    
    /**
     * Option name for settings
     */
    const OPTION_NAME = 'pod_variations_settings';
    
    /**
     * Default settings
     */
    private static $defaults = array(
        'style_size' => 48,      // px
        'size_height' => 40,     // px
        'size_min_width' => 44,  // px
        'color_size' => 34,      // px
        'gap' => 8,              // px
        'border_radius_style' => 12,
        'border_radius_size' => 10,
        'primary_color' => '#8b5cf6',
        'secondary_color' => '#3b82f6',
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_head', array( $this, 'output_custom_css' ) );
    }
    
    /**
     * Add settings page under WooCommerce menu
     */
    public function add_settings_page() {
        add_submenu_page(
            'woocommerce',
            __( 'POD Variations Settings', 'pod-connector' ),
            __( 'POD Variations', 'pod-connector' ),
            'manage_woocommerce',
            'pod-variations-settings',
            array( $this, 'render_settings_page' )
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'pod_variations_settings_group', self::OPTION_NAME, array(
            'sanitize_callback' => array( $this, 'sanitize_settings' )
        ) );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        
        $sanitized['style_size'] = absint( $input['style_size'] ?? self::$defaults['style_size'] );
        $sanitized['size_height'] = absint( $input['size_height'] ?? self::$defaults['size_height'] );
        $sanitized['size_min_width'] = absint( $input['size_min_width'] ?? self::$defaults['size_min_width'] );
        $sanitized['color_size'] = absint( $input['color_size'] ?? self::$defaults['color_size'] );
        $sanitized['gap'] = absint( $input['gap'] ?? self::$defaults['gap'] );
        $sanitized['border_radius_style'] = absint( $input['border_radius_style'] ?? self::$defaults['border_radius_style'] );
        $sanitized['border_radius_size'] = absint( $input['border_radius_size'] ?? self::$defaults['border_radius_size'] );
        $sanitized['primary_color'] = sanitize_hex_color( $input['primary_color'] ?? self::$defaults['primary_color'] );
        $sanitized['secondary_color'] = sanitize_hex_color( $input['secondary_color'] ?? self::$defaults['secondary_color'] );
        
        return $sanitized;
    }
    
    /**
     * Get settings
     */
    public static function get_settings() {
        $settings = get_option( self::OPTION_NAME, array() );
        return wp_parse_args( $settings, self::$defaults );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings = self::get_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'POD Variations Settings', 'pod-connector' ); ?></h1>
            <p class="description"><?php esc_html_e( 'Customize the appearance of POD Variations swatches on product pages.', 'pod-connector' ); ?></p>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'pod_variations_settings_group' ); ?>
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <!-- Style Swatch Size -->
                        <tr>
                            <th scope="row">
                                <label for="style_size"><?php esc_html_e( 'Style Icon Size', 'pod-connector' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="style_size" name="<?php echo self::OPTION_NAME; ?>[style_size]" 
                                       value="<?php echo esc_attr( $settings['style_size'] ); ?>" 
                                       min="24" max="80" step="2" class="small-text" /> px
                                <p class="description"><?php esc_html_e( 'Size of style icons (default: 48px)', 'pod-connector' ); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Size Swatch Height -->
                        <tr>
                            <th scope="row">
                                <label for="size_height"><?php esc_html_e( 'Size Button Height', 'pod-connector' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="size_height" name="<?php echo self::OPTION_NAME; ?>[size_height]" 
                                       value="<?php echo esc_attr( $settings['size_height'] ); ?>" 
                                       min="24" max="60" step="2" class="small-text" /> px
                                <p class="description"><?php esc_html_e( 'Height of size buttons (default: 40px)', 'pod-connector' ); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Size Swatch Min Width -->
                        <tr>
                            <th scope="row">
                                <label for="size_min_width"><?php esc_html_e( 'Size Button Min Width', 'pod-connector' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="size_min_width" name="<?php echo self::OPTION_NAME; ?>[size_min_width]" 
                                       value="<?php echo esc_attr( $settings['size_min_width'] ); ?>" 
                                       min="30" max="80" step="2" class="small-text" /> px
                                <p class="description"><?php esc_html_e( 'Minimum width of size buttons (default: 44px)', 'pod-connector' ); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Color Swatch Size -->
                        <tr>
                            <th scope="row">
                                <label for="color_size"><?php esc_html_e( 'Color Swatch Size', 'pod-connector' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="color_size" name="<?php echo self::OPTION_NAME; ?>[color_size]" 
                                       value="<?php echo esc_attr( $settings['color_size'] ); ?>" 
                                       min="20" max="60" step="2" class="small-text" /> px
                                <p class="description"><?php esc_html_e( 'Size of color swatches (default: 34px)', 'pod-connector' ); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Gap -->
                        <tr>
                            <th scope="row">
                                <label for="gap"><?php esc_html_e( 'Gap Between Items', 'pod-connector' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="gap" name="<?php echo self::OPTION_NAME; ?>[gap]" 
                                       value="<?php echo esc_attr( $settings['gap'] ); ?>" 
                                       min="4" max="20" step="2" class="small-text" /> px
                                <p class="description"><?php esc_html_e( 'Space between swatches (default: 8px)', 'pod-connector' ); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Border Radius Style -->
                        <tr>
                            <th scope="row">
                                <label for="border_radius_style"><?php esc_html_e( 'Style Border Radius', 'pod-connector' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="border_radius_style" name="<?php echo self::OPTION_NAME; ?>[border_radius_style]" 
                                       value="<?php echo esc_attr( $settings['border_radius_style'] ); ?>" 
                                       min="0" max="24" step="1" class="small-text" /> px
                                <p class="description"><?php esc_html_e( 'Border radius for style icons (default: 12px)', 'pod-connector' ); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Border Radius Size -->
                        <tr>
                            <th scope="row">
                                <label for="border_radius_size"><?php esc_html_e( 'Size Border Radius', 'pod-connector' ); ?></label>
                            </th>
                            <td>
                                <input type="number" id="border_radius_size" name="<?php echo self::OPTION_NAME; ?>[border_radius_size]" 
                                       value="<?php echo esc_attr( $settings['border_radius_size'] ); ?>" 
                                       min="0" max="20" step="1" class="small-text" /> px
                                <p class="description"><?php esc_html_e( 'Border radius for size buttons (default: 10px)', 'pod-connector' ); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Primary Color -->
                        <tr>
                            <th scope="row">
                                <label for="primary_color"><?php esc_html_e( 'Primary Color (Style)', 'pod-connector' ); ?></label>
                            </th>
                            <td>
                                <input type="color" id="primary_color" name="<?php echo self::OPTION_NAME; ?>[primary_color]" 
                                       value="<?php echo esc_attr( $settings['primary_color'] ); ?>" />
                                <span class="description"><?php echo esc_html( $settings['primary_color'] ); ?></span>
                                <p class="description"><?php esc_html_e( 'Accent color for style swatches (default: #8b5cf6)', 'pod-connector' ); ?></p>
                            </td>
                        </tr>
                        
                        <!-- Secondary Color -->
                        <tr>
                            <th scope="row">
                                <label for="secondary_color"><?php esc_html_e( 'Secondary Color (Size)', 'pod-connector' ); ?></label>
                            </th>
                            <td>
                                <input type="color" id="secondary_color" name="<?php echo self::OPTION_NAME; ?>[secondary_color]" 
                                       value="<?php echo esc_attr( $settings['secondary_color'] ); ?>" />
                                <span class="description"><?php echo esc_html( $settings['secondary_color'] ); ?></span>
                                <p class="description"><?php esc_html_e( 'Accent color for size buttons (default: #3b82f6)', 'pod-connector' ); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php submit_button(); ?>
                
                <hr />
                
                <h2><?php esc_html_e( 'Preview', 'pod-connector' ); ?></h2>
                <div id="pod-var-preview" style="padding: 20px; background: #f9fafb; border-radius: 8px; max-width: 400px;">
                    <p><strong>Style:</strong></p>
                    <div style="display: flex; gap: <?php echo esc_attr( $settings['gap'] ); ?>px; margin-bottom: 16px;">
                        <div style="width: <?php echo esc_attr( $settings['style_size'] ); ?>px; height: <?php echo esc_attr( $settings['style_size'] ); ?>px; border: 2px solid <?php echo esc_attr( $settings['primary_color'] ); ?>; border-radius: <?php echo esc_attr( $settings['border_radius_style'] ); ?>px; display: flex; align-items: center; justify-content: center; background: #f5f3ff;">
                            <svg width="<?php echo esc_attr( $settings['style_size'] * 0.5 ); ?>" height="<?php echo esc_attr( $settings['style_size'] * 0.5 ); ?>" viewBox="0 0 24 24" fill="none" stroke="<?php echo esc_attr( $settings['primary_color'] ); ?>" stroke-width="1.5">
                                <path d="M20 7l-4-3H8L4 7l3 2v11h10V9l3-2z"/>
                            </svg>
                        </div>
                        <div style="width: <?php echo esc_attr( $settings['style_size'] ); ?>px; height: <?php echo esc_attr( $settings['style_size'] ); ?>px; border: 2px solid #e5e7eb; border-radius: <?php echo esc_attr( $settings['border_radius_style'] ); ?>px; display: flex; align-items: center; justify-content: center;">
                            <svg width="<?php echo esc_attr( $settings['style_size'] * 0.5 ); ?>" height="<?php echo esc_attr( $settings['style_size'] * 0.5 ); ?>" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="1.5">
                                <path d="M4 8l3-4h10l3 4-2 2v10H6V10L4 8z"/>
                            </svg>
                        </div>
                    </div>
                    
                    <p><strong>Size:</strong></p>
                    <div style="display: flex; gap: <?php echo esc_attr( $settings['gap'] ); ?>px; margin-bottom: 16px;">
                        <div style="min-width: <?php echo esc_attr( $settings['size_min_width'] ); ?>px; height: <?php echo esc_attr( $settings['size_height'] ); ?>px; padding: 0 12px; border: 2px solid <?php echo esc_attr( $settings['secondary_color'] ); ?>; border-radius: <?php echo esc_attr( $settings['border_radius_size'] ); ?>px; display: flex; align-items: center; justify-content: center; background: <?php echo esc_attr( $settings['secondary_color'] ); ?>; color: white; font-weight: 600; font-size: 13px;">S</div>
                        <div style="min-width: <?php echo esc_attr( $settings['size_min_width'] ); ?>px; height: <?php echo esc_attr( $settings['size_height'] ); ?>px; padding: 0 12px; border: 2px solid #e5e7eb; border-radius: <?php echo esc_attr( $settings['border_radius_size'] ); ?>px; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 13px; color: #374151;">M</div>
                        <div style="min-width: <?php echo esc_attr( $settings['size_min_width'] ); ?>px; height: <?php echo esc_attr( $settings['size_height'] ); ?>px; padding: 0 12px; border: 2px solid #e5e7eb; border-radius: <?php echo esc_attr( $settings['border_radius_size'] ); ?>px; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 13px; color: #374151;">XL</div>
                    </div>
                    
                    <p><strong>Color:</strong></p>
                    <div style="display: flex; gap: <?php echo esc_attr( $settings['gap'] ); ?>px;">
                        <div style="width: <?php echo esc_attr( $settings['color_size'] ); ?>px; height: <?php echo esc_attr( $settings['color_size'] ); ?>px; border-radius: 50%; background: #000; border: 3px solid #fff; box-shadow: 0 0 0 2px <?php echo esc_attr( $settings['primary_color'] ); ?>;"></div>
                        <div style="width: <?php echo esc_attr( $settings['color_size'] ); ?>px; height: <?php echo esc_attr( $settings['color_size'] ); ?>px; border-radius: 50%; background: #fff; border: 3px solid #fff; box-shadow: 0 0 0 1px #d1d5db;"></div>
                        <div style="width: <?php echo esc_attr( $settings['color_size'] ); ?>px; height: <?php echo esc_attr( $settings['color_size'] ); ?>px; border-radius: 50%; background: #001F3F; border: 3px solid #fff; box-shadow: 0 0 0 1px #d1d5db;"></div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Output custom CSS based on settings
     */
    public function output_custom_css() {
        if ( ! is_product() ) {
            return;
        }
        
        global $post;
        $pod_variations = get_post_meta( $post->ID, '_pod_variations', true );
        
        if ( empty( $pod_variations ) ) {
            return;
        }
        
        $settings = self::get_settings();
        
        // Calculate SVG size (50% of container)
        $svg_size = round( $settings['style_size'] * 0.54 );
        ?>
        <style id="pod-variations-custom-css">
            :root {
                --pod-var-style-size: <?php echo esc_attr( $settings['style_size'] ); ?>px;
                --pod-var-size-height: <?php echo esc_attr( $settings['size_height'] ); ?>px;
                --pod-var-size-min-width: <?php echo esc_attr( $settings['size_min_width'] ); ?>px;
                --pod-var-color-size: <?php echo esc_attr( $settings['color_size'] ); ?>px;
                --pod-var-gap: <?php echo esc_attr( $settings['gap'] ); ?>px;
                --pod-var-radius-style: <?php echo esc_attr( $settings['border_radius_style'] ); ?>px;
                --pod-var-radius-size: <?php echo esc_attr( $settings['border_radius_size'] ); ?>px;
                --pod-var-primary: <?php echo esc_attr( $settings['primary_color'] ); ?>;
                --pod-var-secondary: <?php echo esc_attr( $settings['secondary_color'] ); ?>;
            }
            
            .pod-var-swatches {
                gap: var(--pod-var-gap) !important;
            }
            
            .pod-var-style {
                width: var(--pod-var-style-size) !important;
                height: var(--pod-var-style-size) !important;
                border-radius: var(--pod-var-radius-style) !important;
            }
            
            .pod-var-style:hover,
            .pod-var-style.selected {
                border-color: var(--pod-var-primary) !important;
            }
            
            .pod-var-style.selected {
                background: color-mix(in srgb, var(--pod-var-primary) 10%, white) !important;
                box-shadow: 0 0 0 3px color-mix(in srgb, var(--pod-var-primary) 20%, transparent) !important;
            }
            
            .pod-var-style svg {
                width: <?php echo esc_attr( $svg_size ); ?>px !important;
                height: <?php echo esc_attr( $svg_size ); ?>px !important;
            }
            
            .pod-var-style:hover svg,
            .pod-var-style.selected svg {
                color: var(--pod-var-primary) !important;
            }
            
            .pod-var-size {
                min-width: var(--pod-var-size-min-width) !important;
                height: var(--pod-var-size-height) !important;
                border-radius: var(--pod-var-radius-size) !important;
            }
            
            .pod-var-size:hover {
                border-color: var(--pod-var-secondary) !important;
                background: color-mix(in srgb, var(--pod-var-secondary) 10%, white) !important;
                color: var(--pod-var-secondary) !important;
            }
            
            .pod-var-size.selected {
                border-color: var(--pod-var-secondary) !important;
                background: var(--pod-var-secondary) !important;
                color: #fff !important;
            }
            
            .pod-var-color {
                width: var(--pod-var-color-size) !important;
                height: var(--pod-var-color-size) !important;
            }
            
            .pod-var-color:hover,
            .pod-var-color.selected {
                box-shadow: 0 0 0 2px var(--pod-var-primary) !important;
            }
            
            .pod-var-price-display {
                background: linear-gradient(135deg, color-mix(in srgb, var(--pod-var-primary) 10%, white) 0%, color-mix(in srgb, var(--pod-var-primary) 15%, white) 100%) !important;
            }
            
            .pod-var-price-amount {
                color: var(--pod-var-primary) !important;
            }
        </style>
        <?php
    }
}

// Initialize
new POD_Variations_Settings();
