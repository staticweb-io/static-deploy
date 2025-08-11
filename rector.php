<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\For_\RemoveDeadContinueRector;
use Rector\Php53\Rector\Ternary\TernaryToElvisRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths(
        [
            __DIR__ . '/src',
            __DIR__ . '/src-github',
            __DIR__ . '/tests',
            __DIR__ . '/views',
        ]
    )
    ->withRules(
        [
            ClosureToArrowFunctionRector::class,
        ]
    )
    ->withSets( [ SetList::DEAD_CODE ] )
    ->withSkip(
        [
            // Allow explicit loops to consume iterators for side-effects
            RemoveDeadContinueRector::class,
            TernaryToElvisRector::class,
        ]
    );
