<?php declare(strict_types=1);

namespace StaticDeploy\CLI;

use WP_CLI;

/**
 * Memcached configuration, stats, and data
 */
class Memcached {
    public static function registerCommands(): void {
        Subcommand::register(
            'memcached',
            self::class,
        );
    }

    public static function getMemcached(): \Memcached {
        return \StaticDeploy\Memcached::getMemcached(
            required: true,
        );
    }

    /**
     * Get stats from memcached
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : The format to output the stats data in.
     * ---
     * default: table
     * options:
     *  - table
     *  - json
     *  - csv
     *  - yaml
     *  - count
     * ---
     */
    public function stats(
        array $args,
        array $assoc_args,
    ): void {
        $cfg = Args::parse(
            $args,
            $assoc_args,
            [],
            [ 'format' => [ 'default' => 'table' ] ],
        );

        $mc = self::getMemcached();

        $stats = $mc->getStats();
        foreach ( $stats as $server => $server_stats ) {
            $table = [
                [
                    'name' => 'server',
                    'value' => $server,
                ],
            ];
            foreach ( $server_stats as $name => $value ) {
                $table[] = [
                    'name' => $name,
                    'value' => $value,
                ];
            }

            WP_CLI\Utils\format_items(
                $cfg['format'],
                $table,
                [ 'name', 'value' ]
            );
        }
    }

    /**
     * Dumps memcached key metadata
     *
     * The exact keys available are subject to change, but will include at least:
     *
     * - key
     * - exp (expiration time)
     * - la (last access time)
     * - cas
     * - fetch (if item has been fetched before, yes or no)
     *
     * Note: This is not guaranteed to contain all keys.
     * Memcached may move keys around during the processing.
     *
     * ## OPTIONS
     *
     * [--limit=<n>]
     * : Stop after this many lines.
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *   - yaml
     * ---
     */
    public function metadump( array $args, array $assoc_args ): void {
        $cfg = Args::parse(
            $args,
            $assoc_args,
            [],
            [
                'format' => [ 'default' => 'table' ],
                'limit'  => [ 'default' => null ],
            ]
        );

        $mc = self::getMemcached();
        $items = \StaticDeploy\Memcached::metadump( $mc );

        $output = [];
        $count = 0;
        foreach ( $items as $item ) {
            if ( $cfg['limit'] !== null && ++$count > (int) $cfg['limit'] ) {
                break;
            }

            $output[] = $item;
        }

        // The guaranteed keys
        $keys = [ 'key', 'exp', 'la', 'cas', 'fetch' ];
        if ( $output !== [] ) {
            // Merge any additional keys that are present
            $extra_keys = array_diff( array_keys( $output[0] ), $keys );
            sort( $extra_keys );
            $keys = array_merge( $keys, $extra_keys );
        }

        WP_CLI\Utils\format_items(
            $cfg['format'],
            $output,
            $keys,
        );
    }
}
