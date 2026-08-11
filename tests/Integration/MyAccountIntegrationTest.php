<?php

namespace Jankx\Extensions\Faq\Tests;

use Jankx\Extensions\Faq\FaqManager;
use Jankx\Extensions\Faq\Integration\MyAccountIntegration;
use Jankx\Extensions\Faq\Meta\FaqMetaBoxes;

class MyAccountIntegrationTest extends TestCase
{
    public function test_register_hooks_faq_filters(): void
    {
        $integration = new MyAccountIntegration();
        $integration->register();

        $tags = array_column($GLOBALS['__registered_filters'], 'tag');
        $this->assertContains('jankx/my_account/overview/qa/faqs', $tags);
        $this->assertContains('jankx/my_account/overview/qa/title', $tags);

        $faqFilter = array_values(array_filter($GLOBALS['__registered_filters'], function ($filter) {
            return $filter['tag'] === 'jankx/my_account/overview/qa/faqs';
        }));
        $this->assertSame([$integration, 'provideFaqs'], $faqFilter[0]['callback']);
        $this->assertSame(20, $faqFilter[0]['priority']);
        $this->assertSame(2, $faqFilter[0]['accepted']);
    }

    public function test_provide_faqs_appends_dynamic_faqs(): void
    {
        $this->seedFaq([
            'post_title'  => 'Cách đặt tour?',
            'post_content' => '<p>Đặt online hoặc qua tổng đài.</p>',
        ]);

        $integration = new MyAccountIntegration();
        $existing = [['question' => 'Từ extension khác', 'answer' => 'abc']];

        $items = $integration->provideFaqs($existing, new \WP_User());

        $this->assertCount(2, $items);
        $this->assertSame('Từ extension khác', $items[0]['question']);
        $this->assertSame('Cách đặt tour?', $items[1]['question']);
    }

    public function test_provide_faqs_keeps_existing_when_no_faqs(): void
    {
        $integration = new MyAccountIntegration();

        $this->assertSame([], $integration->provideFaqs([], new \WP_User()));
    }

    public function test_provide_title_uses_configured_title(): void
    {
        update_option(FaqManager::OPTION_OVERVIEW_TITLE, 'Hỏi & Đáp');

        $integration = new MyAccountIntegration();

        $this->assertSame('Hỏi & Đáp', $integration->provideTitle('Frequently Asked Questions', new \WP_User()));
    }

    public function test_provide_title_keeps_default_when_empty(): void
    {
        $integration = new MyAccountIntegration();

        $this->assertSame('Frequently Asked Questions', $integration->provideTitle('Frequently Asked Questions', new \WP_User()));
    }

    public function test_provide_faqs_respects_overview_visibility(): void
    {
        $this->seedFaq(['post_title' => 'Ẩn']);
        $this->seedFaq(['post_title' => 'Hiển thị', 'meta_input' => [FaqMetaBoxes::META_SHOW_IN_OVERVIEW => '1']]);
        $this->seedFaq(['post_title' => 'Bị tắt', 'meta_input' => [FaqMetaBoxes::META_SHOW_IN_OVERVIEW => '0']]);

        $integration = new MyAccountIntegration();
        $items = $integration->provideFaqs([], new \WP_User());

        $this->assertCount(2, $items);
        $this->assertSame(['Ẩn', 'Hiển thị'], array_column($items, 'question'));
    }
}
