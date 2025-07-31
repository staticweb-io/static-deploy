<?php

namespace StaticDeploy;

class DetectPluginRedirects {

    /**
     * Detect redirects from known plugins
     *
     * @return \Iterator<PathInfo>
     */
    public static function detect(): \Iterator {
        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( 'Detecting plugin redirects' );
        }

        yield from self::detectRedirection();
        yield from self::detectRedirectRedirection();
    }

    /**
     * Detect redirects from the Redirection plugin
     * https://wordpress.org/plugins/redirection/
     *
     * @return \Iterator<PathInfo>
     */
    public static function detectRedirection(): \Iterator {
        global $wpdb;

        $table_name = $wpdb->prefix . 'redirection_items';

        if ( ! Db::tableExists( $table_name ) ) {
            if ( STATIC_DEPLOY_DEBUG ) {
                WsLog::d( 'Redirection plugin table not found' );
            }
            return;
        }

        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( 'Detecting redirects from the Redirection plugin' );
        }

        // We only need the URLs because we will crawl them
        // to determine the actual status and location.
        $rows = $wpdb->get_results(
            "SELECT url FROM $table_name WHERE status='enabled'"
        );

        $ct = 0;
        foreach ( $rows as $row ) {
            yield new PathInfo( $row->url );
            ++$ct;
        }

        WsLog::l( 'Detected ' . $ct . ' URL(s) from the Redirection plugin' );
    }

    /**
     * Detect redirects from the Redirect Redirection plugin
     * https://wordpress.org/plugins/redirect-redirection/
     *
     * @return \Iterator<PathInfo>
     */
    public static function detectRedirectRedirection(): \Iterator {
        global $wpdb;

        $table_name = $wpdb->prefix . 'irrp_redirections';

        if ( ! Db::tableExists( $table_name ) ) {
            if ( STATIC_DEPLOY_DEBUG ) {
                WsLog::d( 'Redirect Redirection plugin table not found' );
            }
            return;
        }

        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d( 'Detecting redirects from the Redirect Redirection plugin' );
        }

        // We only need the URLs because we will crawl them
        // to determine the actual status and location.
        $rows = $wpdb->get_results(
            "SELECT `match` FROM $table_name WHERE status=1"
        );

        $ct = 0;
        foreach ( $rows as $row ) {
            yield new PathInfo( $row->match );
            ++$ct;
        }

        WsLog::l( 'Detected ' . $ct . ' URL(s) from the Redirect Redirection plugin' );
    }
}
