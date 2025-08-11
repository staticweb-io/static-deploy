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

        foreach ( $users as $user ) {
            $author_link = get_author_posts_url( $user->ID );

            if ( ! is_string( $author_link ) ) {
                continue;
            }

            $permalink = trim( $author_link );
            yield new PathInfo( URLHelper::makeAbsolutePath( $permalink ) );
        }
    }
}
