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

        if ( $chunk !== [] ) {
            yield $chunk;
        }
    }

    /**
     * Formats a \DateInterval into a human-readable string.
     * Example: 1 minute, 20 seconds
     *
     * Returns null if the interval is less than one second.
     *
     * @param \DateInterval $interval The interval to format.
     * @param int $max_parts The maximum number of parts to include.
     */
    public static function formatIntervalPretty(
        \DateInterval $interval,
        int $max_parts = 1,
    ): ?string {
        $parts = [];

        $map = [
            'y' => [ 'year', 'years' ],
            'm' => [ 'month', 'months' ],
            'd' => [ 'day', 'days' ],
            'h' => [ 'hour', 'hours' ],
            'i' => [ 'minute', 'minutes' ],
            's' => [ 'second', 'seconds' ],
        ];

        foreach ( $map as $key => [ $singular, $plural ] ) {
            $value = $interval->$key;
            if ( $value ) {
                $parts[] = "{$value} " . ( $value === 1 ? $singular : $plural );
            }
            if ( count( $parts ) >= $max_parts ) {
                break;
            }
        }

        if ( $parts === [] ) {
            return null;
        }

        return implode( ', ', $parts );
    }

    public static function strReplaceFirst(
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

    /**
     * Returns a \DateTime in the timezone of the
     * WordPress settings.
     */
    public static function wpDateTime(
        string $datetime = 'now',
    ): \DateTime {
        return new \DateTime(
            $datetime,
            new \DateTimeZone( wp_timezone_string() )
        );
    }
}
