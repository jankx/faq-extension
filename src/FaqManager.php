<?php
namespace Jankx\Extensions\Faq;

use Jankx\Extensions\Faq\Meta\FaqMetaBoxes;
use Jankx\Extensions\Faq\PostTypes\FaqPostType;

/**
 * Queries the FAQ post type.
 *
 * `get_overview_faqs()` respects the extension settings (enabled, limit,
 * categories) plus the per-item overview switch, and returns the array format
 * expected by the My Account overview Q&A section.
 */
class FaqManager
{
    const OPTION_OVERVIEW_ENABLED = 'jankx_faq_overview_enabled';
    const OPTION_OVERVIEW_TITLE = 'jankx_faq_overview_title';
    const OPTION_OVERVIEW_LIMIT = 'jankx_faq_overview_limit';
    const OPTION_OVERVIEW_CATEGORIES = 'jankx_faq_overview_categories';

    /**
     * Fetch FAQ posts.
     *
     * @param array $args Keys: `number`, `category` (term ids), `orderby`,
     *                    `order`, `overview_only` (bool).
     * @return Faq[]
     */
    public static function get_faqs(array $args = []): array
    {
        $args = wp_parse_args($args, [
            'number'        => -1,
            'category'      => [],
            'orderby'       => 'menu_order date',
            'order'         => 'ASC',
            'overview_only' => false,
        ]);

        $queryArgs = [
            'post_type'      => FaqPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $args['number'] < 0 ? -1 : (int) $args['number'],
            'orderby'        => $args['orderby'],
            'order'          => $args['order'],
            'no_found_rows'  => true,
        ];

        $categories = array_filter(array_map('intval', (array) $args['category']));
        if ($categories) {
            $queryArgs['tax_query'] = [[
                'taxonomy' => FaqPostType::TAXONOMY,
                'field'    => 'term_id',
                'terms'    => array_values($categories),
            ]];
        }

        if ($args['overview_only']) {
            $queryArgs['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => FaqMetaBoxes::META_SHOW_IN_OVERVIEW,
                    'value'   => '0',
                    'compare' => '!=',
                ],
                [
                    'key'     => FaqMetaBoxes::META_SHOW_IN_OVERVIEW,
                    'compare' => 'NOT EXISTS',
                ],
            ];
        }

        $query = new \WP_Query($queryArgs);

        return array_map(function ($post) {
            return new Faq($post);
        }, $query->posts);
    }

    /**
     * FAQ items for the My Account overview Q&A section, formatted as
     * `['question' => ..., 'answer' => ...]` entries.
     */
    public static function get_overview_faqs(): array
    {
        if (!get_option(self::OPTION_OVERVIEW_ENABLED, 1)) {
            return [];
        }

        $limit = (int) get_option(self::OPTION_OVERVIEW_LIMIT, 5);
        $categories = (array) get_option(self::OPTION_OVERVIEW_CATEGORIES, []);

        $args = [
            'orderby'       => 'menu_order date',
            'order'         => 'ASC',
            'overview_only' => true,
        ];

        if ($limit > 0) {
            $args['number'] = $limit;
        }
        if ($categories) {
            $args['category'] = array_values(array_filter($categories, 'is_numeric'));
        }

        return array_map(function (Faq $faq) {
            return $faq->toArray();
        }, self::get_faqs($args));
    }
}
