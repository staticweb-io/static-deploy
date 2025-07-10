<?php

namespace WP2Static;

use WP2Static\FileFiltering;

class DetectVendorCache {
    /**
     *   Autoptimize and other vendors use a cache dir one level above the
     *   uploads URL
     *
     *   Ie, domain.com/cache/ or domain.com/subdir/cache/
     *
     *   So, we grab all the files from the its actual cache dir
     *   then strip the site path and any subdir path (no extra logic needed?)
     *
     * @return \Iterator<array> list of URLs
     */
    public static function detect(
        FileFiltering $filtering,
        string $cache_dir,
        string $path_to_trim,
        string $prefix,
        bool $log = false,
    ): \Iterator {
        if ( $log ) {
            WsLog::l( 'Detecting vendor cache' );
        }

        $directory = $cache_dir;

        if ( is_dir( $directory ) ) {
            $iterator = $filtering->crawlableFiles( $directory );

            foreach ( $iterator as $filename => $file_object ) {
                // Standardise all paths to use / (Windows support)
                $filename = str_replace( '\\', '/', $filename );

                $detected_filename =
                    str_replace(
                        $path_to_trim,
                        '',
                        $filename
                    );

                yield [
                    'filename' => $filename,
                    'url' => $prefix . home_url( $detected_filename ),
                ];
            }
        }
    }
}
