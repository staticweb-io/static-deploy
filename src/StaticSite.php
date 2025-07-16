<?php
/*
    StaticSite

    The resulting output of crawling the WordPress site

    Site URLs are all made absolute for easier rewriting during deployment
*/

namespace StaticDeploy;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class StaticSite {

    private static string $crawled_site_path;

    /**
     * Add crawled resource to static site
     */
    public static function add( string $path, string $contents ): void {
        // simple file save, Crawler holds logic for what/where to save
        // Crawler has already processed links, etc
        $full_path = self::getPath() . "$path";

        $directory = dirname( $full_path );

        if ( ! is_dir( $directory ) ) {
            if ( ! wp_mkdir_p( $directory ) ) {
                WsLog::l( 'Couldn\t make directory: ' . $directory );
            }
        }

        file_put_contents( $full_path, $contents );
    }

    public static function getPath(): string {
        if ( ! isset( self::$crawled_site_path ) ) {
            self::$crawled_site_path = Options::getValue( 'crawledSitePath' );
        }
        return SiteInfo::getPath( 'uploads' ) . self::$crawled_site_path;
    }

    /**
     * Delete StaticSite files
     */
    public static function delete(): void {
        WsLog::l( 'Deleting StaticSite files' );

        if ( is_dir( self::getPath() ) ) {
            FilesHelper::deleteDirWithFiles( self::getPath() );

            // The crawled file data is not useful without
            // StaticSite files.
            CrawledFiles::truncate();
        }
    }

    /**
     *  Get all paths in StaticSite
     *
     *  @return string[] StaticSite paths
     */
    public static function getPaths(): array {
        $static_site_dir = self::getPath();

        if ( ! is_dir( $static_site_dir ) ) {
            return [];
        }

        $paths = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $static_site_dir,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ( $iterator as $filename => $file_object ) {
            /**
             * @var string $filename
             */
            $base_name = basename( $filename );
            if ( $base_name !== '.' && $base_name !== '..' ) {
                $real_filepath = realpath( $filename );

                if ( is_string( $real_filepath ) ) {
                    $paths[] = str_replace( $static_site_dir, '', $real_filepath );
                }
            }
        }

        sort( $paths );

        return $paths;
    }

    /**
     * Transform a root-relative path to a static site path.
     *
     * This lets us encapsulate the logic for path transformation in a single
     * place and use it in multiple places.
     */
    public static function transformPath( string $root_relative_path ): string {
        // do some magic here - naive: if URL ends in /, save to /index.html
        // TODO: will need love for example, XML files
        // check content type, serve .xml/rss, etc instead
        if ( mb_substr( $root_relative_path, -1 ) === '/' ) {
            return $root_relative_path . 'index.html';
        }
        return $root_relative_path;
    }
}
