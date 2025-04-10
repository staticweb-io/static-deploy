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
     * Get public URLs for all files in a local directory.
     *
     * @param string $dir
     * @return string[] list of relative, urlencoded URLs
     */
    public function getListOfLocalFilesByDir(
        string $dir,
    ) : array {
        $site_path = SiteInfo::getPath( 'site' );

        if ( ! is_string( $site_path ) ) {
            return [];
        }

        $files = [];

        if ( is_dir( $dir ) ) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $dir,
                    RecursiveDirectoryIterator::SKIP_DOTS
                )
            );

            foreach ( $iterator as $filename => $file_object ) {
                $path_crawlable = $this->pathLooksCrawlable(
                    $filename,
                );

                if ( $path_crawlable ) {
                    $url = str_replace( $site_path, '/', $filename );

                    if ( is_string( $url ) ) {
                        $files[] = $url;
                    }
                }
            }
        }

        return $files;
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