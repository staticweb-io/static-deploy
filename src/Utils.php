<?php

namespace StaticDeploy;

use Exception;

class Utils {
    public static function chunkIterator( iterable $iterator, int $chunk_size ): \Iterator {
        $chunk = [];

        foreach ( $iterator as $item ) {
            $chunk[] = $item;

            if ( count( $chunk ) === $chunk_size ) {
                yield $chunk;
                $chunk = [];
            }
        }

        if ( ! empty( $chunk ) ) {
            yield $chunk;
        }
    }

    /*
     * Takes either an http or https URL and returns a // protocol-relative URL
     *
     * @param string $start timer start
     * @param string $end timer end
     * @return float time between start and finish
     */
    public static function microtime_diff(
        string $start,
        string $end = null
    ): float {
        if ( ! $end ) {
            $end = microtime();
        }

        list( $start_usec, $start_sec ) = explode( ' ', $start );
        list( $end_usec, $end_sec ) = explode( ' ', $end );

        $diff_sec = intval( $end_sec ) - intval( $start_sec );
        $diff_usec = floatval( $end_usec ) - floatval( $start_usec );

        return floatval( $diff_sec ) + $diff_usec;
    }

    /*
     * Adjusts the max_execution_time ini option
     *
     */
    public static function set_max_execution_time(): void {
        if (
            ! function_exists( 'set_time_limit' ) ||
            ! function_exists( 'ini_get' )
        ) {
            return;
        }

        $current_max_execution_time  = intval( ini_get( 'max_execution_time' ) );
        $proposed_max_execution_time =
            ( $current_max_execution_time === 30 ) ? 31 : 30;
        set_time_limit( $proposed_max_execution_time );
        $current_max_execution_time = intval( ini_get( 'max_execution_time' ) );

        if ( $proposed_max_execution_time === $current_max_execution_time ) {
            set_time_limit( 0 );
        }
    }

    public static function str_replace_first(
        string $search,
        string $replace,
        string $subject,
    ): string {
        $pos = strpos( $subject, $search );
        if ( $pos === false ) {
            return $subject;
        }
        return substr_replace( $subject, $replace, $pos, strlen( $search ) );
    }
}
