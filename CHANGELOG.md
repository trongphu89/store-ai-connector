# POD AI Connector - WordPress Plugin Changelog

## Version 2.4.0 - 2026-06-04

### ✨ Multi-Preset Custom Fields

#### Gán nhiều Custom Field Preset cho 1 sản phẩm
- **Trước**: Chỉ gán được 1 preset (Custom Name HOẶC Custom Photo)
- **Sau**: Gán được nhiều preset cùng lúc (Custom Name + Custom Photo + ...)
- **Backwards-compatible**: Sản phẩm cũ (single preset) tự động migrate sang format mới

#### Cách hoạt động
- Plugin lưu `presets` array trong `_pod_custom_fields` meta
- Fields từ tất cả presets được **merge** → hiển thị tất cả trên product page
- Extra fee được **cộng dồn** từ tất cả presets
- Gán lại cùng preset_id → **thay thế** preset đó (không duplicate)

#### Frontend
- Tất cả fields từ mọi preset hiển thị trong 1 form Personalization
- Validation, cart, order, email hoạt động bình thường (không cần thay đổi)

### ⚠️ Upgrade Notes
1. Update plugin zip trên WordPress
2. Không cần xóa custom fields cũ — tự động migrate
3. Test: Gán 2 preset khác nhau cho 1 sản phẩm → kiểm tra product page

---

## Version 2.2.3 - 2026-03-04

### 🐛 Critical Fixes

#### Gallery Update Cache Issue
- **Added**: Auto cache clearing after gallery update
- **Functions**: 
  - `wc_delete_product_transients()` - Clear WooCommerce transients
  - `wp_cache_delete()` - Clear WordPress object cache
- **Applied to**:
  - `pod_connector_update_product_gallery()` - Single product update
  - `pod_connector_bulk_update_galleries()` - Bulk update
- **Impact**: Gallery changes now visible immediately on frontend

### 📝 Code Changes

```php
// Added to both single and bulk update functions
wc_delete_product_transients( $product_id );
wp_cache_delete( 'product-' . $product_id, 'products' );
```

### ⚠️ Upgrade Notes

**From v2.2.2 or earlier**:
1. Backup current plugin
2. Deactivate old version
3. Delete old plugin files
4. Upload new version
5. Activate plugin
6. Test gallery update with 1 product

**Important**: This update is REQUIRED for gallery update feature to work properly.

---

## Version 2.2.2 - Previous Release

### Features
- Bulk gallery update endpoint
- Custom fields management
- POD Variations support
- GMC compliance features

---

## Installation

1. Upload `pod-ai-connector` folder to `/wp-content/plugins/`
2. Activate plugin through WordPress admin
3. Configure API keys in POD AI Designer Pro app
4. Test connection

---

## Requirements

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+
- POD AI Designer Pro v2.2.3+

---

## Support

For issues or questions:
1. Check documentation in POD AI Designer Pro app
2. Review `FIX_AI_DESIGN_MOCKUP_AND_WOOCOMMERCE.md`
3. Test with default theme (Storefront)
4. Clear all caches (WooCommerce, WordPress, Plugin, CDN)
