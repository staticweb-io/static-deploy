<?php declare(strict_types=1);

namespace StaticDeploy\CLI;

use WP_CLI;

class Subcommand {
    /**
     * Adds a subcommand.
     *
     * See https://make.wordpress.org/cli/handbook/references/internal-api/wp-cli-add-command/
     * for details on args.
     */
    public static function register(
        string $slug,
        callable|object|string $callabl,
        array $args = [],
    ): void {
        WP_CLI::add_command(
            self::getName( $slug ),
            $callabl,
            $args,
        );
    }

    public static function getName( string $slug ): string {
        return 'static-deploy ' . $slug;
    }
}
