<?php

namespace StaticDeploy\S3;

use StaticDeploy\Controller;
use StaticDeploy\Options;
use StaticDeploy\SiteInfo;
use StaticDeploy\WsLog;

class S3Controller {
    const ADDON_NAME = 'static-deploy-addon-s3';

    public function run(): void {
        S3Options::registerHooks();

        add_filter(
            Controller::getHookName( 'deployer_class' ),
            [ $this, 'deployerClass' ],
            10,
            2
        );

        add_action(
            Controller::getHookName( 'deploy' ),
            [ $this, 'deploy' ],
            15,
            2
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
}
