# FAQ Extension

Quản lý các câu hỏi thường gặp (Frequently Asked Questions) cho website
nibitour (Jankx). Dữ liệu được quản lý bằng **post type** `jankx_faq` và chia
theo **danh mục** bằng taxonomy `jankx_faq_category`.

## Kiến trúc

```
faq/
├── FaqExtension.php            # Đăng ký hooks, CPT, taxonomy, settings, integration
├── src/
│   ├── Faq.php                 # Model: question, answer, categories, visibility
│   ├── FaqManager.php          # Query FAQ + items cho My Account overview
│   ├── PostTypes/FaqPostType.php    # CPT jankx_faq + taxonomy + cột/lọc admin
│   ├── Meta/FaqMetaBoxes.php        # Meta box bật/tắt hiển thị trong My Account
│   ├── Admin/SettingsPage.php       # Options tiêm FAQ vào overview
│   └── Integration/MyAccountIntegration.php  # Bridge vào overview của my-account
├── manifest.json
└── composer.json
```

## Cách quản lý dữ liệu

- **Câu hỏi** → `post_title`.
- **Trả lời** → `post_content` (hỗ trợ định dạng / shortcode qua `the_content`).
- **Danh mục** → taxonomy `jankx_faq_category` (phân cấp được).
- **Thứ tự** → trường Order (page-attributes) trong khung bên phải màn hình sửa.
- **Hiển thị trong My Account** → checkbox trong meta box "Hiển thị" ở cột bên
  phải (`_faq_show_in_overview`, mặc định bật).

## Tích hợp My Account

Nếu extension `my-account` được cài đặt, FAQ sẽ thay thế mục FAQ hardcode trong
trang **overview** bằng dữ liệu động:

1. `OverviewTab::renderQA` (my-account) không còn chứa FAQ hardcode — nó đọc qua
   filter `jankx/my_account/overview/qa/faqs` và bỏ qua mục nếu không có dữ liệu.
2. `MyAccountIntegration` của extension FAQ lắng nghe filter này và trả về danh
   sách FAQ theo **options** trong trang **Jankx Theme Options → FAQ**:
   - Bật/tắt hiển thị FAQ trong My Account (`jankx_faq_overview_enabled`).
   - Tiêu đề mục FAQ (`jankx_faq_overview_title`, để trống = mặc định).
   - Số lượng tối đa (`jankx_faq_overview_limit`, 0 = tất cả).
   - Chỉ hiển thị các danh mục được chọn (`jankx_faq_overview_categories`).
3. Có thể bật/tắt từng FAQ riêng lẻ qua meta box trên màn hình sửa FAQ.

Các hook tùy biến:

- `jankx/my_account/overview/qa/faqs` — bộ lọc danh sách `['question', 'answer']`.
- `jankx/my_account/overview/qa/title` — bộ lọc tiêu đề mục.

## API cho extension khác

```php
use Jankx\Extensions\Faq\FaqManager;

// Lấy toàn bộ FAQ đã publish, theo thứ tự.
$faqs = FaqManager::get_faqs();

// Lấy FAQ theo danh mục (mảng term_id).
$faqs = FaqManager::get_faqs(['category' => [12, 15]]);

// Lấy đúng dữ liệu đang được hiển thị trong My Account overview.
$items = FaqManager::get_overview_faqs();
```

Mỗi `Faq` có: `getId()`, `getQuestion()`, `getAnswer()`, `getCategories()`,
`getOrder()`, `isVisibleInOverview()`, `toArray()`.
