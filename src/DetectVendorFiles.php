<?php

namespace StaticDeploy;

class DetectVendorFiles {

    /**
     * Detect vendor URLs from filesystem
     *
     * @return \Iterator<array> list of URLs
     */
    public static function detect(
        FileFiltering $filtering,
        string $wp_site_url,
    ): \Iterator {
        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( 'Detecting vendor files' );
        }

        $content_path = SiteInfo::getPath( 'content' );
        $site_url = SiteInfo::getUrl( 'site' );
        $content_url = SiteInfo::getUrl( 'content' );

        $vendor_cache_dirs = [
            $content_path . 'cache/', // cache dir used by Autoptimize and other themes/plugins
            $content_path . 'et-cache/', // cache dir used by Elegant Themes
        ];

        foreach ( $vendor_cache_dirs as $vendor_cache_dir ) {
            if ( is_dir( $vendor_cache_dir ) ) {
                // get difference between home and wp-contents URL
                $prefix = str_replace(
                    $site_url,
                    '/',
                    $content_url
                );

                $vendor_cache_urls = DetectVendorCache::detect(
                    $filtering,
                    $vendor_cache_dir,
                    $content_path,
                    $prefix
                );

                foreach ( $vendor_cache_urls as $arr ) {
                    yield $arr;
                }
            }
        }

        if ( class_exists( 'Custom_Permalinks' ) ) {
            global $wpdb;

            $query = "
                SELECT meta_value
                FROM %s
                WHERE meta_key = '%s'
                ";

            $posts = $wpdb->get_results(
                sprintf(
                    $query,
                    $wpdb->postmeta,
                    'custom_permalink'
                )
            );

            if ( $posts ) {
                foreach ( $posts as $post ) {
                    yield [ 'url' => $wp_site_url . $post->meta_value ];
                }
            }
        }
    }
}
