<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/AuditBundle.php',
        __DIR__.'/Attribute',
        __DIR__.'/DependencyInjection',
        __DIR__.'/Tests',
    ])
    // No argument: the target PHP version is read from the "php" constraint in
    // composer.json, so the rule set follows the bundle instead of drifting.
    ->withPhpSets()
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        earlyReturn: true,
        symfonyCodeQuality: true,
    )
    ->withAttributesSets(symfony: true, phpunit: true)
    ->withComposerBased(symfony: true, phpunit: true)
    ->withSkip([
        // Pure helpers are deliberately static: it documents that they touch no state.
        LocallyCalledStaticMethodToNonStaticRector::class,
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
