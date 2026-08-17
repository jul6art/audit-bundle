<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\DependencyInjection;

use Jul6Art\AuditBundle\DependencyInjection\AuditExtension;
use Jul6Art\CoreBundle\CoreBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

#[CoversClass(AuditExtension::class)]
final class AuditExtensionTest extends TestCase
{
    public function testLoadReadsTheServicesFileWithoutFailing(): void
    {
        $container = $this->containerBuilder();

        new AuditExtension()->load([], $container);

        // The bundle ships no service yet; what matters is that the YAML loader runs,
        // which is why symfony/yaml is now a declared dependency.
        self::assertSame(['service_container'], array_keys($container->getDefinitions()));
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
            'kernel.bundles' => $withCore ? ['CoreBundle' => CoreBundle::class] : [],
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
