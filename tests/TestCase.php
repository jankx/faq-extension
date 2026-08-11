<?php

namespace Jankx\Extensions\Faq\Tests;

use Brain\Monkey;
use Jankx\Extensions\Faq\Tests\Support\PostStore;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case for the FAQ extension.
 *
 * Boots Brain Monkey, stubs the WP functions (see tests/bootstrap.php) and
 * seeds a clean in-memory post store for every test.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Monkey\setUp();
        faq_test_stub_wp_functions();
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__registered_filters'],
            $GLOBALS['__registered_actions'],
            $GLOBALS['__options']
        );

        Monkey\tearDown();
        parent::tearDown();
    }

    protected function seed(array $data): int
    {
        return PostStore::insert($data);
    }

    protected function seedFaq(array $overrides = []): int
    {
        return $this->seed(array_merge([
            'post_type'   => 'jankx_faq',
            'post_status' => 'publish',
            'post_title'  => 'Có những ưu đãi nào cho thành viên?',
            'post_content' => '<p>Mỗi bậc thành viên có những ưu đãi riêng.</p>',
        ], $overrides));
    }
}
