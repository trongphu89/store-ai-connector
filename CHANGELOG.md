# POD AI Connector - WordPress Plugin Changelog
## Version 2.1.7 - 2026-10-13 - DO NOT USE

**Bản lỗi, không phát hành.** Sửa sai hướng: bỏ hẳn item Apparel không có size → feed rỗng với store may mặc. Đã thay bằng 2.1.8.

## Version 2.1.8 - 2026-10-13

Khi nhánh POD không có sizes: lấy size/color từ attribute sản phẩm (pa_size/size, pa_color/color), xuất 1 item cho mỗi tổ hợp: id = <SKU>-<size>-<color>, item_group_id = SKU cha, giá cha, link ?attribute_size=…&attribute_color=…. Không bỏ item nào.

## Version 2.1.6 - 2026-10-13

B3: output_item prints g:sale_price; regular/sale for simple branch. B4: GTIN/MPN for simple. B5: variation_title().

## Version 2.1.5 - 2026-10-13

B1: variation link uses get_permalink(). B2: item_group_id fallback to parent ID. B3: g:price=regular, g:sale_price when on sale.

## Version 2.1.4 - 2026-10-13

### New
- 22.2.2: Feed supports WC_Product_Variable - each variation 1 item with size/color, item_group_id.
- 22.2.4: GTIN/MPN from _global_unique_id/_gtin/_mpn meta; identifier_exists=no only when no GTIN/MPN.

## Version 2.1.3 - 2026-10-13

### Fixed
- Feed: added g:identifier_exists (no) for GMC compliance.

## Version 2.1.2 - 2026-10-13

### Fixed
- 20.2.1: sac_attachment_in_use() uses FIND_IN_SET for _product_image_gallery, checks post_content for wp-image-<id>.
- 20.2.2: build-release-zip.sh creates release zip with store-ai-connector-main root dir, excludes dev files.

## Version 2.1.1 - 2026-10-13

### Fixed
- 19.2.1: /bulk-delete only deletes attachments when force=true AND not used elsewhere.
- 19.2.2: /upsert-page only accepts publish/draft/private status.

## Version 2.1.0 - 2026-10-13

### New REST endpoints (sac/v1)
- `POST /site-settings`, `POST /upsert-page`, `POST /rename-term`.

### Schema filters
- `pod_seo_output_organization`, `pod_seo_organization_schema`, `pod_seo_output_product`.

### Other
- `/bulk-delete`: `force` param (default false = trash).
- Feed: `include_all_products` from `pod_gmc_feed_include_all`.

## Version 2.4.0 - 2026-06-04 (old numbering, actual header was 2.0.0)

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
