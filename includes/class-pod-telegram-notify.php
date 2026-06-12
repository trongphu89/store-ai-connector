<?php
/**
 * POD Telegram Notify - Sales Notification via Telegram
 * Sends notifications to a Telegram channel/group when orders are placed
 * 
 * @package POD_AI_Connector
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class POD_Telegram_Notify {

    /**
     * Telegram Bot API URL
     */
    const API_URL = 'https://api.telegram.org/bot';

    /**
     * Constructor
     */
    public function __construct() {
        // Admin settings
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // WooCommerce order hooks - fire on various order status changes
        add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_processing' ), 10, 1 );
        add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_completed' ), 10, 1 );
        add_action( 'woocommerce_order_status_on-hold', array( $this, 'on_order_on_hold' ), 10, 1 );

        // New order placed (payment received)
        add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ), 10, 1 );

        // REST API for testing
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Get settings
     */
    public function get_settings() {
        return array(
            'enabled'          => get_option( 'pod_telegram_enabled', '0' ),
            'bot_token'        => get_option( 'pod_telegram_bot_token', '' ),
            'chat_id'          => get_option( 'pod_telegram_chat_id', '' ),
            'notify_processing'=> get_option( 'pod_telegram_notify_processing', '1' ),
            'notify_completed' => get_option( 'pod_telegram_notify_completed', '1' ),
            'notify_on_hold'   => get_option( 'pod_telegram_notify_on_hold', '0' ),
            'notify_payment'   => get_option( 'pod_telegram_notify_payment', '0' ),
            'message_template' => get_option( 'pod_telegram_message_template', '' ),
            'include_products' => get_option( 'pod_telegram_include_products', '1' ),
            'include_customer' => get_option( 'pod_telegram_include_customer', '1' ),
            'include_address'  => get_option( 'pod_telegram_include_address', '0' ),
        );
    }

    /**
     * Check if notifications are enabled
     */
    public function is_enabled() {
        $settings = $this->get_settings();
        return $settings['enabled'] === '1' 
            && ! empty( $settings['bot_token'] ) 
            && ! empty( $settings['chat_id'] );
    }

    // =========================================================================
    // ORDER EVENT HANDLERS
    // =========================================================================

    /**
     * Order status changed to Processing
     */
    public function on_order_processing( $order_id ) {
        $settings = $this->get_settings();
        if ( $settings['notify_processing'] !== '1' ) return;
        $this->send_order_notification( $order_id, '🛒 New Order', 'processing' );
    }

    /**
     * Order status changed to Completed
     */
    public function on_order_completed( $order_id ) {
        $settings = $this->get_settings();
        if ( $settings['notify_completed'] !== '1' ) return;
        $this->send_order_notification( $order_id, '✅ Complete order', 'completed' );
    }

    /**
     * Order status changed to On Hold
     */
    public function on_order_on_hold( $order_id ) {
        $settings = $this->get_settings();
        if ( $settings['notify_on_hold'] !== '1' ) return;
        $this->send_order_notification( $order_id, '⏸ Order On Hold', 'on-hold' );
    }

    /**
     * Payment completed
     */
    public function on_payment_complete( $order_id ) {
        $settings = $this->get_settings();
        if ( $settings['notify_payment'] !== '1' ) return;
        $this->send_order_notification( $order_id, '💰 Payment Received', 'payment-complete' );
    }

    // =========================================================================
    // NOTIFICATION LOGIC
    // =========================================================================

    /**
     * Send order notification to Telegram
     */
    public function send_order_notification( $order_id, $event_title = '', $status_type = 'processing' ) {
        if ( ! $this->is_enabled() ) return false;

        $order = wc_get_order( $order_id );
        if ( ! $order ) return false;

        // Prevent duplicate notifications - check meta
        $meta_key = '_telegram_notified_' . sanitize_key( $event_title );
        if ( $order->get_meta( $meta_key ) === 'yes' ) {
            return false;
        }

        if ( $status_type === 'completed' ) {
            $message = '✅ Complete order';
            $reply_to = $order->get_meta('_telegram_first_message_id');
            $result_message_id = $this->send_telegram_message( $message, '', $reply_to );
            if ( $result_message_id ) {
                $order->update_meta_data( $meta_key, 'yes' );
                $order->save();
            }
            return $result_message_id ? true : false;
        }

        $message = $this->build_order_message( $order, $event_title );
        
        $image_url = '';
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product ) {
                $image_id = $product->get_image_id();
                if ( $image_id ) {
                    $url = wp_get_attachment_image_url( $image_id, 'full' );
                    if ( $url ) {
                        $image_url = $url;
                        break;
                    }
                }
            }
        }

        $result_message_id = $this->send_telegram_message( $message, '', null, $image_url );

        if ( $result_message_id ) {
            // Mark as notified to prevent duplicates
            $order->update_meta_data( $meta_key, 'yes' );
            if ( ! $order->get_meta('_telegram_first_message_id') ) {
                $order->update_meta_data( '_telegram_first_message_id', $result_message_id );
            }
            $order->save();
        }

        return $result_message_id ? true : false;
    }

    /**
     * Build order notification message
     */
    public function build_order_message( $order, $event_title = '' ) {
        $settings = $this->get_settings();
        
        // Use custom template if set
        $custom_template = trim( $settings['message_template'] );
        if ( ! empty( $custom_template ) ) {
            return $this->parse_message_template( $custom_template, $order, $event_title );
        }

        // Default message format (Minimalist)
        $currency  = $order->get_currency();
        $total     = $order->get_total();
        $date      = $order->get_date_created()->date( 'Y-m-d H:i:s' );

        $product_names = array();
        foreach ( $order->get_items() as $item ) {
            $product_names[] = $item->get_name() . " (x" . $item->get_quantity() . ")";
            
            // Optionally append variation details
            $meta_data = $item->get_formatted_meta_data( '', true );
            if ( ! empty( $meta_data ) ) {
                $meta_strings = array();
                foreach ( $meta_data as $meta ) {
                    $meta_strings[] = "{$meta->display_key}: {$meta->display_value}";
                }
                $product_names[] = "   _" . implode(', ', $meta_strings) . "_";
            }
        }
        $titles = implode("\n", $product_names);

        $lines = array();
        if ( ! empty( $event_title ) ) {
            $lines[] = $event_title;
        }
        $lines[] = "📅 *Date:* {$date}";
        $lines[] = "📦 *Title:*\n{$titles}";
        $lines[] = "💰 *Total:* {$currency} {$total}";

        return implode( "\n", $lines );
    }

    /**
     * Parse custom message template with order variables
     */
    public function parse_message_template( $template, $order, $event_title = '' ) {
        $replacements = array(
            '{event}'          => $event_title,
            '{site_name}'      => get_bloginfo( 'name' ),
            '{order_id}'       => $order->get_order_number(),
            '{order_total}'    => $order->get_total(),
            '{currency}'       => $order->get_currency(),
            '{status}'         => ucfirst( $order->get_status() ),
            '{payment_method}' => $order->get_payment_method_title(),
            '{date}'           => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
            '{customer_name}'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            '{customer_email}' => $order->get_billing_email(),
            '{customer_phone}' => $order->get_billing_phone(),
            '{shipping_method}'=> $order->get_shipping_method(),
            '{order_url}'      => admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ),
        );

        // Product list
        $product_lines = array();
        foreach ( $order->get_items() as $item ) {
            $product_lines[] = $item->get_name() . ' x' . $item->get_quantity() . ' — ' . $order->get_currency() . ' ' . $item->get_total();
        }
        $replacements['{products}'] = implode( "\n", $product_lines );

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
    }

    // =========================================================================
    // TELEGRAM API
    // =========================================================================

    /**
     * Send message to Telegram
     */
    public function send_telegram_message( $message, $chat_id = '', $reply_to_message_id = null, $image_url = '' ) {
        $settings = $this->get_settings();
        $bot_token = $settings['bot_token'];
        
        if ( empty( $chat_id ) ) {
            $chat_id = $settings['chat_id'];
        }

        if ( empty( $bot_token ) || empty( $chat_id ) ) {
            return false;
        }

        $endpoint = '/sendMessage';
        $body = array(
            'chat_id'    => $chat_id,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true,
        );
        
        if ( ! empty( $image_url ) ) {
            $endpoint = '/sendPhoto';
            $body['photo'] = $image_url;
            $body['caption'] = $message;
        } else {
            $body['text'] = $message;
        }
        
        if ( $reply_to_message_id ) {
            $body['reply_to_message_id'] = $reply_to_message_id;
        }

        $url = self::API_URL . $bot_token . $endpoint;

        $response = wp_remote_post( $url, array(
            'timeout' => 15,
            'body'    => $body,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'POD Telegram: Failed to send message - ' . $response->get_error_message() );
            return false;
        }

        $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $response_body['ok'] ) ) {
            $error_desc = isset( $response_body['description'] ) ? $response_body['description'] : 'Unknown error';
            error_log( 'POD Telegram: API error - ' . $error_desc );
            return false;
        }

        return isset( $response_body['result']['message_id'] ) ? $response_body['result']['message_id'] : true;
    }

    /**
     * Get bot info (for testing connection)
     */
    public function get_bot_info() {
        $settings = $this->get_settings();
        $bot_token = $settings['bot_token'];

        if ( empty( $bot_token ) ) {
            return false;
        }

        $url = self::API_URL . $bot_token . '/getMe';
        $response = wp_remote_get( $url, array( 'timeout' => 10 ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    // =========================================================================
    // REST API
    // =========================================================================

    /**
     * Register REST routes for testing
     */
    public function register_rest_routes() {
        register_rest_route( 'pod-ai/v1', '/telegram/test', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'rest_test_notification' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ) );

        register_rest_route( 'pod-ai/v1', '/telegram/bot-info', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'rest_get_bot_info' ),
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ) );
    }

    /**
     * REST: Send test notification
     */
    public function rest_test_notification( $request ) {
        if ( ! $this->is_enabled() ) {
            return new WP_Error( 'not_configured', 'Telegram notifications are not configured or disabled.', array( 'status' => 400 ) );
        }

        $site_name = get_bloginfo( 'name' );
        $test_message = "🔔 *Test Notification*\n";
        $test_message .= "━━━━━━━━━━━━━━━\n";
        $test_message .= "🏪 *{$site_name}*\n\n";
        $test_message .= "✅ Telegram notifications are working correctly!\n";
        $test_message .= "📅 " . current_time( 'Y-m-d H:i:s' ) . "\n\n";
        $test_message .= "This is a test message from POD AI Connector.";

        $result = $this->send_telegram_message( $test_message );

        if ( $result ) {
            return rest_ensure_response( array(
                'success' => true,
                'message' => 'Test notification sent successfully!',
            ) );
        }

        return new WP_Error( 'send_failed', 'Failed to send test notification. Check your Bot Token and Chat ID.', array( 'status' => 500 ) );
    }

    /**
     * REST: Get bot info
     */
    public function rest_get_bot_info( $request ) {
        $info = $this->get_bot_info();

        if ( ! $info ) {
            return new WP_Error( 'bot_error', 'Could not retrieve bot info. Check your Bot Token.', array( 'status' => 400 ) );
        }

        return rest_ensure_response( $info );
    }

    // =========================================================================
    // ADMIN SETTINGS
    // =========================================================================

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'pod-ai-connector',
            'Telegram Notifications',
            '📱 Telegram Notify',
            'manage_options',
            'pod-telegram-notify',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'pod_telegram_settings', 'pod_telegram_enabled' );
        register_setting( 'pod_telegram_settings', 'pod_telegram_bot_token' );
        register_setting( 'pod_telegram_settings', 'pod_telegram_chat_id' );
        register_setting( 'pod_telegram_settings', 'pod_telegram_notify_processing' );
        register_setting( 'pod_telegram_settings', 'pod_telegram_notify_completed' );
        register_setting( 'pod_telegram_settings', 'pod_telegram_notify_on_hold' );
        register_setting( 'pod_telegram_settings', 'pod_telegram_notify_payment' );
        register_setting( 'pod_telegram_settings', 'pod_telegram_message_template' );
        register_setting( 'pod_telegram_settings', 'pod_telegram_include_products' );
        register_setting( 'pod_telegram_settings', 'pod_telegram_include_customer' );
        register_setting( 'pod_telegram_settings', 'pod_telegram_include_address' );
    }

    /**
     * Render admin settings page
     */
    public function render_admin_page() {
        $settings = $this->get_settings();
        $is_configured = ! empty( $settings['bot_token'] ) && ! empty( $settings['chat_id'] );
        ?>
        <div class="wrap">
            <h1>📱 Telegram Sales Notifications</h1>
            <p>Receive real-time notifications on Telegram when orders are placed on your store.</p>

            <?php if ( ! $is_configured ) : ?>
            <div class="notice notice-info" style="padding: 15px;">
                <h3 style="margin-top: 0;">📋 How to set up Telegram Bot</h3>
                <ol>
                    <li>Open Telegram and search for <strong>@BotFather</strong></li>
                    <li>Send <code>/newbot</code> to create a new bot</li>
                    <li>Follow the prompts to name your bot</li>
                    <li>Copy the <strong>Bot Token</strong> and paste it below</li>
                    <li>Create a channel/group and add your bot as an admin</li>
                    <li>To get the <strong>Chat ID</strong>:
                        <ul style="list-style: disc; margin-left: 20px;">
                            <li>For <strong>channels</strong>: Forward a message from the channel to <strong>@userinfobot</strong>, or use the channel's username as <code>@your_channel_name</code></li>
                            <li>For <strong>groups</strong>: Add <strong>@RawDataBot</strong> to the group to get the Chat ID (format: <code>-100xxxxxxxxxx</code>)</li>
                            <li>For <strong>personal chat</strong>: Send any message to <strong>@userinfobot</strong></li>
                        </ul>
                    </li>
                </ol>
            </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields( 'pod_telegram_settings' ); ?>

                <!-- Connection Settings -->
                <h2 class="title">🔌 Connection Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Notifications</th>
                        <td>
                            <label>
                                <input type="checkbox" name="pod_telegram_enabled" value="1" <?php checked( $settings['enabled'], '1' ); ?> />
                                Send Telegram notifications when orders are placed
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Bot Token <span style="color:red;">*</span></th>
                        <td>
                            <input type="text" name="pod_telegram_bot_token" 
                                   value="<?php echo esc_attr( $settings['bot_token'] ); ?>" 
                                   class="regular-text" 
                                   placeholder="123456789:ABCDefGhIJklMNOpqRsTUVwxyz"
                                   style="width: 450px;" />
                            <p class="description">Get this from <strong>@BotFather</strong> on Telegram</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Chat ID <span style="color:red;">*</span></th>
                        <td>
                            <input type="text" name="pod_telegram_chat_id" 
                                   value="<?php echo esc_attr( $settings['chat_id'] ); ?>" 
                                   class="regular-text" 
                                   placeholder="-100xxxxxxxxxx or @channel_name"
                                   style="width: 350px;" />
                            <p class="description">Channel username (e.g., <code>@my_channel</code>) or numeric Chat ID</p>
                        </td>
                    </tr>
                </table>

                <!-- Notification Events -->
                <h2 class="title">🔔 Notification Events</h2>
                <p>Choose which order events trigger a Telegram notification:</p>
                <table class="form-table">
                    <tr>
                        <th scope="row">Order Processing</th>
                        <td>
                            <label>
                                <input type="checkbox" name="pod_telegram_notify_processing" value="1" <?php checked( $settings['notify_processing'], '1' ); ?> />
                                Notify when order status changes to <strong>Processing</strong> (payment received)
                            </label>
                            <p class="description">⭐ Recommended — This is the most common trigger for new sales</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Order Completed</th>
                        <td>
                            <label>
                                <input type="checkbox" name="pod_telegram_notify_completed" value="1" <?php checked( $settings['notify_completed'], '1' ); ?> />
                                Notify when order status changes to <strong>Completed</strong>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Order On Hold</th>
                        <td>
                            <label>
                                <input type="checkbox" name="pod_telegram_notify_on_hold" value="1" <?php checked( $settings['notify_on_hold'], '1' ); ?> />
                                Notify when order status changes to <strong>On Hold</strong> (awaiting payment)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Payment Complete</th>
                        <td>
                            <label>
                                <input type="checkbox" name="pod_telegram_notify_payment" value="1" <?php checked( $settings['notify_payment'], '1' ); ?> />
                                Notify when payment is completed
                            </label>
                            <p class="description">⚠️ May cause duplicate notifications with "Order Processing" — typically only enable one</p>
                        </td>
                    </tr>
                </table>

                <!-- Message Content -->
                <h2 class="title">📝 Message Content</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Include Products</th>
                        <td>
                            <label>
                                <input type="checkbox" name="pod_telegram_include_products" value="1" <?php checked( $settings['include_products'], '1' ); ?> />
                                Show product details (name, quantity, price)
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Include Customer Info</th>
                        <td>
                            <label>
                                <input type="checkbox" name="pod_telegram_include_customer" value="1" <?php checked( $settings['include_customer'], '1' ); ?> />
                                Show customer name, email, phone
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Include Shipping Address</th>
                        <td>
                            <label>
                                <input type="checkbox" name="pod_telegram_include_address" value="1" <?php checked( $settings['include_address'], '1' ); ?> />
                                Show shipping address in notification
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Custom Message Template</th>
                        <td>
                            <textarea name="pod_telegram_message_template" rows="10" cols="60" 
                                      class="large-text code"
                                      placeholder="Leave empty to use default template"><?php echo esc_textarea( $settings['message_template'] ); ?></textarea>
                            <p class="description">
                                Available variables: 
                                <code>{event}</code>, <code>{site_name}</code>, <code>{order_id}</code>, 
                                <code>{order_total}</code>, <code>{currency}</code>, <code>{status}</code>, 
                                <code>{payment_method}</code>, <code>{date}</code>, <code>{customer_name}</code>, 
                                <code>{customer_email}</code>, <code>{customer_phone}</code>, <code>{products}</code>,
                                <code>{shipping_method}</code>, <code>{order_url}</code>
                            </p>
                            <p class="description">
                                Example: <code>{event} | Order #{order_id} | {currency} {order_total} | {customer_name}</code>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button( 'Save Settings' ); ?>
            </form>

            <!-- Test Section -->
            <?php if ( $is_configured ) : ?>
            <hr />
            <h2 class="title">🧪 Test Notification</h2>
            <p>Send a test message to verify your Telegram bot and chat configuration.</p>
            <button type="button" id="pod-telegram-test-btn" class="button button-secondary" style="font-size: 14px; padding: 5px 20px;">
                📤 Send Test Notification
            </button>
            <span id="pod-telegram-test-result" style="margin-left: 15px; font-weight: bold;"></span>

            <script>
            document.getElementById('pod-telegram-test-btn').addEventListener('click', function() {
                var btn = this;
                var result = document.getElementById('pod-telegram-test-result');
                
                btn.disabled = true;
                btn.textContent = '⏳ Sending...';
                result.textContent = '';
                result.style.color = '';

                fetch('<?php echo esc_url( rest_url( 'pod-ai/v1/telegram/test' ) ); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo wp_create_nonce( 'wp_rest' ); ?>'
                    }
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    btn.disabled = false;
                    btn.textContent = '📤 Send Test Notification';
                    
                    if (data.success) {
                        result.textContent = '✅ ' + data.message;
                        result.style.color = 'green';
                    } else {
                        result.textContent = '❌ ' + (data.message || 'Failed to send notification');
                        result.style.color = 'red';
                    }
                })
                .catch(function(err) {
                    btn.disabled = false;
                    btn.textContent = '📤 Send Test Notification';
                    result.textContent = '❌ Error: ' + err.message;
                    result.style.color = 'red';
                });
            });
            </script>
            <?php endif; ?>

            <!-- Preview Section -->
            <hr />
            <h2 class="title">👁 Message Preview</h2>
            <div style="background: #1a1a2e; color: #eee; padding: 20px; border-radius: 10px; max-width: 500px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; line-height: 1.6;">
                <div style="color: #4fc3f7;">🛒 New Order (Processing)</div>
                <div style="color: #666;">━━━━━━━━━━━━━━━</div>
                <div>🏪 <strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong></div>
                <br/>
                <div>📋 <strong>Order:</strong> #1234</div>
                <div>📅 <strong>Date:</strong> <?php echo current_time( 'Y-m-d H:i:s' ); ?></div>
                <div>💳 <strong>Payment:</strong> PayPal</div>
                <div>📊 <strong>Status:</strong> Processing</div>
                <div>💰 <strong>Total:</strong> USD 49.99</div>
                <br/>
                <div>📦 <strong>Products:</strong></div>
                <div style="margin-left: 10px;">• Custom T-Shirt Design x1 — USD 29.99</div>
                <div style="margin-left: 10px;">• Custom Mug x1 — USD 20.00</div>
                <br/>
                <div>👤 <strong>Customer:</strong></div>
                <div style="margin-left: 10px;">Name: John Doe</div>
                <div style="margin-left: 10px;">Email: john@example.com</div>
                <br/>
                <div style="color: #666;">━━━━━━━━━━━━━━━</div>
                <div>🔗 <a href="#" style="color: #4fc3f7; text-decoration: none;">View Order</a></div>
            </div>
        </div>
        <?php
    }
}

// Initialize
new POD_Telegram_Notify();
