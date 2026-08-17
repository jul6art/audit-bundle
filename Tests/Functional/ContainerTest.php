<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Boots a real kernel with CoreBundle and AuditBundle registered together.
 */
#[CoversNothing]
final class ContainerTest extends AbstractFunctionalTestCase
{
    public function testTheBundleBootsAlongsideCoreBundle(): void
    {
        self::assertTrue($this->boot()->getParameter('audit.enabled'));
    }

    public function testTheDisabledFlagReachesTheContainer(): void
    {
        self::assertFalse($this->boot('test', ['enabled' => false])->getParameter('audit.enabled'));
    }

    /**
     * CoreBundle's own parameters must still be there: the two extensions both
     * prepend, and one must not shadow the other.
     */
    public function testCoreBundleParametersAreStillExposed(): void
    {
        $container = $this->boot();

        self::assertFalse($container->getParameter('core.email_debug'));
        self::assertTrue($container->getParameter('audit.enabled'));
    }

    public function testBootingWithoutCoreBundleIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('bundle is required');

        $this->boot('test', [], withCore: false);
    }
}
