<?php
namespace Jankx\Extensions\Faq;

use Jankx\Extensions\AbstractExtension;

/**
 * FAQ extension for the nibitour site.
 *
 * Manages frequently asked questions through the `jankx_faq` post type grouped
 * by the `jankx_faq_category` taxonomy, and optionally injects FAQs into the
 * My Account overview page when the my-account extension is installed.
 */
class FaqExtension extends AbstractExtension
{
    protected static $instance;

    public function __construct()
    {
        $this->register_autoloader();
        parent::__construct();
    }

    protected function register_autoloader()
    {
        spl_autoload_register(function ($class) {
            $prefix = 'Jankx\\Extensions\\Faq\\';
            $base_dir = __DIR__ . '/src/';
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }
            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        });
    }

    public function init(): void
    {
        self::$instance = $this;
    }

    public static function get_instance(): ?self
    {
        return self::$instance;
    }

    public function register_hooks(): void
    {
        $postType = new \Jankx\Extensions\Faq\PostTypes\FaqPostType();
        $postType->register();

        $metaBoxes = new \Jankx\Extensions\Faq\Meta\FaqMetaBoxes();
        $metaBoxes->register();

        if (is_admin()) {
            $settingsPage = new \Jankx\Extensions\Faq\Admin\SettingsPage();
            $settingsPage->register();
        }

        // Inject FAQs into the My Account overview when that extension exists.
        // Deferred: my-account loads after this extension (alphabetical order),
        // so class_exists() is still false while register_hooks() runs.
        add_action('after_setup_theme', [$this, 'registerMyAccountIntegration'], 20);
    }

    public function registerMyAccountIntegration(): void
    {
        if (class_exists('\Jankx\Extensions\MyAccount\MyAccountExtension')) {
            (new \Jankx\Extensions\Faq\Integration\MyAccountIntegration())->register();
        }
    }
}
