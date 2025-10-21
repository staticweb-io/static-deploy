<?php

namespace StaticDeploy;

trait DeployerTrait {
    public static function registerHooks(): void {
        add_filter(
            Controller::getHookName( 'deployer_class' ),
            self::deployerClass( ... ),
            10,
            2
        );

        add_action(
            Controller::getHookName( 'deploy' ),
            self::deploy( ... ),
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

    public static function deploy( string $processed_site_path, string $enabled_deployer ): void {
        $slug = self::getDeployerSlug();

        if ( $enabled_deployer !== $slug ) {
            return;
        }

        WsLog::l( $slug . ' deploying' );

        $deployer = new self();
        $deployer->uploadFiles( $processed_site_path );
    }

    public function uploadFiles( string $processed_site_path ): void {
        // check if dir exists
        if ( ! is_dir( $processed_site_path ) ) {
            WsLog::w( 'Processed site path does not exist: ' . $processed_site_path );
            return;
        }

        // iterate each file in ProcessedSite
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $processed_site_path,
                \RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        $path_infos = function (
            $files,
            $redirects,
        ) use ( $processed_site_path ) {
            foreach ( $files as $filename => $file_object ) {
                if ( STATIC_DEPLOY_DEBUG ) {
                    WsLog::d( 'Deploying file ' . $filename );
                }
                $base_name = basename( $filename );
                if ( $base_name !== '.' && $base_name !== '..' ) {
                    $path = str_replace( $processed_site_path, '', $filename );
                    yield new PathInfo(
                        $path,
                        filename: $filename,
                    );
                }
            }

            foreach ( $redirects as $redirect ) {
                $path = $redirect->path;

                if ( STATIC_DEPLOY_DEBUG ) {
                    WsLog::d( 'Deploying redirect ' . $path . ' to ' . $redirect->redirect_to );
                }

                if ( mb_substr( $path, -1 ) === '/' ) {
                    $path .= 'index.html';
                }

                yield new PathInfo(
                    $path,
                    redirect_to: $redirect->redirect_to,
                );
            }
        };

        $redirects = CrawledFiles::listRedirects();

        $pis = $path_infos( $files, $redirects );
        $pis = DeployCache::addCacheData( $pis );
        self::uploadFilesIter( $pis );
    }
}
