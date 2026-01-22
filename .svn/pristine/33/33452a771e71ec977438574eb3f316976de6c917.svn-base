<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Utils\Rector;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

// todo: make it configurable
final class ReplaceFunctionCallRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change function calls from htmlspecialchars to escape.',
            [new CodeSample('htmlspecialchars("string");', 'escape("string");')]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        // what node types are we looking for?
        // pick from https://github.com/rectorphp/php-parser-nodes-docs/
        return [FuncCall::class];
    }

    /**
     * @param FuncCall $node
     */
    public function refactor(Node $node): ?Node
    {
        $fct_name = $this->getName($node->name);
        if ($fct_name === null) {
            return null;
        }

        return $fct_name === 'htmlspecialchars' && !$node->isFirstClassCallable()
            ? new FuncCall(new Name('escape'), [$node->getArgs()[0]])
            : null;
    }
}
