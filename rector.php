<?php declare(strict_types=1);

require_once __DIR__ . '/util/rector/RemoveAlwaysFalseIfStatementRector.php';
require_once __DIR__ . '/util/rector/RemoveAlwaysTrueIfConditionRector2.php';
require_once __DIR__ . '/util/rector/ReduceKnownBooleanAnd.php';
require_once __DIR__ . '/util/rector/ReduceKnownBooleanOr.php';
require_once __DIR__ . '/util/rector/ReplaceKnownDefinedWithBooleanRector.php';
require_once __DIR__ . '/util/rector/ReplaceControllerGetHookNameRector.php';

use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\Constants\Rector\FuncCall\ReplaceKnownDefinedWithBooleanRector;
use Rector\DeadCode\Rector\For_\RemoveDeadContinueRector;
use Rector\DeadCode\Rector\If_\RemoveAlwaysFalseIfStatementRector;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector2;
use Rector\Php53\Rector\Ternary\TernaryToElvisRector;
use Rector\Set\ValueObject\SetList;
use StaticDeploy\Rector\ReduceKnownBooleanAnd;
use StaticDeploy\Rector\ReduceKnownBooleanOr;
use StaticDeploy\Rector\ReplaceControllerGetHookNameRector;

// Search rules at https://getrector.com/find-rule

return RectorConfig::configure()
    ->withBootstrapFiles(
        [
            __DIR__ . '/constants.php',
        ],
    )
    ->withPaths(
        array_filter(
            [
                __DIR__ . '/src',
                // tests/ is excluded from the plugin build source
                is_dir( __DIR__ . '/tests' ) ? __DIR__ . '/tests' : null,
                __DIR__ . '/views',
            ]
        )
    )
    ->withRootFiles() // Include staticweb-deploy.php and uninstall.php
    ->withPhpSets() // Detects PHP version from composer.json
    ->withRules(
        [
            RemoveAlwaysFalseIfStatementRector::class,
            RemoveAlwaysTrueIfConditionRector2::class,
            ReduceKnownBooleanAnd::class,
            ReduceKnownBooleanOr::class,
            ReplaceKnownDefinedWithBooleanRector::class,
            ReplaceControllerGetHookNameRector::class,
        ]
    )
    ->withSets(
        [
            SetList::CODE_QUALITY,
            SetList::CODING_STYLE,
            SetList::DEAD_CODE,
            SetList::EARLY_RETURN,
            SetList::INSTANCEOF,
            SetList::PRIVATIZATION,
            SetList::TYPE_DECLARATION,
            SetList::TYPE_DECLARATION_DOCBLOCKS,
        ]
    )
    ->withSkip(
        [
            CatchExceptionNameMatchingTypeRector::class,
            EncapsedStringsToSprintfRector::class,
            NewlineAfterStatementRector::class,
            NewlineBeforeNewAssignSetRector::class,
            // Allow explicit loops to consume iterators for side-effects
            RemoveDeadContinueRector::class,
            TernaryToElvisRector::class,
        ]
    );
