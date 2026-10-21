<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\Symfony\Symfony80\Rector\Class_\RemoveEraseCredentialsRector;

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
        // ⚠️ `eraseCredentials()` left `UserInterface` in Symfony 8, and Rector reasons on the version
        // installed locally: both rules remove it from the test user. It is still declared by the
        // interface on 7.4, where the class would turn abstract and fail to load — the `lowest deps`
        // job. A bundle promising `^7.4 || ^8.0` keeps both. Same skip as admin-bundle; the empty-method
        // rule took this file first with Rector 2.7.
        RemoveEraseCredentialsRector::class,
        RemoveEmptyClassMethodRector::class => [
            __DIR__.'/Tests/Functional/UserWithId.php',
        ],
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
