<?php
/**
 * test_218.php - Real PHP test for store-ai-connector 2.1.8
 *
 * Fixture mimics product 211 (per STORE_REVIEW item 1):
 *   - WC variable product, attributes Size (S-XXL) x Color (3 colors)
 *   - _pod_variations meta exists but has EMPTY sizes (and no styles)
 *   - Expected: 15 feed items (5 sizes x 3 colors), none dropped
 *   - id = <SKU>-<size>-<color>, item_group_id = parent SKU,
 *     parent price, link ?attribute_size=..&attribute_color=..
 *
 * Usage: php test_218.php [path-to-class-pod-gmc-feed.php]
 * Exit 0 = all pass, 1 = failure.
 */

// ---------- WordPress/WooCommerce stubs (test doubles, no WP needed) ----------
define( 'ABSPATH', '/tmp/sac-push/' );

function add_action( ...$a ) {}
function add_filter( ...$a ) {}
function get_option( $k, $d = '' ) { return $d; }
function get_post_meta( $id, $key, $single = false ) {
    $m = $GLOBALS['FAKE_META'][ $id ][ $key ] ?? null;
    if ( $single ) return $m === null ? '' : $m;
    return $m === null ? array() : array( $m );
}
function get_permalink( $id ) { return 'https://example.com/product/tailored-wool-overcoat/'; }
function wp_parse_url( $u ) { return parse_url( $u ); }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function wp_get_attachment_url( $id ) { return $id ? 'https://example.com/img/' . $id . '.jpg' : false; }
function get_bloginfo( $k ) { return 'Test Store'; }
function get_woocommerce_currency() { return 'USD'; }
function get_the_terms( $id, $tax ) { return false; }
function is_wp_error( $t ) { return false; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return str_replace( '&', '&amp;', (string) $s ); }
function sanitize_title( $t ) {
    $t = strtolower( (string) $t );
    $t = preg_replace( '/[^a-z0-9]+/', '-', $t );
    return trim( $t, '-' );
}
function add_query_arg( $args, $url ) {
    $q = array();
    foreach ( (array) $args as $k => $v ) $q[] = rawurlencode( $k ) . '=' . rawurlencode( $v );
    return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . implode( '&', $q );
}

// ---------- Fake WC product 211 ----------
class FakeProduct211 {
    public $price = '49.99';
    public $attrs = array( 'pa_size' => 'S, M, L, XL, XXL', 'pa_color' => 'Black, White, Red' );
    public function get_id() { return 211; }
    public function get_name() { return 'Tailored Wool Overcoat'; }
    public function get_sku() { return 'JAC-TMT-946'; }
    public function get_price() { return $this->price; }
    public function get_regular_price() { return $this->price; }
    public function get_sale_price() { return ''; }
    public function get_short_description() { return 'A tailored wool overcoat.'; }
    public function get_description() { return 'Full description here.'; }
    public function get_image_id() { return 101; }
    public function get_gallery_image_ids() { return array(); }
    public function is_in_stock() { return true; }
    public function is_type( $t ) { return $t === 'variable'; }
    public function get_attribute( $name ) { return $this->attrs[ $name ] ?? ''; }
}

$feed_file = $argv[1] ?? '/tmp/sac-push/includes/class-pod-gmc-feed.php';
require $feed_file;

$settings = array(
    'default_gender'          => 'male',
    'default_age_group'       => 'adult',
    'default_color'           => 'Multicolor',
    'default_brand'           => 'TestBrand',
    'default_google_category' => 'Apparel & Accessories > Clothing > Outerwear > Coats & Jackets',
);

function run_feed( $product, $settings, $meta ) {
    $GLOBALS['FAKE_META'] = array( $product->get_id() => $meta );
    $feed = new POD_GMC_Feed();
    $m = new ReflectionMethod( 'POD_GMC_Feed', 'output_product_items' );
    $m->setAccessible( true );
    ob_start();
    $m->invoke( $feed, $product, $settings );
    return ob_get_clean();
}

function items_of( $xml ) {
    preg_match_all( '#<item>(.*?)</item>#s', $xml, $mm );
    return $mm[1];
}
function tag_of( $item, $tag ) {
    return preg_match( '#<' . preg_quote( $tag, '#' ) . '>(.*?)</' . preg_quote( $tag, '#' ) . '>#s', $item, $mm ) ? $mm[1] : null;
}

$fail = 0;
function check( $label, $cond, $detail = '' ) {
    global $fail;
    if ( $cond ) { echo "PASS: $label\n"; }
    else { $fail++; echo "FAIL: $label" . ( $detail ? " -- $detail" : '' ) . "\n"; }
}

// ================= Case 1: product 211 fixture -> 15 items =================
$meta211 = array( '_pod_variations' => array( 'useSharedSizesColors' => true, 'sizes' => array(), 'colors' => array() ) );
$xml = run_feed( new FakeProduct211(), $settings, $meta211 );
$items = items_of( $xml );

check( '211: exactly 15 items (5 sizes x 3 colors)', count( $items ) === 15, 'got ' . count( $items ) );

$ids = array();
foreach ( $items as $it ) $ids[] = tag_of( $it, 'g:id' );
check( '211: first id = JAC-TMT-946-s-black', ( $ids[0] ?? '' ) === 'JAC-TMT-946-s-black', 'got ' . ( $ids[0] ?? '?' ) );
check( '211: last id = JAC-TMT-946-xxl-red', ( $ids[14] ?? '' ) === 'JAC-TMT-946-xxl-red', 'got ' . ( $ids[14] ?? '?' ) );
check( '211: all 15 ids unique', count( array_unique( $ids ) ) === 15 );

$ok_group = $ok_price = $ok_link = $ok_size = $ok_color = true;
foreach ( $items as $it ) {
    if ( tag_of( $it, 'g:item_group_id' ) !== 'JAC-TMT-946' ) $ok_group = false;
    if ( tag_of( $it, 'g:price' ) !== '49.99 USD' ) $ok_price = false;
    $link = tag_of( $it, 'link' );
    if ( strpos( $link, 'attribute_size=' ) === false || strpos( $link, 'attribute_color=' ) === false ) $ok_link = false;
    if ( tag_of( $it, 'g:size' ) === null || tag_of( $it, 'g:size' ) === '' ) $ok_size = false;
    if ( tag_of( $it, 'g:color' ) === null || tag_of( $it, 'g:color' ) === '' ) $ok_color = false;
}
check( '211: item_group_id = parent SKU on all items', $ok_group );
check( '211: price = 49.99 USD (parent price) on all items', $ok_price );
check( '211: link has ?attribute_size=..&attribute_color=.. on all items', $ok_link );
check( '211: g:size present on all items', $ok_size );
check( '211: g:color present on all items', $ok_color );
check( '211: title "Tailored Wool Overcoat - S - Black" present',
    strpos( $xml, 'Tailored Wool Overcoat - S - Black' ) !== false );

// ================= Case 2: zero price -> skipped (GMC invalid) =================
$p2 = new FakeProduct211(); $p2->price = '0';
check( 'zero price -> 0 items (skipped)', count( items_of( run_feed( $p2, $settings, $meta211 ) ) ) === 0 );

// ================= Case 3: no size/color attributes -> 1 item, default color =================
$p3 = new FakeProduct211(); $p3->attrs = array();
$items3 = items_of( run_feed( $p3, $settings, $meta211 ) );
check( 'no attributes -> 1 item (nothing dropped)', count( $items3 ) === 1, 'got ' . count( $items3 ) );
if ( count( $items3 ) === 1 ) {
    check( 'no attributes -> id = parent SKU', tag_of( $items3[0], 'g:id' ) === 'JAC-TMT-946', tag_of( $items3[0], 'g:id' ) );
    check( 'no attributes -> color = default Multicolor', tag_of( $items3[0], 'g:color' ) === 'Multicolor' );
}

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit( $fail === 0 ? 0 : 1 );
