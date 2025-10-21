<?php

namespace StaticDeploy;

/*
    Manage WP Cron schedules for processing job queue

*/
class WPCron {

    public static function setRecurringEvent( int $interval ): void {
        $next_timestamp = wp_next_scheduled( Controller::getHookName( 'process_queue' ) );

        if ( $interval === 0 ) {

            if ( ! $next_timestamp ) {
                return;
            }

            wp_unschedule_event( $next_timestamp, Controller::getHookName( 'process_queue' ) );
            return;
        }

        // remove existing first
        if ( $next_timestamp ) {
            wp_unschedule_event( $next_timestamp, Controller::getHookName( 'process_queue' ) );
        }

        $interval = $interval . 'min' . ( $interval > 1 ? 's' : '' );

        WsLog::l( 'Setting auto queue processing interval to ' . $interval );

        $result = wp_schedule_event(
            time(),
            $interval,
            Controller::getHookName( 'process_queue' )
        );

        if ( ! $result ) {
            WsLog::l( 'Unable to schedule WP Cron recurring event' );
        }
    }

    public static function clearRecurringEvent(): void {
        $next_timestamp = wp_next_scheduled( Controller::getHookName( 'process_queue' ) );

        if ( ! $next_timestamp ) {
            return;
        }

        wp_unschedule_event( $next_timestamp, Controller::getHookName( 'process_queue' ) );
    }

    /**
     * Register custom WP Cron schedule intervals
     *
     * @param array<string, mixed> $schedules array of CRON schedules
     * @return mixed[] array of CRON schedules
     */
    public static function customCronSchedules( array $schedules ): array {
        $schedules['1min'] = [
            'interval' => 1 * MINUTE_IN_SECONDS,
            'display' => 'Every minute',
        ];

        $schedules['5mins'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => 'Every 5 minutes',
        ];

        $schedules['10mins'] = [
            'interval' => 10 * MINUTE_IN_SECONDS,
            'display' => 'Every 10 minutes',
        ];

        return $schedules;
    }

    /**
     * Override WP-Cron to use http basic auth creds if set
     *
     * @param array<string, mixed> $cron_request WP-Cron request
     * @return mixed[] WP-Cron request
     */
    public static function cronWithBasicAuth( array $cron_request ): array {
        $auth_user = Options::getValue( 'basicAuthUser' );
        $auth_password = Options::getValue( 'basicAuthPassword' );

        if ( ! $auth_user || ! $auth_password ) {
            return $cron_request;
        }

        $auth_headers = [
            'Authorization' =>
                sprintf( 'Basic %s', base64_encode( $auth_user . ':' . $auth_password ) ),
        ];

        $cron_request_args = (array) ( $cron_request['args'] ?? [] );
        $cron_request_headers = (array) ( $cron_request_args['headers'] ?? [] );

        return array_merge( $cron_request_headers, $auth_headers );
    }
}
