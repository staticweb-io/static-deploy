<?php

declare (strict_types=1);
namespace Rector\DeadCode\Rector\If_;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\If_;
use PhpParser\NodeVisitor;
use PHPStan\Type\IntersectionType;
use Rector\DeadCode\NodeAnalyzer\SafeLeftTypeBooleanAndOrAnalyzer;
use Rector\NodeAnalyzer\ExprAnalyzer;
use Rector\PhpParser\Node\BetterNodeFinder;
use Rector\PHPStan\ScopeFetcher;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
/**
 * @see \Rector\Tests\DeadCode\Rector\If_\RemoveAlwaysFalseIfStatementRector\RemoveAlwaysFalseIfStatementRectorTest
 */
final class RemoveAlwaysFalseIfStatementRector extends AbstractRector
{
    /**
     * @readonly
     */
    private ExprAnalyzer $exprAnalyzer;
    /**
     * @readonly
     */
    private BetterNodeFinder $betterNodeFinder;
    /**
     * @readonly
     */
    private SafeLeftTypeBooleanAndOrAnalyzer $safeLeftTypeBooleanAndOrAnalyzer;
    public function __construct(ExprAnalyzer $exprAnalyzer, BetterNodeFinder $betterNodeFinder, SafeLeftTypeBooleanAndOrAnalyzer $safeLeftTypeBooleanAndOrAnalyzer)
    {
        $this->exprAnalyzer = $exprAnalyzer;
        $this->betterNodeFinder = $betterNodeFinder;
        $this->safeLeftTypeBooleanAndOrAnalyzer = $safeLeftTypeBooleanAndOrAnalyzer;
    }
    public function getRuleDefinition() : RuleDefinition
    {
        return new RuleDefinition('Remove if statement whose condition is always false', [new CodeSample(<<<'CODE_SAMPLE'
final class SomeClass
{
    public function go()
    {
        if (1 !== 1) {
            return 'no';
        }

        return 'yes';
    }
}
CODE_SAMPLE
, <<<'CODE_SAMPLE'
final class SomeClass
{
    public function go()
    {
        return 'yes';
    }
}
CODE_SAMPLE
)]);
    }
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes() : array
    {
        return [If_::class];
    }
    /**
     * @param If_ $node
     * @return int|null|Stmt[]|If_
     */
    public function refactor(Node $node)
    {
        if ($node->cond instanceof BooleanAnd) {
            return $this->refactorIfWithBooleanAnd($node);
        }
        $conditionStaticType = $this->nodeTypeResolver->getNativeType($node->cond);
        if (!$conditionStaticType->isFalse()->yes()) {
            return null;
        }
        if ($this->shouldSkipExpr($node->cond)) {
            return null;
        }
        if ($this->shouldSkipFromVariable($node->cond)) {
            return null;
        }
        $hasAssign = (bool) $this->betterNodeFinder->findFirstInstanceOf($node->cond, Assign::class);
        if ($hasAssign) {
            return null;
        }
        $type = ScopeFetcher::fetch($node)->getNativeType($node->cond);
        if (!$type->isFalse()->yes()) {
            return null;
        }
        if ($node->elseifs !== []) {
            if ($node->else instanceof Else_) {
                // Ignore combined else and elseifs.
                // It's not clear how this would be handled since we
                // don't know the original order of the statements.
                return null;
            }
            $firstElseIf = $node->elseifs[0];
            $if = new If_($firstElseIf->cond, ['stmts' => $firstElseIf->stmts]);
            if (count($node->elseifs) > 1) {
                $if->elseifs = \array_slice($node->elseifs, 1);
            }
            return $if;
        }
        if ($node->else instanceof Else_) {
            return $node->else->stmts;
        }
        return NodeVisitor::REMOVE_NODE;
    }
    private function shouldSkipFromVariable(Expr $expr) : bool
    {
        /** @var Variable[] $variables */
        $variables = $this->betterNodeFinder->findInstancesOf($expr, [Variable::class]);
        foreach ($variables as $variable) {
            if ($this->exprAnalyzer->isNonTypedFromParam($variable)) {
                return \true;
            }
            $type = $this->nodeTypeResolver->getNativeType($variable);
            if ($type instanceof IntersectionType) {
                foreach ($type->getTypes() as $subType) {
                    if ($subType->isArray()->yes()) {
                        return \true;
                    }
                }
            }
        }
        return \false;
    }
    private function shouldSkipExpr(Expr $expr) : bool
    {
        return (bool) $this->betterNodeFinder->findInstancesOf($expr, [PropertyFetch::class, StaticPropertyFetch::class, ArrayDimFetch::class, MethodCall::class, StaticCall::class]);
    }
    private function refactorIfWithBooleanAnd(If_ $if) : ?If_
    {
        if (!$if->cond instanceof BooleanAnd) {
            return null;
        }
        $booleanAnd = $if->cond;
        $leftType = $this->getType($booleanAnd->left);
        if (!$leftType->isTrue()->yes()) {
            return null;
        }
        if (!$this->safeLeftTypeBooleanAndOrAnalyzer->isSafe($booleanAnd)) {
            return null;
        }
        $if->cond = $booleanAnd->right;
        return $if;
    }
}