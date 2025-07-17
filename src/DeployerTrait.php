<?php

namespace StaticDeploy;

trait DeployerTrait {
    public static function registerHooks(): void {
        add_filter(
            Controller::getHookName( 'deployer_class' ),
            [ self::class, 'deployerClass' ],
            10,
            2
        );

        add_action(
            Controller::getHookName( 'deploy' ),
            [ self::class, 'deploy' ],
            15,
            2
        );

        $data = self::getDeployerData();

        do_action(
            Controller::getHookName( 'register_addon' ),
            self::getDeployerSlug(),
            'deploy',
            $data['name'],
            $data['url'],
            $data['description']
        );
    }

    public static function deployerClass(
        string $deployer_class,
        string $enabled_deployer
    ): string {
        if ( $enabled_deployer !== self::getDeployerSlug() ) {
            return $deployer_class;
        }
        return self::class;
    }

    public function deploy( string $processed_site_path, string $enabled_deployer ): void {
        $name = self::getDeployerData()['name'];

        if ( $enabled_deployer !== $name ) {
            return;
        }

        WsLog::l( $name . ' deploying' );

        $deployer = new self();
        $deployer->uploadFiles( $processed_site_path );
    }
}
