<?php
namespace Jankx\Extensions\Faq\Integration;

use Jankx\Extensions\Faq\FaqManager;

/**
 * Bridges the FAQ extension into the My Account overview page.
 *
 * Only registered when the my-account extension is installed. Provides the
 * Q&A items (and optionally the section title) through the filters that
 * `OverviewTab::renderQA` already exposes.
 */
class MyAccountIntegration
{
    public function register(): void
    {
        add_filter('jankx/my_account/overview/qa/faqs', [$this, 'provideFaqs'], 20, 2);
        add_filter('jankx/my_account/overview/qa/title', [$this, 'provideTitle'], 20, 2);
    }

    /**
     * Append the dynamic FAQs to whatever other extensions supplied.
     */
    public function provideFaqs(array $faqs, $user): array
    {
        return array_merge($faqs, FaqManager::get_overview_faqs());
    }

    /**
     * Override the section title with the configured one when set.
     */
    public function provideTitle(string $title, $user): string
    {
        $overviewTitle = get_option(FaqManager::OPTION_OVERVIEW_TITLE, '');

        return $overviewTitle !== '' ? $overviewTitle : $title;
    }
}
