<?php
/*
    ProcessedSite

    A processed version of a StaticSite, with URLs rewritten, folders renamed
    and other modifications made to prepare it for a Deployer
*/

namespace StaticDeploy;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

class ProcessedSite {

    private static string $processed_site_path;

    public static function getPath(): string {
        if ( ! isset( self::$processed_site_path ) ) {
            self::$processed_site_path =
                Options::getValue( 'processedSitePath' );
        }

        return SiteInfo::getPath( 'uploads' ) . self::$processed_site_path;
    }

    /**
     * Add PathInfo contents to processed site
     */
    public static function add(
        PathInfo $path_info,
    ): void {
        FilesHelper::writePathInfo(
            self::getPath(),
            $path_info,
        );
    }

    /**
     * Delete processed site files
     */
    public static function delete(): void {
        WsLog::l( 'Deleting ProcessedSite files' );

        if ( is_dir( self::getPath() ) ) {
            FilesHelper::deleteDirWithFiles( self::getPath() );
        }
    }

    /**
     *  Get all paths in ProcessedSite
     *
     *  @return string[] ProcessedSite paths
     */
    public static function getPaths(): array {
        $processed_site_dir = self::getPath();

        if ( ! is_dir( $processed_site_dir ) ) {
            return [];
        }

        $paths = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $processed_site_dir,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ( $iterator as $filename => $file_object ) {

            $base_name = basename( (string) $filename );
            if ( $base_name !== '.' && $base_name !== '..' ) {
                $real_filepath = realpath( $filename );

                if ( is_string( $real_filepath ) ) {
                    $paths[] = str_replace( $processed_site_dir, '', $real_filepath );
                }
            }
        }

        sort( $paths );

        return $paths;
    }
}
