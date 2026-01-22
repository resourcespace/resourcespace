<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Utils\Rector;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Name;
use Rector\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;

final class EscapeLanguageStringsRector extends AbstractRector
{
    public function getRuleDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            'Change instances where $lang is directly echoed without escaping for XSS.'
            [new CodeSample('echo $lang["string"];', 'escape($lang["string"]);')]
        );
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        // What node types are we looking for? Pick from https://github.com/rectorphp/php-parser-nodes-docs/
        return [Echo_::class];
    }

    /**
     * @param Echo_ $node
     */
    public function refactor(Node $node): ?Node
    {
        $expr = $node->exprs[0];
        $var_name = $expr->var->name;
        $dim_value = $expr->dim->value;

        // Only look for this use case form: echo $lang['home'];
        if (!(is_a($expr, ArrayDimFetch::class, false) && $var_name === 'lang')) {
            return null;
        }

        // Only load the en version because we assume other translations follow its format (e.g. if a string contains
        // HTML tags, all the other translations should do too).
        require dirname(__DIR__) . '/languages/en.php';
        $plugins_path = dirname(__DIR__) . '/plugins';
        $valid_plugins = scandir($plugins_path) ?: [];
        $plugin = array_pop(
            array_reverse(
                array_diff(explode('/', $this->file->getFilePath()), explode('/', $plugins_path))
            )
        );
        $plugin_en_file = "{$plugins_path}/{$plugin}/languages/en.php";
        if (in_array($plugin, $valid_plugins) && file_exists($plugin_en_file)) {
            require $plugin_en_file;
        }

        if (!isset($lang[$dim_value])) {
            return null;
        }

        $fct_name = $lang[$dim_value] !== strip_tags($lang[$dim_value]) ? 'strip_tags_and_attributes' : 'escape';

        return new Echo_([new FuncCall(new Name($fct_name), [new Arg($expr)])]);
    }
}
