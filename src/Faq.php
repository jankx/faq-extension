<?php
namespace Jankx\Extensions\Faq;

use Jankx\Extensions\Faq\Meta\FaqMetaBoxes;
use Jankx\Extensions\Faq\PostTypes\FaqPostType;

/**
 * Value object wrapping a single `jankx_faq` post.
 */
class Faq
{
    protected $post;

    public function __construct($post)
    {
        $this->post = $post instanceof \WP_Post ? $post : get_post($post);
    }

    public function getId(): int
    {
        return (int) $this->post->ID;
    }

    public function getQuestion(): string
    {
        return $this->post->post_title;
    }

    public function getAnswer(): string
    {
        return apply_filters('the_content', $this->post->post_content);
    }

    public function getOrder(): int
    {
        return (int) $this->post->menu_order;
    }

    public function getCategories(): array
    {
        $terms = wp_get_post_terms($this->post->ID, FaqPostType::TAXONOMY, ['fields' => 'names']);

        return is_wp_error($terms) ? [] : (array) $terms;
    }

    public function isVisibleInOverview(): bool
    {
        return get_post_meta($this->post->ID, FaqMetaBoxes::META_SHOW_IN_OVERVIEW, true) !== '0';
    }

    /**
     * Formatted FAQ for the `jankx/my_account/overview/qa/faqs` filter.
     */
    public function toArray(): array
    {
        return [
            'question'   => $this->getQuestion(),
            'answer'     => $this->getAnswer(),
            'categories' => $this->getCategories(),
        ];
    }
}
