# Store AI Connector — WordPress Plugin Changelog

## Version 2.5.5 - 2026-11-02

### Critical Fixes & Enhancements
- **Virtual Variants (`POD_Option_Forms` & `POD_Option_Forms_Admin`)**: Wrapped class definitions in `if ( ! class_exists( ..., false ) ) : ... endif;` so PHP 8+ runtime class binding always executes `POD_Option_Forms::init()` and `POD_Option_Forms_Admin::init()` (`woocommerce_before_add_to_cart_button` priority 14, `"From $XX.XX"` shop archive badge, and WP-Admin product tab).
- **Fleece Preset (`preset_sweatshirt_hoodie`)**: Added dedicated virtual variant preset for `sweatshirts`, `crewnecks`, `hoodies`, and `custom-team-hoodies` so sweatshirt base prices are never double-charged, plus expanded category/title fallback matching.
- **Structured SEO Product Description Styling (`.pod-seo-description`)**: Added clean section headings, bullet spacing, FAQ cards, and CTA layout styles enqueued across all WooCommerce product pages.

---

## Version 2.5.3 - 2026-11-02

### Virtual Cloudflare R2 WebP Attachments & SEO Migration
- Added virtual R2 attachment support (`_pod_external_r2_url`) and `POST /wp-json/sac/v1/migrate-images-to-r2` so WooCommerce serves `< 1 MB` SEO-named `.webp` images directly from R2 without storing binaries in `wp-content/uploads/`.

---

## Version 2.4.0 - 2026-06-04

### Multi-Preset Custom Fields
- Support merging multiple Personalization presets (e.g. Custom Photo + Custom Name) on a single product.
