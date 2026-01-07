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
    ->withDowngradeSets( php81: true );
