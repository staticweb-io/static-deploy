<?php

namespace WP2Static;

class DetectPostURLs {

    /**
     * Detect Post URLs
     *
     * @return \Iterator<array> list of URLs
     */
    public static function detect( bool $log = false ): \Iterator {
        if ( $log ) {
            WsLog::l( 'Detecting post URLs' );
        }

        global $wpdb;

        $post_ids = $wpdb->get_col(
            "SELECT ID
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type = 'post'"
        );

        foreach ( $post_ids as $post_id ) {
            $permalink = get_permalink( $post_id );

            if ( ! $permalink ) {
                continue;
            }

            if ( strpos( $permalink, '?post_type' ) !== false ) {
                continue;
            }

            yield [ 'url' => $permalink ];
        }
    }
}
