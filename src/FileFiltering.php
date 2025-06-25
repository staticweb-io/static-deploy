<?php

namespace WP2Static;

use PHLAK\Splat\Anchors;
use PHLAK\Splat\Pattern;
use WP2Static\CoreOptions;
use WP2Static\FileIgnorePattern;
use WP2Static\SiteInfo;

class FileFiltering {

    /**
     * @var array<FileIgnorePattern>
     * Files and directories to ignore
     */
    private $patterns_to_ignore;

    public function __construct() {
        $this->patterns_to_ignore = [];

        $filenames_to_ignore = CoreOptions::getLineDelimitedBlobValue( 'filenamesToIgnore' );

        $filenames_to_ignore =
            apply_filters(
                Controller::getHookName( 'filenames_to_ignore' ),
                $filenames_to_ignore
            );

        foreach ( $filenames_to_ignore as $filename ) {
            $this->patterns_to_ignore[] = new FileIgnorePattern( $filename );
        }

        $file_extensions_to_ignore = CoreOptions::getLineDelimitedBlobValue(
            'fileExtensionsToIgnore'
        );

        $file_extensions_to_ignore =
            apply_filters(
                Controller::getHookName( 'file_extensions_to_ignore' ),
                $file_extensions_to_ignore
            );

        foreach ( $file_extensions_to_ignore as $extension ) {
            $this->patterns_to_ignore[] = new FileIgnorePattern(
                "**$extension"
            );
        }
    }

    /**
     * Returns crawlable files in a given directory, recursively.
     *
     * @param string $directory
     * @return \Iterator
     * 
     */
    public function crawlableFiles(
        string $directory,
    ) : \Iterator {
        $abs_base_dir = ( new \SplFileInfo( $directory ) )->getPathname();

        $dir_iter = new \RecursiveDirectoryIterator(
            $directory,
            \RecursiveDirectoryIterator::SKIP_DOTS,
        );

        // Using a callback filter is more efficient than
        // filtering later because we avoid recursing into
        // blocked directories.
        $filter_iter = new \RecursiveCallbackFilterIterator(
            $dir_iter,
            function ( $current, $key, $iterator ) use ( $abs_base_dir ) {
                // Filter out both directories and files
                foreach ( $this->patterns_to_ignore as $pattern ) {
                    if ( $pattern->matches( $abs_base_dir, $current ) ) {
                        return false;
                    }
                }

                return true;
            }
        );

        return new \RecursiveIteratorIterator( $filter_iter );
    }

    /**
     * Get public URLs for all files in a local directory.
     *
     * @param string $dir
     * @return \Iterator
     */
    public function getListOfLocalFilesByDir(
        string $dir,
    ) : \Iterator {
        $site_path = SiteInfo::getPath( 'site' );

        if ( is_string( $site_path ) &&is_dir( $dir ) ) {
            $iterator = $this->crawlableFiles( $dir );

            foreach ( $iterator as $filename => $file_object ) {
                $url = str_replace( $site_path, '/', $filename );

                yield [
                    'filename' => $filename,
                    'url' => $url,
                ];
            }
        }
    }

    /**
     * @param string $path
     * @return bool  True if the given path does not match an ignore pattern
     */
    public function pathLooksCrawlable(
        string $path,
    ) : bool {
        foreach ( $this->patterns_to_ignore as $pattern ) {
            if ( $pattern->matchesPath( $path ) ) {
                return false;
            }
        }

        return true;
    }
}