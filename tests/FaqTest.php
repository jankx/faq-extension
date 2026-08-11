<?php

namespace Jankx\Extensions\Faq\Tests;

use Jankx\Extensions\Faq\Faq;
use Jankx\Extensions\Faq\Meta\FaqMetaBoxes;
use Jankx\Extensions\Faq\PostTypes\FaqPostType;

class FaqTest extends TestCase
{
    public function test_model_exposes_post_data(): void
    {
        $id = $this->seedFaq([
            'menu_order'   => 3,
            'post_content' => '<p>Câu trả lời chi tiết.</p>',
            'terms_input'  => [FaqPostType::TAXONOMY => ['Đặt tour', 'Thanh toán']],
        ]);

        $faq = new Faq($id);

        $this->assertSame($id, $faq->getId());
        $this->assertSame('Có những ưu đãi nào cho thành viên?', $faq->getQuestion());
        $this->assertSame('<p>Câu trả lời chi tiết.</p>', $faq->getAnswer());
        $this->assertSame(3, $faq->getOrder());
        $this->assertSame(['Đặt tour', 'Thanh toán'], $faq->getCategories());
    }

    public function test_model_accepts_wp_post_instance(): void
    {
        $id = $this->seedFaq(['menu_order' => 1]);
        $faq = new Faq(get_post($id));

        $this->assertSame($id, $faq->getId());
    }

    public function test_visible_in_overview_defaults_to_true(): void
    {
        $faq = new Faq($this->seedFaq());

        $this->assertTrue($faq->isVisibleInOverview());
    }

    public function test_visible_in_overview_when_meta_is_zero(): void
    {
        $faq = new Faq($this->seedFaq([
            'meta_input' => [FaqMetaBoxes::META_SHOW_IN_OVERVIEW => '0'],
        ]));

        $this->assertFalse($faq->isVisibleInOverview());
    }

    public function test_visible_in_overview_when_meta_is_one(): void
    {
        $faq = new Faq($this->seedFaq([
            'meta_input' => [FaqMetaBoxes::META_SHOW_IN_OVERVIEW => '1'],
        ]));

        $this->assertTrue($faq->isVisibleInOverview());
    }

    public function test_to_array_formats_for_overview(): void
    {
        $faq = new Faq($this->seedFaq([
            'terms_input' => [FaqPostType::TAXONOMY => ['Thanh toán']],
        ]));

        $this->assertSame([
            'question'   => 'Có những ưu đãi nào cho thành viên?',
            'answer'     => '<p>Mỗi bậc thành viên có những ưu đãi riêng.</p>',
            'categories' => ['Thanh toán'],
        ], $faq->toArray());
    }
}
