<?php declare(strict_types=1);

namespace StaticDeploy\CLI;

use WP_CLI;

class Subcommand {
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

    public static function getName( string $slug ): string {
        return 'static-deploy ' . $slug;
    }
}
