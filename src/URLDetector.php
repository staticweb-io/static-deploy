<?php
/*
    URLDetector

    Detects URLs from WordPress DB, filesystem and user input

    Users can control detection levels

    Saves URLs to CrawlQueue

*/

namespace WP2Static;

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
        if ( ! $quiet ) {
            WsLog::l( 'Starting to detect WordPress site URLs.' );
        }

        do_action(
            'wp2static_detect'
        );

        $arrays_to_merge = [];

        // TODO: detect robots.txt, etc before adding
        $arrays_to_merge[] = [
            '/',
            '/robots.txt',
            '/favicon.ico',
            '/sitemap.xml',
        ];

        $iterators_to_merge = [];

        /*
            TODO: reimplement detection for URLs:
                'detectCommentPagination',
                'detectComments',
                'detectFeedURLs',

        // other options:

         - robots
         - favicon
         - sitemaps

        */

        if ( CoreOptions::getValue( 'detectPosts' ) ) {
            $arrays_to_merge[] = DetectPostURLs::detect();
        }

        if ( CoreOptions::getValue( 'detectPages' ) ) {
            $arrays_to_merge[] = DetectPageURLs::detect();
        }

        if ( CoreOptions::getValue( 'detectCustomPostTypes' ) ) {
            $arrays_to_merge[] = DetectCustomPostTypeURLs::detect();
        }

        if ( CoreOptions::getValue( 'detectUploads' ) ) {
            $filtering = new FileFiltering();
            $arrays_to_merge[] =
                $filtering->getListOfLocalFilesByDir(
                    SiteInfo::getPath( 'uploads' ),
                );
        }

        $detect_sitemaps = apply_filters( 'wp2static_detect_sitemaps', 1 );

        if ( $detect_sitemaps ) {
            $arrays_to_merge[] = DetectSitemapsURLs::detect( SiteInfo::getURL( 'site' ) );
        }

        $detect_parent_theme = apply_filters( 'wp2static_detect_parent_theme', 1 );

        if ( $detect_parent_theme ) {
            $iterators_to_merge[] = DetectThemeAssets::detect( 'parent' );
        }

        $detect_child_theme = apply_filters( 'wp2static_detect_child_theme', 1 );

        if ( $detect_child_theme ) {
            $iterators_to_merge[] = DetectThemeAssets::detect( 'child' );
        }

        $detect_plugin_assets = apply_filters( 'wp2static_detect_plugin_assets', 1 );

        if ( $detect_plugin_assets ) {
            $iterators_to_merge[] = DetectPluginAssets::detect();
        }

        $detect_wpinc_assets = apply_filters( 'wp2static_detect_wpinc_assets', 1 );

        if ( $detect_wpinc_assets ) {
            $iterators_to_merge[] = DetectWPIncludesAssets::detect();
        }

        $detect_vendor_cache = apply_filters( 'wp2static_detect_vendor_cache', 1 );

        if ( $detect_vendor_cache ) {
            $arrays_to_merge[] = DetectVendorFiles::detect( SiteInfo::getURL( 'site' ) );
        }

        $detect_posts_pagination = apply_filters( 'wp2static_detect_posts_pagination', 1 );

        if ( $detect_posts_pagination ) {
            $arrays_to_merge[] = DetectPostsPaginationURLs::detect( SiteInfo::getURL( 'site' ) );
        }

        $detect_archives = apply_filters( 'wp2static_detect_archives', 1 );

        if ( $detect_archives ) {
            $arrays_to_merge[] = DetectArchiveURLs::detect();
        }

        $detect_categories = apply_filters( 'wp2static_detect_categories', 1 );

        if ( $detect_categories ) {
            $arrays_to_merge[] = DetectCategoryURLs::detect();
        }

        $detect_category_pagination = apply_filters( 'wp2static_detect_category_pagination', 1 );

        if ( $detect_category_pagination ) {
            $arrays_to_merge[] = DetectCategoryPaginationURLs::detect();
        }

        $detect_authors = apply_filters( 'wp2static_detect_authors', 1 );

        if ( $detect_authors ) {
            $arrays_to_merge[] = DetectAuthorsURLs::detect();
        }

        $detect_authors_pagination = apply_filters( 'wp2static_detect_authors_pagination', 1 );

        if ( $detect_authors_pagination ) {
            $arrays_to_merge[] = DetectAuthorPaginationURLs::detect( SiteInfo::getUrl( 'site' ) );
        }

        $home_url = SiteInfo::getUrl( 'home' );
        $unique_urls = [];

        foreach ( $arrays_to_merge as $array ) {
            $iterators_to_merge[] = new \ArrayIterator( $array );
        }

        foreach ( $iterators_to_merge as $iter ) {
            foreach ( $iter as $detected ) {
                if ( ! is_array( $detected ) ) {
                    $detected = [ 'url' => $detected ];
                }

                $path = FilesHelper::cleanDetectedURL( $home_url, $detected['url'] );

                if ( $path && ! isset( $unique_urls[$path] ) ) {
                    $unique_urls[$path] = true;

                    $detected_ct = count( $unique_urls );
                    if ( count($unique_urls) % 300 === 0 ) {
                        $notice = "Detection progress: $detected_ct unique URLs found";
                        WsLog::l( $notice );
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

    public static function enqueueURLs() : string {
        $unique_urls = [];

        foreach ( static::detectURLs() as $d ) {
            $unique_urls[] = $d['path'];
        }

        CrawlQueue::addUrls( $unique_urls );

        return (string) count( $unique_urls );
    }
}

