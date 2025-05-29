<?php
/*
    URLDetector

    Detects URLs from WordPress DB, filesystem and user input

    Users can control detection levels

    Saves URLs to CrawlQueue

*/

namespace WP2Static;

use WP2Static\FileFiltering;

class URLDetector {

    public static function countURLs() : int {
        return count( static::detectURLs( $quiet = true ) );
    }

    /**
     * Detect URLs within site
     *
     * @return array<string>
     */
    public static function detectURLs( bool $quiet = false ) : array {
        return iterator_to_array( static::detectURLsIter( $quiet ) );
    }

    public static function detectURLsIter( bool $quiet = false ) : \Iterator {
        $log_steps = CoreOptions::getValue( 'logDetectionSteps' );

        if ( ! $quiet ) {
            WsLog::l( 'Starting to detect WordPress site URLs.' );
        }

        do_action(
            'wp2static_detect'
        );

        $filtering = new FileFiltering();

        $arrays_to_merge = [];

        $arrays_to_merge[] = [
            '/',
            '/robots.txt',
            '/favicon.ico',
            '/sitemap.xml',
        ];

        $iterators_to_merge = [];

        $detect_parent_theme = apply_filters( 'wp2static_detect_parent_theme', 1 );

        if ( $detect_parent_theme ) {
            $iterators_to_merge[] = DetectThemeAssets::detect( $filtering, 'parent', log: $log_steps );
        }

        $detect_child_theme = apply_filters( 'wp2static_detect_child_theme', 1 );

        if ( $detect_child_theme ) {
            $iterators_to_merge[] = DetectThemeAssets::detect( $filtering, 'child', log: $log_steps );
        }

        $detect_plugin_assets = apply_filters( 'wp2static_detect_plugin_assets', 1 );

        if ( $detect_plugin_assets ) {
            $iterators_to_merge[] = DetectPluginAssets::detect( $filtering, log: $log_steps );
        }

        $detect_wpinc_assets = apply_filters( 'wp2static_detect_wpinc_assets', 1 );

        if ( $detect_wpinc_assets ) {
            $iterators_to_merge[] = DetectWPIncludesAssets::detect( $filtering, log: $log_steps );
        }

        if ( CoreOptions::getValue( 'detectUploads' ) ) {
            $iterators_to_merge[] =
                $filtering->getListOfLocalFilesByDir(
                    SiteInfo::getPath( 'uploads' ),
                );
        }

        $detect_vendor_cache = apply_filters( 'wp2static_detect_vendor_cache', 1 );

        if ( $detect_vendor_cache ) {
            $iterators_to_merge[] = DetectVendorFiles::detect( $filtering, SiteInfo::getURL( 'site' ), log: $log_steps );
        }

        $detect_sitemaps = apply_filters( 'wp2static_detect_sitemaps', 1 );

        if ( $detect_sitemaps ) {
            $iterators_to_merge[] = DetectSitemapsURLs::detect( SiteInfo::getURL( 'site' ), log: $log_steps );
        }

        if ( CoreOptions::getValue( 'detectPosts' ) ) {
            $arrays_to_merge[] = DetectPostURLs::detect( log: $log_steps );
        }

        if ( CoreOptions::getValue( 'detectPages' ) ) {
            $arrays_to_merge[] = DetectPageURLs::detect( log: $log_steps );
        }

        if ( CoreOptions::getValue( 'detectCustomPostTypes' ) ) {
            $arrays_to_merge[] = DetectCustomPostTypeURLs::detect( log: $log_steps );
        }

        $detect_posts_pagination = apply_filters( 'wp2static_detect_posts_pagination', 1 );

        if ( $detect_posts_pagination ) {
            $arrays_to_merge[] = DetectPostsPaginationURLs::detect( SiteInfo::getURL( 'site' ), log: $log_steps );
        }

        $detect_archives = apply_filters( 'wp2static_detect_archives', 1 );

        if ( $detect_archives ) {
            $arrays_to_merge[] = DetectArchiveURLs::detect( log: $log_steps );
        }

        $detect_categories = apply_filters( 'wp2static_detect_categories', 1 );

        if ( $detect_categories ) {
            $arrays_to_merge[] = DetectCategoryURLs::detect( log: $log_steps );
        }

        $detect_category_pagination = apply_filters( 'wp2static_detect_category_pagination', 1 );

        if ( $detect_category_pagination ) {
            $arrays_to_merge[] = DetectCategoryPaginationURLs::detect( log: $log_steps );
        }

        $detect_authors = apply_filters( 'wp2static_detect_authors', 1 );

        if ( $detect_authors ) {
            $arrays_to_merge[] = DetectAuthorsURLs::detect( log: $log_steps );
        }

        $detect_authors_pagination = apply_filters( 'wp2static_detect_authors_pagination', 1 );

        if ( $detect_authors_pagination ) {
            $arrays_to_merge[] = DetectAuthorPaginationURLs::detect( SiteInfo::getUrl( 'site' ), log: $log_steps );
        }

        $home_url = SiteInfo::getUrl( 'home' );
        $unique_urls = [];

        foreach ( $arrays_to_merge as $array ) {
            $iterators_to_merge[] = new \ArrayIterator( $array );
        }

        $last_log_time = microtime( true );

        foreach ( $iterators_to_merge as $iter ) {
            foreach ( $iter as $detected ) {
                if ( ! is_array( $detected ) ) {
                    $detected = [ 'url' => $detected ];
                }

                $path = FilesHelper::cleanDetectedURL( $home_url, $detected['url'] );

                if ( $path && ! isset( $unique_urls[$path] ) ) {
                    $unique_urls[$path] = true;

                    $detected_ct = count( $unique_urls );
                    $now = microtime( true );

                    if ( $now - $last_log_time >= 60 ) {
                        WsLog::l( 'Detected ' . $path );
                        $notice = "Detection progress: $detected_ct unique URLs found";
                        WsLog::l( $notice );
                        $last_log_time = microtime( true );
                    }

                    $detected['path'] = $path;
                    yield $detected;
                }
            }
        }

        $detected_ct = count( $unique_urls );

        if ( ! $quiet ) {
            WsLog::l(
                "Detection complete. $detected_ct URLs found."
            );
        }
    }

    public static function enqueueURLs() : int {
        $count = 0;

        $detected = static::detectURLsIter();
        foreach ( CrawlQueue::addPathsIter($detected) as $_ ) {
            $count++;
        }

        return $count;
    }
}

