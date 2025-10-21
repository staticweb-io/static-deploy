<?php

namespace StaticDeploy;

class DetectPluginAssets {

    /**
     * Detect Plugin assets
     *
     * @return \Iterator<PathInfo>
     */
    public static function detect(
        FileFiltering $filtering,
    ): \Iterator {
        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( 'Detecting plugin assets' );
        }

        $plugins_path = SiteInfo::getPath( 'plugins' );
        $plugins_url = SiteInfo::getUrl( 'plugins' );

        if ( is_dir( $plugins_path ) ) {
            $iterator = $filtering->crawlableFiles( $plugins_path );

            /**
             * @var string[] $active_plugins
             */
            $active_plugins = get_option( 'active_plugins' );
            /**
             * @var string[] $active_sitewide_plugins
             */
            $active_sitewide_plugins = get_option( 'active_sitewide_plugins' );

            if ( is_multisite() ) {
                $active_plugins = array_unique(
                    array_merge(
                        $active_plugins,
                        array_keys( $active_sitewide_plugins )
                    )
                );
            }

            $active_plugin_dirs = array_map(
                function ( int|string $active_plugin ): string {
                    $dir = explode( '/', $active_plugin )[0];
                    if ( STATIC_DEPLOY_DEBUG ) {
                        WsLog::d( "Active plugin dir: {$dir}" );
                    }
                    return $dir;
                },
                $active_plugins
            );

            foreach ( $iterator as $filename => $file_object ) {
                $matches_active_plugin_dir =
                    ( str_replace( $active_plugin_dirs, '', $filename ) !== $filename );

                if ( ! $matches_active_plugin_dir ) {
                    continue;
                }

                // Standardise all paths to use / (Windows support)
                $filename = str_replace( '\\', '/', $filename );

                $detected_filename =
                    str_replace(
                        $plugins_path,
                        $plugins_url,
                        $filename
                    );

                $detected_filename =
                    str_replace(
                        get_home_url(),
                        '',
                        $detected_filename
                    );

                if ( is_string( $detected_filename ) ) {
                    yield new PathInfo(
                        $detected_filename,
                        filename: $filename,
                    );
                }
            }
        }
    }
}
