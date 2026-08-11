<?php

namespace Jankx\Extensions\Faq\Tests\Support;

/**
 * In-memory WordPress post/meta/terms store used by the FAQ unit tests.
 *
 * Posts keep a `menu_order` field (used by FaqManager ordering) and terms are
 * stored by id so the WP_Query stub can resolve `tax_query` term ids to names.
 */
class PostStore
{
    protected static $posts = [];
    protected static $meta = [];
    protected static $termNames = []; // postId => taxonomy => [names]
    protected static $termIds = []; // termId => ['name' =>, 'taxonomy' =>]
    protected static $postTermIds = []; // postId => taxonomy => [termId]
    protected static $nextId = 1;
    protected static $nextTermId = 1;

    public static function reset(): void
    {
        self::$posts = [];
        self::$meta = [];
        self::$termNames = [];
        self::$termIds = [];
        self::$postTermIds = [];
        self::$nextId = 1;
        self::$nextTermId = 1;
    }

    public static function insert(array $data): int
    {
        $id = self::$nextId++;

        $post = new \WP_Post();
        $post->ID = $id;
        $post->post_type = $data['post_type'] ?? 'post';
        $post->post_status = $data['post_status'] ?? 'publish';
        $post->post_title = $data['post_title'] ?? '';
        $post->post_excerpt = $data['post_excerpt'] ?? '';
        $post->post_content = $data['post_content'] ?? '';
        $post->post_date = $data['post_date'] ?? '2026-01-01 00:00:00';
        $post->post_name = $data['post_name'] ?? ('post-' . $id);
        $post->menu_order = $data['menu_order'] ?? 0;

        self::$posts[$id] = $post;

        if (!empty($data['meta_input'])) {
            foreach ($data['meta_input'] as $key => $value) {
                self::updateMeta($id, $key, $value);
            }
        }

        if (!empty($data['terms_input'])) {
            foreach ($data['terms_input'] as $taxonomy => $terms) {
                foreach ((array) $terms as $term) {
                    self::assignTerm($id, $taxonomy, $term);
                }
            }
        }

        return $id;
    }

    public static function all(): array
    {
        return self::$posts;
    }

    public static function get(int $id): ?\WP_Post
    {
        return self::$posts[$id] ?? null;
    }

    public static function meta(int $id, string $key)
    {
        if (!isset(self::$meta[$id])) {
            return null;
        }

        return array_key_exists($key, self::$meta[$id]) ? self::$meta[$id][$key] : null;
    }

    public static function updateMeta(int $id, string $key, $value): void
    {
        if (!isset(self::$meta[$id])) {
            self::$meta[$id] = [];
        }

        self::$meta[$id][$key] = $value;
    }

    public static function termNames(int $id, string $taxonomy): array
    {
        return self::$termNames[$id][$taxonomy] ?? [];
    }

    public static function termIdByName(string $name, string $taxonomy = 'jankx_faq_category'): ?int
    {
        foreach (self::$termIds as $id => $data) {
            if ($data['taxonomy'] === $taxonomy && $data['name'] === $name) {
                return $id;
            }
        }

        return null;
    }

    public static function resolveTerms(string $taxonomy, array $ids): array
    {
        $names = [];
        foreach ($ids as $termId) {
            if (isset(self::$termIds[$termId]) && self::$termIds[$termId]['taxonomy'] === $taxonomy) {
                $names[] = self::$termIds[$termId]['name'];
            }
        }

        return $names;
    }

    protected static function assignTerm(int $postId, string $taxonomy, string $name): void
    {
        $termId = null;
        foreach (self::$termIds as $id => $data) {
            if ($data['taxonomy'] === $taxonomy && $data['name'] === $name) {
                $termId = $id;
                break;
            }
        }

        if ($termId === null) {
            $termId = self::$nextTermId++;
            self::$termIds[$termId] = ['name' => $name, 'taxonomy' => $taxonomy];
        }

        self::$postTermIds[$postId][$taxonomy][] = $termId;
        self::$termNames[$postId][$taxonomy][] = $name;
    }
}
