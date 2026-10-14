<?php
// 2.1.13 test: verify no-cache headers and purge endpoint exist
define('ABSPATH', '/tmp/');
if (!function_exists('add_action')) { function add_action($a,$b,$c=10,$d=1) {} }
if (!function_exists('add_filter')) { function add_filter($a,$b,$c=10,$d=1) {} }
if (!function_exists('nocache_headers')) { function nocache_headers() { echo "nocache_headers called\n"; } }

// Check store-ai-connector.php contains the changes
$c = file_get_contents('/tmp/sac-2113/store-ai-connector.php');
assert(strpos($c, "X-LiteSpeed-Cache-Control: no-cache") !== false, "Should have LiteSpeed no-cache header");
assert(strpos($c, "sac_purge_cache") !== false, "Should have purge function");
assert(strpos($c, "/purge-cache") !== false, "Should have purge route");
assert(strpos($c, "litespeed_purge_all") !== false, "Should purge litespeed");
echo "✓ store-ai-connector.php has no-cache headers and purge endpoint\n";

// Check feed has no-cache
$f = file_get_contents('/tmp/sac-2113/includes/class-pod-gmc-feed.php');
assert(strpos($f, "X-LiteSpeed-Cache-Control: no-cache") !== false, "Feed should have no-cache");
echo "✓ Feed has no-cache headers\n";

// Check version
assert(strpos($c, "2.1.13") !== false, "Version should be 2.1.13");
echo "✓ Version 2.1.13\n";

echo "\nALL PASS\n";
