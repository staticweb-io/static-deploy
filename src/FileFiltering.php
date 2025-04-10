<?php

namespace WP2Static;

use WP2Static\CoreOptions;
use WP2Static\SiteInfo;

class FileFiltering {

    /**
     * @var array<string>
     * File and directory names to ignore
     */
    private $filenames_to_ignore;

    /**
     * @var array<string>
     * File extensions to ignore
     */
    private $file_extensions_to_ignore;

    public function __construct() {
        $filenames_to_ignore = CoreOptions::getLineDelimitedBlobValue( 'filenamesToIgnore' );

        $this->filenames_to_ignore =
            apply_filters(
                'wp2static_filenames_to_ignore',
                $filenames_to_ignore
            );

        $file_extensions_to_ignore = CoreOptions::getLineDelimitedBlobValue(
            'fileExtensionsToIgnore'
        );

        $this->file_extensions_to_ignore =
            apply_filters(
                'wp2static_file_extensions_to_ignore',
                $file_extensions_to_ignore
            );
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
        $dir_iter = new \RecursiveDirectoryIterator(
            $directory,
            \RecursiveDirectoryIterator::SKIP_DOTS,
        );

        // Using a callback filter is more efficient than
        // filtering later because we avoid recursing into
        // blocked directories.
        $filter_iter = new \RecursiveCallbackFilterIterator(
            $dir_iter,
            function ( $current, $key, $iterator ) {
                $filename = $current->getFilename();

                // Filter out both directories and files
                foreach ( $this->filenames_to_ignore as $filename_to_ignore ) {
                    if ( $filename === $filename_to_ignore ) {
                        return false;
                    }
                }

                // Filter only files
                if ( $current->isFile() ) {
                    /*
                      Prepare the file extension list for regex:
                      - Add prepending (escaped) \ for a literal . at the start of
                        the file extension
                      - Add $ at the end to match end of string
                      - Add i modifier for case insensitivity
                    */
                    foreach ( $this->file_extensions_to_ignore as $extension ) {
                        if ( preg_match( "/\\{$extension}$/i", $filename ) ) {
                            return false;
                        }
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
     * Ensure a given filepath has an allowed filename and extension.
     *
     * @param string $file_name
     * @return bool  True if the given file does not have a disallowed filename
     *               or extension.
     */
    public function pathLooksCrawlable(
        string $file_name,
    ) : bool {
        $filename_matches = 0;

        str_ireplace( $this->filenames_to_ignore, '', $file_name, $filename_matches );

        // If we found matches we don't need to go any further
        if ( $filename_matches ) {
            return false;
        }

        /*
          Prepare the file extension list for regex:
          - Add prepending (escaped) \ for a literal . at the start of
            the file extension
          - Add $ at the end to match end of string
          - Add i modifier for case insensitivity
        */
        foreach ( $this->file_extensions_to_ignore as $extension ) {
            if ( preg_match( "/\\{$extension}$/i", $file_name ) ) {
                return false;
            }
        }

        return true;
    }
}