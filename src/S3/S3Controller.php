<?php

namespace WP2Static\S3;

use WP2Static\Controller;
use WP2Static\Options;
use WP2Static\SiteInfo;
use WP2Static\WsLog;

class S3Controller {
    public function run(): void {
        add_filter(
            Controller::getHookName( 'add_menu_items' ),
            [ 'WP2Static\S3\S3Controller', 'addSubmenuPage' ]
        );

        add_filter(
            Controller::getHookName( 'deployer_class' ),
            [ $this, 'deployerClass' ],
            10,
            2
        );

        add_action(
            'admin_post_' . Controller::getHookName( 's3_save_options' ),
            [ $this, 'saveOptionsFromUI' ],
            15,
            1
        );

        add_action(
            Controller::getHookName( 'deploy' ),
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
            Controller::getHookName( 'register_addon' ),
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
        $view['nonce_action'] = Controller::getHookName( 's3_save_options' );
        $view['uploads_path'] = SiteInfo::getPath( 'uploads' );

        $view['options'] = Options::getAll( S3Options::optionSpecs() );

        require_once __DIR__ . '/../../views/s3/s3-options-page.php';
    }


    public function deploy( string $processed_site_path, string $enabled_deployer ): void {
        if ( $enabled_deployer !== 'wp2static-addon-s3' ) {
            return;
        }

        WsLog::l( 'S3 Addon deploying' );

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
        $submenu_pages['s3'] = [ 'WP2Static\S3\S3Controller', 'renderS3Page' ];

        return $submenu_pages;
    }

    public static function saveOptionsFromUI(): void {
        check_admin_referer( Controller::getHookName( 's3_save_options' ) );

        Options::saveFromAdmin( S3Options::optionSpecs() );

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
