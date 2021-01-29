<?php

namespace Jul6Art\AuditBundle\DependencyInjection;

use Jul6Art\CoreBundle\CoreBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class AuditExtension.
 */
class AuditExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('services.yaml');

        $this->addAnnotatedClassesToCompile([
            'Jul6Art\\AuditBundle\\',
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function prepend(ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (!isset($bundles['CoreBundle'])) {
            throw new \RuntimeException(sprintf('"%s" bundle is required', CoreBundle::class));
        }

        $configs = $container->resolveEnvPlaceholders($container->getExtensionConfig($this->getAlias()), true);

        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach ($config as $key => $parameter) {
            $container->setParameter(sprintf('%s.%s', $this->getAlias(), $key), $parameter);
        }
    }
}
