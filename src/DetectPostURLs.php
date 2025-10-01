<?php

namespace StaticDeploy;

class DetectPostURLs {

    /**
     * Detect Post URLs
     *
     * @return \Iterator<PathInfo> list of URLs
     */
    public static function detect(): \Iterator {
        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( 'Detecting post URLs' );
        }

        $posts = get_posts(
            [
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => -1,
            ],
        );

        foreach ( $posts as $post ) {
            $permalink = get_permalink( $post );

            if ( ! $permalink ) {
                continue;
            }

            if ( str_contains( $permalink, '?post_type' ) ) {
                continue;
            }

            yield new PathInfo( URLHelper::makeAbsolutePath( $permalink ) );
        }
    }
}
