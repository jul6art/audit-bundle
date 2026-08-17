<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests;

use Jul6Art\AuditBundle\AuditBundle;
use Jul6Art\AuditBundle\DependencyInjection\AuditExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditBundle::class)]
final class AuditBundleTest extends TestCase
{
    public function testItResolvesTheAuditExtensionByConvention(): void
    {
        $extension = new AuditBundle()->getContainerExtension();

        self::assertInstanceOf(AuditExtension::class, $extension);
        self::assertSame('audit', $extension->getAlias());
    }

    public function testItsPathPointsAtTheBundleRoot(): void
    {
        $bundle = new AuditBundle();

        self::assertSame('AuditBundle', $bundle->getName());
        self::assertFileExists($bundle->getPath().'/Resources/config/services.yaml');
    }
}
