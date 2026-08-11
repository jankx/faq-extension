<?php
namespace Jankx\Extensions\Faq\PostTypes;

/**
 * Registers the `jankx_faq` post type and the `jankx_faq_category`
 * taxonomy, plus the admin list columns and filters for both.
 */
class FaqPostType
{
    const POST_TYPE = 'jankx_faq';
    const TAXONOMY = 'jankx_faq_category';

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('init', [$this, 'registerTaxonomy']);
        add_filter('use_block_editor_for_post_type', [$this, 'disableGutenberg'], 10, 2);

        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'registerColumns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'renderColumn'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'registerSortableColumns']);

        add_action('restrict_manage_posts', [$this, 'renderFilters'], 10, 2);
        add_filter('parse_query', [$this, 'applyFilters']);
    }

    public function disableGutenberg(string $enabled, string $postType): bool
    {
        if ($postType === self::POST_TYPE) {
            return false;
        }
        return $enabled;
    }

    public function registerPostType(): void
    {
        if (post_type_exists(self::POST_TYPE)) {
            return;
        }

        $labels = [
            'name' => _x('Frequently Asked Questions', 'Post type general name', 'jankx'),
            'singular_name' => _x('Frequently Asked Question', 'Post type singular name', 'jankx'),
            'menu_name' => _x('FAQs', 'Admin Menu text', 'jankx'),
            'name_admin_bar' => __('FAQs', 'jankx'),
            'add_new' => __('Thêm mới', 'jankx'),
            'add_new_item' => __('Thêm mới câu hỏi', 'jankx'),
            'new_item' => __('Câu hỏi mới', 'jankx'),
            'edit_item' => __('Chỉnh sửa câu hỏi', 'jankx'),
            'view_item' => __('Xem câu hỏi', 'jankx'),
            'all_items' => __('Tất cả câu hỏi', 'jankx'),
            'search_items' => __('Tìm kiếm câu hỏi', 'jankx'),
            'parent_item_colon' => __('Câu hỏi cha:', 'jankx'),
            'not_found' => __('Không tìm thấy câu hỏi', 'jankx'),
            'not_found_in_trash' => __('Không tìm thấy câu hỏi trong thùng rác', 'jankx'),
            'featured_image' => __('Ảnh đại diện', 'jankx'),
            'set_featured_image' => __('Đặt ảnh đại diện', 'jankx'),
            'remove_featured_image' => __('Xóa ảnh đại diện', 'jankx'),
            'use_featured_image' => __('Sử dụng làm ảnh đại diện', 'jankx'),
            'archives' => __('FAQs', 'jankx'),
            'attributes' => __('Thuộc tính FAQ', 'jankx'),
            'filter_items_list' => __('Lọc danh sách câu hỏi', 'jankx'),
            'items_list_navigation' => __('Điều hướng danh sách câu hỏi', 'jankx'),
            'items_list' => __('Danh sách câu hỏi', 'jankx'),
            'item_published' => __('Câu hỏi đã xuất bản', 'jankx'),
            'item_published_privately' => __('Câu hỏi đã xuất bản riêng tư', 'jankx'),
            'item_reverted_to_draft' => __('Câu hỏi đã chuyển về bản nháp', 'jankx'),
            'item_updated' => __('Câu hỏi đã cập nhật', 'jankx'),
        ];

        $args = [
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'query_var' => 'faq',
            'rewrite' => ['slug' => 'faq'],
            'capability_type' => 'post',
            'has_archive' => false,
            'hierarchical' => false,
            'menu_position' => 30,
            'menu_icon' => 'dashicons-editor-help',
            'supports' => ['title', 'editor', 'page-attributes', 'custom-fields'],
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    public function registerTaxonomy(): void
    {
        if (taxonomy_exists(self::TAXONOMY)) {
            return;
        }

        $labels = [
            'name' => _x('Danh mục FAQ', 'taxonomy general name', 'jankx'),
            'singular_name' => _x('Danh mục FAQ', 'taxonomy singular name', 'jankx'),
            'search_items' => __('Tìm kiếm danh mục', 'jankx'),
            'popular_items' => __('Danh mục phổ biến', 'jankx'),
            'all_items' => __('Tất cả danh mục', 'jankx'),
            'parent_item' => __('Danh mục cha', 'jankx'),
            'parent_item_colon' => __('Danh mục cha:', 'jankx'),
            'edit_item' => __('Chỉnh sửa danh mục', 'jankx'),
            'update_item' => __('Cập nhật danh mục', 'jankx'),
            'add_new_item' => __('Thêm danh mục mới', 'jankx'),
            'new_item_name' => __('Tên danh mục mới', 'jankx'),
            'menu_name' => __('Danh mục FAQ', 'jankx'),
        ];

        $args = [
            'hierarchical' => true,
            'labels' => $labels,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'faq-category'],
        ];

        register_taxonomy(self::TAXONOMY, [self::POST_TYPE], $args);
    }

    /* ---------------------------------------------------------------------
     * Admin list columns
     * ------------------------------------------------------------------- */

    public function registerColumns(array $columns): array
    {
        $newColumns = [];
        foreach ($columns as $key => $label) {
            $newColumns[$key] = $label;
            if ($key === 'title') {
                $newColumns['jankx_faq_order'] = __('Thứ tự', 'jankx');
            }
        }
        $newColumns['jankx_faq_overview'] = __('My Account', 'jankx');

        return $newColumns;
    }

    public function renderColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'jankx_faq_order':
                echo esc_html((string) get_post_field('menu_order', $postId));
                break;

            case 'jankx_faq_overview':
                $show = get_post_meta($postId, \Jankx\Extensions\Faq\Meta\FaqMetaBoxes::META_SHOW_IN_OVERVIEW, true);
                if ($show === '0') {
                    esc_html_e('Ẩn', 'jankx');
                } else {
                    esc_html_e('Hiển thị', 'jankx');
                }
                break;
        }
    }

    public function registerSortableColumns(array $columns): array
    {
        $columns['jankx_faq_order'] = 'menu_order';
        $columns['title'] = 'title';

        return $columns;
    }

    /* ---------------------------------------------------------------------
     * Admin filters
     * ------------------------------------------------------------------- */

    public function renderFilters(string $postType, string $which): void
    {
        if ($postType !== self::POST_TYPE || $which !== 'top') {
            return;
        }

        $terms = get_terms([
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
        ]);

        if (!$terms || is_wp_error($terms)) {
            return;
        }

        $current = isset($_GET['jankx_faq_cat']) ? absint($_GET['jankx_faq_cat']) : 0;
        ?>
        <select name="jankx_faq_cat">
            <option value="0" <?php selected($current, 0); ?>><?php esc_html_e('Tất cả danh mục', 'jankx'); ?></option>
            <?php foreach ($terms as $term): ?>
                <option value="<?php echo esc_attr($term->term_id); ?>" <?php selected($current, $term->term_id); ?>>
                    <?php echo esc_html($term->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function applyFilters(\WP_Query $query): void
    {
        if (!is_admin() || $query->get('post_type') !== self::POST_TYPE || !$query->is_main_query()) {
            return;
        }

        $catId = isset($_GET['jankx_faq_cat']) ? absint($_GET['jankx_faq_cat']) : 0;
        if ($catId) {
            $taxQuery = (array) $query->get('tax_query');
            $taxQuery[] = [
                'taxonomy' => self::TAXONOMY,
                'field' => 'term_id',
                'terms' => [$catId],
            ];
            $query->set('tax_query', $taxQuery);
        }
    }
}
