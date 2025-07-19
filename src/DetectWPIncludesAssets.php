<?php

namespace StaticDeploy;

class DetectWPIncludesAssets {

    /**
     * Detect assets within wp-includes path
     *
     * @return \Iterator<array>
     * @throw StaticDeployException
     */
    public static function detect(
        FileFiltering $filtering,
    ): \Iterator {
        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( 'Detecting assets within wp-includes path' );
        }

        $includes_path = SiteInfo::getPath( 'includes' );
        $includes_url = SiteInfo::getUrl( 'includes' );
        $home_url = SiteInfo::getUrl( 'home' );

        if ( is_dir( $includes_path ) ) {
            $iterator = $filtering->crawlableFiles( $includes_path );

            foreach ( $iterator as $filename => $file_object ) {
                // Standardise all paths to use / (Windows support)
                $filename = str_replace( '\\', '/', $filename );

                $detected_filename =
                    str_replace(
                        $includes_path,
                        $includes_url,
                        $filename
                    );

                $detected_filename =
                    str_replace(
                        $home_url,
                        '',
                        $detected_filename
                    );

                if ( ! is_string( $detected_filename ) ) {
                    continue;
                }

                yield [
                    'filename' => $filename,
                    'url' => '/' . $detected_filename,
                ];
            }
        }
    }
}
