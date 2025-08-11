<?php declare(strict_types=1);

namespace StaticDeploy;

/*
 * Trait for controllers that manage a set of options
 * and an options page.
 */
trait OptionsControllerTrait {
    public static function activateForSingleSite(): void {
        Options::seedOptions( self::getSpecs() );
    }

    public static function deactivateForSingleSite(): void {
    }

    public static function registerHooks(): void {
        add_action(
            'admin_post_' . self::getAdminAction(),
            self::saveFromAdmin( ... ),
            15,
            1
        );

        add_action(
            'admin_menu',
            self::addOptionsPage( ... ),
            15,
            1
        );

        add_filter(
            Controller::getHookName( 'add_menu_items' ),
            self::addSubmenuPage( ... )
        );
    }

    public static function addOptionsPage(): void {
        $title = self::getPageData()['title'];
        add_submenu_page(
            '',
            $title,
            $title,
            'manage_options',
            self::getOptionsPageSlug(),
            self::renderPage( ... )
        );
    }

    /**
     * Add submenu
     *
     * @param mixed[] $submenu_pages array of submenu pages
     * @return mixed[] array of submenu pages
     */
    public static function addSubmenuPage( array $submenu_pages ): array {
        $submenu_pages[ self::getOptionsPageSlug() ] = self::renderPage( ... );

        return $submenu_pages;
    }

    public static function getValue( string $slug ): string {
        $name = self::getName( $slug );
        $option_spec = self::getSpecs()[ $name ];

        if ( ! $option_spec ) {
            throw WsLog::ex( "Unknown option: $name" );
        }

        return Options::getOption( $option_spec )->value;
    }

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

    public static function saveFromAdmin(): void {
        check_admin_referer( self::getAdminAction() );

        Options::saveFromAdmin( self::getSpecs() );

        $url = 'admin.php?page=' . self::getOptionsPageSlug();
        wp_safe_redirect( admin_url( $url ) );
        exit;
    }
}
