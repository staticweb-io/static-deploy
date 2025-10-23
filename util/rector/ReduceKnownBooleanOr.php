<?php

declare(strict_types=1);

namespace StaticDeploy\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use Rector\DeadCode\NodeAnalyzer\SafeLeftTypeBooleanAndOrAnalyzer;
use Rector\PhpParser\Node\Value\ValueResolver;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class ReduceKnownBooleanOr extends AbstractRector
{
    public function __construct(
        private readonly SafeLeftTypeBooleanAndOrAnalyzer $safeLeftTypeBooleanAndOrAnalyzer,
        private readonly ValueResolver $valueResolver
    ) {
    }

    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition('Remove `or false` that has no added value', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function run()
    {
        return false || 5 === 1;
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function run()
    {
        return 5 === 1;
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [BooleanOr::class];
    }

    /**
     * @param BooleanOr $node
     */
    public function refactor(Node $node): ?Node
    {
        /* TODO: Ensure type doesn't change for exprs like f() || false
         * if ($this->isFalseOrBooleanOrFalses($node->right)) {
         *     return $node->left;
         * }
         */

        if (! $this->safeLeftTypeBooleanAndOrAnalyzer->isSafe($node)) {
            return null;
        }

        $conditionStaticType = $this->getType($node->left);

        if ($conditionStaticType->isFalse()->yes()) {
            return $node->right;
        }

        if ($conditionStaticType->isTrue()->yes()) {
            return new ConstFetch(new Name('true'));
        }

        return null;
    }

    private function isFalseOrBooleanOrFalses(Expr $expr): bool
    {
        if ($this->valueResolver->isFalse($expr)) {
            return true;
        }

        if (! $expr instanceof BooleanOr) {
            return false;
        }

        if (! $this->isFalseOrBooleanOrFalses($expr->left)) {
            return false;
        }

        return $this->isFalseOrBooleanOrFalses($expr->right);
    }
}
