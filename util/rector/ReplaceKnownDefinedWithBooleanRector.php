<?php

declare(strict_types=1);

namespace Rector\Constants\Rector\FuncCall;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Expr\ConstFetch;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replace defined() calls with boolean values when the constant is defined in bootstrap
 *
 * @see \Rector\Tests\DeadCode\Rector\FuncCall\ReplaceDefinedWithBooleanRector\ReplaceDefinedWithBooleanRectorTest
 */
final class ReplaceKnownDefinedWithBooleanRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Replace defined() calls with boolean true values when the constant is defined in bootstrap', [
            new CodeSample(
                <<<'CODE_SAMPLE'
if (defined('BOOTSTRAPPED_CONSTANT')) {
    // do something
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
if (true) {
    // do something
}
CODE_SAMPLE
            )
        ]);
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    /**
     * @param FuncCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if (!$this->isName($node, 'defined')) {
            return null;
        }

        if (count($node->args) !== 1) {
            return null;
        }

        $firstArg = $node->args[0]->value;
        if (!$firstArg instanceof String_) {
            return null;
        }

        if (defined($firstArg->value)) {
            return new ConstFetch(new Name('true'));
        }

        return null;
    }
}