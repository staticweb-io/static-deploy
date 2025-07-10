<?php

namespace WP2Static;

class DetectAuthorsURLs {

    /**
     * Detect Authors URLs
     *
     * @return \Iterator<array> list of URLs
     */
    public static function detect( bool $log = false ): \Iterator {
        if ( $log ) {
            WsLog::l( 'Detecting author URLs' );
        }

        global $wp_rewrite, $wpdb;

        $users = get_users();

        foreach ( $users as $author ) {
            $author_link = get_author_posts_url( $author->ID );

            if ( ! is_string( $author_link ) ) {
                continue;
            }

            $permalink = trim( $author_link );

            yield [ 'url' => $permalink ];
        }
    }
}
