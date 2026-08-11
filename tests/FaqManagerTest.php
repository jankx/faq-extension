<?php

namespace Jankx\Extensions\Faq\Tests;

use Jankx\Extensions\Faq\Faq;
use Jankx\Extensions\Faq\FaqManager;
use Jankx\Extensions\Faq\Meta\FaqMetaBoxes;
use Jankx\Extensions\Faq\PostTypes\FaqPostType;
use Jankx\Extensions\Faq\Tests\Support\PostStore;

class FaqManagerTest extends TestCase
{
    protected function seedThreeFaqs(): array
    {
        $ids = [
            'first'  => $this->seedFaq([
                'post_title'  => 'FAQ 1',
                'menu_order'  => 2,
                'post_date'   => '2026-01-01 00:00:00',
                'meta_input'  => [FaqMetaBoxes::META_SHOW_IN_OVERVIEW => '1'],
                'terms_input' => [FaqPostType::TAXONOMY => ['Đặt tour']],
            ]),
            'second' => $this->seedFaq([
                'post_title'  => 'FAQ 2',
                'menu_order'  => 1,
                'post_date'   => '2026-01-02 00:00:00',
                'terms_input' => [FaqPostType::TAXONOMY => ['Thanh toán']],
            ]),
            'third'  => $this->seedFaq([
                'post_title'  => 'FAQ 3',
                'menu_order'  => 3,
                'post_date'   => '2026-01-03 00:00:00',
                'meta_input'  => [FaqMetaBoxes::META_SHOW_IN_OVERVIEW => '0'],
                'terms_input' => [FaqPostType::TAXONOMY => ['Đặt tour', 'Thanh toán']],
            ]),
        ];

        return $ids;
    }

    public function test_get_faqs_returns_published_faqs_ordered_by_menu_order(): void
    {
        $this->seedThreeFaqs();

        $faqs = FaqManager::get_faqs();

        $this->assertCount(3, $faqs);
        $this->assertContainsOnlyInstancesOf(Faq::class, $faqs);
        $this->assertSame(['FAQ 2', 'FAQ 1', 'FAQ 3'], array_map(function (Faq $faq) {
            return $faq->getQuestion();
        }, $faqs));
    }

    public function test_get_faqs_excludes_drafts(): void
    {
        $this->seedFaq(['post_title' => 'Published']);
        $this->seedFaq(['post_title' => 'Draft', 'post_status' => 'draft']);

        $faqs = FaqManager::get_faqs();

        $this->assertCount(1, $faqs);
        $this->assertSame('Published', $faqs[0]->getQuestion());
    }

    public function test_get_faqs_filters_by_category(): void
    {
        $ids = $this->seedThreeFaqs();

        $faqs = FaqManager::get_faqs([
            'category' => [PostStore::termIdByName('Đặt tour')],
        ]);

        $this->assertCount(2, $faqs);
        $this->assertSame(['FAQ 1', 'FAQ 3'], array_map(function (Faq $faq) {
            return $faq->getQuestion();
        }, $faqs));
        $this->assertSame([$ids['first'], $ids['third']], array_map(function (Faq $faq) {
            return $faq->getId();
        }, $faqs));
    }

    public function test_get_faqs_limits_number(): void
    {
        $this->seedThreeFaqs();

        $faqs = FaqManager::get_faqs(['number' => 2]);

        $this->assertCount(2, $faqs);
    }

    public function test_get_faqs_overview_only_excludes_meta_zero(): void
    {
        $this->seedThreeFaqs();

        $faqs = FaqManager::get_faqs(['overview_only' => true]);

        $this->assertCount(2, $faqs);
        $this->assertSame(['FAQ 2', 'FAQ 1'], array_map(function (Faq $faq) {
            return $faq->getQuestion();
        }, $faqs));
    }

    public function test_get_overview_faqs_returns_formatted_items(): void
    {
        $this->seedThreeFaqs();

        $items = FaqManager::get_overview_faqs();

        $this->assertCount(2, $items);
        $this->assertSame('FAQ 2', $items[0]['question']);
        $this->assertSame('FAQ 1', $items[1]['question']);
        $this->assertArrayHasKey('answer', $items[0]);
        $this->assertArrayHasKey('categories', $items[0]);
    }

    public function test_get_overview_faqs_empty_when_disabled(): void
    {
        $this->seedFaq();
        update_option(FaqManager::OPTION_OVERVIEW_ENABLED, '0');

        $this->assertSame([], FaqManager::get_overview_faqs());
    }

    public function test_get_overview_faqs_respects_limit(): void
    {
        $this->seedThreeFaqs();
        update_option(FaqManager::OPTION_OVERVIEW_LIMIT, '1');

        $items = FaqManager::get_overview_faqs();

        $this->assertCount(1, $items);
        $this->assertSame('FAQ 2', $items[0]['question']);
    }

    public function test_get_overview_faqs_limit_zero_returns_all(): void
    {
        $this->seedThreeFaqs();
        update_option(FaqManager::OPTION_OVERVIEW_LIMIT, '0');

        $this->assertCount(2, FaqManager::get_overview_faqs());
    }

    public function test_get_overview_faqs_filters_by_category(): void
    {
        $this->seedThreeFaqs();
        update_option(FaqManager::OPTION_OVERVIEW_CATEGORIES, [PostStore::termIdByName('Thanh toán')]);

        $items = FaqManager::get_overview_faqs();

        $this->assertCount(1, $items);
        $this->assertSame('FAQ 2', $items[0]['question']);
    }

    public function test_get_overview_faqs_empty_when_no_faqs(): void
    {
        $this->assertSame([], FaqManager::get_overview_faqs());
    }
}
