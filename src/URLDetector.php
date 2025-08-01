<?php
/*
    URLDetector

    Detects URLs from WordPress DB, filesystem and user input

    Users can control detection levels

    Saves URLs to DetectedFiles

*/

namespace StaticDeploy;

class URLDetector {
    /**
     * Detect URLs within site
     *
     * @return \Iterator<PathInfo>
     */
    public static function detectURLsIter( bool $quiet = false ): \Iterator {
        if ( ! $quiet ) {
            WsLog::l( 'Starting to detect WordPress site URLs.' );
        }

        do_action(
            Controller::getHookName( 'detect' )
        );

        $filtering = new FileFiltering();

        $iterators_to_merge = [];

        $iterators_to_merge[] = new \ArrayIterator(
            [
                new PathInfo( '/' ),
                new PathInfo( '/robots.txt' ),
                new PathInfo( '/favicon.ico' ),
                new PathInfo( '/sitemap.xml' ),
            ]
        );

        $detect_parent_theme = apply_filters(
            Controller::getHookName( 'detect_parent_theme' ),
            1
        );

        if ( $detect_parent_theme ) {
            $iterators_to_merge[] = DetectThemeAssets::detect(
                $filtering,
                'parent',
            );
        }

        $detect_child_theme = apply_filters(
            Controller::getHookName( 'detect_child_theme' ),
            1
        );

        if ( $detect_child_theme ) {
            $iterators_to_merge[] = DetectThemeAssets::detect(
                $filtering,
                'child',
            );
        }

        $detect_plugin_assets = apply_filters(
            Controller::getHookName( 'detect_plugin_assets' ),
            1
        );

        if ( $detect_plugin_assets ) {
            $iterators_to_merge[] = DetectPluginAssets::detect( $filtering );
        }

        $detect_wpinc_assets = apply_filters(
            Controller::getHookName( 'detect_wpinc_assets' ),
            1
        );

        if ( $detect_wpinc_assets ) {
            $iterators_to_merge[] = DetectWPIncludesAssets::detect( $filtering );
        }

        if ( Options::getValue( 'detectUploads' ) ) {
            $iterators_to_merge[] =
                $filtering->getListOfLocalFilesByDir(
                    SiteInfo::getPath( 'uploads' ),
                );
        }

        $detect_vendor_cache = apply_filters(
            Controller::getHookName( 'detect_vendor_cache' ),
            1
        );

        if ( $detect_vendor_cache ) {
            $iterators_to_merge[] = DetectVendorFiles::detect(
                $filtering,
                SiteInfo::getURL( 'site' ),
            );
        }

        $detect_sitemaps = apply_filters(
            Controller::getHookName( 'detect_sitemaps' ),
            1
        );

        if ( $detect_sitemaps ) {
            $iterators_to_merge[] = DetectSitemapsURLs::detect(
                SiteInfo::getURL( 'site' ),
            );
        }

        if ( Options::getValue( 'detectPosts' ) ) {
            $iterators_to_merge[] = DetectPostURLs::detect();
        }

        if ( Options::getValue( 'detectPages' ) ) {
            $iterators_to_merge[] = DetectPageURLs::detect();
        }

        if ( Options::getValue( 'detectCustomPostTypes' ) ) {
            $iterators_to_merge[] = DetectCustomPostTypeURLs::detect();
        }

        $detect_posts_pagination = apply_filters(
            Controller::getHookName( 'detect_posts_pagination' ),
            1
        );

        if ( $detect_posts_pagination ) {
            $iterators_to_merge[] = DetectPostsPaginationURLs::detect(
                SiteInfo::getURL( 'site' ),
            );
        }

        $detect_archives = apply_filters(
            Controller::getHookName( 'detect_archives' ),
            1
        );

        if ( $detect_archives ) {
            $iterators_to_merge[] = DetectArchiveURLs::detect();
        }

        $detect_categories = apply_filters(
            Controller::getHookName( 'detect_categories' ),
            1
        );

        if ( $detect_categories ) {
            $iterators_to_merge[] = DetectCategoryURLs::detect();
        }

        $detect_category_pagination = apply_filters(
            Controller::getHookName( 'detect_category_pagination' ),
            1
        );

        if ( $detect_category_pagination ) {
            $iterators_to_merge[] = DetectCategoryPaginationURLs::detect();
        }

        $detect_authors = apply_filters(
            Controller::getHookName( 'detect_authors' ),
            1
        );

        if ( $detect_authors ) {
            $iterators_to_merge[] = DetectAuthorsURLs::detect();
        }

        $detect_authors_pagination = apply_filters(
            Controller::getHookName( 'detect_authors_pagination' ),
            1
        );

        if ( $detect_authors_pagination ) {
            $iterators_to_merge[] = DetectAuthorPaginationURLs::detect(
                SiteInfo::getUrl( 'site' ),
            );
        }

        $iterators_to_merge[] = DetectPluginRedirects::detect();

        $home_url = SiteInfo::getUrl( 'home' );
        $unique_urls = [];

        $last_log_time = microtime( true );

        foreach ( $iterators_to_merge as $iter ) {
            foreach ( $iter as $detected ) {
                $path = $detected->path;

                if ( ! isset( $unique_urls[ $path ] ) ) {
                    $unique_urls[ $path ] = true;

                    $detected_ct = count( $unique_urls );
                    $now = microtime( true );

                    if ( $now - $last_log_time >= 60 ) {
                        WsLog::l( 'Detected ' . $path );
                        $notice = "Detection progress: $detected_ct unique URLs found";
                        WsLog::l( $notice );
                        $last_log_time = microtime( true );
                    }

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

    public static function enqueueURLs(): int {
        $count = 0;

        $detected = static::detectURLsIter();
        foreach ( DetectedFiles::addPathsIter( $detected ) as $_ ) {
            ++$count;
        }

        return $count;
    }
}
