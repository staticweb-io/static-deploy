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

            if ( str_contains( $permalink, '?post_type' ) ) {
                continue;
            }

            yield new PathInfo( URLHelper::makeAbsolutePath( $permalink ) );
        }
    }
}
