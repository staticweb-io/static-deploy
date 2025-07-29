<?php declare(strict_types=1);

namespace StaticDeploy\CLI;

use WP_CLI;

class Subcommand {
    private static array $hidden_commands = [];

    /**
     * Adds a subcommand.
     *
     * See https://make.wordpress.org/cli/handbook/references/internal-api/wp-cli-add-command/
     * for details on args.
     *
     * @param string $slug
     * @param callable|object|string|string[] $callabl
     * @param array $args
     */
    public static function register(
        string $slug,
        // Even though the docs say that add_command takes
        // callable|object|string, it actually takes string[]
        // as well.
        callable|object|string|array $callabl,
        array $args = [],
    ): void {
        WP_CLI::add_command(
            self::getName( $slug ),
            $callabl,
            $args,
        );
    }

    /**
     * Adds a subcommand that is hidden from the main
     * command listing.
     *
     * See https://make.wordpress.org/cli/handbook/references/internal-api/wp-cli-add-command/
     * for details on args.
     *
     * @param string $slug
     * @param callable|object|string|string[] $callabl
     * @param array $args
     */
    public static function registerHidden(
        string $slug,
        callable|object|string|array $callabl,
        array $args = [],
    ): void {
        $slug_parts = explode( ' ', $slug );
        self::$hidden_commands[] = [
            $slug_parts,
            $callabl,
            $args,
        ];
    }

    public static function addHiddenCommands(): void {
        global $argv;

        $start = array_search( 'static-deploy', $argv );

        if ( $start === false || $start === count( $argv ) - 1 ) {
            return;
        }

        foreach ( self::$hidden_commands as $data ) {
            $ct = count( $data[0] );
            for ( $i = 0; $i < $ct; $i++ ) {
                if ( $argv[ $start + $i ] !== $data[0][ $i ] ) {
                    continue;
                }
            }
            if ( $i === $ct ) {
                WP_CLI::add_command(
                    self::getName( implode( ' ', $data[0] ) ),
                    $data[1],
                    $data[2],
                );
            }
        }
    }

    public static function getName( string $slug ): string {
        return 'static-deploy ' . $slug;
    }
}
