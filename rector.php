<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\For_\RemoveDeadContinueRector;
use Rector\Php53\Rector\Ternary\TernaryToElvisRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector;
use Rector\Php81\Rector\Array_\FirstClassCallableRector;
use Rector\Set\ValueObject\SetList;

// Search rules at https://getrector.com/find-rule

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
            ChangeSwitchToMatchRector::class,
            ClosureToArrowFunctionRector::class,
            FirstClassCallableRector::class,
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
