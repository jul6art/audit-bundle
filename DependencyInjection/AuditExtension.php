<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\DependencyInjection;

use Jul6Art\CoreBundle\CoreBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class AuditExtension.
 *
 * @phpstan-type AuditConfig array{enabled: bool}
 */
class AuditExtension extends Extension implements PrependExtensionInterface
{
    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('services.yaml');
    }

    /**
     * @throws \RuntimeException if CoreBundle is not registered
     */
    #[\Override]
    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (!\is_array($bundles) || !isset($bundles['CoreBundle'])) {
            throw new \RuntimeException(\sprintf('"%s" bundle is required', CoreBundle::class));
        }

        foreach ($this->resolveConfig($container) as $key => $parameter) {
            $container->setParameter(\sprintf('%s.%s', $this->getAlias(), $key), $parameter);
        }
    }

    /**
     * Normalises the processed configuration into a shape the rest of the class can
     * rely on: Symfony's config layer only guarantees an untyped array.
     *
     * @return AuditConfig
     */
    private function resolveConfig(ContainerBuilder $container): array
    {
        $configs = $container->resolveEnvPlaceholders($container->getExtensionConfig($this->getAlias()), true);

        $config = $this->processConfiguration(new Configuration(), \is_array($configs) ? $configs : []);

        return [
            'enabled' => false !== ($config['enabled'] ?? true),
        ];
    }
}
