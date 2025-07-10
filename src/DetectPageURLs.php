<?php

namespace WP2Static;

class DetectPageURLs {

    /**
     * Detect Page URLs
     *
     * @return \Iterator<array> list of URLs
     */
    public static function detect( bool $log = false ): \Iterator {
        if ( $log ) {
            WsLog::l( 'Detecting page URLs' );
        }

        global $wpdb;

        $page_ids = $wpdb->get_col(
            "SELECT ID
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type = 'page'"
        );

        foreach ( $page_ids as $page_id ) {
            $permalink = get_page_link( $page_id );

            if ( strpos( $permalink, '?post_type' ) !== false ) {
                continue;
            }

            yield [ 'url' => $permalink ];
        }
    }
}
