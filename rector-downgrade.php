<?php declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths(
        [
            __DIR__ . '/src',
            __DIR__ . '/vendor',
            __DIR__ . '/views',
        ]
    )
    ->withRootFiles() // Include staticweb-deploy.php and uninstall.php
    // Skip dev dependencies
    ->withSkip(
        [
            __DIR__ . '/vendor/dealerdirect',
            __DIR__ . '/vendor/mikey179',
            __DIR__ . '/vendor/myclabs',
            __DIR__ . '/vendor/nikic',
            __DIR__ . '/vendor/phar-io',
            __DIR__ . '/vendor/phpcompatibility',
            __DIR__ . '/vendor/phpcsstandards',
            __DIR__ . '/vendor/php-parallel-lint',
            __DIR__ . '/vendor/phpstan',
            __DIR__ . '/vendor/php-stubs',
            __DIR__ . '/vendor/phpunit',
            __DIR__ . '/vendor/rector',
            __DIR__ . '/vendor/sebastian',
            __DIR__ . '/vendor/squizlabs',
            __DIR__ . '/vendor/staabm',
            __DIR__ . '/vendor/szepeviktor',
            __DIR__ . '/vendor/theseer',
            __DIR__ . '/vendor/wp-coding-standards',
        ]
    )
    ->withDowngradeSets( php81: true );
