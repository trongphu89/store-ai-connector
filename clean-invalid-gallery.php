<?php
/**
 * Clean Invalid Gallery IDs from WooCommerce Products
 * 
 * Run once: https://your-site.com/wp-content/plugins/pod-ai-connector/clean-invalid-gallery.php
 * Then delete this file for security
 * 
 * @package POD_AI_Connector
 */

// Load WordPress
require_once('../../../wp-load.php');

// Security check
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator to run this script.');
}

// Prevent timeout
set_time_limit(300);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Clean Invalid Gallery IDs</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .summary { background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>🧹 Clean Invalid Gallery IDs</h1>
    <p class="info">This script will remove invalid attachment IDs from product galleries.</p>
    
    <?php
    
    $action = isset($_GET['action']) ? $_GET['action'] : 'scan';
    
    if ($action === 'scan') {
        // Scan mode - just show what would be cleaned
        echo '<h2>📊 Scan Results (Preview Mode)</h2>';
        echo '<p>No changes will be made. Click "Clean Now" to apply fixes.</p>';
        
        $products = wc_get_products(array(
            'limit' => -1,
            'status' => 'any'
        ));
        
        $issues = array();
        $total_products = 0;
        $products_with_issues = 0;
        
        foreach ($products as $product) {
            $gallery_ids = $product->get_gallery_image_ids();
            
            if (empty($gallery_ids)) continue;
            
            $total_products++;
            $invalid_ids = array();
            
            foreach ($gallery_ids as $img_id) {
                if (!wp_attachment_is_image($img_id)) {
                    $invalid_ids[] = $img_id;
                }
            }
            
            if (count($invalid_ids) > 0) {
                $products_with_issues++;
                $issues[] = array(
                    'product_id' => $product->get_id(),
                    'product_name' => $product->get_name(),
                    'invalid_ids' => $invalid_ids,
                    'total_gallery' => count($gallery_ids),
                    'valid_count' => count($gallery_ids) - count($invalid_ids)
                );
            }
        }
        
        if (count($issues) > 0) {
            echo '<table>';
            echo '<tr><th>Product ID</th><th>Product Name</th><th>Invalid IDs</th><th>Gallery Count</th><th>Will Keep</th></tr>';
            
            foreach ($issues as $issue) {
                echo '<tr>';
                echo '<td>' . $issue['product_id'] . '</td>';
                echo '<td>' . esc_html($issue['product_name']) . '</td>';
                echo '<td class="error">' . implode(', ', $issue['invalid_ids']) . '</td>';
                echo '<td>' . $issue['total_gallery'] . '</td>';
                echo '<td class="success">' . $issue['valid_count'] . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            
            echo '<div class="summary">';
            echo '<h3>Summary</h3>';
            echo '<p>Total products with gallery: <strong>' . $total_products . '</strong></p>';
            echo '<p>Products with invalid IDs: <strong class="error">' . $products_with_issues . '</strong></p>';
            echo '<p>Total invalid IDs to remove: <strong class="error">' . array_sum(array_column($issues, 'total_gallery')) - array_sum(array_column($issues, 'valid_count')) . '</strong></p>';
            echo '</div>';
            
            echo '<p><a href="?action=clean" style="display: inline-block; background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">🧹 Clean Now</a></p>';
            
        } else {
            echo '<p class="success">✅ No invalid gallery IDs found! All products are clean.</p>';
        }
        
        echo '<div class="summary">';
        echo '<h3>What are invalid IDs?</h3>';
        echo '<ul>';
        echo '<li>Attachment IDs that don\'t exist in the database</li>';
        echo '<li>Attachment IDs that are not images (PDFs, videos, etc.)</li>';
        echo '<li>Attachment IDs that have been deleted (trash)</li>';
        echo '</ul>';
        echo '</div>';
        
    } elseif ($action === 'clean') {
        // Clean mode - actually fix the issues
        echo '<h2>🧹 Cleaning Invalid Gallery IDs...</h2>';
        
        $products = wc_get_products(array(
            'limit' => -1,
            'status' => 'any'
        ));
        
        $cleaned = 0;
        $total = 0;
        $total_removed = 0;
        
        echo '<table>';
        echo '<tr><th>Product ID</th><th>Product Name</th><th>Removed IDs</th><th>Status</th></tr>';
        
        foreach ($products as $product) {
            $gallery_ids = $product->get_gallery_image_ids();
            
            if (empty($gallery_ids)) continue;
            
            $total++;
            $valid_ids = array();
            $removed_ids = array();
            
            foreach ($gallery_ids as $img_id) {
                if (wp_attachment_is_image($img_id)) {
                    $valid_ids[] = $img_id;
                } else {
                    $removed_ids[] = $img_id;
                }
            }
            
            if (count($removed_ids) > 0) {
                $product->set_gallery_image_ids($valid_ids);
                $product->save();
                
                // Clear cache
                wc_delete_product_transients($product->get_id());
                wp_cache_delete('product-' . $product->get_id(), 'products');
                
                $cleaned++;
                $total_removed += count($removed_ids);
                
                echo '<tr>';
                echo '<td>' . $product->get_id() . '</td>';
                echo '<td>' . esc_html($product->get_name()) . '</td>';
                echo '<td class="error">' . implode(', ', $removed_ids) . '</td>';
                echo '<td class="success">✅ Cleaned</td>';
                echo '</tr>';
            }
        }
        
        echo '</table>';
        
        echo '<div class="summary">';
        echo '<h3>✅ Cleaning Complete!</h3>';
        echo '<p>Total products with gallery: <strong>' . $total . '</strong></p>';
        echo '<p>Products cleaned: <strong class="success">' . $cleaned . '</strong></p>';
        echo '<p>Total invalid IDs removed: <strong class="error">' . $total_removed . '</strong></p>';
        echo '</div>';
        
        echo '<p class="info">💡 You can now test uploading new gallery images. They should work correctly.</p>';
        echo '<p class="error">⚠️ Remember to delete this file (clean-invalid-gallery.php) for security!</p>';
    }
    
    ?>
    
    <hr>
    <p><a href="?action=scan">🔍 Scan Again</a> | <a href="<?php echo admin_url('admin.php?page=wc-status&tab=tools'); ?>">WooCommerce Tools</a></p>
    
</body>
</html>
