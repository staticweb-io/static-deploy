<?php

namespace StaticDeploy;

class DetectPageURLs {

    /**
     * Detect Page URLs
     *
     * @return \Iterator<PathInfo> list of URLs
     */
    public static function detect(): \Iterator {
        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( 'Detecting page URLs' );
        }

        $pages = get_posts(
            [
                'post_type' => 'page',
                'post_status' => 'publish',
                'posts_per_page' => -1,
            ],
        );

        foreach ( $pages as $page ) {
            $permalink = get_page_link( $page );

            if ( str_contains( $permalink, '?post_type' ) ) {
                continue;
            }

            yield new PathInfo( URLHelper::makeAbsolutePath( $permalink ) );
        }
    }
}
