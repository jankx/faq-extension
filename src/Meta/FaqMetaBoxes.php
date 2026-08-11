<?php
namespace Jankx\Extensions\Faq\Meta;

use Jankx\Extensions\Faq\PostTypes\FaqPostType;

/**
 * Meta box for a single FAQ post.
 *
 * Lets the editor choose whether the FAQ should be shown in the My Account
 * overview section (the global settings in the FAQ settings page decide the
 * category/limit on top of this per-item switch).
 */
class FaqMetaBoxes
{
    const NONCE_NAME = 'jankx_faq_meta_nonce';
    const NONCE_ACTION = 'jankx_faq_meta_action';
    const META_SHOW_IN_OVERVIEW = '_faq_show_in_overview';

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_' . FaqPostType::POST_TYPE, [$this, 'saveMetaBoxes']);
    }

    public function addMetaBoxes(): void
    {
        add_meta_box(
            'jankx_faq_display',
            __('Hiển thị', 'jankx'),
            [$this, 'renderDisplayMetaBox'],
            FaqPostType::POST_TYPE,
            'side',
            'high'
        );
    }

    public function renderDisplayMetaBox(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $show = get_post_meta($post->ID, self::META_SHOW_IN_OVERVIEW, true);
        if ($show === '') {
            $show = '1';
        }
        ?>
        <p>
            <label style="display:block;margin-bottom:6px;">
                <input type="checkbox"
                       name="jankx_faq_show_in_overview"
                       value="1"
                       <?php checked($show, '1'); ?> />
                <?php esc_html_e('Hiển thị trong mục FAQ của My Account', 'jankx'); ?>
            </label>
            <span class="description">
                <?php esc_html_e('Có thể bật/tắt ở cấp độ toàn cục trong trang cài đặt FAQ.', 'jankx'); ?>
            </span>
        </p>
        <?php
    }

    public function saveMetaBoxes(int $postId): void
    {
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        $show = isset($_POST['jankx_faq_show_in_overview']) && $_POST['jankx_faq_show_in_overview'] === '1' ? '1' : '0';
        update_post_meta($postId, self::META_SHOW_IN_OVERVIEW, $show);
    }
}
