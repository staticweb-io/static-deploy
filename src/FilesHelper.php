<?php

namespace WP2Static;

use WP2Static\FileFiltering;

class FilesHelper {

    /**
     * Recursively delete a directory
     *
     * @throws WP2StaticException
     */
    public static function deleteDirWithFiles( string $dir ) : void {
        if ( is_dir( $dir ) ) {
            $dir_files = scandir( $dir );

            if ( ! $dir_files ) {
                $err = 'Trying to delete nonexistent dir: ' . $dir;
                WsLog::l( $err );
                throw new WP2StaticException( $err );
            }

            $files = array_diff( $dir_files, [ '.', '..' ] );

            foreach ( $files as $file ) {
                ( is_dir( "$dir/$file" ) ) ?
                self::deleteDirWithFiles( "$dir/$file" ) :
                unlink( "$dir/$file" );
            }

            rmdir( $dir );
        }
    }

    /**
     * Cleans detected URL before use. Accepts relative and absolute URLs
     * both with and without starting or trailing slashes.
     *
     * @param string $urls list of absolute or relative URLs
     * @return string|null list of relative URLs
     * @throws WP2StaticException
     */
    public static function cleanDetectedURL( string &$home_url, string &$url ) : ?string {
        if ( ! $url ) {
            return null;
        }

        // NOTE: 2 x str_replace's significantly faster than
        // 1 x str_replace with search/replace arrays of 2 length
        $url = str_replace(
            $home_url,
            '/',
            $url
        );

        $url = str_replace(
            '//',
            '/',
            $url
        );

        if ( ! is_string( $url ) ) {
            return null;
        }

        $url = strtok( $url, '#' );

        if ( ! $url ) {
            return null;
        }

        $url = strtok( $url, '?' );

        if ( ! $url ) {
            return null;
        }

        return $url;
    }

    /**
     * Clean all detected URLs before use. Accepts relative and absolute URLs
     * both with and without starting or trailing slashes.
     *
     * @param string[] $urls list of absolute or relative URLs
     * @return string[]|null[] list of relative URLs
     * @throws WP2StaticException
     */
    public static function cleanDetectedURLs( array $urls ) : array {
        $home_url = SiteInfo::getUrl( 'home' );

        if ( ! is_string( $home_url ) ) {
            $err = 'Home URL not defined ';
            WsLog::l( $err );
            throw new WP2StaticException( $err );
        }

        $cleaned_urls = [];

        foreach ( $urls as $url ) {
            $url = self::cleanDetectedURL( $home_url, $url );
            if ( $url ) {
                $cleaned_urls[] = $url;
            }
        }

        if ( empty( $cleaned_urls ) ) {
            $err = 'No valid URLs left after cleaning';
            WsLog::l( $err );
            return [];
        }

        return $cleaned_urls;
    }
}
