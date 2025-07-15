<?php

namespace WP2Static\S3;

use WP2Static\Options;

class Controller {
    public function run(): void {
        add_filter(
            'wp2static_add_menu_items',
            [ 'WP2Static\S3\Controller', 'addSubmenuPage' ]
        );

        add_filter(
            'wp2static_deployer_class',
            [ $this, 'deployerClass' ],
            10,
            2
        );

        add_action(
            'admin_post_wp2static_s3_save_options',
            [ $this, 'saveOptionsFromUI' ],
            15,
            1
        );

        add_action(
            'wp2static_deploy',
            [ $this, 'deploy' ],
            15,
            2
        );

        add_action(
            'admin_menu',
            [ $this, 'addOptionsPage' ],
            15,
            1
        );

        do_action(
            'wp2static_register_addon',
            'wp2static-addon-s3',
            'deploy',
            'S3 Deployment',
            'https://wp2static.com/addons/s3/',
            'Deploys to S3 with optional CloudFront cache invalidation'
        );
    }

    public static function deployerClass(
        string $deployer_class,
        string $enabled_deployer
    ): string {
        if ( $enabled_deployer !== 'wp2static-addon-s3' ) {
            return $deployer_class;
        }
        return Deployer::class;
    }

    public static function renderS3Page(): void {
        Options::seedOptions( S3Options::optionSpecs() );

        $view = [];
        $view['nonce_action'] = 'wp2static-s3-options';
        $view['uploads_path'] = \WP2Static\SiteInfo::getPath( 'uploads' );
        $s3_path = \WP2Static\SiteInfo::getPath( 'uploads' ) . 'wp2static-processed-site.s3';

        $view['options'] = Options::getAll();

        $view['s3_url'] =
            is_file( $s3_path ) ?
                \WP2Static\SiteInfo::getUrl( 'uploads' ) . 'wp2static-processed-site.s3' : '#';

        require_once __DIR__ . '/../views/s3-page.php';
    }


    public function deploy( string $processed_site_path, string $enabled_deployer ): void {
        if ( $enabled_deployer !== 'wp2static-addon-s3' ) {
            return;
        }

        \WP2Static\WsLog::l( 'S3 Addon deploying' );

        $s3_deployer = new Deployer();
        $s3_deployer->uploadFiles( $processed_site_path );
    }

    public static function activateForSingleSite(): void {
        Options::seedOptions( S3Options::optionSpecs() );
    }

    public static function deactivateForSingleSite(): void {
    }

    /**
     * Add WP2Static submenu
     *
     * @param mixed[] $submenu_pages array of submenu pages
     * @return mixed[] array of submenu pages
     */
    public static function addSubmenuPage( array $submenu_pages ): array {
        $submenu_pages['s3'] = [ 'WP2Static\S3\Controller', 'renderS3Page' ];

        return $submenu_pages;
    }

    public static function saveOptionsFromUI(): void {
        check_admin_referer( 'wp2static-s3-options' );

        Options::saveFromUI( S3Options::optionSpecs() );

        wp_safe_redirect( admin_url( 'admin.php?page=wp2static-addon-s3' ) );
        exit;
    }

    public function addOptionsPage(): void {
        add_submenu_page(
            '',
            'S3 Deployment Options',
            'S3 Deployment Options',
            'manage_options',
            'wp2static-addon-s3',
            [ $this, 'renderS3Page' ]
        );
    }
}
