<?php declare(strict_types=1);

namespace StaticDeploy\CLI;

use WP_CLI;

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
        $lines = \StaticDeploy\Memcached::metadump( $mc );

        $output = [];
        $count = 0;
        foreach ( $lines as $line ) {
            $line = rtrim( $line );
            if ( $cfg['limit'] !== null && ++$count > (int) $cfg['limit'] ) {
                break;
            }

            if ( preg_match( '/key=([^ ]+)/', $line, $m ) ) {
                $output[] = [ 'key' => $m[1] ];
            }
        }

        WP_CLI\Utils\format_items(
            $cfg['format'],
            $output,
            [ 'key' ]
        );
    }
}
