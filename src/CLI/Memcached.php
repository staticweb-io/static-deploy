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

        $mc = \StaticDeploy\Memcached::getMemcached();
        if ( ! $mc ) {
            WP_CLI::error( 'Memcached is not enabled or is not managed by this plugin.' );
        }

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
}
