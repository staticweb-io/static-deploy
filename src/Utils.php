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
