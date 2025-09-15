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
     * @param callable|object|string|string[] $callabl
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
     * @param callable|object|string|string[] $callabl
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

        $start = array_search( 'static-deploy', $argv, true );

        if ( $start === false || $start === count( $argv ) - 1 ) {
            return;
        }

        foreach ( self::$hidden_commands as $hidden_command ) {
            $ct = count( $hidden_command[0] );
            for ( $i = 0; $i < $ct; $i++ ) {
                if ( $argv[ $start + $i ] !== $hidden_command[0][ $i ] ) {
                    continue;
                }
            }
            if ( $i === $ct ) {
                WP_CLI::add_command(
                    self::getName( implode( ' ', $hidden_command[0] ) ),
                    $hidden_command[1],
                    $hidden_command[2],
                );
            }
        }
    }

    public static function getName( string $slug ): string {
        return 'static-deploy ' . $slug;
    }
}
