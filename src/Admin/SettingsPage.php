<?php
namespace Jankx\Extensions\Faq\Admin;

use Jankx\Extensions\Faq\FaqManager;
use Jankx\Extensions\Faq\PostTypes\FaqPostType;

/**
 * Settings page for the FAQ extension.
 *
 * The options here control how FAQs are injected into the My Account overview
 * when the my-account extension is installed.
 */
class SettingsPage
{
    const PAGE_SLUG = 'jankx-faq-settings';
    const OPTION_GROUP = 'jankx_faq_settings';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 25);
        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'jankx-theme-options',
            __('FAQ Settings', 'jankx'),
            __('FAQ', 'jankx'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting(self::OPTION_GROUP, FaqManager::OPTION_OVERVIEW_ENABLED, [
            'default'          => '1',
            'sanitize_callback' => 'absint',
        ]);

        register_setting(self::OPTION_GROUP, FaqManager::OPTION_OVERVIEW_TITLE, [
            'default'          => '',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        register_setting(self::OPTION_GROUP, FaqManager::OPTION_OVERVIEW_LIMIT, [
            'default'          => '5',
            'sanitize_callback' => 'absint',
        ]);

        register_setting(self::OPTION_GROUP, FaqManager::OPTION_OVERVIEW_CATEGORIES, [
            'default'          => [],
            'sanitize_callback' => function ($value) {
                return array_map('absint', array_filter((array) $value));
            },
        ]);
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $myAccountInstalled = class_exists('\Jankx\Extensions\MyAccount\MyAccountExtension');
        $categories = get_terms([
            'taxonomy'   => FaqPostType::TAXONOMY,
            'hide_empty' => false,
        ]);
        $selectedCategories = (array) get_option(FaqManager::OPTION_OVERVIEW_CATEGORIES, []);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Cài đặt FAQ', 'jankx'); ?></h1>
            <p class="description">
                <?php esc_html_e('Quản lý câu hỏi thường gặp và tích hợp FAQ vào trang My Account.', 'jankx'); ?>
            </p>

            <?php if (!$myAccountInstalled) : ?>
                <div class="notice notice-info">
                    <p>
                        <?php esc_html_e('Extension My Account chưa được cài đặt. FAQ vẫn được quản lý bình thường, nhưng sẽ không được tiêm vào trang My Account.', 'jankx'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php" style="max-width: 700px; margin-top: 20px;">
                <?php settings_fields(self::OPTION_GROUP); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="jankx_faq_overview_enabled">
                                <?php esc_html_e('Hiển thị FAQ trong My Account', 'jankx'); ?>
                            </label>
                        </th>
                        <td>
                            <select id="jankx_faq_overview_enabled"
                                    name="<?php echo esc_attr(FaqManager::OPTION_OVERVIEW_ENABLED); ?>"
                                    class="regular-text">
                                <option value="1" <?php selected(get_option(FaqManager::OPTION_OVERVIEW_ENABLED, 1), '1'); ?>>
                                    <?php esc_html_e('Bật', 'jankx'); ?>
                                </option>
                                <option value="0" <?php selected(get_option(FaqManager::OPTION_OVERVIEW_ENABLED, 1), '0'); ?>>
                                    <?php esc_html_e('Tắt', 'jankx'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Bật để hiển thị mục FAQ trong trang tổng quan (overview) của My Account.', 'jankx'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="jankx_faq_overview_title">
                                <?php esc_html_e('Tiêu đề mục FAQ', 'jankx'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="text"
                                   id="jankx_faq_overview_title"
                                   name="<?php echo esc_attr(FaqManager::OPTION_OVERVIEW_TITLE); ?>"
                                   value="<?php echo esc_attr(get_option(FaqManager::OPTION_OVERVIEW_TITLE, '')); ?>"
                                   class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Để trống để dùng tiêu đề mặc định "Frequently Asked Questions".', 'jankx'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="jankx_faq_overview_limit">
                                <?php esc_html_e('Số lượng FAQ tối đa', 'jankx'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number"
                                   id="jankx_faq_overview_limit"
                                   name="<?php echo esc_attr(FaqManager::OPTION_OVERVIEW_LIMIT); ?>"
                                   value="<?php echo esc_attr(get_option(FaqManager::OPTION_OVERVIEW_LIMIT, 5)); ?>"
                                   class="regular-text"
                                   min="0"
                                   step="1">
                            <p class="description">
                                <?php esc_html_e('Nhập 0 để hiển thị tất cả.', 'jankx'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Danh mục hiển thị', 'jankx'); ?>
                        </th>
                        <td>
                            <?php if ($categories && !is_wp_error($categories)) : ?>
                                <?php foreach ($categories as $term) : ?>
                                    <label style="display:block;margin-bottom:4px;">
                                        <input type="checkbox"
                                               name="<?php echo esc_attr(FaqManager::OPTION_OVERVIEW_CATEGORIES); ?>[]"
                                               value="<?php echo esc_attr($term->term_id); ?>"
                                               <?php checked(in_array($term->term_id, $selectedCategories, true)); ?> />
                                        <?php echo esc_html($term->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="description">
                                    <?php esc_html_e('Chưa có danh mục nào. Tạo danh mục trong mục FAQ → Danh mục FAQ.', 'jankx'); ?>
                                </p>
                            <?php endif; ?>
                            <p class="description">
                                <?php esc_html_e('Chỉ hiển thị FAQ thuộc các danh mục được chọn. Không chọn mục nào để hiển thị tất cả.', 'jankx'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Lưu cài đặt', 'jankx')); ?>
            </form>

            <div style="margin-top: 40px; padding: 20px; background: #fff; border: 1px solid #e2e4e7; border-radius: 8px; max-width: 700px;">
                <h2 style="margin-top: 0;"><?php esc_html_e('Quản lý câu hỏi FAQ', 'jankx'); ?></h2>
                <p>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=jankx_faq')); ?>"
                       class="button button-primary">
                        <?php esc_html_e('Xem tất cả câu hỏi', 'jankx'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=jankx_faq')); ?>"
                       class="button">
                        <?php esc_html_e('Thêm câu hỏi mới', 'jankx'); ?>
                    </a>
                    <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=jankx_faq_category&post_type=jankx_faq')); ?>"
                       class="button">
                        <?php esc_html_e('Quản lý danh mục', 'jankx'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
}
