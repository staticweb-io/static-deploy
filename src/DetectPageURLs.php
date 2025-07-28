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

            $url = \Wa72\Url\Url::parse( $permalink );
            $url->setHost( '' );
            $url->setScheme( '' );

            yield new PathInfo( $url->write() );
        }
    }
}
