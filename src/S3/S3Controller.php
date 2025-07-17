<?php

namespace StaticDeploy\S3;

use StaticDeploy\Controller;
use StaticDeploy\Options;
use StaticDeploy\SiteInfo;
use StaticDeploy\WsLog;

class S3Controller {
    const ADDON_NAME = 'static-deploy-addon-s3';

    public function run(): void {
        add_filter(
            Controller::getHookName( 'add_menu_items' ),
            [ 'StaticDeploy\S3\S3Controller', 'addSubmenuPage' ]
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
            self::ADDON_NAME,
            'deploy',
            'S3 Deployment',
            'https://github.com/staticweb-io/static-deploy',
            'Deploys to S3 with optional CloudFront cache invalidation'
        );
    }

    public static function deployerClass(
        string $deployer_class,
        string $enabled_deployer
    ): string {
        if ( $enabled_deployer !== self::ADDON_NAME ) {
            return $deployer_class;
        }
        return Deployer::class;
    }

    public function deploy( string $processed_site_path, string $enabled_deployer ): void {
        if ( $enabled_deployer !== self::ADDON_NAME ) {
            return;
        }

        WsLog::l( 'S3 Addon deploying' );

        $s3_deployer = new Deployer();
        $s3_deployer->uploadFiles( $processed_site_path );
    }

    public static function activateForSingleSite(): void {
        Options::seedOptions( S3Options::getSpecs() );
    }

    public static function deactivateForSingleSite(): void {
    }

    /**
     * Add submenu
     *
     * @param mixed[] $submenu_pages array of submenu pages
     * @return mixed[] array of submenu pages
     */
    public static function addSubmenuPage( array $submenu_pages ): array {
        $submenu_pages['s3'] = [ S3Options::class, 'renderPage' ];

        return $submenu_pages;
    }

    public static function saveOptionsFromUI(): void {
        check_admin_referer( Controller::getHookName( 's3_save_options' ) );

        Options::saveFromAdmin( S3Options::getSpecs() );

        wp_safe_redirect( admin_url( 'admin.php?page=' . self::ADDON_NAME ) );
        exit;
    }

    public function addOptionsPage(): void {
        add_submenu_page(
            '',
            'S3 Deployment Options',
            'S3 Deployment Options',
            'manage_options',
            self::ADDON_NAME,
            [ S3Options::class, 'renderPage' ]
        );
    }
}
