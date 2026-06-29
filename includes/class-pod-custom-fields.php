<?php
/**
 * POD Custom Fields - Frontend Form, Cart, Order Integration
 * Handles custom fields display on product page, cart integration, and order processing
 * 
 * @package POD_AI_Connector
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class POD_Custom_Fields {

    /**
     * Initialize hooks
     */
    public static function init() {
        // Frontend form - priority 20 to render AFTER POD Variations (priority 15)
        add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_custom_fields_form' ), 20 );
        add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_custom_fields' ), 10, 3 );
        
        // Cart integration
        add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_custom_fields_to_cart' ), 10, 3 );
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_custom_fields_in_cart' ), 10, 2 );
        add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'add_personalization_fee' ) );
        
        // Order integration
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_custom_fields_to_order' ), 10, 4 );
        add_action( 'woocommerce_admin_order_item_headers', array( __CLASS__, 'admin_order_item_headers' ) );
        add_action( 'woocommerce_admin_order_item_values', array( __CLASS__, 'admin_order_item_values' ), 10, 3 );
        
        // Email integration
        add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( __CLASS__, 'format_order_item_meta' ), 10, 2 );
        
        // Enqueue styles
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
        
        // AJAX file upload
        add_action( 'wp_ajax_pod_upload_custom_file', array( __CLASS__, 'handle_file_upload' ) );
        add_action( 'wp_ajax_nopriv_pod_upload_custom_file', array( __CLASS__, 'handle_file_upload' ) );
        
        // Secure file download
        add_action( 'init', array( __CLASS__, 'handle_secure_download' ) );
    }


    /**
     * Enqueue frontend styles
     */
    public static function enqueue_styles() {
        if ( is_product() ) {
            wp_enqueue_style( 
                'pod-custom-fields', 
                plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/custom-fields.css',
                array(),
                SAC_VERSION
            );
        }
    }

    /**
     * Get custom fields config for a product
     */
    public static function get_product_custom_fields( $product_id ) {
        $custom_fields = get_post_meta( $product_id, '_pod_custom_fields', true );
        if ( empty( $custom_fields ) || ! is_array( $custom_fields ) ) {
            return null;
        }
        return $custom_fields;
    }

    /**
     * Render custom fields form on product page
     */
    public static function render_custom_fields_form() {
        global $product;
        
        if ( ! $product ) {
            return;
        }
        
        $custom_fields = self::get_product_custom_fields( $product->get_id() );
        
        if ( ! $custom_fields || empty( $custom_fields['fields'] ) ) {
            return;
        }
        
        $fields = $custom_fields['fields'];
        $enable_fee = ! empty( $custom_fields['enable_extra_fee'] );
        $fee_amount = isset( $custom_fields['extra_fee'] ) ? $custom_fields['extra_fee'] : '0';
        $fee_label = isset( $custom_fields['fee_label'] ) ? $custom_fields['fee_label'] : 'Personalization Fee';
        $is_optional = ! empty( $custom_fields['is_optional'] );
        $optional_label = isset( $custom_fields['optional_label'] ) ? $custom_fields['optional_label'] : 'Add Personalization';
        
        ?>
        <div class="pod-custom-fields-container" data-optional="<?php echo $is_optional ? 'true' : 'false'; ?>">
            <?php if ( $is_optional ) : ?>
                <label class="pod-optional-toggle">
                    <input type="checkbox" name="pod_enable_personalization" id="pod_enable_personalization" value="1" />
                    <span class="pod-toggle-label">
                        <span class="pod-toggle-icon">✏️</span>
                        <?php echo esc_html( $optional_label ); ?>
                        <?php if ( $enable_fee && floatval( $fee_amount ) > 0 ) : ?>
                            <span class="pod-toggle-fee">(+$<?php echo esc_html( $fee_amount ); ?>)</span>
                        <?php endif; ?>
                    </span>
                </label>
                <div class="pod-custom-fields-wrapper" style="display: none;">
            <?php else : ?>
                <h4 class="pod-custom-fields-title">
                    <span class="pod-icon">✏️</span>
                    <?php esc_html_e( 'Personalization', 'pod-connector' ); ?>
                </h4>
                <div class="pod-custom-fields-wrapper">
            <?php endif; ?>
            
                <div class="pod-custom-fields-form">
                    <?php foreach ( $fields as $field ) : ?>
                        <?php self::render_field( $field ); ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php wp_nonce_field( 'pod_custom_fields', 'pod_custom_fields_nonce' ); ?>
        </div>
        
        <?php if ( $is_optional ) : ?>
        <script>
        jQuery(function($) {
            var $container = $('.pod-custom-fields-container[data-optional="true"]');
            var $checkbox = $container.find('#pod_enable_personalization');
            var $wrapper = $container.find('.pod-custom-fields-wrapper');
            var $inputs = $wrapper.find('input, select, textarea');
            
            // Initially disable all inputs
            $inputs.prop('disabled', true);
            
            $checkbox.on('change', function() {
                if ($(this).is(':checked')) {
                    $wrapper.slideDown(200);
                    $inputs.prop('disabled', false);
                } else {
                    $wrapper.slideUp(200);
                    $inputs.prop('disabled', true).val('');
                    // Clear file previews
                    $wrapper.find('.pod-file-preview').hide();
                    $wrapper.find('.pod-file-dropzone').show();
                    $wrapper.find('.pod-file-path').val('');
                }
            });
        });
        </script>
        <?php endif; ?>
        <?php
    }


    /**
     * Render individual field
     */
    private static function render_field( $field ) {
        $field_id = isset( $field['id'] ) ? sanitize_key( $field['id'] ) : '';
        $type = isset( $field['type'] ) ? $field['type'] : 'text';
        $label = isset( $field['label'] ) ? $field['label'] : '';
        $placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
        $required = ! empty( $field['required'] );
        $max_length = isset( $field['maxLength'] ) ? intval( $field['maxLength'] ) : 0;
        
        $input_name = 'pod_custom_' . $field_id;
        $required_attr = $required ? 'required' : '';
        $required_star = $required ? '<span class="required">*</span>' : '';
        
        ?>
        <div class="pod-field pod-field-<?php echo esc_attr( $type ); ?>">
            <label for="<?php echo esc_attr( $input_name ); ?>">
                <?php echo esc_html( $label ); ?> <?php echo $required_star; ?>
            </label>
            
            <?php if ( $type === 'text' || $type === 'number' ) : ?>
                <input 
                    type="text" 
                    id="<?php echo esc_attr( $input_name ); ?>"
                    name="<?php echo esc_attr( $input_name ); ?>"
                    placeholder="<?php echo esc_attr( $placeholder ); ?>"
                    <?php echo $required_attr; ?>
                    <?php if ( $max_length > 0 ) : ?>maxlength="<?php echo esc_attr( $max_length ); ?>"<?php endif; ?>
                    class="pod-input"
                />
                
            <?php elseif ( $type === 'file' ) : ?>
                <?php 
                $allowed_types = isset( $field['allowedFileTypes'] ) ? $field['allowedFileTypes'] : array( 'jpg', 'png' );
                $max_size = isset( $field['maxFileSize'] ) ? intval( $field['maxFileSize'] ) : 5;
                ?>
                <div class="pod-file-upload-area" data-field-id="<?php echo esc_attr( $field_id ); ?>">
                    <input 
                        type="file" 
                        id="<?php echo esc_attr( $input_name ); ?>_file"
                        accept="<?php echo esc_attr( '.' . implode( ',.', $allowed_types ) ); ?>"
                        class="pod-file-input"
                        data-max-size="<?php echo esc_attr( $max_size ); ?>"
                    />
                    <input 
                        type="hidden" 
                        name="<?php echo esc_attr( $input_name ); ?>"
                        id="<?php echo esc_attr( $input_name ); ?>"
                        class="pod-file-path"
                        <?php echo $required_attr; ?>
                    />
                    <div class="pod-file-dropzone">
                        <span class="pod-file-icon">📁</span>
                        <span class="pod-file-text"><?php esc_html_e( 'Click or drag file here', 'pod-connector' ); ?></span>
                        <span class="pod-file-hint"><?php printf( esc_html__( 'Allowed: %s (Max %dMB)', 'pod-connector' ), strtoupper( implode( ', ', $allowed_types ) ), $max_size ); ?></span>
                    </div>
                    <div class="pod-file-preview" style="display:none;">
                        <img src="" alt="Preview" class="pod-file-preview-img" />
                        <span class="pod-file-name"></span>
                        <button type="button" class="pod-file-remove">×</button>
                    </div>
                    <div class="pod-file-uploading" style="display:none;">
                        <span class="pod-spinner"></span>
                        <span><?php esc_html_e( 'Uploading...', 'pod-connector' ); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }


    /**
     * Validate custom fields before adding to cart
     */
    public static function validate_custom_fields( $passed, $product_id, $quantity ) {
        $custom_fields = self::get_product_custom_fields( $product_id );
        
        if ( ! $custom_fields || empty( $custom_fields['fields'] ) ) {
            return $passed;
        }
        
        // Verify nonce
        if ( ! isset( $_POST['pod_custom_fields_nonce'] ) || 
             ! wp_verify_nonce( $_POST['pod_custom_fields_nonce'], 'pod_custom_fields' ) ) {
            return $passed;
        }
        
        // Check if optional and not enabled
        $is_optional = ! empty( $custom_fields['is_optional'] );
        if ( $is_optional && empty( $_POST['pod_enable_personalization'] ) ) {
            return $passed; // Skip validation if personalization not enabled
        }
        
        foreach ( $custom_fields['fields'] as $field ) {
            $field_id = isset( $field['id'] ) ? sanitize_key( $field['id'] ) : '';
            $input_name = 'pod_custom_' . $field_id;
            $required = ! empty( $field['required'] );
            $label = isset( $field['label'] ) ? $field['label'] : $field_id;
            
            $value = isset( $_POST[ $input_name ] ) ? sanitize_text_field( $_POST[ $input_name ] ) : '';
            
            if ( $required && empty( $value ) ) {
                wc_add_notice( sprintf( __( '%s is required.', 'pod-connector' ), $label ), 'error' );
                $passed = false;
            }
            
            // Validate max length
            if ( ! empty( $value ) && isset( $field['maxLength'] ) && strlen( $value ) > intval( $field['maxLength'] ) ) {
                wc_add_notice( sprintf( __( '%s exceeds maximum length of %d characters.', 'pod-connector' ), $label, $field['maxLength'] ), 'error' );
                $passed = false;
            }
        }
        
        return $passed;
    }

    /**
     * Add custom fields data to cart item
     */
    public static function add_custom_fields_to_cart( $cart_item_data, $product_id, $variation_id ) {
        $custom_fields = self::get_product_custom_fields( $product_id );
        
        if ( ! $custom_fields || empty( $custom_fields['fields'] ) ) {
            return $cart_item_data;
        }
        
        // Verify nonce
        if ( ! isset( $_POST['pod_custom_fields_nonce'] ) || 
             ! wp_verify_nonce( $_POST['pod_custom_fields_nonce'], 'pod_custom_fields' ) ) {
            return $cart_item_data;
        }
        
        // Check if optional and not enabled
        $is_optional = ! empty( $custom_fields['is_optional'] );
        if ( $is_optional && empty( $_POST['pod_enable_personalization'] ) ) {
            return $cart_item_data; // Skip if personalization not enabled
        }
        
        $custom_data = array();
        
        foreach ( $custom_fields['fields'] as $field ) {
            $field_id = isset( $field['id'] ) ? sanitize_key( $field['id'] ) : '';
            $input_name = 'pod_custom_' . $field_id;
            $value = isset( $_POST[ $input_name ] ) ? sanitize_text_field( $_POST[ $input_name ] ) : '';
            
            if ( ! empty( $value ) ) {
                $custom_data[ $field_id ] = array(
                    'label' => isset( $field['label'] ) ? $field['label'] : $field_id,
                    'value' => $value,
                    'type' => isset( $field['type'] ) ? $field['type'] : 'text',
                );
            }
        }
        
        if ( ! empty( $custom_data ) ) {
            $cart_item_data['pod_custom_fields'] = $custom_data;
            $cart_item_data['pod_custom_fields_config'] = array(
                'enable_extra_fee' => ! empty( $custom_fields['enable_extra_fee'] ),
                'extra_fee' => isset( $custom_fields['extra_fee'] ) ? $custom_fields['extra_fee'] : '0',
                'fee_label' => isset( $custom_fields['fee_label'] ) ? $custom_fields['fee_label'] : 'Personalization Fee',
            );
        }
        
        return $cart_item_data;
    }


    /**
     * Display custom fields in cart
     */
    public static function display_custom_fields_in_cart( $item_data, $cart_item ) {
        if ( empty( $cart_item['pod_custom_fields'] ) ) {
            return $item_data;
        }
        
        foreach ( $cart_item['pod_custom_fields'] as $field_id => $field_data ) {
            $display_value = $field_data['value'];
            
            // For file fields, show filename only
            if ( $field_data['type'] === 'file' && ! empty( $display_value ) ) {
                $display_value = '📎 ' . basename( $display_value );
            }
            
            $item_data[] = array(
                'key' => $field_data['label'],
                'value' => $display_value,
            );
        }
        
        return $item_data;
    }

    /**
     * Add personalization fee to cart
     */
    public static function add_personalization_fee( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }
        
        $total_fee = 0;
        $fee_label = 'Personalization Fee';
        
        foreach ( $cart->get_cart() as $cart_item ) {
            if ( ! empty( $cart_item['pod_custom_fields'] ) && ! empty( $cart_item['pod_custom_fields_config'] ) ) {
                $config = $cart_item['pod_custom_fields_config'];
                
                if ( ! empty( $config['enable_extra_fee'] ) && floatval( $config['extra_fee'] ) > 0 ) {
                    $total_fee += floatval( $config['extra_fee'] ) * $cart_item['quantity'];
                    $fee_label = $config['fee_label'];
                }
            }
        }
        
        if ( $total_fee > 0 ) {
            $cart->add_fee( $fee_label, $total_fee );
        }
    }

    /**
     * Save custom fields to order item meta
     */
    public static function save_custom_fields_to_order( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['pod_custom_fields'] ) ) {
            return;
        }
        
        foreach ( $values['pod_custom_fields'] as $field_id => $field_data ) {
            $meta_key = '_pod_custom_' . $field_id;
            $item->add_meta_data( $field_data['label'], $field_data['value'], true );
            
            // Also save with internal key for programmatic access
            $item->add_meta_data( $meta_key, $field_data['value'], true );
            
            // Mark file fields for secure download
            if ( $field_data['type'] === 'file' ) {
                $item->add_meta_data( '_pod_custom_file_' . $field_id, $field_data['value'], true );
            }
        }
        
        // Save fee info
        if ( ! empty( $values['pod_custom_fields_config'] ) ) {
            $config = $values['pod_custom_fields_config'];
            if ( ! empty( $config['enable_extra_fee'] ) && floatval( $config['extra_fee'] ) > 0 ) {
                $item->add_meta_data( '_pod_personalization_fee', $config['extra_fee'], true );
            }
        }
    }


    /**
     * Add custom fields header in admin order view
     */
    public static function admin_order_item_headers() {
        echo '<th class="pod-custom-fields-header">' . esc_html__( 'Custom Fields', 'pod-connector' ) . '</th>';
    }

    /**
     * Display custom fields in admin order item row
     */
    public static function admin_order_item_values( $product, $item, $item_id ) {
        echo '<td class="pod-custom-fields-cell">';
        
        $has_custom = false;
        $meta_data = $item->get_meta_data();
        
        foreach ( $meta_data as $meta ) {
            $key = $meta->key;
            
            // Skip internal meta
            if ( strpos( $key, '_' ) === 0 ) {
                // Check for file fields
                if ( strpos( $key, '_pod_custom_file_' ) === 0 ) {
                    $file_path = $meta->value;
                    if ( ! empty( $file_path ) ) {
                        $download_url = self::get_secure_download_url( $item_id, $key );
                        echo '<div class="pod-admin-file">';
                        echo '<strong>📎 File:</strong> ';
                        echo '<a href="' . esc_url( $download_url ) . '" target="_blank">' . esc_html( basename( $file_path ) ) . '</a>';
                        echo '</div>';
                        $has_custom = true;
                    }
                }
                continue;
            }
            
            // Display visible meta (custom field labels)
            if ( ! in_array( $key, array( '_reduced_stock', '_restock_refunded_items' ) ) ) {
                // Skip WooCommerce internal meta
                if ( strpos( $key, 'pa_' ) === 0 ) continue;
                
                echo '<div class="pod-admin-field">';
                echo '<strong>' . esc_html( $key ) . ':</strong> ';
                echo esc_html( $meta->value );
                echo '</div>';
                $has_custom = true;
            }
        }
        
        if ( ! $has_custom ) {
            echo '<span class="na">—</span>';
        }
        
        echo '</td>';
    }

    /**
     * Format order item meta for emails
     */
    public static function format_order_item_meta( $formatted_meta, $item ) {
        foreach ( $formatted_meta as $key => $meta ) {
            // Hide internal meta from emails
            if ( strpos( $meta->key, '_pod_custom_' ) === 0 ) {
                unset( $formatted_meta[ $key ] );
            }
            
            // Format file paths to show filename only
            if ( strpos( $meta->value, '/pod-custom/' ) !== false ) {
                $formatted_meta[ $key ]->display_value = '📎 ' . basename( $meta->value );
            }
        }
        
        return $formatted_meta;
    }


    /**
     * Handle AJAX file upload
     */
    public static function handle_file_upload() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'pod_custom_fields' ) ) {
            wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
        }
        
        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( array( 'message' => 'No file uploaded' ) );
        }
        
        $file = $_FILES['file'];
        
        // Check for errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( array( 'message' => 'Upload error: ' . $file['error'] ) );
        }
        
        // Validate file type
        $allowed_types = array( 'jpg', 'jpeg', 'png', 'svg', 'pdf' );
        $file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        
        if ( ! in_array( $file_ext, $allowed_types ) ) {
            wp_send_json_error( array( 'message' => 'File type not allowed. Allowed: ' . implode( ', ', $allowed_types ) ) );
        }
        
        // Validate file size (max 10MB)
        $max_size = 10 * 1024 * 1024;
        if ( $file['size'] > $max_size ) {
            wp_send_json_error( array( 'message' => 'File too large. Max 10MB.' ) );
        }
        
        // Create secure upload directory
        $upload_dir = wp_upload_dir();
        $custom_dir = $upload_dir['basedir'] . '/pod-custom';
        
        if ( ! file_exists( $custom_dir ) ) {
            wp_mkdir_p( $custom_dir );
            
            // Create .htaccess to deny direct access
            $htaccess = $custom_dir . '/.htaccess';
            file_put_contents( $htaccess, "Order Deny,Allow\nDeny from all" );
            
            // Create index.php
            file_put_contents( $custom_dir . '/index.php', '<?php // Silence is golden' );
        }
        
        // Generate unique filename
        $unique_name = wp_generate_uuid4() . '_' . time() . '.' . $file_ext;
        $file_path = $custom_dir . '/' . $unique_name;
        
        // Move uploaded file
        if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) {
            wp_send_json_error( array( 'message' => 'Failed to save file' ) );
        }
        
        // Return relative path for storage
        $relative_path = '/pod-custom/' . $unique_name;
        
        wp_send_json_success( array(
            'path' => $relative_path,
            'filename' => $file['name'],
            'preview' => in_array( $file_ext, array( 'jpg', 'jpeg', 'png' ) ) 
                ? $upload_dir['baseurl'] . $relative_path 
                : null
        ) );
    }


    /**
     * Get secure download URL for admin
     */
    public static function get_secure_download_url( $item_id, $meta_key ) {
        $nonce = wp_create_nonce( 'pod_download_' . $item_id . '_' . $meta_key );
        return add_query_arg( array(
            'pod_download' => 1,
            'item_id' => $item_id,
            'meta_key' => $meta_key,
            'nonce' => $nonce,
        ), admin_url( 'admin.php' ) );
    }

    /**
     * Handle secure file download
     */
    public static function handle_secure_download() {
        if ( ! isset( $_GET['pod_download'] ) || $_GET['pod_download'] != 1 ) {
            return;
        }
        
        // Must be admin
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( 'Unauthorized access' );
        }
        
        $item_id = isset( $_GET['item_id'] ) ? intval( $_GET['item_id'] ) : 0;
        $meta_key = isset( $_GET['meta_key'] ) ? sanitize_text_field( $_GET['meta_key'] ) : '';
        $nonce = isset( $_GET['nonce'] ) ? $_GET['nonce'] : '';
        
        // Verify nonce
        if ( ! wp_verify_nonce( $nonce, 'pod_download_' . $item_id . '_' . $meta_key ) ) {
            wp_die( 'Invalid request' );
        }
        
        // Get file path from order item meta
        $item = new WC_Order_Item_Product( $item_id );
        $file_path = $item->get_meta( $meta_key );
        
        if ( empty( $file_path ) ) {
            wp_die( 'File not found' );
        }
        
        // Build full path
        $upload_dir = wp_upload_dir();
        $full_path = $upload_dir['basedir'] . $file_path;
        
        if ( ! file_exists( $full_path ) ) {
            wp_die( 'File not found on server' );
        }
        
        // Serve file
        $filename = basename( $full_path );
        $mime_type = mime_content_type( $full_path );
        
        header( 'Content-Type: ' . $mime_type );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $full_path ) );
        header( 'Cache-Control: no-cache, must-revalidate' );
        
        readfile( $full_path );
        exit;
    }
}

// Initialize
POD_Custom_Fields::init();
