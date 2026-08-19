<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\DependencyInjection;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\Events;
use Jul6Art\AuditBundle\Entity\AuditLog;
use Jul6Art\AuditBundle\EventListener\AuditableListener;
use Jul6Art\AuditBundle\EventListener\PurgeAuditListener;
use Jul6Art\AuditBundle\Service\ActorResolver;
use Jul6Art\AuditBundle\Service\AuditLogger;
use Jul6Art\CoreBundle\CoreBundle;
use Jul6Art\CoreBundle\Event\EntityPurgedEvent;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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

        $config = $this->processConfiguration(new Configuration(), $configs);

        if (false === ($config['enabled'] ?? true)) {
            return;
        }

        $logClass = self::assertLogClass($config['log_class'] ?? null);

        $this->registerActorResolver($container);
        $this->registerLogger($container, $logClass);
        $this->registerAuditableListener($container, self::ignoredFields($config));
        $this->registerPurgeListener($container);
    }

    /**
     * The one piece of configuration the bundle cannot do without, so the failure has to be
     * loud: a mapped superclass has no table, and a misconfigured `log_class` would leave every
     * `#[Auditable]` silently inoperative — the exact trap the README warns about.
     *
     * @return class-string<AuditLog>
     */
    private static function assertLogClass(mixed $logClass): string
    {
        if (!\is_string($logClass) || '' === $logClass) {
            throw new InvalidConfigurationException(\sprintf('Set "audit.log_class" to your concrete entity extending "%s". The bundle ships a mapped superclass and cannot guess which entity holds your audit trail.', AuditLog::class));
        }

        if (!class_exists($logClass)) {
            throw new InvalidConfigurationException(\sprintf('"audit.log_class" points at "%s", which does not exist.', $logClass));
        }

        if (!is_subclass_of($logClass, AuditLog::class)) {
            throw new InvalidConfigurationException(\sprintf('"audit.log_class" is "%s", which does not extend "%s".', $logClass, AuditLog::class));
        }

        return $logClass;
    }

    /**
     * @param array<mixed> $config
     *
     * @return list<string>
     */
    private static function ignoredFields(array $config): array
    {
        $fields = $config['ignored_fields'] ?? [];

        if (!\is_array($fields)) {
            return [];
        }

        return array_values(array_filter($fields, \is_string(...)));
    }

    /**
     * Resolving the actor needs a token storage, which only `symfony/security-bundle` provides.
     * Without it the resolver is absent and every row is recorded with a null actor — correct
     * for a CLI command, and the honest answer for an application that has no security layer.
     */
    private function registerActorResolver(ContainerBuilder $container): void
    {
        if (!interface_exists(TokenStorageInterface::class)) {
            return;
        }

        $container->register(ActorResolver::class, ActorResolver::class)
            ->addMethodCall('setTokenStorage', [new Reference('security.token_storage')]);
    }

    /**
     * @param class-string<AuditLog> $logClass
     */
    private function registerLogger(ContainerBuilder $container, string $logClass): void
    {
        $container->register(AuditLogger::class, AuditLogger::class)
            ->setArguments([
                new Reference('doctrine.orm.entity_manager'),
                new Reference('request_stack'),
                $logClass,
                self::optionalActorResolver($container),
            ])
            ->setPublic(true);
    }

    /**
     * @param list<string> $ignoredFields
     */
    private function registerAuditableListener(ContainerBuilder $container, array $ignoredFields): void
    {
        $definition = $container->register(AuditableListener::class, AuditableListener::class)
            ->setArguments([
                new Reference(AuditLogger::class),
                $ignoredFields,
                self::optionalActorResolver($container),
            ])
            ->setPublic(true);

        foreach ([Events::postPersist, Events::postUpdate, Events::preRemove] as $event) {
            $definition->addTag('doctrine.event_listener', ['event' => $event]);
        }
    }

    /**
     * Only useful when `core-bundle` is recent enough to dispatch the event, i.e. when the purge
     * command exists at all. The event name is a string here on purpose: referencing the class
     * constant would make this extension unloadable against an older core-bundle.
     */
    private function registerPurgeListener(ContainerBuilder $container): void
    {
        if (!class_exists(EntityPurgedEvent::class)) {
            return;
        }

        $container->register(PurgeAuditListener::class, PurgeAuditListener::class)
            ->setArguments([new Reference(AuditLogger::class)])
            ->addTag('kernel.event_listener', [
                'event' => EntityPurgedEvent::NAME,
                'method' => 'onEntityPurged',
            ]);
    }

    private static function optionalActorResolver(ContainerBuilder $container): ?Reference
    {
        return $container->hasDefinition(ActorResolver::class) ? new Reference(ActorResolver::class) : null;
    }

    /**
     * @throws \RuntimeException if CoreBundle or DoctrineBundle is not registered
     */
    #[\Override]
    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (!\is_array($bundles) || !isset($bundles['CoreBundle'])) {
            throw new \RuntimeException(\sprintf('"%s" bundle is required', CoreBundle::class));
        }

        $config = $this->resolveConfig($container);

        // Le trail vit en base : sans DoctrineBundle, la seule erreur serait un service
        // « doctrine.orm.entity_manager » introuvable, à des lieues de la cause. Une piste
        // d'audit sans base n'est pas un mode dégradé, c'est une configuration fausse — mais un
        // projet qui coupe l'audit (`enabled: false`) n'a aucune raison d'installer l'ORM.
        if ($config['enabled'] && !isset($bundles['DoctrineBundle'])) {
            throw new \RuntimeException(\sprintf('"%s" bundle is required: the audit trail is stored through the ORM.', DoctrineBundle::class));
        }

        foreach ($config as $key => $parameter) {
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
