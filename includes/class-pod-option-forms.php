<?php
/**
 * POD Dynamic Option Forms & Virtual Variants Engine
 *
 * Lightweight meta/option-based virtual product variations and custom addons
 * inspired by WCPA + FashionOpenStudio + Custom Jersey workflows.
 * Works 100% standalone via WP-Admin AND syncs two-way with POD AI Hub via REST API.
 *
 * Zero `product_variation` rows are created in `wp_posts`.
 *
 * @package Store_AI_Connector
 * @since   2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'POD_Option_Forms', false ) ) {
    return;
}

class POD_Option_Forms {

    const OPTION_KEY          = 'pod_option_forms_v1';
    const SEEDED_FLAG_KEY     = 'pod_option_forms_seeded_v1';
    const PRODUCT_FORM_META   = '_pod_option_form_id';
    const PRODUCT_EXCLUDE_KEY = '_pod_exclude_option_forms';
    const ORDER_META_JSON_KEY = '_pod_options_json';

    /**
     * Built-in garment & product SVG line-art icons (clean monochrome strokes)
     */
    public static function get_svg_library(): array {
        return array(
            't-shirt' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10c2.5 3.5 7.5 5 10 5s7.5-1.5 10-5l14 8-5 12-9-4v28H22V26l-9 4-5-12 14-8z"/></svg>',
            'v-neck' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10l10 13 10-13 14 8-5 12-9-4v28H22V26l-9 4-5-12 14-8z"/></svg>',
            'long-sleeve' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10c2.5 3 7.5 4.5 10 4.5S39.5 13 42 10l13 7 4 33-8 2-3-24-6 2v24H22V30l-6-2-3 24-8-2 4-33 13-7z"/></svg>',
            'hoodie' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12c2-4 6-6 11-6s9 2 11 6l12 7 4 32-8 2-3-22-5 2v21H21V33l-5-2-3 22-8-2 4-32 12-7z"/><path d="M25 13c2 3 5 4.5 7 4.5s5-1.5 7-4.5"/><path d="M25 42h14l2 8H23l2-8z"/><path d="M29 18v10M35 18v10"/></svg>',
            'sweatshirt' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11c2.5 3 7.5 4.5 10 4.5S39.5 14 42 11l13 7 4 31-8 2-3-22-6 2v21H22V31l-6-2-3 22-8-2 4-31 13-7z"/><path d="M22 50h20"/></svg>',
            'tank-top' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M23 10h4c1 6 3 9 5 9s4-3 5-9h4l3 16v28H20V26l3-16z"/></svg>',
            'jersey' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10l10 10 10-10 14 8-5 12-9-4v28H22V26l-9 4-5-12 14-8z"/><path d="M32 20v34"/></svg>',
            'mug' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="12" y="14" width="30" height="38" rx="4"/><path d="M42 22h7a6 6 0 0 1 6 6v8a6 6 0 0 1-6 6h-7"/></svg>',
            'tumbler' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="19" y="10" width="26" height="6" rx="2"/><path d="M21 16h22l-3 40H24l-3-40z"/></svg>',
            'ornament' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="27" y="10" width="10" height="6" rx="2"/><circle cx="32" cy="36" r="18"/></svg>',
            'canvas' => '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="12" y="10" width="40" height="44" rx="2"/><rect x="17" y="15" width="30" height="34"/></svg>',
        );
    }

    /**
     * Built-in starter presets covering Apparel 6-Styles, Custom Baseball Jersey, Mug, and Tumbler
     */
    public static function get_starter_presets(): array {
        $size_guide_apparel_html = '
            <div class="pod-of-guide-wrap">
                <h4>Apparel Size Chart (Inches)</h4>
                <p>Relaxed unisex retail fit. Order one size up for an oversized streetwear look.</p>
                <table class="pod-of-guide-table">
                    <thead><tr><th>Size</th><th>Chest Width (in)</th><th>Body Length (in)</th><th>Sleeve Center Back (in)</th></tr></thead>
                    <tbody>
                        <tr><td><strong>S</strong></td><td>18"</td><td>28"</td><td>15.6"</td></tr>
                        <tr><td><strong>M</strong></td><td>20"</td><td>29"</td><td>17.0"</td></tr>
                        <tr><td><strong>L</strong></td><td>22"</td><td>30"</td><td>18.5"</td></tr>
                        <tr><td><strong>XL</strong></td><td>24"</td><td>31"</td><td>20.0"</td></tr>
                        <tr><td><strong>2XL</strong></td><td>26"</td><td>32"</td><td>21.5"</td></tr>
                        <tr><td><strong>3XL</strong></td><td>28"</td><td>33"</td><td>22.9"</td></tr>
                        <tr><td><strong>4XL</strong></td><td>30"</td><td>34"</td><td>24.2"</td></tr>
                        <tr><td><strong>5XL</strong></td><td>32"</td><td>35"</td><td>25.5"</td></tr>
                    </tbody>
                </table>
            </div>';

        $jersey_guide_html = '
            <div class="pod-of-guide-wrap">
                <h4>Baseball Jersey Size Guide (Inches)</h4>
                <table class="pod-of-guide-table">
                    <thead><tr><th>Size</th><th>Chest (in)</th><th>Front Length (in)</th><th>Back Length (in)</th></tr></thead>
                    <tbody>
                        <tr><td><strong>S</strong></td><td>41.7"</td><td>29.9"</td><td>31.1"</td></tr>
                        <tr><td><strong>M</strong></td><td>44.1"</td><td>31.1"</td><td>32.3"</td></tr>
                        <tr><td><strong>L</strong></td><td>46.5"</td><td>32.3"</td><td>33.5"</td></tr>
                        <tr><td><strong>XL</strong></td><td>48.8"</td><td>33.5"</td><td>34.6"</td></tr>
                        <tr><td><strong>2XL</strong></td><td>52.0"</td><td>34.6"</td><td>35.8"</td></tr>
                        <tr><td><strong>3XL</strong></td><td>55.1"</td><td>35.8"</td><td>37.0"</td></tr>
                    </tbody>
                </table>
            </div>';

        $comparing_methods_html = '
            <div class="pod-of-guide-wrap">
                <h4>Stitched (Embroidered) vs Sublimation Print</h4>
                <table class="pod-of-guide-table">
                    <thead><tr><th>Feature</th><th>Stitched (Embroidered)</th><th>Sublimation Print</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Craftsmanship</strong></td><td>Premium tackle-twill fabric letters & numbers double-stitched onto jersey</td><td>High-definition dye infused directly into breathable mesh fabric</td></tr>
                        <tr><td><strong>Feel & Weight</strong></td><td>Classic raised pro-league 3D texture</td><td>Lightweight, smooth, zero-chafe athletic feel</td></tr>
                        <tr><td><strong>Durability</strong></td><td>Heirloom stitch durability</td><td>Never cracks, peels, or fades</td></tr>
                    </tbody>
                </table>
            </div>';

        return array(
            array(
                'id'                   => 'preset_apparel_6_styles',
                'name'                 => 'Apparel — 6 Styles Virtual Variants (FashionOpenStudio UI)',
                'description'          => 'Single T-Shirt mockup sells Unisex T-Shirt, V-neck, Long Sleeve, Hoodie, Sweatshirt & Tank Top with Fit Type, Size Guide, and 15 Color Pills.',
                'is_active'            => true,
                'priority'             => 10,
                'category_slugs'       => array( 't-shirts', 'apparel', 'shirts', 'clothing', 'trending' ),
                'tag_slugs'            => array(),
                'show_price_summary'   => false,
                'enable_gmc_schema'    => true,
                'fields'               => array(
                    array(
                        'id'           => 'fit_type',
                        'type'         => 'button_group',
                        'label'        => 'Fit Type',
                        'required'     => true,
                        'show_check'   => true,
                        'show_selected_in_label' => false,
                        'semantic_role'=> 'fit',
                        'options'      => array(
                            array( 'id' => 'men', 'label' => 'Men', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'women', 'label' => 'Women', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'youth', 'label' => 'Youth', 'price_mode' => 'none', 'price' => 0 ),
                        ),
                    ),
                    array(
                        'id'            => 'style',
                        'type'          => 'visual_card_group',
                        'label'         => 'Style',
                        'required'      => true,
                        'semantic_role' => 'style',
                        'options'       => array(
                            array( 'id' => 'unisex-t-shirt', 'label' => 'Unisex T-Shirt', 'icon' => 't-shirt', 'price_mode' => 'add_fixed', 'price' => 0, 'sku_prefix' => 'G5000' ),
                            array( 'id' => 'unisex-v-neck', 'label' => 'Unisex V-neck', 'icon' => 'v-neck', 'price_mode' => 'add_fixed', 'price' => 2.00, 'sku_prefix' => 'BC3005' ),
                            array( 'id' => 'unisex-long-sleeve', 'label' => 'Unisex Long Sleeve', 'icon' => 'long-sleeve', 'price_mode' => 'add_fixed', 'price' => 5.00, 'sku_prefix' => 'G5400' ),
                            array( 'id' => 'unisex-hoodie', 'label' => 'Unisex Hoodie', 'icon' => 'hoodie', 'price_mode' => 'add_fixed', 'price' => 15.00, 'sku_prefix' => 'G18500' ),
                            array( 'id' => 'unisex-sweatshirt', 'label' => 'Unisex Sweatshirt', 'icon' => 'sweatshirt', 'price_mode' => 'add_fixed', 'price' => 11.00, 'sku_prefix' => 'G18000' ),
                            array( 'id' => 'unisex-tank-top', 'label' => 'Unisex Tank Top', 'icon' => 'tank-top', 'price_mode' => 'add_fixed', 'price' => 1.00, 'sku_prefix' => 'G5200' ),
                        ),
                    ),
                    array(
                        'id'            => 'size',
                        'type'          => 'button_group',
                        'label'         => 'Size',
                        'required'      => true,
                        'show_check'    => false,
                        'semantic_role' => 'size',
                        'helper_popup'  => array(
                            'enabled'  => true,
                            'label'    => 'Size Guide',
                            'position' => 'inline_label',
                            'icon'     => 'ruler',
                            'content'  => $size_guide_apparel_html,
                        ),
                        'options'       => array(
                            array( 'id' => 's', 'label' => 'S', 'price_mode' => 'add_fixed', 'price' => 0 ),
                            array( 'id' => 'm', 'label' => 'M', 'price_mode' => 'add_fixed', 'price' => 0 ),
                            array( 'id' => 'l', 'label' => 'L', 'price_mode' => 'add_fixed', 'price' => 0 ),
                            array( 'id' => 'xl', 'label' => 'XL', 'price_mode' => 'add_fixed', 'price' => 0 ),
                            array( 'id' => '2xl', 'label' => '2XL', 'price_mode' => 'add_fixed', 'price' => 2.00 ),
                            array( 'id' => '3xl', 'label' => '3XL', 'price_mode' => 'add_fixed', 'price' => 3.00 ),
                            array( 'id' => '4xl', 'label' => '4XL', 'price_mode' => 'add_fixed', 'price' => 4.00 ),
                            array( 'id' => '5xl', 'label' => '5XL', 'price_mode' => 'add_fixed', 'price' => 5.00 ),
                        ),
                    ),
                    array(
                        'id'            => 'color',
                        'type'          => 'color_swatch_group',
                        'swatch_style'  => 'pill',
                        'label'         => 'Color',
                        'required'      => true,
                        'semantic_role' => 'color',
                        'options'       => array(
                            array( 'id' => 'black', 'label' => 'Black', 'hex' => '#111111', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'sport-grey', 'label' => 'Sport Grey', 'hex' => '#9CA3AF', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'white', 'label' => 'White', 'hex' => '#FFFFFF', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'red', 'label' => 'Red', 'hex' => '#DC2626', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'cardinal-red', 'label' => 'Cardinal Red', 'hex' => '#881337', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'irish-green', 'label' => 'Irish Green', 'hex' => '#16A34A', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'royal', 'label' => 'Royal', 'hex' => '#1D4ED8', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'purple', 'label' => 'Purple', 'hex' => '#581C87', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'military-green', 'label' => 'Military Green', 'hex' => '#4D5D3B', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'forest-green', 'label' => 'Forest Green', 'hex' => '#14532D', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'navy', 'label' => 'Navy', 'hex' => '#1E293B', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'charcoal', 'label' => 'Charcoal', 'hex' => '#4B5563', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'light-pink', 'label' => 'Light Pink', 'hex' => '#FCE7F3', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'azalea', 'label' => 'Azalea', 'hex' => '#EC4899', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'gold', 'label' => 'Gold', 'hex' => '#FACC15', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'orange', 'label' => 'Orange', 'hex' => '#EA580C', 'price_mode' => 'none', 'price' => 0 ),
                        ),
                    ),
                ),
            ),
            array(
                'id'                   => 'preset_custom_jersey',
                'name'                 => 'Custom Jersey — Gender, Material, Team Name/Number & Logo',
                'description'          => 'Full custom sports jersey configurator with Stitched vs Print material upcharge, conditional personalization fields, and custom logo upload.',
                'is_active'            => true,
                'priority'             => 15,
                'category_slugs'       => array( 'jerseys', 'baseball-jerseys', 'custom-jerseys' ),
                'tag_slugs'            => array( 'jersey' ),
                'show_price_summary'   => true,
                'enable_gmc_schema'    => true,
                'fields'               => array(
                    array(
                        'id'                     => 'gender',
                        'type'                   => 'button_group',
                        'label'                  => 'Gender',
                        'required'               => true,
                        'show_check'             => false,
                        'button_theme'           => 'solid_dark',
                        'show_selected_in_label' => true,
                        'semantic_role'          => 'fit',
                        'options'                => array(
                            array( 'id' => 'mens', 'label' => "Men's", 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'womens', 'label' => "Women's", 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'kids', 'label' => "Kid's", 'price_mode' => 'add_fixed', 'price' => -2.00 ),
                            array( 'id' => 'preschool', 'label' => 'Preschool', 'price_mode' => 'add_fixed', 'price' => -3.00 ),
                        ),
                    ),
                    array(
                        'id'            => 'jersey_size',
                        'type'          => 'dropdown',
                        'label'         => 'Size',
                        'placeholder'   => 'Choose one',
                        'required'      => true,
                        'semantic_role' => 'size',
                        'helper_popup'  => array(
                            'enabled'  => true,
                            'label'    => 'Size guide',
                            'position' => 'inline_label',
                            'icon'     => 'ruler',
                            'content'  => $jersey_guide_html,
                        ),
                        'options'       => array(
                            array( 'id' => 's', 'label' => 'S', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'm', 'label' => 'M', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'l', 'label' => 'L', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'xl', 'label' => 'XL', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => '2xl', 'label' => '2XL (+$2.00)', 'price_mode' => 'add_fixed', 'price' => 2.00 ),
                            array( 'id' => '3xl', 'label' => '3XL (+$3.00)', 'price_mode' => 'add_fixed', 'price' => 3.00 ),
                            array( 'id' => '4xl', 'label' => '4XL (+$4.00)', 'price_mode' => 'add_fixed', 'price' => 4.00 ),
                        ),
                    ),
                    array(
                        'id'                     => 'material',
                        'type'                   => 'button_group',
                        'label'                  => 'Material',
                        'required'               => true,
                        'button_theme'           => 'solid_dark',
                        'show_selected_in_label' => true,
                        'semantic_role'          => 'style',
                        'helper_popup'           => array(
                            'enabled'  => true,
                            'label'    => 'Comparing Methods',
                            'position' => 'below_field',
                            'icon'     => 'info',
                            'content'  => $comparing_methods_html,
                        ),
                        'options'                => array(
                            array( 'id' => 'stitched', 'label' => 'Stitched(Embroidered)', 'price_mode' => 'add_fixed', 'price' => 11.99 ),
                            array( 'id' => 'print', 'label' => 'Print', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'blank', 'label' => 'Blank(No Name/Number)', 'price_mode' => 'none', 'price' => 0 ),
                        ),
                    ),
                    array(
                        'id'          => 'front_team_name',
                        'type'        => 'text_input',
                        'label'       => 'Front - Team Name',
                        'placeholder' => 'Enter Team Name',
                        'required'    => false,
                        'max_length'  => 20,
                        'condition'   => array( 'field_id' => 'material', 'operator' => 'neq', 'value' => 'blank' ),
                    ),
                    array(
                        'id'          => 'front_number',
                        'type'        => 'text_input',
                        'label'       => 'Front - Number',
                        'placeholder' => 'e.g. 00',
                        'required'    => false,
                        'max_length'  => 4,
                        'condition'   => array( 'field_id' => 'material', 'operator' => 'neq', 'value' => 'blank' ),
                    ),
                    array(
                        'id'          => 'back_your_name',
                        'type'        => 'text_input',
                        'label'       => 'Back - Your Name',
                        'placeholder' => 'Enter Back Name',
                        'required'    => false,
                        'max_length'  => 18,
                        'condition'   => array( 'field_id' => 'material', 'operator' => 'neq', 'value' => 'blank' ),
                    ),
                    array(
                        'id'          => 'back_number',
                        'type'        => 'text_input',
                        'label'       => 'Back - Number',
                        'placeholder' => 'e.g. 00',
                        'required'    => false,
                        'max_length'  => 4,
                        'condition'   => array( 'field_id' => 'material', 'operator' => 'neq', 'value' => 'blank' ),
                    ),
                    array(
                        'id'                     => 'logo_location',
                        'type'                   => 'button_group',
                        'label'                  => '1ST Logo Location(Optional)',
                        'required'               => true,
                        'button_theme'           => 'solid_dark',
                        'show_selected_in_label' => true,
                        'default_option_id'      => 'none',
                        'options'                => array(
                            array( 'id' => 'left-sleeve', 'label' => 'Left Sleeve', 'price_mode' => 'add_fixed', 'price' => 3.99 ),
                            array( 'id' => 'right-sleeve', 'label' => 'Right Sleeve', 'price_mode' => 'add_fixed', 'price' => 3.99 ),
                            array( 'id' => 'left-chest', 'label' => 'Left Chest', 'price_mode' => 'add_fixed', 'price' => 3.99 ),
                            array( 'id' => 'right-chest', 'label' => 'Right Chest', 'price_mode' => 'add_fixed', 'price' => 3.99 ),
                            array( 'id' => 'back-neck', 'label' => 'Back Neck', 'price_mode' => 'add_fixed', 'price' => 3.99 ),
                            array( 'id' => 'none', 'label' => 'None', 'price_mode' => 'none', 'price' => 0 ),
                        ),
                    ),
                    array(
                        'id'          => 'custom_logo_file',
                        'type'        => 'image_upload',
                        'label'       => 'Upload Custom Logo',
                        'button_text' => 'Add More LOGO',
                        'required'    => false,
                        'condition'   => array( 'field_id' => 'logo_location', 'operator' => 'neq', 'value' => 'none' ),
                    ),
                ),
            ),
            array(
                'id'                 => 'preset_drinkware_mug',
                'name'               => 'Drinkware — Ceramic Mug (11oz / 15oz)',
                'description'        => 'Virtual capacity and accent color options for ceramic coffee mugs.',
                'is_active'          => true,
                'priority'           => 20,
                'category_slugs'     => array( 'mugs', 'coffee-mugs' ),
                'tag_slugs'          => array(),
                'show_price_summary' => false,
                'enable_gmc_schema'  => true,
                'fields'             => array(
                    array(
                        'id'            => 'mug_size',
                        'type'          => 'button_group',
                        'label'         => 'Size',
                        'required'      => true,
                        'show_check'    => true,
                        'semantic_role' => 'size',
                        'options'       => array(
                            array( 'id' => '11oz', 'label' => '11oz Classic Mug', 'price_mode' => 'none', 'price' => 0, 'sku_prefix' => 'MUG11' ),
                            array( 'id' => '15oz', 'label' => '15oz Large Mug (+$3.00)', 'price_mode' => 'add_fixed', 'price' => 3.00, 'sku_prefix' => 'MUG15' ),
                        ),
                    ),
                    array(
                        'id'            => 'mug_color',
                        'type'          => 'color_swatch_group',
                        'swatch_style'  => 'pill',
                        'label'         => 'Color',
                        'required'      => true,
                        'semantic_role' => 'color',
                        'options'       => array(
                            array( 'id' => 'white', 'label' => 'Classic White', 'hex' => '#FFFFFF', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'black-accent', 'label' => 'Black Two-Tone', 'hex' => '#111827', 'price_mode' => 'add_fixed', 'price' => 1.50 ),
                        ),
                    ),
                ),
            ),
            array(
                'id'                 => 'preset_drinkware_tumbler',
                'name'               => 'Drinkware — Insulated Tumbler (20oz / 30oz)',
                'description'        => 'Virtual size and finish options for insulated stainless steel tumblers.',
                'is_active'          => true,
                'priority'           => 25,
                'category_slugs'     => array( 'tumblers', 'drinkware' ),
                'tag_slugs'          => array(),
                'show_price_summary' => false,
                'enable_gmc_schema'  => true,
                'fields'             => array(
                    array(
                        'id'            => 'tumbler_size',
                        'type'          => 'button_group',
                        'label'         => 'Capacity',
                        'required'      => true,
                        'show_check'    => true,
                        'semantic_role' => 'size',
                        'options'       => array(
                            array( 'id' => '20oz', 'label' => '20oz Skinny Tumbler', 'price_mode' => 'none', 'price' => 0, 'sku_prefix' => 'TUMB20' ),
                            array( 'id' => '30oz', 'label' => '30oz Travel Tumbler (+$4.00)', 'price_mode' => 'add_fixed', 'price' => 4.00, 'sku_prefix' => 'TUMB30' ),
                        ),
                    ),
                    array(
                        'id'            => 'tumbler_color',
                        'type'          => 'color_swatch_group',
                        'swatch_style'  => 'pill',
                        'label'         => 'Finish',
                        'required'      => true,
                        'semantic_role' => 'color',
                        'options'       => array(
                            array( 'id' => 'silver', 'label' => 'Brushed Silver', 'hex' => '#CBD5E1', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'black', 'label' => 'Matte Black', 'hex' => '#111827', 'price_mode' => 'none', 'price' => 0 ),
                            array( 'id' => 'white', 'label' => 'Pearl White', 'hex' => '#FFFFFF', 'price_mode' => 'none', 'price' => 0 ),
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Initialize hooks
     */
    public static function init() {
        add_action( 'init', array( __CLASS__, 'maybe_seed_default_forms' ), 15 );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ), 12 );

        // Render form before Add to Cart button (priority 14 — runs cleanly before POD_Custom_Fields priority 20)
        add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_product_option_form' ), 14 );
        add_filter( 'body_class', array( __CLASS__, 'add_body_class' ) );
        add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'filter_shop_price_html' ), 998, 2 );

        // Cart & Order hooks
        add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 12, 3 );
        add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 12, 3 );
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_cart_item_data' ), 12, 2 );
        add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'calculate_cart_totals' ), 25, 1 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_order_line_item_meta' ), 12, 4 );

        // AJAX file upload for custom logo / image_upload fields
        add_action( 'wp_ajax_pod_of_upload_file', array( __CLASS__, 'handle_ajax_upload' ) );
        add_action( 'wp_ajax_nopriv_pod_of_upload_file', array( __CLASS__, 'handle_ajax_upload' ) );

        // GMC JSON-LD ProductGroup schema
        add_action( 'wp_head', array( __CLASS__, 'output_gmc_schema_jsonld' ), 98 );
    }

    /**
     * Handle frontend AJAX image upload for `image_upload` option fields (e.g. Custom Logo)
     */
    public static function handle_ajax_upload() {
        check_ajax_referer( 'pod_of_upload_nonce', 'nonce' );

        if ( empty( $_FILES['file'] ) || ! empty( $_FILES['file']['error'] ) ) {
            wp_send_json_error( array( 'message' => 'No valid file uploaded.' ) );
        }

        $file     = $_FILES['file'];
        $max_size = 8 * 1024 * 1024; // 8MB max
        if ( intval( $file['size'] ) > $max_size ) {
            wp_send_json_error( array( 'message' => 'File exceeds 8MB limit.' ) );
        }

        $checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
            'gif'          => 'image/gif',
        ) );
        if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
            wp_send_json_error( array( 'message' => 'Only JPG, PNG, WEBP, or GIF images are allowed.' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $uploaded = wp_handle_upload( $file, array( 'test_form' => false ) );
        if ( isset( $uploaded['error'] ) ) {
            wp_send_json_error( array( 'message' => $uploaded['error'] ) );
        }

        wp_send_json_success( array(
            'url'      => $uploaded['url'],
            'filename' => basename( $uploaded['file'] ),
        ) );
    }

    /**
     * Seed starter presets once if registry is empty
     */
    public static function maybe_seed_default_forms() {
        if ( get_option( self::SEEDED_FLAG_KEY ) ) {
            return;
        }
        $existing = get_option( self::OPTION_KEY, array() );
        if ( empty( $existing ) || ! is_array( $existing ) ) {
            update_option( self::OPTION_KEY, self::get_starter_presets(), false );
        }
        update_option( self::SEEDED_FLAG_KEY, '1', false );
    }

    /**
     * Get all configured option forms sorted by priority
     */
    public static function get_forms(): array {
        self::maybe_seed_default_forms();
        $forms = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $forms ) ) {
            return array();
        }
        usort( $forms, function( $a, $b ) {
            $pa = isset( $a['priority'] ) ? intval( $a['priority'] ) : 50;
            $pb = isset( $b['priority'] ) ? intval( $b['priority'] ) : 50;
            return $pa <=> $pb;
        } );
        return array_values( $forms );
    }

    /**
     * Save full forms list
     */
    public static function save_forms( array $forms ): void {
        update_option( self::OPTION_KEY, array_values( $forms ), false );
    }

    /**
     * Upsert a single form by ID
     */
    public static function upsert_form( array $form ): array {
        $forms = self::get_forms();
        $id    = ! empty( $form['id'] ) ? sanitize_key( $form['id'] ) : 'form_' . wp_generate_password( 8, false, false );
        $form['id'] = $id;
        $form['name'] = isset( $form['name'] ) ? sanitize_text_field( $form['name'] ) : 'Untitled Option Form';
        $form['description'] = isset( $form['description'] ) ? sanitize_text_field( $form['description'] ) : '';
        $form['is_active'] = isset( $form['is_active'] ) ? (bool) $form['is_active'] : true;
        $form['priority'] = isset( $form['priority'] ) ? intval( $form['priority'] ) : 20;
        $form['show_price_summary'] = ! empty( $form['show_price_summary'] );
        $form['enable_gmc_schema'] = isset( $form['enable_gmc_schema'] ) ? (bool) $form['enable_gmc_schema'] : true;

        $cat_slugs = array();
        if ( ! empty( $form['category_slugs'] ) && is_array( $form['category_slugs'] ) ) {
            foreach ( $form['category_slugs'] as $slug ) {
                $s = sanitize_title( $slug );
                if ( $s ) {
                    $cat_slugs[] = $s;
                }
            }
        }
        $form['category_slugs'] = array_values( array_unique( $cat_slugs ) );

        $tag_slugs = array();
        if ( ! empty( $form['tag_slugs'] ) && is_array( $form['tag_slugs'] ) ) {
            foreach ( $form['tag_slugs'] as $slug ) {
                $s = sanitize_title( $slug );
                if ( $s ) {
                    $tag_slugs[] = $s;
                }
            }
        }
        $form['tag_slugs'] = array_values( array_unique( $tag_slugs ) );
        $form['fields']    = isset( $form['fields'] ) && is_array( $form['fields'] ) ? array_values( $form['fields'] ) : array();
        $form['updated_at']= current_time( 'mysql', true );

        $replaced = false;
        foreach ( $forms as $idx => $existing ) {
            if ( isset( $existing['id'] ) && $existing['id'] === $id ) {
                $forms[ $idx ] = $form;
                $replaced      = true;
                break;
            }
        }
        if ( ! $replaced ) {
            $forms[] = $form;
        }
        self::save_forms( $forms );
        return $form;
    }

    /**
     * Delete a form by ID
     */
    public static function delete_form( string $form_id ): bool {
        $forms  = self::get_forms();
        $before = count( $forms );
        $forms  = array_values( array_filter( $forms, function( $f ) use ( $form_id ) {
            return ( $f['id'] ?? '' ) !== $form_id;
        } ) );
        self::save_forms( $forms );
        return count( $forms ) < $before;
    }

    /**
     * Import existing WCPA (`wcpa_pt_forms`) forms into POD Option Forms (1-Click Migration)
     */
    public static function import_from_wcpa(): int {
        $wcpa_posts = get_posts( array(
            'post_type'      => 'wcpa_pt_forms',
            'post_status'    => array( 'publish', 'draft' ),
            'posts_per_page' => 100,
        ) );
        if ( empty( $wcpa_posts ) ) {
            return 0;
        }
        $imported = 0;
        foreach ( $wcpa_posts as $p ) {
            $raw_json = get_post_meta( $p->ID, '_wcpa_fb-editor-data', true );
            $decoded  = is_string( $raw_json ) ? json_decode( $raw_json, true ) : $raw_json;
            if ( empty( $decoded ) || ! is_array( $decoded ) ) {
                continue;
            }
            $cat_terms = wp_get_post_terms( $p->ID, 'product_cat', array( 'fields' => 'slugs' ) );
            $cat_slugs = is_wp_error( $cat_terms ) ? array() : array_values( $cat_terms );

            $fields = array();
            foreach ( $decoded as $idx => $item ) {
                $w_type = $item['type'] ?? 'text';
                if ( in_array( $w_type, array( 'header', 'paragraph' ), true ) ) {
                    continue;
                }
                $fid   = sanitize_key( $item['name'] ?? ( 'wcpa_f_' . $idx ) );
                $label = wp_strip_all_tags( $item['label'] ?? ucfirst( $fid ) );
                $req   = ! empty( $item['required'] );
                $mapped_type = 'text_input';
                $swatch_style = 'pill';

                if ( $w_type === 'select' ) {
                    $mapped_type = 'dropdown';
                } elseif ( in_array( $w_type, array( 'radio-group', 'checkbox-group' ), true ) ) {
                    $mapped_type = 'button_group';
                } elseif ( $w_type === 'image-group' ) {
                    $mapped_type = 'visual_card_group';
                } elseif ( $w_type === 'color-group' ) {
                    $mapped_type = 'color_swatch_group';
                } elseif ( $w_type === 'textarea' ) {
                    $mapped_type = 'textarea';
                } elseif ( $w_type === 'file' ) {
                    $mapped_type = 'image_upload';
                }

                $opts = array();
                if ( ! empty( $item['values'] ) && is_array( $item['values'] ) ) {
                    foreach ( $item['values'] as $v_idx => $val ) {
                        $opt_price = isset( $val['price'] ) ? floatval( $val['price'] ) : 0;
                        $opts[] = array(
                            'id'         => sanitize_title( $val['value'] ?? $val['label'] ?? ( 'opt-' . $v_idx ) ),
                            'label'      => wp_strip_all_tags( $val['label'] ?? '' ),
                            'price_mode' => $opt_price != 0 ? 'add_fixed' : 'none',
                            'price'      => $opt_price,
                            'hex'        => $val['color'] ?? '#111827',
                            'image_url'  => $val['image'] ?? '',
                        );
                    }
                }

                $fields[] = array(
                    'id'           => $fid,
                    'type'         => $mapped_type,
                    'swatch_style' => $swatch_style,
                    'label'        => $label,
                    'required'     => $req,
                    'placeholder'  => $item['placeholder'] ?? '',
                    'options'      => $opts,
                );
            }

            if ( ! empty( $fields ) ) {
                self::upsert_form( array(
                    'id'                 => 'wcpa_import_' . $p->ID,
                    'name'               => $p->post_title . ' (Imported from WCPA)',
                    'description'        => 'Imported automatically from WooCommerce Custom Product Addons.',
                    'is_active'          => $p->post_status === 'publish',
                    'priority'           => 30,
                    'category_slugs'     => $cat_slugs,
                    'show_price_summary' => true,
                    'fields'             => $fields,
                ) );
                $imported++;
            }
        }
        return $imported;
    }

    /**
     * Resolve the active Option Form for a given WooCommerce Product ID.
     * Note: Only renders on Simple products (so native Variable products don't double-render unless converted).
     * Also skips if legacy `_pod_variations` is explicitly set AND product has no explicit `_pod_option_form_id`,
     * UNLESS the category matches an active Option Form and `_pod_variations` is empty.
     */
    public static function resolve_form_for_product( int $product_id ): ?array {
        if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
            return null;
        }
        $product = wc_get_product( $product_id );
        if ( ! $product || ! $product->is_type( 'simple' ) ) {
            return null;
        }
        if ( get_post_meta( $product_id, self::PRODUCT_EXCLUDE_KEY, true ) ) {
            return null;
        }

        $forms = self::get_forms();
        if ( empty( $forms ) ) {
            return null;
        }

        // 1. Explicit per-product form ID override
        $explicit_id = get_post_meta( $product_id, self::PRODUCT_FORM_META, true );
        if ( ! empty( $explicit_id ) ) {
            foreach ( $forms as $f ) {
                if ( ( $f['id'] ?? '' ) === $explicit_id && ! empty( $f['is_active'] ) ) {
                    return $f;
                }
            }
        }

        // If legacy `_pod_variations` is already present on this product, let legacy `POD_Variations` render it unless overridden
        $legacy_pv = get_post_meta( $product_id, '_pod_variations', true );
        if ( ! empty( $legacy_pv ) ) {
            return null;
        }

        // 2. Match by Product Category slugs
        $cat_terms = get_the_terms( $product_id, 'product_cat' );
        $prod_cat_slugs = array();
        if ( ! empty( $cat_terms ) && ! is_wp_error( $cat_terms ) ) {
            foreach ( $cat_terms as $t ) {
                $prod_cat_slugs[] = strtolower( $t->slug );
                $prod_cat_slugs[] = sanitize_title( $t->name );
            }
        }
        $prod_cat_slugs = array_unique( $prod_cat_slugs );

        if ( ! empty( $prod_cat_slugs ) ) {
            foreach ( $forms as $f ) {
                if ( empty( $f['is_active'] ) || empty( $f['category_slugs'] ) ) {
                    continue;
                }
                foreach ( $f['category_slugs'] as $c_slug ) {
                    if ( in_array( strtolower( $c_slug ), $prod_cat_slugs, true ) ) {
                        return $f;
                    }
                }
            }
        }

        // 3. Match by Product Tag slugs
        $tag_terms = get_the_terms( $product_id, 'product_tag' );
        if ( ! empty( $tag_terms ) && ! is_wp_error( $tag_terms ) ) {
            $prod_tag_slugs = array_map( function( $t ) { return strtolower( $t->slug ); }, $tag_terms );
            foreach ( $forms as $f ) {
                if ( empty( $f['is_active'] ) || empty( $f['tag_slugs'] ) ) {
                    continue;
                }
                foreach ( $f['tag_slugs'] as $t_slug ) {
                    if ( in_array( strtolower( $t_slug ), $prod_tag_slugs, true ) ) {
                        return $f;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Convert a heavy WooCommerce Variable Product into a lightweight Simple Product
     * and remove its child `product_variation` rows from `wp_posts` + `wp_postmeta`.
     */
    public static function convert_variable_product_to_simple( int $product_id, string $form_id = '', ?float $base_price = null ): array {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return array( 'success' => false, 'error' => 'Product not found' );
        }

        $deleted_variations = 0;
        $resolved_price     = $base_price;

        if ( $product->is_type( 'variable' ) ) {
            if ( $resolved_price === null || $resolved_price <= 0 ) {
                $min_var_price  = floatval( $product->get_variation_price( 'min', false ) );
                $resolved_price = $min_var_price > 0 ? $min_var_price : 24.99;
            }
            $children = $product->get_children();
            foreach ( $children as $child_id ) {
                wp_delete_post( intval( $child_id ), true );
                $deleted_variations++;
            }
            wp_set_object_terms( $product_id, 'simple', 'product_type' );
        } else {
            if ( $resolved_price === null || $resolved_price <= 0 ) {
                $cur = floatval( $product->get_regular_price() ?: $product->get_price() );
                $resolved_price = $cur > 0 ? $cur : 24.99;
            }
        }

        // Clear variation attributes and set simple price
        update_post_meta( $product_id, '_regular_price', number_format( $resolved_price, 2, '.', '' ) );
        update_post_meta( $product_id, '_price', number_format( $resolved_price, 2, '.', '' ) );
        update_post_meta( $product_id, '_stock_status', 'instock' );
        update_post_meta( $product_id, '_manage_stock', 'no' );
        delete_post_meta( $product_id, '_pod_variations' );

        if ( ! empty( $form_id ) ) {
            update_post_meta( $product_id, self::PRODUCT_FORM_META, sanitize_key( $form_id ) );
        }

        if ( function_exists( 'wc_delete_product_transients' ) ) {
            wc_delete_product_transients( $product_id );
        }
        clean_post_cache( $product_id );

        return array(
            'success'            => true,
            'product_id'         => $product_id,
            'deleted_variations' => $deleted_variations,
            'regular_price'      => number_format( $resolved_price, 2, '.', '' ),
            'assigned_form_id'   => $form_id ?: ( self::resolve_form_for_product( $product_id )['id'] ?? null ),
        );
    }

    /**
     * Add body class when active Option Form applies to current single product
     */
    public static function add_body_class( $classes ) {
        if ( function_exists( 'is_product' ) && is_product() ) {
            global $post;
            if ( $post && self::resolve_form_for_product( intval( $post->ID ) ) ) {
                $classes[] = 'pod-option-forms-active';
            }
        }
        return $classes;
    }

    /**
     * Enqueue frontend CSS and JS
     */
    public static function enqueue_frontend_assets() {
        if ( ! function_exists( 'is_product' ) ) {
            return;
        }
        $plugin_root_file = defined( 'SAC_PLUGIN_FILE' ) ? SAC_PLUGIN_FILE : ( dirname( __DIR__ ) . '/store-ai-connector.php' );
        $plugin_base_url  = trailingslashit( plugin_dir_url( $plugin_root_file ) );

        if ( is_shop() || is_product_category() || is_product_tag() || is_search() ) {
            wp_enqueue_style(
                'pod-option-forms-css',
                $plugin_base_url . 'assets/css/pod-option-forms.css',
                array(),
                SAC_VERSION
            );
            return;
        }
        if ( ! is_product() ) {
            return;
        }
        global $post;
        if ( ! $post ) {
            return;
        }
        $form = self::resolve_form_for_product( intval( $post->ID ) );
        if ( ! $form ) {
            return;
        }

        wp_enqueue_style(
            'pod-option-forms-css',
            $plugin_base_url . 'assets/css/pod-option-forms.css',
            array(),
            SAC_VERSION
        );
        wp_enqueue_script(
            'pod-option-forms-js',
            $plugin_base_url . 'assets/js/pod-option-forms.js',
            array( 'jquery' ),
            SAC_VERSION,
            true
        );
        wp_localize_script( 'pod-option-forms-js', 'podOptionFormsConfig', array(
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'uploadNonce'    => wp_create_nonce( 'pod_of_upload_nonce' ),
            'currencySymbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
        ) );
    }

    /**
     * Filter shop/archive price HTML to show "From $XX.XX" when form has price-modifying options
     */
    public static function filter_shop_price_html( $price_html, $product ) {
        if ( is_admin() || ! $product ) {
            return $price_html;
        }
        if ( function_exists( 'is_product' ) && is_product() ) {
            global $post;
            if ( $post && intval( $post->ID ) === intval( $product->get_id() ) ) {
                return $price_html;
            }
        }
        $form = self::resolve_form_for_product( intval( $product->get_id() ) );
        if ( ! $form || empty( $form['fields'] ) ) {
            return $price_html;
        }
        $base_price = floatval( $product->get_price() );
        if ( $base_price <= 0 ) {
            return $price_html;
        }
        $has_variation = false;
        foreach ( $form['fields'] as $field ) {
            if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
                foreach ( $field['options'] as $opt ) {
                    $p = isset( $opt['price'] ) ? floatval( $opt['price'] ) : 0;
                    if ( $p > 0 ) {
                        $has_variation = true;
                        break 2;
                    }
                }
            }
        }
        if ( $has_variation ) {
            return '<span class="pod-of-from-price"><span class="pod-of-from-word">From</span> ' . wc_price( $base_price ) . '</span>';
        }
        return $price_html;
    }

    /**
     * Determine whether a hex color is light (for contrasting dark text & checkmark)
     */
    public static function is_light_hex( string $hex ): bool {
        $clean = ltrim( trim( $hex ), '#' );
        if ( strlen( $clean ) === 3 ) {
            $clean = $clean[0] . $clean[0] . $clean[1] . $clean[1] . $clean[2] . $clean[2];
        }
        if ( strlen( $clean ) !== 6 ) {
            return false;
        }
        $r = hexdec( substr( $clean, 0, 2 ) );
        $g = hexdec( substr( $clean, 2, 2 ) );
        $b = hexdec( substr( $clean, 4, 2 ) );
        $luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
        return $luminance > 0.68;
    }

    /**
     * Render the Dynamic Option Form on single product page
     */
    public static function render_product_option_form() {
        global $product;
        if ( ! $product ) {
            return;
        }
        $form = self::resolve_form_for_product( intval( $product->get_id() ) );
        if ( ! $form || empty( $form['fields'] ) ) {
            return;
        }

        $base_price    = floatval( $product->get_price() ?: 24.99 );
        $svg_icons     = self::get_svg_library();
        $popups_to_out = array();
        ?>
        <div class="pod-of-container"
             data-form-id="<?php echo esc_attr( $form['id'] ); ?>"
             data-base-price="<?php echo esc_attr( number_format( $base_price, 2, '.', '' ) ); ?>"
             data-show-summary="<?php echo ! empty( $form['show_price_summary'] ) ? '1' : '0'; ?>">

            <div class="pod-of-live-price-bar">
                <span class="pod-of-live-price-amount"><?php echo wp_kses_post( wc_price( $base_price ) ); ?></span>
                <span class="pod-of-stock-pill">In Stock &middot; Made to Order</span>
            </div>

            <?php foreach ( $form['fields'] as $field ) :
                $fid        = sanitize_key( $field['id'] ?? '' );
                $ftype      = $field['type'] ?? 'button_group';
                $label      = $field['label'] ?? ucfirst( $fid );
                $required   = ! empty( $field['required'] );
                $options    = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
                $condition  = isset( $field['condition'] ) && is_array( $field['condition'] ) ? $field['condition'] : null;
                $popup      = isset( $field['helper_popup'] ) && is_array( $field['helper_popup'] ) && ! empty( $field['helper_popup']['enabled'] ) ? $field['helper_popup'] : null;
                $popup_id   = $popup ? 'pod_popup_' . $fid : '';
                if ( $popup ) {
                    $popups_to_out[ $popup_id ] = $popup;
                }

                $default_opt_id = $field['default_option_id'] ?? ( $options[0]['id'] ?? '' );
                $selected_opt   = null;
                foreach ( $options as $o ) {
                    if ( ( $o['id'] ?? '' ) === $default_opt_id ) {
                        $selected_opt = $o;
                        break;
                    }
                }
                if ( ! $selected_opt && ! empty( $options ) && $ftype !== 'dropdown' ) {
                    $selected_opt = $options[0];
                }
                $cond_attrs = '';
                if ( $condition && ! empty( $condition['field_id'] ) ) {
                    $cond_attrs = sprintf(
                        ' data-cond-field="%s" data-cond-op="%s" data-cond-val="%s"',
                        esc_attr( sanitize_key( $condition['field_id'] ) ),
                        esc_attr( $condition['operator'] ?? 'eq' ),
                        esc_attr( $condition['value'] ?? '' )
                    );
                }
            ?>
            <div class="pod-of-field pod-of-type-<?php echo esc_attr( $ftype ); ?>"
                 data-field-id="<?php echo esc_attr( $fid ); ?>"
                 data-field-type="<?php echo esc_attr( $ftype ); ?>"
                 data-field-label="<?php echo esc_attr( $label ); ?>"
                 data-semantic-role="<?php echo esc_attr( $field['semantic_role'] ?? '' ); ?>"
                 data-required="<?php echo $required ? '1' : '0'; ?>"<?php echo $cond_attrs; ?>>

                <div class="pod-of-field-header">
                    <label class="pod-of-label">
                        <span class="pod-of-label-text"><?php echo esc_html( $label ); ?><?php if ( $required ) : ?><span class="pod-of-req">*</span><?php endif; ?></span>
                        <?php if ( $ftype === 'color_swatch_group' && $selected_opt ) : ?>
                            <span class="pod-of-selected-color-name"><?php echo esc_html( strtoupper( $selected_opt['label'] ) ); ?></span>
                        <?php elseif ( ! empty( $field['show_selected_in_label'] ) && $selected_opt ) : ?>
                            <span class="pod-of-selected-value-name"><?php echo esc_html( $selected_opt['label'] ); ?></span>
                            <span class="pod-of-selected-upcharge"><?php
                                $sp = floatval( $selected_opt['price'] ?? 0 );
                                echo $sp > 0 ? '(+$' . number_format( $sp, 2 ) . ')' : '';
                            ?></span>
                        <?php endif; ?>
                    </label>

                    <?php if ( $popup && ( $popup['position'] ?? 'inline_label' ) === 'inline_label' ) : ?>
                        <button type="button" class="pod-of-popup-link" data-popup-target="<?php echo esc_attr( $popup_id ); ?>">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="10" rx="2"/><path d="M6 7v4M10 7v3M14 7v4M18 7v3"/></svg>
                            <span><?php echo esc_html( $popup['label'] ?? 'Size Guide' ); ?></span>
                        </button>
                    <?php endif; ?>
                </div>

                <?php if ( $ftype === 'button_group' ) :
                    $btn_theme  = $field['button_theme'] ?? 'outline_green';
                    $show_check = ! empty( $field['show_check'] );
                ?>
                    <div class="pod-of-btn-group theme-<?php echo esc_attr( $btn_theme ); ?>">
                        <?php foreach ( $options as $opt ) :
                            $is_sel = $selected_opt && ( $opt['id'] === $selected_opt['id'] );
                        ?>
                            <button type="button"
                                    class="pod-of-choice pod-of-btn <?php echo $is_sel ? 'is-selected' : ''; ?>"
                                    data-opt-id="<?php echo esc_attr( $opt['id'] ); ?>"
                                    data-opt-label="<?php echo esc_attr( $opt['label'] ); ?>"
                                    data-price-mode="<?php echo esc_attr( $opt['price_mode'] ?? 'none' ); ?>"
                                    data-price="<?php echo esc_attr( floatval( $opt['price'] ?? 0 ) ); ?>"
                                    data-sku-prefix="<?php echo esc_attr( $opt['sku_prefix'] ?? '' ); ?>">
                                <?php if ( $show_check ) : ?>
                                    <span class="pod-of-btn-check-circle">✓</span>
                                <?php endif; ?>
                                <span><?php echo esc_html( $opt['label'] ); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ( $ftype === 'visual_card_group' ) : ?>
                    <div class="pod-of-card-grid">
                        <?php foreach ( $options as $opt ) :
                            $is_sel   = $selected_opt && ( $opt['id'] === $selected_opt['id'] );
                            $icon_key = $opt['icon'] ?? 't-shirt';
                            $svg_html = $svg_icons[ $icon_key ] ?? $svg_icons['t-shirt'];
                        ?>
                            <button type="button"
                                    class="pod-of-choice pod-of-style-card <?php echo $is_sel ? 'is-selected' : ''; ?>"
                                    data-opt-id="<?php echo esc_attr( $opt['id'] ); ?>"
                                    data-opt-label="<?php echo esc_attr( $opt['label'] ); ?>"
                                    data-price-mode="<?php echo esc_attr( $opt['price_mode'] ?? 'none' ); ?>"
                                    data-price="<?php echo esc_attr( floatval( $opt['price'] ?? 0 ) ); ?>"
                                    data-sku-prefix="<?php echo esc_attr( $opt['sku_prefix'] ?? '' ); ?>">
                                <span class="pod-of-style-card-box">
                                    <?php if ( ! empty( $opt['image_url'] ) ) : ?>
                                        <img src="<?php echo esc_url( $opt['image_url'] ); ?>" alt="<?php echo esc_attr( $opt['label'] ); ?>" loading="lazy" />
                                    <?php else : ?>
                                        <?php echo $svg_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <?php endif; ?>
                                </span>
                                <span class="pod-of-style-card-caption"><?php echo esc_html( $opt['label'] ); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ( $ftype === 'color_swatch_group' ) :
                    $swatch_style = $field['swatch_style'] ?? 'pill';
                ?>
                    <div class="pod-of-color-group style-<?php echo esc_attr( $swatch_style ); ?>">
                        <?php foreach ( $options as $opt ) :
                            $is_sel   = $selected_opt && ( $opt['id'] === $selected_opt['id'] );
                            $hex      = $opt['hex'] ?? '#111827';
                            $is_light = self::is_light_hex( $hex );
                        ?>
                            <button type="button"
                                    class="pod-of-choice pod-of-color-item <?php echo $is_sel ? 'is-selected' : ''; ?> <?php echo $is_light ? 'is-light' : 'is-dark'; ?>"
                                    style="background-color: <?php echo esc_attr( $hex ); ?>;"
                                    data-opt-id="<?php echo esc_attr( $opt['id'] ); ?>"
                                    data-opt-label="<?php echo esc_attr( $opt['label'] ); ?>"
                                    data-hex="<?php echo esc_attr( $hex ); ?>"
                                    data-price-mode="<?php echo esc_attr( $opt['price_mode'] ?? 'none' ); ?>"
                                    data-price="<?php echo esc_attr( floatval( $opt['price'] ?? 0 ) ); ?>"
                                    title="<?php echo esc_attr( $opt['label'] ); ?>">
                                <?php if ( $swatch_style === 'pill' ) : ?>
                                    <span class="pod-of-color-pill-text"><?php echo esc_html( $opt['label'] ); ?></span>
                                <?php endif; ?>
                                <span class="pod-of-color-tick">✓</span>
                            </button>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ( $ftype === 'dropdown' ) : ?>
                    <select class="pod-of-select" data-field-id="<?php echo esc_attr( $fid ); ?>">
                        <option value=""><?php echo esc_html( $field['placeholder'] ?? 'Choose one' ); ?></option>
                        <?php foreach ( $options as $opt ) : ?>
                            <option value="<?php echo esc_attr( $opt['id'] ); ?>"
                                    data-opt-label="<?php echo esc_attr( $opt['label'] ); ?>"
                                    data-price-mode="<?php echo esc_attr( $opt['price_mode'] ?? 'none' ); ?>"
                                    data-price="<?php echo esc_attr( floatval( $opt['price'] ?? 0 ) ); ?>">
                                <?php echo esc_html( $opt['label'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ( in_array( $ftype, array( 'text_input', 'number_input' ), true ) ) : ?>
                    <input type="<?php echo $ftype === 'number_input' ? 'number' : 'text'; ?>"
                           class="pod-of-text-input"
                           placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"
                           <?php if ( ! empty( $field['max_length'] ) ) : ?>maxlength="<?php echo intval( $field['max_length'] ); ?>"<?php endif; ?>
                           data-price-mode="<?php echo esc_attr( $field['price_mode'] ?? 'none' ); ?>"
                           data-price="<?php echo esc_attr( floatval( $field['price'] ?? 0 ) ); ?>" />

                <?php elseif ( $ftype === 'textarea' ) : ?>
                    <textarea class="pod-of-textarea"
                              rows="3"
                              placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"
                              <?php if ( ! empty( $field['max_length'] ) ) : ?>maxlength="<?php echo intval( $field['max_length'] ); ?>"<?php endif; ?>
                              data-price-mode="<?php echo esc_attr( $field['price_mode'] ?? 'none' ); ?>"
                              data-price="<?php echo esc_attr( floatval( $field['price'] ?? 0 ) ); ?>"></textarea>

                <?php elseif ( $ftype === 'image_upload' ) : ?>
                    <div class="pod-of-upload-wrap">
                        <label class="pod-of-upload-btn">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                            <span><?php echo esc_html( $field['button_text'] ?? 'Upload Image / Logo' ); ?></span>
                            <input type="file" accept=".jpg,.jpeg,.png,.webp,.svg" class="pod-of-file-input" style="display:none;" />
                        </label>
                        <div class="pod-of-upload-preview" style="display:none;">
                            <img src="" alt="Uploaded preview" />
                            <span class="pod-of-upload-filename"></span>
                            <button type="button" class="pod-of-upload-clear">&times;</button>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ( $popup && ( $popup['position'] ?? '' ) === 'below_field' ) : ?>
                    <div class="pod-of-below-popup-wrap">
                        <button type="button" class="pod-of-compare-btn" data-popup-target="<?php echo esc_attr( $popup_id ); ?>">
                            <?php echo esc_html( $popup['label'] ?? 'Learn More' ); ?>
                        </button>
                    </div>
                <?php endif; ?>

                <input type="hidden"
                       class="pod-of-hidden-val"
                       name="pod_of_val[<?php echo esc_attr( $fid ); ?>]"
                       value="<?php echo $selected_opt ? esc_attr( $selected_opt['id'] ) : ''; ?>" />
            </div>
            <?php endforeach; ?>

            <div class="pod-of-addon-summary" style="display:none;">
                Selections will add <strong class="pod-of-addon-amount">$0.00</strong> to the base price
            </div>

            <input type="hidden" name="pod_of_form_id" value="<?php echo esc_attr( $form['id'] ); ?>" />
            <input type="hidden" name="pod_of_payload_json" class="pod-of-payload-json" value="" />
            <?php wp_nonce_field( 'pod_of_cart_action', 'pod_of_nonce' ); ?>

            <?php foreach ( $popups_to_out as $pid => $p_cfg ) : ?>
                <div id="<?php echo esc_attr( $pid ); ?>" class="pod-of-modal-backdrop" style="display:none;" role="dialog" aria-modal="true">
                    <div class="pod-of-modal-dialog">
                        <button type="button" class="pod-of-modal-close" aria-label="Close">&times;</button>
                        <div class="pod-of-modal-body">
                            <?php echo wp_kses_post( $p_cfg['content'] ?? '' ); ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Evaluate whether a field's condition passes given submitted values
     */
    private static function condition_passes( array $field, array $submitted_vals ): bool {
        if ( empty( $field['condition'] ) || ! is_array( $field['condition'] ) || empty( $field['condition']['field_id'] ) ) {
            return true;
        }
        $target_id = sanitize_key( $field['condition']['field_id'] );
        $op        = $field['condition']['operator'] ?? 'eq';
        $expected  = strval( $field['condition']['value'] ?? '' );
        $actual    = strval( $submitted_vals[ $target_id ] ?? '' );

        if ( $op === 'neq' ) {
            return $actual !== $expected;
        }
        return $actual === $expected;
    }

    /**
     * Server-side price & selection calculation (never trusts client-calculated price!)
     */
    public static function evaluate_submission( int $product_id, array $form, array $raw_vals ): array {
        $product    = wc_get_product( $product_id );
        $base_price = $product ? floatval( $product->get_price() ?: 24.99 ) : 24.99;
        $add_total  = 0.0;
        $entries    = array();
        $semantic   = array(
            'fit'        => '',
            'style'      => '',
            'size'       => '',
            'color'      => '',
            'sku_prefix' => '',
        );

        foreach ( $form['fields'] as $field ) {
            $fid = sanitize_key( $field['id'] ?? '' );
            if ( ! $fid || ! self::condition_passes( $field, $raw_vals ) ) {
                continue;
            }
            $ftype = $field['type'] ?? 'button_group';
            $label = $field['label'] ?? ucfirst( $fid );
            $role  = $field['semantic_role'] ?? '';
            $val   = isset( $raw_vals[ $fid ] ) ? sanitize_text_field( wp_unslash( $raw_vals[ $fid ] ) ) : '';

            if ( in_array( $ftype, array( 'button_group', 'visual_card_group', 'color_swatch_group', 'dropdown' ), true ) ) {
                $matched_opt = null;
                foreach ( ( $field['options'] ?? array() ) as $opt ) {
                    if ( strval( $opt['id'] ?? '' ) === $val ) {
                        $matched_opt = $opt;
                        break;
                    }
                }
                if ( $matched_opt ) {
                    $pmode = $matched_opt['price_mode'] ?? 'none';
                    $pamt  = floatval( $matched_opt['price'] ?? 0 );
                    if ( $pmode === 'override_base' && $pamt > 0 ) {
                        $base_price = $pamt;
                    } elseif ( $pmode === 'add_fixed' ) {
                        $add_total += $pamt;
                    } elseif ( $pmode === 'add_percent' ) {
                        $add_total += round( $base_price * ( $pamt / 100.0 ), 2 );
                    }

                    $entries[] = array(
                        'id'    => $fid,
                        'label' => $label,
                        'value' => $matched_opt['label'],
                        'raw'   => $matched_opt['id'],
                        'price' => $pamt,
                    );

                    if ( $role && isset( $semantic[ $role ] ) ) {
                        $semantic[ $role ] = $matched_opt['label'];
                    }
                    if ( ! empty( $matched_opt['sku_prefix'] ) ) {
                        $semantic['sku_prefix'] = $matched_opt['sku_prefix'];
                    }
                }
            } else {
                if ( $val !== '' ) {
                    $pmode = $field['price_mode'] ?? 'none';
                    $pamt  = floatval( $field['price'] ?? 0 );
                    if ( $pmode === 'add_fixed' ) {
                        $add_total += $pamt;
                    }
                    $entries[] = array(
                        'id'    => $fid,
                        'label' => $label,
                        'value' => $val,
                        'raw'   => $val,
                        'price' => $pamt,
                    );
                }
            }
        }

        $final_price = max( 0.01, round( $base_price + $add_total, 2 ) );
        return array(
            'form_id'     => $form['id'],
            'form_name'   => $form['name'] ?? '',
            'entries'     => $entries,
            'semantic'    => $semantic,
            'base_price'  => $base_price,
            'add_total'   => round( $add_total, 2 ),
            'final_price' => $final_price,
        );
    }

    /**
     * Validate required fields when adding to cart
     */
    public static function validate_add_to_cart( $passed, $product_id, $quantity ) {
        $form = self::resolve_form_for_product( intval( $product_id ) );
        if ( ! $form || empty( $_POST['pod_of_form_id'] ) ) {
            return $passed;
        }
        $raw_vals = isset( $_POST['pod_of_val'] ) && is_array( $_POST['pod_of_val'] ) ? $_POST['pod_of_val'] : array();
        foreach ( $form['fields'] as $field ) {
            if ( empty( $field['required'] ) || ! self::condition_passes( $field, $raw_vals ) ) {
                continue;
            }
            $fid = sanitize_key( $field['id'] ?? '' );
            $val = isset( $raw_vals[ $fid ] ) ? trim( sanitize_text_field( wp_unslash( $raw_vals[ $fid ] ) ) ) : '';
            if ( $val === '' ) {
                wc_add_notice( sprintf( 'Please select or enter "%s".', $field['label'] ?? $fid ), 'error' );
                return false;
            }
        }
        return $passed;
    }

    /**
     * Attach evaluated virtual options to WooCommerce cart item
     */
    public static function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        $form = self::resolve_form_for_product( intval( $product_id ) );
        if ( ! $form || empty( $_POST['pod_of_form_id'] ) ) {
            return $cart_item_data;
        }
        $raw_vals = isset( $_POST['pod_of_val'] ) && is_array( $_POST['pod_of_val'] ) ? $_POST['pod_of_val'] : array();
        $evaluated = self::evaluate_submission( intval( $product_id ), $form, $raw_vals );
        if ( ! empty( $evaluated['entries'] ) ) {
            $cart_item_data['pod_option_form_data'] = $evaluated;
            $cart_item_data['unique_pod_of_key']    = md5( wp_json_encode( $evaluated['entries'] ) );
        }
        return $cart_item_data;
    }

    /**
     * Display selected options in Cart & Checkout summary
     */
    public static function display_cart_item_data( $item_data, $cart_item ) {
        if ( empty( $cart_item['pod_option_form_data']['entries'] ) ) {
            return $item_data;
        }
        foreach ( $cart_item['pod_option_form_data']['entries'] as $entry ) {
            $item_data[] = array(
                'key'   => $entry['label'],
                'value' => $entry['value'],
            );
        }
        return $item_data;
    }

    /**
     * Dynamically override cart item unit price from server-evaluated virtual options
     */
    public static function calculate_cart_totals( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }
        foreach ( $cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['pod_option_form_data']['final_price'] ) ) {
                $cart_item['data']->set_price( floatval( $cart_item['pod_option_form_data']['final_price'] ) );
            }
        }
    }

    /**
     * Persist selected virtual options & semantic POD variation meta on WooCommerce Order Line Item
     */
    public static function save_order_line_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['pod_option_form_data'] ) ) {
            return;
        }
        $data = $values['pod_option_form_data'];
        $item->add_meta_data( self::ORDER_META_JSON_KEY, wp_json_encode( $data ), true );

        // Visible human-readable meta rows in WP-Admin Order & Customer Emails
        foreach ( ( $data['entries'] ?? array() ) as $entry ) {
            $item->add_meta_data( $entry['label'], $entry['value'], true );
        }

        // Standard semantic keys for seamless POD AI Hub fulfillment sync
        $sem = $data['semantic'] ?? array();
        if ( ! empty( $sem['fit'] ) ) {
            $item->add_meta_data( '_pod_variation_fit', $sem['fit'], true );
        }
        if ( ! empty( $sem['style'] ) ) {
            $item->add_meta_data( '_pod_variation_style', $sem['style'], true );
        }
        if ( ! empty( $sem['size'] ) ) {
            $item->add_meta_data( '_pod_variation_size', $sem['size'], true );
        }
        if ( ! empty( $sem['color'] ) ) {
            $item->add_meta_data( '_pod_variation_color', $sem['color'], true );
        }
        if ( ! empty( $sem['sku_prefix'] ) ) {
            $item->add_meta_data( '_pod_variation_sku_prefix', $sem['sku_prefix'], true );
        }
        $item->add_meta_data( '_pod_variation_price', $data['final_price'], true );
    }

    /**
     * Output Google Merchant Center compliant `ProductGroup` JSON-LD Schema for virtual variants
     */
    public static function output_gmc_schema_jsonld() {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) {
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
        $form = self::resolve_form_for_product( intval( $product->get_id() ) );
        if ( ! $form || empty( $form['enable_gmc_schema'] ) ) {
            return;
        }

        $base_price = floatval( $product->get_price() ?: 24.99 );
        $base_url   = get_permalink( $product->get_id() );
        $currency   = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
        $image_url  = $product->get_image_id() ? wp_get_attachment_url( $product->get_image_id() ) : '';
        $sku_root   = $product->get_sku() ?: ( 'pod-' . $product->get_id() );

        $style_opts = array();
        $size_opts  = array();
        foreach ( $form['fields'] as $f ) {
            if ( ( $f['semantic_role'] ?? '' ) === 'style' && ! empty( $f['options'] ) ) {
                $style_opts = $f['options'];
            } elseif ( ( $f['semantic_role'] ?? '' ) === 'size' && ! empty( $f['options'] ) ) {
                $size_opts = $f['options'];
            }
        }
        if ( empty( $style_opts ) ) {
            $style_opts = array( array( 'id' => 'standard', 'label' => 'Standard', 'price_mode' => 'none', 'price' => 0 ) );
        }
        if ( empty( $size_opts ) ) {
            $size_opts = array( array( 'id' => 'one-size', 'label' => 'One Size', 'price_mode' => 'none', 'price' => 0 ) );
        }

        $variants = array();
        $prices   = array();
        foreach ( array_slice( $style_opts, 0, 6 ) as $st ) {
            $st_price = ( ( $st['price_mode'] ?? '' ) === 'override_base' && floatval( $st['price'] ?? 0 ) > 0 )
                ? floatval( $st['price'] )
                : ( $base_price + floatval( $st['price'] ?? 0 ) );
            foreach ( array_slice( $size_opts, 0, 8 ) as $sz ) {
                $v_price  = max( 0.01, $st_price + floatval( $sz['price'] ?? 0 ) );
                $prices[] = $v_price;
                $v_url    = add_query_arg( array( 'pod-style' => $st['id'], 'pod-size' => $sz['id'] ), $base_url );
                $variants[] = array(
                    '@type'  => 'Product',
                    'name'   => $product->get_name() . ' - ' . $st['label'] . ' - ' . $sz['label'],
                    'sku'    => $sku_root . '-' . $st['id'] . '-' . $sz['id'],
                    'size'   => $sz['label'],
                    'image'  => $image_url,
                    'url'    => $v_url,
                    'offers' => array(
                        '@type'           => 'Offer',
                        'price'           => number_format( $v_price, 2, '.', '' ),
                        'priceCurrency'   => $currency,
                        'availability'    => 'https://schema.org/InStock',
                        'priceValidUntil' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
                        'url'             => $v_url,
                    ),
                );
            }
        }

        $schema = array(
            '@context'       => 'https://schema.org',
            '@type'          => 'ProductGroup',
            'name'           => $product->get_name(),
            'url'            => $base_url,
            'productGroupID' => $sku_root,
            'image'          => $image_url,
            'variesBy'       => array( 'https://schema.org/size' ),
            'offers'         => array(
                '@type'         => 'AggregateOffer',
                'lowPrice'      => number_format( min( $prices ), 2, '.', '' ),
                'highPrice'     => number_format( max( $prices ), 2, '.', '' ),
                'priceCurrency' => $currency,
                'offerCount'    => count( $variants ),
                'availability'  => 'https://schema.org/InStock',
            ),
            'hasVariant'     => $variants,
        );

        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
    }
}

POD_Option_Forms::init();
