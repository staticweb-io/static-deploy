<?php declare(strict_types=1);

namespace StaticDeploy;

/*
 * Trait for controllers that manage a set of options
 * and an options page.
 */
trait OptionsControllerTrait {
    public static function renderPage(): void {
        $option_specs = self::getSpecs();
        Options::seedOptions( $option_specs );

        $page = self::getPageData();

        $view = [
            'nonce_action' => self::getAdminAction(),
            'options' => Options::getAll( $option_specs ),
            'sections' => $page['sections'],
            'title' => $page['title'],
        ];

        require_once __DIR__ . '/../views/render-options-page.php';
    }
}
