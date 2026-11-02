<?php
/**
 * POD Dynamic Option Forms — Standalone WP-Admin Visual Builder & Product Data Tab
 *
 * Allows any WooCommerce store (even without POD AI Hub connection) to create,
 * customize, import from WCPA, map to Product Categories, and convert heavy
 * Variable products to lightweight Simple products.
 *
 * @package Store_AI_Connector
 * @since   2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'POD_Option_Forms_Admin', false ) ) :

class POD_Option_Forms_Admin {

    private static $initialized = false;

    public static function init() {
        if ( self::$initialized ) {
            return;
        }
        self::$initialized = true;
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_menus' ), 25 );
        add_action( 'admin_post_pod_of_save_form', array( __CLASS__, 'handle_save_form' ) );
        add_action( 'admin_post_pod_of_quick_action', array( __CLASS__, 'handle_quick_action' ) );

        // WooCommerce Product Data Tab ("POD Virtual Options")
        add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_data_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_product_data_panel' ) );
        add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_data_panel' ) );
    }

    public static function register_admin_menus() {
        global $submenu;
        $already_registered = false;
        if ( isset( $submenu['pod-ai-connector'] ) && is_array( $submenu['pod-ai-connector'] ) ) {
            foreach ( $submenu['pod-ai-connector'] as $item ) {
                if ( isset( $item[2] ) && $item[2] === 'pod-option-forms' ) {
                    $already_registered = true;
                    break;
                }
            }
        }
        if ( ! $already_registered ) {
            add_submenu_page(
                'pod-ai-connector',
                'Variant Ảo & Custom Options',
                '⚡ Variant Ảo (Options)',
                'manage_options',
                'pod-option-forms',
                array( __CLASS__, 'render_admin_page' )
            );
        }

        if ( class_exists( 'WooCommerce' ) ) {
            add_submenu_page(
                'edit.php?post_type=product',
                'POD Virtual Option Forms',
                '⚡ Variant Ảo (POD)',
                'manage_options',
                'pod-option-forms-wc',
                array( __CLASS__, 'render_admin_page' )
            );
        }
    }

    /**
     * Handle quick actions: delete, toggle, load_presets, import_wcpa, convert_all_variable
     */
    public static function handle_quick_action() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'pod_of_quick_action_nonce' );

        $act      = isset( $_REQUEST['pod_action'] ) ? sanitize_key( $_REQUEST['pod_action'] ) : '';
        $redirect = admin_url( 'admin.php?page=pod-option-forms' );

        if ( $act === 'delete' && ! empty( $_REQUEST['form_id'] ) ) {
            POD_Option_Forms::delete_form( sanitize_key( $_REQUEST['form_id'] ) );
            $redirect = add_query_arg( 'pod_msg', 'deleted', $redirect );

        } elseif ( $act === 'load_presets' ) {
            foreach ( POD_Option_Forms::get_starter_presets() as $preset ) {
                POD_Option_Forms::upsert_form( $preset );
            }
            $redirect = add_query_arg( 'pod_msg', 'presets_loaded', $redirect );

        } elseif ( $act === 'import_wcpa' ) {
            $count    = POD_Option_Forms::import_from_wcpa();
            $redirect = add_query_arg( array( 'pod_msg' => 'wcpa_imported', 'count' => $count ), $redirect );

        } elseif ( $act === 'convert_all_variable' && function_exists( 'wc_get_products' ) ) {
            $var_ids = wc_get_products( array(
                'type'   => 'variable',
                'limit'  => 250,
                'return' => 'ids',
                'status' => array( 'publish', 'draft', 'private' ),
            ) );
            $converted = 0;
            $deleted_v = 0;
            foreach ( $var_ids as $pid ) {
                $res = POD_Option_Forms::convert_variable_product_to_simple( intval( $pid ) );
                if ( ! empty( $res['success'] ) ) {
                    $converted++;
                    $deleted_v += intval( $res['deleted_variations'] ?? 0 );
                }
            }
            $redirect = add_query_arg( array(
                'pod_msg'   => 'converted_all',
                'converted' => $converted,
                'deleted_v' => $deleted_v,
            ), $redirect );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Save Visual Form Builder JSON payload
     */
    public static function handle_save_form() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'pod_of_save_form_nonce' );

        $raw_json = isset( $_POST['form_payload_json'] ) ? wp_unslash( $_POST['form_payload_json'] ) : '';
        $decoded  = json_decode( $raw_json, true );
        if ( ! is_array( $decoded ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=pod-option-forms&pod_msg=invalid_json' ) );
            exit;
        }

        $saved = POD_Option_Forms::upsert_form( $decoded );
        wp_safe_redirect( admin_url( 'admin.php?page=pod-option-forms&edit_form=' . urlencode( $saved['id'] ) . '&pod_msg=saved' ) );
        exit;
    }

    /**
     * Render Standalone WP-Admin Page (List View + Visual Form Builder)
     */
    public static function render_admin_page() {
        $forms     = POD_Option_Forms::get_forms();
        $edit_id   = isset( $_GET['edit_form'] ) ? sanitize_key( $_GET['edit_form'] ) : '';
        $is_new    = isset( $_GET['new_form'] );
        $msg       = isset( $_GET['pod_msg'] ) ? sanitize_key( $_GET['pod_msg'] ) : '';
        $svg_icons = array_keys( POD_Option_Forms::get_svg_library() );

        $wc_cats = array();
        if ( taxonomy_exists( 'product_cat' ) ) {
            $terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $t ) {
                    $wc_cats[] = array( 'slug' => $t->slug, 'name' => $t->name, 'count' => $t->count );
                }
            }
        }

        $editing_form = null;
        if ( $is_new ) {
            $editing_form = array(
                'id'                 => 'form_' . strtolower( wp_generate_password( 6, false, false ) ),
                'name'               => 'New Product Option Form',
                'description'        => '',
                'is_active'          => true,
                'priority'           => 20,
                'category_slugs'     => array(),
                'tag_slugs'          => array(),
                'show_price_summary' => true,
                'enable_gmc_schema'  => true,
                'fields'             => array(),
            );
        } elseif ( $edit_id ) {
            foreach ( $forms as $f ) {
                if ( ( $f['id'] ?? '' ) === $edit_id ) {
                    $editing_form = $f;
                    break;
                }
            }
        }
        ?>
        <div class="wrap" style="max-width:1180px;">
            <h1 style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <span>⚡ POD Dynamic Option Forms &amp; Virtual Variants</span>
                <span style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=pod-option-forms&new_form=1' ) ); ?>" class="button button-primary">+ Create New Option Form</a>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pod_of_quick_action&pod_action=load_presets' ), 'pod_of_quick_action_nonce' ) ); ?>" class="button">Restore 4 Starter Presets</a>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pod_of_quick_action&pod_action=import_wcpa' ), 'pod_of_quick_action_nonce' ) ); ?>" class="button">Import from WCPA</a>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=pod_of_quick_action&pod_action=convert_all_variable' ), 'pod_of_quick_action_nonce' ) ); ?>"
                       class="button"
                       onclick="return confirm('Convert all heavy Variable Products on this store into lightweight Simple Products (deletes child product_variation rows) so Virtual Option Forms take over?');">
                        ⚡ Convert Store Variable Products to Lightweight Simple
                    </a>
                </span>
            </h1>

            <?php if ( function_exists( 'sac_render_top_nav_tabs' ) ) : ?>
                <?php sac_render_top_nav_tabs( 'virtual-variants' ); ?>
            <?php endif; ?>

            <p class="description" style="font-size:13.5px;margin-bottom:16px;">
                Sell multiple styles (T-Shirt, V-neck, Long Sleeve, Hoodie, Sweatshirt, Tank Top, Custom Jerseys, Mugs, Tumblers), sizes, colors, and personalizations on a single <strong>Simple Product</strong> with <strong>0 extra rows in <code>wp_posts</code></strong>. Works 100% standalone or synced with POD AI Hub.
            </p>

            <?php if ( $msg === 'saved' ) : ?>
                <div class="notice notice-success is-dismissible"><p><strong>Option Form saved!</strong> Storefront changes are live immediately.</p></div>
            <?php elseif ( $msg === 'deleted' ) : ?>
                <div class="notice notice-info is-dismissible"><p>Option Form deleted.</p></div>
            <?php elseif ( $msg === 'presets_loaded' ) : ?>
                <div class="notice notice-success is-dismissible"><p>4 built-in Starter Presets (Apparel 6-Styles, Custom Jersey, Ceramic Mug, Insulated Tumbler) restored!</p></div>
            <?php elseif ( $msg === 'wcpa_imported' ) : ?>
                <div class="notice notice-success is-dismissible"><p>Imported <strong><?php echo intval( $_GET['count'] ?? 0 ); ?></strong> form(s) from WCPA.</p></div>
            <?php elseif ( $msg === 'converted_all' ) : ?>
                <div class="notice notice-success is-dismissible"><p>Converted <strong><?php echo intval( $_GET['converted'] ?? 0 ); ?></strong> Variable product(s) to lightweight Simple products and cleaned up <strong><?php echo intval( $_GET['deleted_v'] ?? 0 ); ?></strong> heavy <code>product_variation</code> DB rows!</p></div>
            <?php endif; ?>

            <?php if ( $editing_form ) : ?>
                <?php self::render_visual_builder( $editing_form, $wc_cats, $svg_icons ); ?>
            <?php else : ?>
                <div style="background:#fff;border:1px solid #cbd5e1;border-radius:10px;overflow:hidden;margin-top:14px;">
                    <table class="wp-list-table widefat striped" style="border:none;">
                        <thead>
                            <tr>
                                <th style="width:28%;">Form Name &amp; ID</th>
                                <th style="width:10%;">Status</th>
                                <th style="width:26%;">Assigned Categories (Auto-Match)</th>
                                <th style="width:20%;">Fields Summary</th>
                                <th style="width:16%;text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $forms ) ) : ?>
                                <tr><td colspan="5" style="padding:20px;text-align:center;">No Option Forms found. Click <strong>Restore 4 Starter Presets</strong> above to start.</td></tr>
                            <?php else : ?>
                                <?php foreach ( $forms as $f ) :
                                    $fid     = $f['id'] ?? '';
                                    $edit_u  = admin_url( 'admin.php?page=pod-option-forms&edit_form=' . urlencode( $fid ) );
                                    $del_u   = wp_nonce_url( admin_url( 'admin-post.php?action=pod_of_quick_action&pod_action=delete&form_id=' . urlencode( $fid ) ), 'pod_of_quick_action_nonce' );
                                    $f_cats  = $f['category_slugs'] ?? array();
                                    $f_flds  = $f['fields'] ?? array();
                                ?>
                                <tr>
                                    <td>
                                        <strong><a href="<?php echo esc_url( $edit_u ); ?>" style="font-size:14px;"><?php echo esc_html( $f['name'] ?? $fid ); ?></a></strong>
                                        <div style="font-size:11.5px;color:#64748b;margin-top:3px;">ID: <code><?php echo esc_html( $fid ); ?></code> &middot; Priority: <?php echo intval( $f['priority'] ?? 20 ); ?></div>
                                        <?php if ( ! empty( $f['description'] ) ) : ?>
                                            <div style="font-size:12px;color:#475569;margin-top:4px;"><?php echo esc_html( $f['description'] ); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( ! empty( $f['is_active'] ) ) : ?>
                                            <span style="display:inline-block;padding:2px 9px;border-radius:99px;background:#dcfce7;color:#166534;font-weight:700;font-size:11.5px;">Active</span>
                                        <?php else : ?>
                                            <span style="display:inline-block;padding:2px 9px;border-radius:99px;background:#f1f5f9;color:#475569;font-weight:600;font-size:11.5px;">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ( empty( $f_cats ) ) : ?>
                                            <span style="color:#94a3b8;font-size:12px;">Manual per-product only</span>
                                        <?php else : ?>
                                            <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                                <?php foreach ( $f_cats as $cs ) : ?>
                                                    <span style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:4px;padding:1px 7px;font-size:11.5px;font-weight:600;"><?php echo esc_html( $cs ); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size:12px;color:#334155;">
                                            <strong><?php echo count( $f_flds ); ?> field(s):</strong>
                                            <?php
                                            $lbls = array_map( function( $fl ) { return $fl['label'] ?? $fl['id'] ?? ''; }, array_slice( $f_flds, 0, 5 ) );
                                            echo esc_html( implode( ', ', array_filter( $lbls ) ) );
                                            if ( count( $f_flds ) > 5 ) echo '...';
                                            ?>
                                        </div>
                                    </td>
                                    <td style="text-align:right;">
                                        <a href="<?php echo esc_url( $edit_u ); ?>" class="button button-small button-primary">Edit Builder</a>
                                        <a href="<?php echo esc_url( $del_u ); ?>" class="button button-small" style="color:#dc2626;border-color:#fca5a5;" onclick="return confirm('Delete this Option Form?');">Delete</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Interactive JS Visual Form Builder in WP-Admin
     */
    private static function render_visual_builder( array $form, array $wc_cats, array $svg_icons ) {
        ?>
        <div style="margin:12px 0;">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=pod-option-forms' ) ); ?>" class="button">&larr; Back to All Option Forms</a>
        </div>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="pod-of-builder-form">
            <input type="hidden" name="action" value="pod_of_save_form" />
            <?php wp_nonce_field( 'pod_of_save_form_nonce' ); ?>
            <input type="hidden" name="form_payload_json" id="pod-of-payload-input" value="" />

            <div style="background:#fff;border:1px solid #cbd5e1;border-radius:10px;padding:20px;margin-bottom:18px;">
                <h2 style="margin-top:0;">1. Form Settings &amp; Category Assignment</h2>
                <div style="display:grid;grid-template-columns:2fr 1fr 120px;gap:14px;margin-bottom:14px;">
                    <div>
                        <label style="font-weight:700;display:block;margin-bottom:4px;">Form Name</label>
                        <input type="text" id="b-name" class="regular-text" style="width:100%;" value="<?php echo esc_attr( $form['name'] ?? '' ); ?>" required />
                    </div>
                    <div>
                        <label style="font-weight:700;display:block;margin-bottom:4px;">Form ID (Slug)</label>
                        <input type="text" id="b-id" class="regular-text" style="width:100%;" value="<?php echo esc_attr( $form['id'] ?? '' ); ?>" />
                    </div>
                    <div>
                        <label style="font-weight:700;display:block;margin-bottom:4px;">Priority</label>
                        <input type="number" id="b-priority" style="width:100%;" value="<?php echo intval( $form['priority'] ?? 20 ); ?>" />
                    </div>
                </div>

                <div style="margin-bottom:14px;">
                    <label style="font-weight:700;display:block;margin-bottom:4px;">Description</label>
                    <input type="text" id="b-desc" style="width:100%;" value="<?php echo esc_attr( $form['description'] ?? '' ); ?>" />
                </div>

                <div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:16px;">
                    <label><input type="checkbox" id="b-active" <?php checked( ! empty( $form['is_active'] ) ); ?> /> <strong>Active on Storefront</strong></label>
                    <label><input type="checkbox" id="b-summary" <?php checked( ! empty( $form['show_price_summary'] ) ); ?> /> Show <code>Selections will add $X.XX to the base price</code> box</label>
                    <label><input type="checkbox" id="b-gmc" <?php checked( ! isset( $form['enable_gmc_schema'] ) || ! empty( $form['enable_gmc_schema'] ) ); ?> /> Output Google Merchant Center <code>ProductGroup</code> JSON-LD Schema</label>
                </div>

                <div>
                    <label style="font-weight:700;display:block;margin-bottom:6px;">Auto-Apply to WooCommerce Category Slugs (comma-separated or click store categories below):</label>
                    <input type="text" id="b-cats" style="width:100%;margin-bottom:8px;" value="<?php echo esc_attr( implode( ', ', $form['category_slugs'] ?? array() ) ); ?>" placeholder="e.g. t-shirts, apparel, hoodies" />
                    <?php if ( ! empty( $wc_cats ) ) : ?>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;">
                            <?php foreach ( $wc_cats as $cat ) : ?>
                                <button type="button" class="button button-small pod-cat-chip" data-slug="<?php echo esc_attr( $cat['slug'] ); ?>">
                                    + <?php echo esc_html( $cat['name'] ); ?> (<code><?php echo esc_html( $cat['slug'] ); ?></code>)
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="background:#fff;border:1px solid #cbd5e1;border-radius:10px;padding:20px;margin-bottom:18px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <h2 style="margin:0;">2. Custom Fields &amp; Virtual Variant Swatches</h2>
                    <button type="button" class="button button-primary" id="pod-add-field-btn">+ Add Field</button>
                </div>
                <div id="pod-fields-mount"></div>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary button-hero">💾 Save Option Form</button>
            </p>
        </form>

        <script>
        (function() {
            var state = <?php echo wp_json_encode( $form ); ?>;
            var svgIcons = <?php echo wp_json_encode( $svg_icons ); ?>;
            state.fields = Array.isArray(state.fields) ? state.fields : [];

            var mount = document.getElementById('pod-fields-mount');

            function esc(s) {
                return String(s == null ? '' : s)
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            }

            function renderFields() {
                if (!state.fields.length) {
                    mount.innerHTML = '<div style="padding:18px;border:1px dashed #cbd5e1;border-radius:8px;text-align:center;color:#64748b;">No fields yet. Click <strong>+ Add Field</strong> to add Buttons, Style SVG Cards, Color Pills, Dropdowns, Text Inputs, or Logo Upload.</div>';
                    return;
                }
                var html = '';
                state.fields.forEach(function(f, fIdx) {
                    var hasOpts = ['button_group', 'visual_card_group', 'color_swatch_group', 'dropdown'].indexOf(f.type) !== -1;
                    var cond = f.condition || { field_id: '', operator: 'neq', value: '' };
                    var pop = f.helper_popup || { enabled: false, label: 'Size Guide', position: 'inline_label', content: '' };

                    html += '<div style="border:1px solid #cbd5e1;border-radius:8px;padding:14px;margin-bottom:14px;background:#f8fafc;">';
                    html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">';
                    html += '<strong>#' + (fIdx + 1) + ' — ' + esc(f.label || f.id) + ' <code style="margin-left:6px;">' + esc(f.type) + '</code></strong>';
                    html += '<div style="display:flex;gap:6px;">';
                    if (fIdx > 0) html += '<button type="button" class="button button-small" data-fmove="-1" data-fidx="' + fIdx + '">&uarr;</button>';
                    if (fIdx < state.fields.length - 1) html += '<button type="button" class="button button-small" data-fmove="1" data-fidx="' + fIdx + '">&darr;</button>';
                    html += '<button type="button" class="button button-small" style="color:#dc2626;" data-fdel="' + fIdx + '">Remove Field</button>';
                    html += '</div></div>';

                    html += '<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin-bottom:10px;">';
                    html += '<div><label style="font-size:12px;font-weight:600;">Field Label</label><input type="text" style="width:100%;" data-fk="label" data-fidx="' + fIdx + '" value="' + esc(f.label) + '"/></div>';
                    html += '<div><label style="font-size:12px;font-weight:600;">Field ID</label><input type="text" style="width:100%;" data-fk="id" data-fidx="' + fIdx + '" value="' + esc(f.id) + '"/></div>';
                    html += '<div><label style="font-size:12px;font-weight:600;">Field Type</label><select style="width:100%;" data-fk="type" data-fidx="' + fIdx + '">';
                    [
                        ['button_group', 'Button Group (Fit / Size / Material)'],
                        ['visual_card_group', 'Visual SVG/Image Cards (6 Apparel Styles)'],
                        ['color_swatch_group', 'Color Swatches (FashionOpenStudio Pills / Circles)'],
                        ['dropdown', 'Dropdown Select'],
                        ['text_input', 'Text Input (Personalization Name/Number)'],
                        ['number_input', 'Number Input'],
                        ['textarea', 'Textarea'],
                        ['image_upload', 'Image / Logo File Upload']
                    ].forEach(function(pair) {
                        html += '<option value="' + pair[0] + '"' + (f.type === pair[0] ? ' selected' : '') + '>' + pair[1] + '</option>';
                    });
                    html += '</select></div>';
                    html += '<div><label style="font-size:12px;font-weight:600;">Fulfillment Role</label><select style="width:100%;" data-fk="semantic_role" data-fidx="' + fIdx + '">';
                    [['', 'Custom / Addon'], ['fit', 'Fit / Gender'], ['style', 'Product Style'], ['size', 'Size / Capacity'], ['color', 'Color']].forEach(function(pair) {
                        html += '<option value="' + pair[0] + '"' + ((f.semantic_role || '') === pair[0] ? ' selected' : '') + '>' + pair[1] + '</option>';
                    });
                    html += '</select></div></div>';

                    html += '<div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin-bottom:10px;font-size:12.5px;">';
                    html += '<label><input type="checkbox" data-fbool="required" data-fidx="' + fIdx + '"' + (f.required ? ' checked' : '') + '/> Required (*)</label>';
                    if (f.type === 'button_group') {
                        html += '<label><input type="checkbox" data-fbool="show_check" data-fidx="' + fIdx + '"' + (f.show_check ? ' checked' : '') + '/> Checkmark icon</label>';
                        html += '<label>Button Theme: <select data-fk="button_theme" data-fidx="' + fIdx + '"><option value="outline_green"' + ((f.button_theme || 'outline_green') === 'outline_green' ? ' selected' : '') + '>Outline Green</option><option value="solid_dark"' + (f.button_theme === 'solid_dark' ? ' selected' : '') + '>Solid Black</option></select></label>';
                    }
                    if (f.type === 'color_swatch_group') {
                        html += '<label>Swatch Style: <select data-fk="swatch_style" data-fidx="' + fIdx + '"><option value="pill"' + ((f.swatch_style || 'pill') === 'pill' ? ' selected' : '') + '>Labeled Color Pill</option><option value="circle"' + (f.swatch_style === 'circle' ? ' selected' : '') + '>Round Circle</option></select></label>';
                    }
                    html += '<label>Show If Field ID: <input type="text" style="width:100px;" placeholder="e.g. material" data-fcond="field_id" data-fidx="' + fIdx + '" value="' + esc(cond.field_id) + '"/></label>';
                    html += '<select data-fcond="operator" data-fidx="' + fIdx + '"><option value="eq"' + (cond.operator === 'eq' ? ' selected' : '') + '>Equals (=)</option><option value="neq"' + (cond.operator !== 'eq' ? ' selected' : '') + '>Not Equals (!=)</option></select>';
                    html += '<input type="text" style="width:100px;" placeholder="value" data-fcond="value" data-fidx="' + fIdx + '" value="' + esc(cond.value) + '"/>';
                    html += '</div>';

                    // Helper Popup config
                    html += '<div style="margin-bottom:10px;padding:8px 10px;background:#fff;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;">';
                    html += '<label><input type="checkbox" data-fpop="enabled" data-fidx="' + fIdx + '"' + (pop.enabled ? ' checked' : '') + '/> <strong>Enable Helper Modal Popup</strong> (Size Guide / Comparing Methods)</label>';
                    if (pop.enabled) {
                        html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">';
                        html += '<input type="text" placeholder="Button Label (e.g. Size Guide)" data-fpop="label" data-fidx="' + fIdx + '" value="' + esc(pop.label) + '"/>';
                        html += '<select data-fpop="position" data-fidx="' + fIdx + '"><option value="inline_label"' + (pop.position !== 'below_field' ? ' selected' : '') + '>Inline next to Field Label</option><option value="below_field"' + (pop.position === 'below_field' ? ' selected' : '') + '>Button Below Field</option></select>';
                        html += '</div>';
                        html += '<textarea rows="3" style="width:100%;margin-top:6px;font-family:monospace;font-size:11.5px;" placeholder="Popup HTML content (table or text)" data-fpop="content" data-fidx="' + fIdx + '">' + esc(pop.content) + '</textarea>';
                    }
                    html += '</div>';

                    if (hasOpts) {
                        f.options = Array.isArray(f.options) ? f.options : [];
                        html += '<table class="widefat" style="background:#fff;border:1px solid #e2e8f0;"><thead><tr><th>Label</th><th>ID</th>';
                        if (f.type === 'visual_card_group') html += '<th>SVG Icon / Image URL</th>';
                        if (f.type === 'color_swatch_group') html += '<th>Hex Color</th>';
                        html += '<th>Price Mode</th><th>Amount ($)</th><th>SKU Prefix</th><th></th></tr></thead><tbody>';
                        f.options.forEach(function(o, oIdx) {
                            html += '<tr>';
                            html += '<td><input type="text" style="width:100%;" data-ok="label" data-fidx="' + fIdx + '" data-oidx="' + oIdx + '" value="' + esc(o.label) + '"/></td>';
                            html += '<td><input type="text" style="width:100%;" data-ok="id" data-fidx="' + fIdx + '" data-oidx="' + oIdx + '" value="' + esc(o.id) + '"/></td>';
                            if (f.type === 'visual_card_group') {
                                html += '<td><select data-ok="icon" data-fidx="' + fIdx + '" data-oidx="' + oIdx + '">';
                                svgIcons.forEach(function(ic) {
                                    html += '<option value="' + ic + '"' + ((o.icon || 't-shirt') === ic ? ' selected' : '') + '>' + ic + '</option>';
                                });
                                html += '</select></td>';
                            }
                            if (f.type === 'color_swatch_group') {
                                html += '<td><input type="color" data-ok="hex" data-fidx="' + fIdx + '" data-oidx="' + oIdx + '" value="' + esc(o.hex || '#111827') + '"/></td>';
                            }
                            html += '<td><select data-ok="price_mode" data-fidx="' + fIdx + '" data-oidx="' + oIdx + '">';
                            [['none', 'No Change'], ['add_fixed', '+ Add Fixed ($)'], ['override_base', 'Override Base ($)'], ['add_percent', '+ Add %']].forEach(function(pm) {
                                html += '<option value="' + pm[0] + '"' + ((o.price_mode || 'none') === pm[0] ? ' selected' : '') + '>' + pm[1] + '</option>';
                            });
                            html += '</select></td>';
                            html += '<td><input type="number" step="0.01" style="width:80px;" data-ok="price" data-fidx="' + fIdx + '" data-oidx="' + oIdx + '" value="' + Number(o.price || 0) + '"/></td>';
                            html += '<td><input type="text" style="width:80px;" data-ok="sku_prefix" data-fidx="' + fIdx + '" data-oidx="' + oIdx + '" value="' + esc(o.sku_prefix || '') + '"/></td>';
                            html += '<td><button type="button" class="button button-small" data-odel="' + oIdx + '" data-fidx="' + fIdx + '">&times;</button></td>';
                            html += '</tr>';
                        });
                        html += '</tbody></table>';
                        html += '<div style="margin-top:8px;"><button type="button" class="button button-small" data-oadd="' + fIdx + '">+ Add Option</button></div>';
                    }
                    html += '</div>';
                });
                mount.innerHTML = html;
            }

            mount.addEventListener('input', function(e) {
                var t = e.target;
                var fIdx = parseInt(t.getAttribute('data-fidx'), 10);
                if (isNaN(fIdx) || !state.fields[fIdx]) return;
                if (t.hasAttribute('data-fk')) {
                    state.fields[fIdx][t.getAttribute('data-fk')] = t.value;
                    if (t.getAttribute('data-fk') === 'type') renderFields();
                } else if (t.hasAttribute('data-fcond')) {
                    state.fields[fIdx].condition = state.fields[fIdx].condition || { field_id: '', operator: 'neq', value: '' };
                    state.fields[fIdx].condition[t.getAttribute('data-fcond')] = t.value;
                } else if (t.hasAttribute('data-fpop') && t.type !== 'checkbox') {
                    state.fields[fIdx].helper_popup = state.fields[fIdx].helper_popup || { enabled: true, label: 'Size Guide', position: 'inline_label', content: '' };
                    state.fields[fIdx].helper_popup[t.getAttribute('data-fpop')] = t.value;
                } else if (t.hasAttribute('data-ok')) {
                    var oIdx = parseInt(t.getAttribute('data-oidx'), 10);
                    var key = t.getAttribute('data-ok');
                    state.fields[fIdx].options[oIdx][key] = key === 'price' ? parseFloat(t.value || '0') : t.value;
                }
            });

            mount.addEventListener('change', function(e) {
                var t = e.target;
                var fIdx = parseInt(t.getAttribute('data-fidx'), 10);
                if (isNaN(fIdx) || !state.fields[fIdx]) return;
                if (t.hasAttribute('data-fbool')) {
                    state.fields[fIdx][t.getAttribute('data-fbool')] = t.checked;
                } else if (t.getAttribute('data-fpop') === 'enabled') {
                    state.fields[fIdx].helper_popup = state.fields[fIdx].helper_popup || { label: 'Size Guide', position: 'inline_label', content: '' };
                    state.fields[fIdx].helper_popup.enabled = t.checked;
                    renderFields();
                } else if (t.tagName === 'SELECT') {
                    t.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });

            mount.addEventListener('click', function(e) {
                var btn = e.target.closest('button');
                if (!btn) return;
                if (btn.hasAttribute('data-fdel')) {
                    state.fields.splice(parseInt(btn.getAttribute('data-fdel'), 10), 1);
                    renderFields();
                } else if (btn.hasAttribute('data-fmove')) {
                    var idx = parseInt(btn.getAttribute('data-fidx'), 10);
                    var dir = parseInt(btn.getAttribute('data-fmove'), 10);
                    var tmp = state.fields[idx];
                    state.fields[idx] = state.fields[idx + dir];
                    state.fields[idx + dir] = tmp;
                    renderFields();
                } else if (btn.hasAttribute('data-oadd')) {
                    var fi = parseInt(btn.getAttribute('data-oadd'), 10);
                    state.fields[fi].options = state.fields[fi].options || [];
                    var nextN = state.fields[fi].options.length + 1;
                    state.fields[fi].options.push({ id: 'opt-' + nextN, label: 'Option ' + nextN, price_mode: 'none', price: 0, hex: '#111827', icon: 't-shirt' });
                    renderFields();
                } else if (btn.hasAttribute('data-odel')) {
                    var fi2 = parseInt(btn.getAttribute('data-fidx'), 10);
                    var oi2 = parseInt(btn.getAttribute('data-odel'), 10);
                    state.fields[fi2].options.splice(oi2, 1);
                    renderFields();
                }
            });

            document.getElementById('pod-add-field-btn').addEventListener('click', function() {
                var n = state.fields.length + 1;
                state.fields.push({
                    id: 'custom_field_' + n,
                    type: 'button_group',
                    label: 'Option ' + n,
                    required: true,
                    button_theme: 'outline_green',
                    options: [
                        { id: 'standard', label: 'Standard', price_mode: 'none', price: 0 }
                    ]
                });
                renderFields();
            });

            document.querySelectorAll('.pod-cat-chip').forEach(function(chip) {
                chip.addEventListener('click', function() {
                    var slug = chip.getAttribute('data-slug');
                    var inp = document.getElementById('b-cats');
                    var list = inp.value.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
                    if (list.indexOf(slug) === -1) list.push(slug);
                    inp.value = list.join(', ');
                });
            });

            document.getElementById('pod-of-builder-form').addEventListener('submit', function() {
                state.name = document.getElementById('b-name').value;
                state.id = document.getElementById('b-id').value;
                state.priority = parseInt(document.getElementById('b-priority').value || '20', 10);
                state.description = document.getElementById('b-desc').value;
                state.is_active = document.getElementById('b-active').checked;
                state.show_price_summary = document.getElementById('b-summary').checked;
                state.enable_gmc_schema = document.getElementById('b-gmc').checked;
                state.category_slugs = document.getElementById('b-cats').value.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
                document.getElementById('pod-of-payload-input').value = JSON.stringify(state);
            });

            renderFields();
        })();
        </script>
        <?php
    }

    /**
     * Add "POD Virtual Options" tab inside WooCommerce Product Edit Screen
     */
    public static function add_product_data_tab( $tabs ) {
        $tabs['pod_virtual_options'] = array(
            'label'    => 'POD Virtual Options',
            'target'   => 'pod_virtual_options_panel',
            'class'    => array(),
            'priority' => 65,
        );
        return $tabs;
    }

    public static function render_product_data_panel() {
        global $post;
        $product_id = $post ? intval( $post->ID ) : 0;
        $forms      = POD_Option_Forms::get_forms();
        $selected   = get_post_meta( $product_id, POD_Option_Forms::PRODUCT_FORM_META, true );
        $excluded   = get_post_meta( $product_id, POD_Option_Forms::PRODUCT_EXCLUDE_KEY, true );
        ?>
        <div id="pod_virtual_options_panel" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label for="pod_option_form_id"><strong>Option Form Override</strong></label>
                    <select id="pod_option_form_id" name="pod_option_form_id" class="select short">
                        <option value="">— Auto-detect from Product Category —</option>
                        <?php foreach ( $forms as $f ) : ?>
                            <option value="<?php echo esc_attr( $f['id'] ); ?>" <?php selected( $selected, $f['id'] ); ?>>
                                <?php echo esc_html( $f['name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p class="form-field">
                    <label for="pod_exclude_option_forms">Disable Virtual Options</label>
                    <input type="checkbox" id="pod_exclude_option_forms" name="pod_exclude_option_forms" value="1" <?php checked( ! empty( $excluded ) ); ?> />
                    <span class="description">Do not display any POD Option Form on this product.</span>
                </p>
                <p class="form-field">
                    <label>Convert Variable &rarr; Simple</label>
                    <input type="checkbox" name="pod_convert_to_simple_now" value="1" />
                    <span class="description">Convert this product to a lightweight Simple Product on Save (removes child <code>product_variation</code> rows).</span>
                </p>
            </div>
        </div>
        <?php
    }

    public static function save_product_data_panel( $post_id ) {
        if ( isset( $_POST['pod_option_form_id'] ) ) {
            $val = sanitize_key( $_POST['pod_option_form_id'] );
            if ( $val ) {
                update_post_meta( $post_id, POD_Option_Forms::PRODUCT_FORM_META, $val );
            } else {
                delete_post_meta( $post_id, POD_Option_Forms::PRODUCT_FORM_META );
            }
        }
        if ( ! empty( $_POST['pod_exclude_option_forms'] ) ) {
            update_post_meta( $post_id, POD_Option_Forms::PRODUCT_EXCLUDE_KEY, '1' );
        } else {
            delete_post_meta( $post_id, POD_Option_Forms::PRODUCT_EXCLUDE_KEY );
        }
        if ( ! empty( $_POST['pod_convert_to_simple_now'] ) ) {
            POD_Option_Forms::convert_variable_product_to_simple( intval( $post_id ), sanitize_key( $_POST['pod_option_form_id'] ?? '' ) );
        }
    }
}

endif;

POD_Option_Forms_Admin::init();
