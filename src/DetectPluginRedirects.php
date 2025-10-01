<?php

namespace StaticDeploy;

use GuzzleHttp\Psr7\Utils as Psr7Utils;

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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT url FROM %i WHERE status='enabled'",
                $table_name,
            )
        );

        $ct = 0;
        foreach ( $rows as $row ) {
            $uri = Psr7Utils::uriFor( $row->url );
            $msg = PathInfo::pathErrorMessage( $uri );
            if ( $msg ) {
                WsLog::l(
                    'Can\'t use path pattern from Redirection plugin: '
                    . $uri . ' - ' . $msg
                );
                continue;
            }

            yield new PathInfo( $uri );
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT `match` FROM %i WHERE status=1',
                $table_name,
            )
        );

        $ct = 0;
        foreach ( $rows as $row ) {
            $uri = Psr7Utils::uriFor( $row->match );
            $msg = PathInfo::pathErrorMessage( $uri );
            if ( $msg ) {
                WsLog::l(
                    'Can\'t use path pattern from Redirect Redirection plugin: '
                    . $uri . ' - ' . $msg
                );
                continue;
            }

            yield new PathInfo( $uri );
            ++$ct;
        }

        WsLog::l( 'Detected ' . $ct . ' URL(s) from the Redirect Redirection plugin' );
    }
}
