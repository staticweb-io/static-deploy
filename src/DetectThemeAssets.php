<?php

namespace StaticDeploy;

class DetectThemeAssets {

    /**
     * Detect theme public URLs from filesystem
     *
     * @return \Iterator<array>
     */
    public static function detect(
        FileFiltering $filtering,
        string $theme_type,
    ): \Iterator {
        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( 'Detecting theme assets' );
        }

        $template_path = '';
        $site_path = SiteInfo::getPath( 'site' );

        if ( $theme_type === 'parent' ) {
            $template_path = SiteInfo::getPath( 'parent_theme' );
        } else {
            $template_path = SiteInfo::getPath( 'child_theme' );
        }

        if ( is_dir( $template_path ) ) {
            $iterator = $filtering->crawlableFiles( $site_path );

            foreach ( $iterator as $filename => $file_object ) {
                // Standardise all paths to use / (Windows support)
                $filename = str_replace( '\\', '/', $filename );

                $detected_filename =
                    str_replace(
                        $site_path,
                        '/',
                        $filename
                    );

                if ( is_string( $detected_filename ) ) {
                    yield [
                        'filename' => $filename,
                        'url' => $detected_filename,
                    ];
                }
            }
        }
    }
}
