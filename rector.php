<?php declare(strict_types=1);

require_once __DIR__ . '/util/rector/RemoveAlwaysFalseIfStatementRector.php';
require_once __DIR__ . '/util/rector/RemoveAlwaysTrueIfConditionRector2.php';

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\For_\RemoveDeadContinueRector;
use Rector\DeadCode\Rector\If_\RemoveAlwaysFalseIfStatementRector;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector2;
use Rector\Php53\Rector\Ternary\TernaryToElvisRector;
use Rector\Set\ValueObject\SetList;

// Search rules at https://getrector.com/find-rule

return RectorConfig::configure()
    ->withBootstrapFiles(
        [
            __DIR__ . '/constants.php',
        ],
    )
    ->withPaths(
        [
            __DIR__ . '/src',
            __DIR__ . '/src-github',
            __DIR__ . '/tests',
            __DIR__ . '/views',
        ]
    )
    ->withRootFiles() // Include static-deploy.php and uninstall.php
    ->withPhpSets() // Detects PHP version from composer.json
    ->withRules(
        [
            RemoveAlwaysFalseIfStatementRector::class,
            RemoveAlwaysTrueIfConditionRector2::class,
        ]
    )
    ->withSets(
        [
            SetList::DEAD_CODE,
            SetList::EARLY_RETURN,
            SetList::INSTANCEOF,
        ]
    )
    ->withSkip(
        [
            // Allow explicit loops to consume iterators for side-effects
            RemoveDeadContinueRector::class,
            TernaryToElvisRector::class,
        ]
    );
