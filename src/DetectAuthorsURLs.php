<?php

namespace StaticDeploy;

class DetectAuthorsURLs {

    /**
     * Detect Authors URLs
     *
     * @return \Iterator<PathInfo> list of URLs
     */
    public static function detect(): \Iterator {
        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( 'Detecting author URLs' );
        }

        global $wp_rewrite, $wpdb;

        $users = get_users();

        foreach ( $users as $author ) {
            $author_link = get_author_posts_url( $author->ID );

            if ( ! is_string( $author_link ) ) {
                continue;
            }

            $permalink = trim( $author_link );

            yield new PathInfo( $permalink );
        }
    }
}
