<?php

declare(strict_types=1);

namespace StaticDeploy\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

/**
 * Replaces Controller::getHookName('siteinfo') with e.g., 'static_deploy_siteinfo'
 * Prefix is determined by STATIC_DEPLOY_HOOK_NAME_PREFIX
 */
final class ReplaceControllerGetHookNameRector extends AbstractRector {
    public function getRuleDefinition(): RuleDefinition {
        $before = "\$hook = Controller::getHookName('siteinfo');";
        $after  = "\$hook = 'static_deploy_siteinfo';";

        return new RuleDefinition(
            'Replace Controller::getHookName() calls with static string literals',
            [
                new CodeSample( $before, $after ),
            ]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array {
        return [ StaticCall::class ];
    }

    /**
     * @param StaticCall $node
     */
    public function refactor( Node $node ): ?Node {
        if ( ! defined( 'STATIC_DEPLOY_HOOK_NAME_PREFIX' ) ) {
            return null;
        }

        // Check if this is a static call to getHookName
        if ( ! $this->isName( $node->name, 'getHookName' ) ) {
            return null;
        }

        // Check if this is a static call to Controller::getHookName or self::getHookName
        if ( ! $node->class instanceof Name ) {
            return null;
        }

        $class_name = $this->getName( $node->class );

        // Handle explicit Controller references
        $is_controller = $class_name === 'Controller'
            || $class_name === 'StaticDeploy\Controller';

        // Handle self:: references - check if we're inside the Controller class
        $is_self_in_controller = false;
        if ( $class_name === 'self' ) {
            $scope = $node->getAttribute( 'scope' );
            if ( $scope !== null ) {
                $class_reflection = $scope->getClassReflection();
                if ( $class_reflection !== null ) {
                    $current_class         = $class_reflection->getName();
                    $is_self_in_controller = $current_class === 'StaticDeploy\Controller';
                }
            }
        }

        if ( ! $is_controller && ! $is_self_in_controller ) {
            return null;
        }

        // Check if there's exactly one argument and it's a string
        if ( count( $node->args ) !== 1 ) {
            return null;
        }

        $arg = $node->args[0]->value;
        if ( ! $arg instanceof String_ ) {
            return null;
        }

        $hook_slug          = $arg->value;
        $replacement_string = STATIC_DEPLOY_HOOK_NAME_PREFIX . $hook_slug;

        return new String_( $replacement_string );
    }
}
