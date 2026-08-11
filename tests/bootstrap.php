<?php
/**
 * FAQ Extension - PHPUnit bootstrap.
 *
 * Loads:
 *  1. The Composer autoloader (dev deps: phpunit, brain/monkey) when present.
 *  2. A PSR-4 fallback autoloader for the extension src and tests.
 *  3. A small in-memory WordPress post/meta/terms store (Tests\Support\PostStore).
 *  4. A minimal WP_Query stub supporting the args used by FaqManager
 *     (post_type, post_status, posts_per_page, orderby 'menu_order date',
 *     tax_query, meta_query with OR + NOT EXISTS, no_found_rows).
 *  5. Brain Monkey aliases for the WP functions used by the FAQ classes.
 */

use Brain\Monkey;

if (!defined('ABSPATH')) {
    define('ABSPATH', 'unit-test');
}

// 1. Composer autoloader (dev dependencies + PSR-4 for src and tests).
$composerAutoload = __DIR__ . '/../libs/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// 2. PSR-4 fallback autoloaders (cover the case where the Composer autoloader
//    has not been regenerated).
spl_autoload_register(function ($class) {
    $prefix = 'Jankx\\Extensions\\Faq\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

spl_autoload_register(function ($class) {
    $prefix = 'Jankx\\Extensions\\Faq\\Tests\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// 3. WordPress class stubs.
if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public $ID;
        public $post_type;
        public $post_status;
        public $post_title;
        public $post_excerpt;
        public $post_content;
        public $post_date;
        public $post_name;
        public $menu_order;

        public function __construct($data = [])
        {
            foreach ($data as $key => $value) {
                $this->$key = $value;
            }
        }
    }
}

if (!class_exists('WP_Query')) {
    /**
     * Minimal WP_Query that filters the in-memory PostStore. Understands the
     * args used by FaqManager: post_type, post_status, posts_per_page,
     * orderby ('menu_order date', 'date'), order, tax_query (single group with
     * field 'term_id'), meta_query (relation OR, key/value/compare with
     * '!=' and 'NOT EXISTS') and no_found_rows.
     */
    class WP_Query
    {
        public $posts = [];
        public $found_posts = 0;
        public $post_count = 0;
        public $max_num_pages = 0;

        protected $args = [];

        public function __construct($args = [])
        {
            $this->args = $args;

            $matched = [];
            foreach (\Jankx\Extensions\Faq\Tests\Support\PostStore::all() as $id => $post) {
                if (!$this->matches($args, $post)) {
                    continue;
                }
                $matched[$id] = $post;
            }

            uasort($matched, function ($a, $b) {
                return $this->compare($a, $b);
            });

            $this->found_posts = count($matched);

            $perPage = (int) ($args['posts_per_page'] ?? 10);

            if ($perPage === -1) {
                $pagePosts = $matched;
            } else {
                $pagePosts = array_slice($matched, 0, $perPage, true);
            }

            $this->max_num_pages = $perPage === -1 ? 1 : (int) ceil($this->found_posts / $perPage);
            $this->posts = array_values($pagePosts);
            $this->post_count = count($this->posts);
        }

        protected function matches(array $args, \WP_Post $post): bool
        {
            $postType = $args['post_type'] ?? 'post';
            if ($postType !== 'any') {
                $types = (array) $postType;
                if (!in_array($post->post_type, $types, true)) {
                    return false;
                }
            }

            $status = $args['post_status'] ?? 'publish';
            if (!empty($status) && $status !== 'any' && $post->post_status !== $status) {
                return false;
            }

            if (!$this->matchesTax($args['tax_query'] ?? null, $post)) {
                return false;
            }

            return $this->matchesMeta($args['meta_query'] ?? null, $post);
        }

        protected function matchesTax($taxQuery, \WP_Post $post): bool
        {
            if (!$taxQuery) {
                return true;
            }

            foreach ($taxQuery as $condition) {
                if (isset($condition['relation'])) {
                    continue;
                }
                $taxonomy = $condition['taxonomy'] ?? '';
                $field = $condition['field'] ?? 'term_id';
                $terms = (array) ($condition['terms'] ?? []);

                if ($field === 'slug') {
                    $names = $terms;
                } else {
                    $names = \Jankx\Extensions\Faq\Tests\Support\PostStore::resolveTerms($taxonomy, $terms);
                }

                $postTerms = \Jankx\Extensions\Faq\Tests\Support\PostStore::termNames($post->ID, $taxonomy);
                if (!array_intersect($names, $postTerms)) {
                    return false;
                }
            }

            return true;
        }

        protected function matchesMeta($metaQuery, \WP_Post $post): bool
        {
            if (!$metaQuery) {
                return true;
            }

            if (isset($metaQuery['relation'])) {
                $relation = strtoupper($metaQuery['relation']);
                $results = [];
                foreach ($metaQuery as $condition) {
                    if (!is_array($condition) || isset($condition['relation'])) {
                        continue;
                    }
                    $results[] = $this->matchesMetaCondition($condition, $post);
                }

                return $relation === 'OR'
                    ? in_array(true, $results, true)
                    : !in_array(false, $results, true);
            }

            return $this->matchesMetaCondition($metaQuery, $post);
        }

        protected function matchesMetaCondition(array $condition, \WP_Post $post): bool
        {
            $key = $condition['key'] ?? '';
            $value = \Jankx\Extensions\Faq\Tests\Support\PostStore::meta($post->ID, $key);
            $compare = strtoupper($condition['compare'] ?? '=');

            if ($compare === 'NOT EXISTS') {
                return $value === null;
            }
            if ($compare === 'EXISTS') {
                return $value !== null;
            }
            if ($value === null) {
                return false;
            }

            switch ($compare) {
                case '!=':
                    return (string) $value !== (string) ($condition['value'] ?? '');
                case '>':
                    return (float) $value > (float) ($condition['value'] ?? 0);
                case '<':
                    return (float) $value < (float) ($condition['value'] ?? 0);
                case '=':
                default:
                    return (string) $value === (string) ($condition['value'] ?? '');
            }
        }

        protected function compare(\WP_Post $a, \WP_Post $b): int
        {
            $orderby = (string) ($this->args['orderby'] ?? 'date');
            $order = strtoupper((string) ($this->args['order'] ?? 'DESC'));

            $keys = $orderby === 'menu_order date' ? ['menu_order', 'post_date'] : ['post_date'];

            foreach ($keys as $key) {
                $cmp = $a->$key <=> $b->$key;
                if ($cmp !== 0) {
                    return $order === 'ASC' ? $cmp : -$cmp;
                }
            }

            return 0;
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        protected $errors = [];
        protected $codes = [];

        public function __construct($code = '', $message = '', $data = '')
        {
            if ($code) {
                $this->errors[$code] = [$message];
                $this->codes[] = $code;
            }
        }

        public function get_error_code()
        {
            return $this->codes[0] ?? '';
        }

        public function get_error_message()
        {
            $code = $this->get_error_code();

            return $code ? ($this->errors[$code][0] ?? '') : '';
        }
    }
}

if (!class_exists('WP_User')) {
    class WP_User
    {
        public $ID = 0;
        public $user_login = '';
        public $roles = [];
    }
}

// 4. Brain Monkey function stubs used by the FAQ tests.
if (!function_exists('faq_test_stub_wp_functions')) {
    function faq_test_stub_wp_functions()
    {
        $GLOBALS['__registered_filters'] = [];
        $GLOBALS['__registered_actions'] = [];
        $GLOBALS['__options'] = [];

        \Jankx\Extensions\Faq\Tests\Support\PostStore::reset();

        Brain\Monkey\Functions\when('__')->returnArg();
        Brain\Monkey\Functions\when('esc_html__')->returnArg();
        Brain\Monkey\Functions\when('esc_html')->returnArg();
        Brain\Monkey\Functions\when('esc_attr')->returnArg();
        Brain\Monkey\Functions\when('esc_url')->returnArg();
        Brain\Monkey\Functions\when('_e')->alias(function ($text) {
            echo $text;
        });

        Brain\Monkey\Functions\when('wp_parse_args')->alias(function ($args, $defaults = []) {
            if (is_object($args)) {
                $args = get_object_vars($args);
            }
            $args = (array) $args;

            return array_merge($defaults, $args);
        });

        Brain\Monkey\Functions\when('add_filter')->alias(function ($tag, $callback, $priority = 10, $accepted = 1) {
            $GLOBALS['__registered_filters'][] = [
                'tag' => $tag,
                'callback' => $callback,
                'priority' => $priority,
                'accepted' => $accepted,
            ];

            return true;
        });

        Brain\Monkey\Functions\when('add_action')->alias(function ($tag, $callback, $priority = 10, $accepted = 1) {
            $GLOBALS['__registered_actions'][] = [
                'tag' => $tag,
                'callback' => $callback,
                'priority' => $priority,
                'accepted' => $accepted,
            ];

            return true;
        });

        Brain\Monkey\Functions\when('apply_filters')->alias(function ($tag, $value) {
            return $value;
        });

        Brain\Monkey\Functions\when('do_action')->alias(function ($tag, ...$args) {
            return null;
        });

        Brain\Monkey\Functions\when('is_wp_error')->alias(function ($thing) {
            return $thing instanceof \WP_Error;
        });

        // Settings.
        Brain\Monkey\Functions\when('get_option')->alias(function ($option, $default = false) {
            return array_key_exists($option, $GLOBALS['__options']) ? $GLOBALS['__options'][$option] : $default;
        });

        Brain\Monkey\Functions\when('update_option')->alias(function ($option, $value) {
            $GLOBALS['__options'][$option] = $value;

            return true;
        });

        // Post store accessors.
        Brain\Monkey\Functions\when('get_post')->alias(function ($id = null) {
            $id = $id instanceof \WP_Post ? $id->ID : (int) $id;

            return \Jankx\Extensions\Faq\Tests\Support\PostStore::get($id);
        });

        Brain\Monkey\Functions\when('get_post_meta')->alias(function ($id, $key, $single = false) {
            $id = $id instanceof \WP_Post ? $id->ID : (int) $id;
            $value = \Jankx\Extensions\Faq\Tests\Support\PostStore::meta($id, $key);

            if ($value === null) {
                return $single ? '' : [];
            }

            return $single ? $value : [$value];
        });

        Brain\Monkey\Functions\when('update_post_meta')->alias(function ($id, $key, $value) {
            $id = $id instanceof \WP_Post ? $id->ID : (int) $id;
            \Jankx\Extensions\Faq\Tests\Support\PostStore::updateMeta($id, $key, $value);

            return true;
        });

        Brain\Monkey\Functions\when('wp_get_post_terms')->alias(function ($post, $taxonomy, $args = []) {
            $id = $post instanceof \WP_Post ? $post->ID : (int) $post;
            $names = \Jankx\Extensions\Faq\Tests\Support\PostStore::termNames($id, $taxonomy);

            if (empty($names)) {
                return new \WP_Error('no_terms', 'Không có danh mục.');
            }

            return $names;
        });

        Brain\Monkey\Functions\when('is_admin')->alias(function () {
            return false;
        });
    }
}
