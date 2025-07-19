<?php

namespace StaticDeploy;

class DetectCustomPostTypeURLs {

    /**
     * Detect Custom Post Type URLs
     *
     * @return Iterator<array>
     */
    public static function detect(): \Iterator {
        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( 'Detecting custom post type URLs' );
        }

        global $wpdb;

        $post_ids = $wpdb->get_col(
            "SELECT ID
            FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND post_type NOT IN ('revision','nav_menu_item')"
        );

        foreach ( $post_ids as $post_id ) {
            $permalink = get_post_permalink( $post_id );

            if ( ! is_string( $permalink ) ) {
                continue;
            }

            if ( strpos( $permalink, '?post_type' ) !== false ) {
                continue;
            }

            yield [ 'url' => $permalink ];
        }
    }
}
