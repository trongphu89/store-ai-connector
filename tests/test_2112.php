<?php
// 2.1.12 test: real product 211 fixture + numeric custom attrs
error_reporting(E_ALL & ~E_DEPRECATED);
define('ABSPATH', '/tmp/');

// Mock WP functions
if (!function_exists('sanitize_title')) {
    function sanitize_title($t) { return strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($t))); }
}
if (!function_exists('wc_get_product_terms')) {
    function wc_get_product_terms($pid, $tax, $args) { return array(); }
}
if (!function_exists('get_term')) {
    function get_term($id) { return false; }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($t) { return false; }
}

// Mock attribute class
class MockAttr {
    private $name; private $options; private $is_tax;
    public function __construct($name, $options, $is_tax = false) {
        $this->name = $name; $this->options = $options; $this->is_tax = $is_tax;
    }
    public function get_name() { return $this->name; }
    public function get_options() { return $this->options; }
    public function is_taxonomy() { return $this->is_tax; }
}

// Mock product class
class MockProduct {
    private $attrs; private $id;
    public function __construct($id, $attrs) { $this->id = $id; $this->attrs = $attrs; }
    public function get_id() { return $this->id; }
    public function get_attributes() { return $this->attrs; }
}

// Test the helper function logic directly
if (!function_exists('add_action')) { function add_action($a,$b,$c=10,$d=1) {} }
if (!function_exists('add_filter')) { function add_filter($a,$b,$c=10,$d=1) {} }
require_once '/tmp/sac-2112/includes/class-pod-gmc-feed.php';

// Use reflection to test the private method
$feed = new POD_GMC_Feed();
$ref = new ReflectionClass($feed);
$method = $ref->getMethod('get_expand_attr_values');
$method->setAccessible(true);

// Test 1: Real product 211 - custom attributes (size: S,M,L,XL,XXL / color: Blue Denim,Black,Olive Green)
echo "=== Test 1: Product 211 (custom attrs) ===\n";
$prod211 = new MockProduct(211, array(
    'size' => new MockAttr('size', array('S', 'M', 'L', 'XL', 'XXL'), false),
    'color' => new MockAttr('color', array('Blue Denim', 'Black', 'Olive Green'), false),
));
$sv = $method->invoke($feed, $prod211, 'size');
$cv = $method->invoke($feed, $prod211, 'color');
echo "Size names: " . implode(', ', $sv['names']) . "\n";
echo "Size slugs: " . implode(', ', $sv['slugs']) . "\n";
echo "Size attr: " . $sv['attr_name'] . "\n";
echo "Color names: " . implode(', ', $cv['names']) . "\n";
echo "Color slugs: " . implode(', ', $cv['slugs']) . "\n";
echo "Color attr: " . $cv['attr_name'] . "\n";
$exp_size = count($sv['names']) * count($cv['names']);
echo "Expected items: $exp_size (5x3=15)\n";
assert($exp_size === 15, "Should be 15 items");
assert($sv['attr_name'] === 'size', "Should use 'size' not 'pa_size'");
assert($cv['attr_name'] === 'color', "Should use 'color' not 'pa_color'");
echo "PASS\n\n";

// Test 2: Numeric custom attributes (38, 40, 42) - should NOT be treated as term IDs
echo "=== Test 2: Numeric custom attrs (38/40/42) ===\n";
$prod_num = new MockProduct(999, array(
    'size' => new MockAttr('size', array('38', '40', '42'), false), // is_taxonomy=false!
));
$nv = $method->invoke($feed, $prod_num, 'size');
echo "Names: " . implode(', ', $nv['names']) . "\n";
echo "Attr: " . $nv['attr_name'] . "\n";
assert($nv['names'] === array('38', '40', '42'), "Numeric strings should be preserved");
assert($nv['attr_name'] === 'size', "Should not guess term");
echo "PASS - numeric custom options NOT mis-looked-up as terms\n\n";

echo "ALL PASS\n";
