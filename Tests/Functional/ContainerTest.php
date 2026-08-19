<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Functional;

use Jul6Art\AuditBundle\Tests\Fixtures\Entity\AuditLog;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Boots a real kernel with CoreBundle and AuditBundle registered together.
 */
#[CoversNothing]
final class ContainerTest extends AbstractFunctionalTestCase
{
    /**
     * `log_class` est obligatoire dès que le bundle est actif : le nommer ici est le minimum
     * qu'une application doit écrire.
     */
    private const array CONFIG = ['log_class' => AuditLog::class];

    public function testTheBundleBootsAlongsideCoreBundle(): void
    {
        self::assertTrue($this->boot('test', self::CONFIG, withOrm: true)->getParameter('audit.enabled'));
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
        $container = $this->boot('test', self::CONFIG, withOrm: true);

        self::assertFalse($container->getParameter('core.email_debug'));
        self::assertTrue($container->getParameter('audit.enabled'));
    }

    public function testBootingWithoutCoreBundleIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('bundle is required');

        $this->boot('test', [], withCore: false);
    }

    /**
     * Sans DoctrineBundle, la seule erreur serait un service introuvable au fond du conteneur.
     * On préfère une phrase.
     */
    public function testBootingWithoutDoctrineIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/stored through the ORM/');

        $this->boot('test', self::CONFIG);
    }
}
