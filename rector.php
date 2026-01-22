<?php

declare(strict_types=1);

use Montala\ResourceSpace\Utils\Rector\EscapeLanguageStringsRector;
use Montala\ResourceSpace\Utils\Rector\ReplaceFunctionCallRector;
use Rector\Config\RectorConfig;
use Rector\Removing\Rector\FuncCall\RemoveFuncCallArgRector;
use Rector\Removing\ValueObject\RemoveFuncCallArg;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNativeCallRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictScalarReturnExprRector;

/*
Common refactoring examples:

1. Removing a functions' argument:
    ->withConfiguredRule(
        RemoveFuncCallArgRector::class,
        [new RemoveFuncCallArg('get_edit_access', 2)]
    )

Result:
-function get_edit_access($resource,$status=-999,$metadata=false,&$resourcedata="")
+function get_edit_access($resource,$status=-999,&$resourcedata="")

2. TBD
*/
return RectorConfig::configure()
    ->withSkip([
        __DIR__ . '/plugins/*/css',
        __DIR__ . '/plugins/*/dbstruct',
        __DIR__ . '/plugins/*/lib',
        __DIR__ . '/vendor',
    ])
    ->withPaths([
        __DIR__ . '/index.php',
        __DIR__ . '/login.php',
        __DIR__ . '/api',
        __DIR__ . '/batch',
        __DIR__ . '/include',
        __DIR__ . '/languages', # can be useful for language changes
        __DIR__ . '/pages',
        __DIR__ . '/plugins',
        __DIR__ . '/templates/contact_sheet',
        __DIR__ . '/tests',
        __DIR__ . '/upgrade',

        // __DIR__ . '/css', # shouldn't contain PHP
        // __DIR__ . '/lib', # shouldn't really contain our code!
    ])
    ->withPreparedSets(deadCode: true)
    // Reach current PHP version (based on composer.json) - https://getrector.com/documentation/php-version-features
    ->withPhpSets()
    ->withRules([
        EscapeLanguageStringsRector::class,
        // ReplaceFunctionCallRector::class,
        AddVoidReturnTypeWhereNoReturnRector::class,
        ReturnTypeFromStrictNativeCallRector::class,
        ReturnTypeFromStrictScalarReturnExprRector::class,
    ])
;
