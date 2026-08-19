<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Jul6Art\AuditBundle\DependencyInjection\AuditExtension;
use Jul6Art\AuditBundle\EventListener\AuditableListener;
use Jul6Art\AuditBundle\EventListener\PurgeAuditListener;
use Jul6Art\AuditBundle\Service\AuditLogger;
use Jul6Art\AuditBundle\Tests\Fixtures\Entity\AuditLog as ConcreteAuditLog;
use Jul6Art\CoreBundle\CoreBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

#[CoversClass(AuditExtension::class)]
final class AuditExtensionTest extends TestCase
{
    public function testLoadRegistersTheAuditStack(): void
    {
        $container = $this->containerBuilder();

        new AuditExtension()->load([['log_class' => ConcreteAuditLog::class]], $container);

        $registered = array_keys($container->getDefinitions());

        self::assertContains(AuditLogger::class, $registered);
        self::assertContains(AuditableListener::class, $registered);
        self::assertContains(PurgeAuditListener::class, $registered, 'core-bundle diffuse EntityPurgedEvent : le listener doit suivre.');
    }

    /**
     * Le listener Doctrine doit être marqué sur les trois événements, sinon l'attribut ne
     * déclenche rien — et rien ne le signalerait.
     */
    public function testTheListenerIsTaggedOnTheThreeDoctrineEvents(): void
    {
        $container = $this->containerBuilder();

        new AuditExtension()->load([['log_class' => ConcreteAuditLog::class]], $container);

        $events = array_column(
            $container->getDefinition(AuditableListener::class)->getTag('doctrine.event_listener'),
            'event',
        );

        self::assertSame(['postPersist', 'postUpdate', 'preRemove'], $events);
    }

    public function testDisablingTheBundleRegistersNothing(): void
    {
        $container = $this->containerBuilder();

        new AuditExtension()->load([['enabled' => false]], $container);

        self::assertSame(['service_container'], array_keys($container->getDefinitions()));
    }

    /**
     * Le piège que ce refus évite : une superclasse mappée n'a pas de table, donc un `log_class`
     * absent ou faux laisserait chaque `#[Auditable]` **silencieusement** inopérant. Mieux vaut
     * ne pas démarrer.
     */
    public function testAMissingLogClassIsRefusedLoudly(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/audit\.log_class/');

        new AuditExtension()->load([[]], $this->containerBuilder());
    }

    public function testALogClassThatDoesNotExistIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        new AuditExtension()->load([['log_class' => 'App\\Nope']], $this->containerBuilder());
    }

    public function testALogClassNotExtendingTheSuperclassIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/does not extend/');

        new AuditExtension()->load([['log_class' => \stdClass::class]], $this->containerBuilder());
    }

    public function testPrependExposesTheConfigurationAsParameters(): void
    {
        self::assertTrue($this->prepend([])->getParameter('audit.enabled'));
    }

    public function testPrependExposesTheDisabledFlag(): void
    {
        self::assertFalse($this->prepend(['enabled' => false])->getParameter('audit.enabled'));
    }

    public function testPrependRequiresCoreBundle(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains(CoreBundle::class);

        $this->prepend([], withCore: false);
    }

    private function containerBuilder(bool $withCore = true): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag([
            'kernel.bundles' => $withCore
                ? ['CoreBundle' => CoreBundle::class, 'DoctrineBundle' => DoctrineBundle::class]
                : [],
            'kernel.environment' => 'test',
        ]));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function prepend(array $config, bool $withCore = true): ContainerBuilder
    {
        $container = $this->containerBuilder($withCore);
        $extension = new AuditExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension('audit', $config);

        $extension->prepend($container);

        return $container;
    }
}
