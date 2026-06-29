<?php
/**
 * POD Site Variables - Shortcode Variables System
 * 
 * Allows managing site-wide variables that can be used in content via shortcodes.
 * Perfect for clone sites - change variables in one place, update everywhere.
 * 
 * Usage: [pod_var name="phone"] or [pod_var:phone]
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class POD_Site_Variables {
    
    private static $instance = null;
    const OPTION_KEY = 'pod_site_variables';
    
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Register shortcodes
        add_shortcode( 'pod_var', array( $this, 'render_shortcode' ) );
        
        // CRITICAL: Parse inline shortcodes BEFORE WordPress processes standard shortcodes
        // This ensures [pod_var:xxx] is converted to [pod_var name="xxx"] first
        add_filter( 'the_content', array( $this, 'parse_inline_shortcodes' ), 5 );
        add_filter( 'the_excerpt', array( $this, 'parse_inline_shortcodes' ), 5 );
        add_filter( 'widget_text', array( $this, 'parse_inline_shortcodes' ), 5 );
        add_filter( 'widget_text_content', array( $this, 'parse_inline_shortcodes' ), 5 );
        add_filter( 'get_the_excerpt', array( $this, 'parse_inline_shortcodes' ), 5 );
        
        // THEN apply do_shortcode to process [pod_var name="xxx"]
        add_filter( 'the_content', 'do_shortcode', 11 );
        add_filter( 'the_excerpt', 'do_shortcode', 11 );
        add_filter( 'widget_text', 'do_shortcode', 11 );
        add_filter( 'widget_text_content', 'do_shortcode', 11 );
        
        // Apply to custom fields and meta
        add_filter( 'get_post_metadata', array( $this, 'parse_meta_shortcodes' ), 10, 4 );
        
        // Apply to all text widgets
        add_filter( 'widget_display_callback', array( $this, 'parse_widget_shortcodes' ), 10, 3 );
        
        // Apply to theme options and customizer (for footer, header content)
        add_filter( 'option_theme_mods_' . get_option( 'stylesheet' ), array( $this, 'parse_theme_mods' ), 10 );
        add_filter( 'theme_mod_footer_content', array( $this, 'parse_inline_shortcodes' ), 10 );
        add_filter( 'theme_mod_header_content', array( $this, 'parse_inline_shortcodes' ), 10 );
        
        // Apply to Elementor content
        add_filter( 'elementor/frontend/the_content', array( $this, 'parse_inline_shortcodes' ), 5 );
        add_filter( 'elementor/widget/render_content', array( $this, 'parse_inline_shortcodes' ), 10 );
        
        // Register REST API endpoints
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        
        // Admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_pod_save_site_variables', array( $this, 'ajax_save_variables' ) );
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get all variables
        register_rest_route( 'sac/v1', '/site-variables', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'api_get_variables' ),
            'permission_callback' => 'sac_verify_simple',
        ) );
        
        // Update variables
        register_rest_route( 'sac/v1', '/site-variables', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'api_update_variables' ),
            'permission_callback' => 'sac_verify',
        ) );
        
        // Delete variable
        register_rest_route( 'sac/v1', '/site-variables/(?P<key>[a-zA-Z0-9_-]+)', array(
            'methods'             => 'DELETE',
            'callback'            => array( $this, 'api_delete_variable' ),
            'permission_callback' => 'sac_verify',
        ) );
    }
    
    /**
     * Get all variables
     */
    public function get_variables() {
        $variables = get_option( self::OPTION_KEY, array() );
        return is_array( $variables ) ? $variables : array();
    }
    
    /**
     * Get single variable value
     */
    public function get_variable( $key, $default = '' ) {
        $variables = $this->get_variables();
        return isset( $variables[ $key ] ) ? $variables[ $key ]['value'] : $default;
    }
    
    /**
     * Set variable
     */
    public function set_variable( $key, $value, $description = '' ) {
        $variables = $this->get_variables();
        $variables[ $key ] = array(
            'value'       => $value,
            'description' => $description,
            'updated_at'  => current_time( 'mysql' ),
        );
        return update_option( self::OPTION_KEY, $variables );
    }
    
    /**
     * Delete variable
     */
    public function delete_variable( $key ) {
        $variables = $this->get_variables();
        if ( isset( $variables[ $key ] ) ) {
            unset( $variables[ $key ] );
            return update_option( self::OPTION_KEY, $variables );
        }
        return false;
    }
    
    /**
     * Render shortcode [pod_var name="xxx"]
     */
    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'name'    => '',
            'default' => '',
        ), $atts, 'pod_var' );
        
        if ( empty( $atts['name'] ) ) {
            return $atts['default'];
        }
        
        return $this->get_variable( $atts['name'], $atts['default'] );
    }
    
    /**
     * Parse inline shortcodes [pod_var:name]
     * Converts [pod_var:xxx] to actual value directly
     */
    public function parse_inline_shortcodes( $content ) {
        if ( empty( $content ) || ! is_string( $content ) ) {
            return $content;
        }
        
        // Match [pod_var:variable_name] pattern
        $pattern = '/\[pod_var:([a-zA-Z0-9_-]+)\]/';
        
        $content = preg_replace_callback( $pattern, function( $matches ) {
            $key = $matches[1];
            $value = $this->get_variable( $key, '' );
            
            // IMPORTANT: If variable not found, return empty string (not the shortcode)
            // This prevents showing [pod_var:xxx] on frontend
            return $value !== '' ? $value : '';
        }, $content );
        
        return $content;
    }
    
    /**
     * Parse shortcodes in post meta fields
     */
    public function parse_meta_shortcodes( $value, $object_id, $meta_key, $single ) {
        // Only process on frontend
        if ( is_admin() && ! wp_doing_ajax() ) {
            return $value;
        }
        
        // Get the meta value if not already retrieved
        if ( null === $value ) {
            return $value;
        }
        
        // Remove this filter to prevent infinite loop
        remove_filter( 'get_post_metadata', array( $this, 'parse_meta_shortcodes' ), 10 );
        
        // Get the actual meta value
        $meta_value = get_post_meta( $object_id, $meta_key, $single );
        
        // Re-add the filter
        add_filter( 'get_post_metadata', array( $this, 'parse_meta_shortcodes' ), 10, 4 );
        
        // Parse shortcodes if it's a string
        if ( is_string( $meta_value ) ) {
            $meta_value = $this->parse_inline_shortcodes( $meta_value );
        } elseif ( is_array( $meta_value ) ) {
            array_walk_recursive( $meta_value, function( &$item ) {
                if ( is_string( $item ) ) {
                    $item = $this->parse_inline_shortcodes( $item );
                }
            } );
        }
        
        return $meta_value;
    }
    
    /**
     * Parse shortcodes in widget content
     */
    public function parse_widget_shortcodes( $instance, $widget, $args ) {
        if ( ! empty( $instance ) && is_array( $instance ) ) {
            array_walk_recursive( $instance, function( &$item ) {
                if ( is_string( $item ) ) {
                    $item = $this->parse_inline_shortcodes( $item );
                }
            } );
        }
        return $instance;
    }
    
    /**
     * Parse shortcodes in theme mods (customizer settings)
     */
    public function parse_theme_mods( $mods ) {
        if ( ! empty( $mods ) && is_array( $mods ) ) {
            array_walk_recursive( $mods, function( &$item ) {
                if ( is_string( $item ) ) {
                    $item = $this->parse_inline_shortcodes( $item );
                }
            } );
        }
        return $mods;
    }
    
    /**
     * API: Get all variables
     */
    public function api_get_variables( $request ) {
        $variables = $this->get_variables();
        
        // Format for API response
        $formatted = array();
        foreach ( $variables as $key => $data ) {
            $formatted[] = array(
                'key'         => $key,
                'value'       => $data['value'],
                'description' => isset( $data['description'] ) ? $data['description'] : '',
                'updated_at'  => isset( $data['updated_at'] ) ? $data['updated_at'] : '',
            );
        }
        
        return rest_ensure_response( array(
            'success'   => true,
            'variables' => $formatted,
            'count'     => count( $formatted ),
        ) );
    }
    
    /**
     * API: Update variables (bulk)
     */
    public function api_update_variables( $request ) {
        $params = $request->get_json_params();
        $variables = isset( $params['variables'] ) ? $params['variables'] : array();
        $replace_all = isset( $params['replace_all'] ) ? (bool) $params['replace_all'] : false;
        
        if ( empty( $variables ) ) {
            return rest_ensure_response( array( 'success' => false, 'error' => 'No variables provided' ) );
        }
        
        // If replace_all, clear existing variables first
        if ( $replace_all ) {
            delete_option( self::OPTION_KEY );
        }
        
        $results = array();
        foreach ( $variables as $var ) {
            $key = isset( $var['key'] ) ? sanitize_key( $var['key'] ) : '';
            $value = isset( $var['value'] ) ? sanitize_text_field( $var['value'] ) : '';
            $description = isset( $var['description'] ) ? sanitize_text_field( $var['description'] ) : '';
            
            if ( empty( $key ) ) {
                $results[] = array( 'key' => $key, 'success' => false, 'error' => 'Invalid key' );
                continue;
            }
            
            $this->set_variable( $key, $value, $description );
            $results[] = array( 'key' => $key, 'success' => true );
        }
        
        return rest_ensure_response( array(
            'success' => true,
            'results' => $results,
            'total'   => count( $this->get_variables() ),
        ) );
    }
    
    /**
     * API: Delete single variable
     */
    public function api_delete_variable( $request ) {
        $key = $request->get_param( 'key' );
        
        if ( empty( $key ) ) {
            return rest_ensure_response( array( 'success' => false, 'error' => 'Key is required' ) );
        }
        
        $deleted = $this->delete_variable( $key );
        
        return rest_ensure_response( array(
            'success' => $deleted,
            'key'     => $key,
        ) );
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            'Site Variables',
            'Site Variables',
            'manage_options',
            'pod-site-variables',
            array( $this, 'render_admin_page' )
        );
    }
    
    /**
     * AJAX: Save variables from admin
     */
    public function ajax_save_variables() {
        check_ajax_referer( 'pod_site_variables_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }
        
        $variables = isset( $_POST['variables'] ) ? $_POST['variables'] : array();
        
        // Clear and rebuild
        delete_option( self::OPTION_KEY );
        
        foreach ( $variables as $var ) {
            $key = sanitize_key( $var['key'] );
            $value = sanitize_text_field( $var['value'] );
            $description = sanitize_text_field( $var['description'] );
            
            if ( ! empty( $key ) ) {
                $this->set_variable( $key, $value, $description );
            }
        }
        
        wp_send_json_success( array( 'count' => count( $this->get_variables() ) ) );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $variables = $this->get_variables();
        $nonce = wp_create_nonce( 'pod_site_variables_nonce' );
        ?>
        <div class="wrap">
            <h1>Site Variables</h1>
            <p>Quản lý các biến dùng chung trên toàn site. Sử dụng shortcode <code>[pod_var name="key"]</code> hoặc <code>[pod_var:key]</code> trong nội dung.</p>
            
            <div style="background:#fff;padding:20px;margin:20px 0;border:1px solid #ccd0d4;border-radius:8px;">
                <h2>Variables</h2>
                <table class="wp-list-table widefat fixed striped" id="variables-table">
                    <thead>
                        <tr>
                            <th style="width:150px;">Key</th>
                            <th style="width:250px;">Value</th>
                            <th>Description</th>
                            <th style="width:100px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="variables-body">
                        <?php foreach ( $variables as $key => $data ) : ?>
                        <tr data-key="<?php echo esc_attr( $key ); ?>">
                            <td><input type="text" class="var-key" value="<?php echo esc_attr( $key ); ?>" style="width:100%;" /></td>
                            <td><input type="text" class="var-value" value="<?php echo esc_attr( $data['value'] ); ?>" style="width:100%;" /></td>
                            <td><input type="text" class="var-desc" value="<?php echo esc_attr( isset( $data['description'] ) ? $data['description'] : '' ); ?>" style="width:100%;" /></td>
                            <td><button type="button" class="button delete-var">Delete</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p style="margin-top:15px;">
                    <button type="button" class="button" id="add-variable">+ Add Variable</button>
                    <button type="button" class="button button-primary" id="save-variables">Save All</button>
                </p>
            </div>
            
            <div style="background:#f0f6fc;padding:15px;border:1px solid #c3c4c7;border-radius:4px;">
                <h3 style="margin-top:0;">Common Variables</h3>
                <p>Các biến thường dùng cho clone site:</p>
                <ul>
                    <li><code>phone</code> - Số điện thoại</li>
                    <li><code>email</code> - Email liên hệ</li>
                    <li><code>address</code> - Địa chỉ</li>
                    <li><code>company_name</code> - Tên công ty</li>
                    <li><code>facebook</code> - Link Facebook</li>
                    <li><code>zalo</code> - Số Zalo</li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Add new variable row
            $('#add-variable').on('click', function() {
                var row = '<tr>' +
                    '<td><input type="text" class="var-key" placeholder="variable_key" style="width:100%;" /></td>' +
                    '<td><input type="text" class="var-value" placeholder="Value" style="width:100%;" /></td>' +
                    '<td><input type="text" class="var-desc" placeholder="Description (optional)" style="width:100%;" /></td>' +
                    '<td><button type="button" class="button delete-var">Delete</button></td>' +
                    '</tr>';
                $('#variables-body').append(row);
            });
            
            // Delete variable row
            $(document).on('click', '.delete-var', function() {
                $(this).closest('tr').remove();
            });
            
            // Save all variables
            $('#save-variables').on('click', function() {
                var variables = [];
                $('#variables-body tr').each(function() {
                    var key = $(this).find('.var-key').val().trim();
                    var value = $(this).find('.var-value').val().trim();
                    var desc = $(this).find('.var-desc').val().trim();
                    
                    if (key) {
                        variables.push({ key: key, value: value, description: desc });
                    }
                });
                
                $.post(ajaxurl, {
                    action: 'pod_save_site_variables',
                    nonce: '<?php echo esc_js( $nonce ); ?>',
                    variables: variables
                }, function(response) {
                    if (response.success) {
                        alert('Saved ' + response.data.count + ' variables!');
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Initialize
POD_Site_Variables::get_instance();
