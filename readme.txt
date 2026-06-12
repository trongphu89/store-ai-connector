=== POD AI Designer Connector ===
Contributors: dieptrader
Tags: pod, print-on-demand, woocommerce, api, bulk-operations
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast & secure connector for POD AI Designer Pro app. Optimized bulk operations for products, posts, and media.

== Description ==

POD AI Designer Connector là plugin kết nối giữa website WordPress/WooCommerce và ứng dụng POD AI Designer Pro.

**Tính năng chính:**

* 🚀 **Bulk Operations** - Fetch/upload/create/update/delete hàng loạt trong 1 request
* 🔐 **HMAC Security** - Xác thực bảo mật với API Key + Secret + Signature
* ⚡ **Optimized Performance** - Giảm số lượng HTTP requests, tăng tốc độ xử lý
* 📦 **WooCommerce Integration** - Hỗ trợ đầy đủ products, categories, tags
* 📝 **WordPress Posts** - Quản lý bài viết blog
* 🖼️ **Media Library** - Upload và quản lý ảnh

**API Endpoints:**

* `/wp-json/pod-connector/v1/health` - Kiểm tra kết nối
* `/wp-json/pod-connector/v1/bulk-fetch` - Lấy products, posts, media cùng lúc
* `/wp-json/pod-connector/v1/bulk-upload` - Upload nhiều ảnh
* `/wp-json/pod-connector/v1/bulk-products` - Tạo nhiều sản phẩm
* `/wp-json/pod-connector/v1/bulk-update` - Cập nhật nhiều sản phẩm
* `/wp-json/pod-connector/v1/bulk-delete` - Xóa hàng loạt

== Installation ==

1. Upload thư mục `pod-ai-connector` vào `/wp-content/plugins/`
2. Kích hoạt plugin trong WordPress Admin > Plugins
3. Vào Settings > POD AI Connector để lấy API credentials
4. Copy credentials vào app POD AI Designer Pro

== Frequently Asked Questions ==

= Plugin có yêu cầu WooCommerce không? =

Không bắt buộc. Plugin vẫn hoạt động với WordPress posts và media. Tuy nhiên, để quản lý sản phẩm thì cần WooCommerce.

= Làm sao để bảo mật API? =

Plugin sử dụng HMAC-SHA256 signature với timestamp. Mỗi request cần có:
- X-POD-API-Key: API key
- X-POD-Signature: HMAC signature
- X-POD-Timestamp: Unix timestamp (trong vòng 5 phút)

= Tôi có thể tạo lại API key không? =

Có. Vào Settings > POD AI Connector và click "Tạo key mới". Lưu ý: key cũ sẽ không còn hoạt động.

== Changelog ==

= 1.0.0 =
* Initial release
* Bulk fetch (products, posts, media, categories)
* Bulk upload images
* Bulk create/update/delete products
* HMAC signature authentication
* Admin settings page

== Upgrade Notice ==

= 1.0.0 =
First release.
