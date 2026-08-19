<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Fixtures;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Jul6Art\AuditBundle\AuditBundle;
use Jul6Art\AuditBundle\EventListener\AuditableListener;
use Jul6Art\AuditBundle\EventListener\PurgeAuditListener;
use Jul6Art\AuditBundle\Service\ActorResolver;
use Jul6Art\AuditBundle\Service\AuditLogger;
use Jul6Art\CoreBundle\CoreBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Minimal application kernel used by the functional tests.
 */
final class TestKernel extends Kernel
{
    /**
     * @param array<string, mixed> $auditConfig configuration for the "audit" extension
     * @param bool                 $withCore    AuditExtension requires CoreBundle, so the
     *                                          tests need to be able to leave it out
     * @param bool                 $withOrm     registers DoctrineBundle on an in-memory SQLite
     *                                          database, which is what the audit trail needs to
     *                                          be exercised for real rather than mocked
     */
    public function __construct(
        string $environment,
        private readonly array $auditConfig = [],
        private readonly bool $withCore = true,
        private readonly string $uniqueId = 'default',
        private readonly bool $withOrm = false,
    ) {
        // Debug mode installs Symfony's error handler and never removes it, which
        // PHPUnit rightly reports as leaking global state.
        parent::__construct($environment, false);
    }

    /**
     * @return iterable<BundleInterface>
     */
    #[\Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();

        if ($this->withCore) {
            yield new SecurityBundle();
            yield new CoreBundle();
        }

        if ($this->withOrm) {
            yield new DoctrineBundle();
        }

        yield new AuditBundle();
    }

    #[\Override]
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load($this->configure(...));
    }

    /**
     * Les services de la sécurité et du résolveur d'acteur sont privés ; les tests ont besoin de
     * les atteindre pour observer ce que le bundle produit réellement. Même dispositif que dans
     * core-bundle.
     */
    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            #[\Override]
            public function process(ContainerBuilder $container): void
            {
                $exposed = [
                    'doctrine.orm.default_entity_manager',
                    'event_dispatcher',
                    'request_stack',
                    'security.token_storage',
                    'security.untracked_token_storage',
                    ActorResolver::class,
                    AuditLogger::class,
                    AuditableListener::class,
                    PurgeAuditListener::class,
                ];

                foreach ($container->getDefinitions() as $id => $definition) {
                    if (\in_array($id, $exposed, true)) {
                        $definition->setPublic(true);
                    }
                }

                foreach ($container->getAliases() as $id => $alias) {
                    if (\in_array($id, $exposed, true)) {
                        $alias->setPublic(true);
                    }
                }
            }
        }, PassConfig::TYPE_BEFORE_REMOVING, 100);
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    #[\Override]
    public function getCacheDir(): string
    {
        return $this->buildDir().'/cache';
    }

    #[\Override]
    public function getLogDir(): string
    {
        return $this->buildDir().'/log';
    }

    private function buildDir(): string
    {
        return \sprintf('%s/jul6art-audit-bundle-tests/%s/%s', sys_get_temp_dir(), $this->uniqueId, $this->environment);
    }

    /**
     * The whole `Tests/Fixtures/Entity` directory is mapped, so a new fixture entity is picked
     * up without touching this method.
     */
    private function configureDoctrine(ContainerBuilder $container): void
    {
        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ],
            'orm' => [
                'controller_resolver' => ['auto_mapping' => false],
                'mappings' => [
                    'AuditBundleTests' => [
                        'type' => 'attribute',
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Jul6Art\\AuditBundle\\Tests\\Fixtures\\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);
    }

    private function configure(ContainerBuilder $container): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'audit-bundle-tests',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'translator' => ['default_path' => '%kernel.project_dir%/translations'],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        if ($this->withCore) {
            $container->loadFromExtension('security', [
                'providers' => ['in_memory' => ['memory' => null]],
                'firewalls' => ['main' => ['security' => false]],
            ]);
        }

        if ($this->withOrm) {
            $this->configureDoctrine($container);
        }

        $container->loadFromExtension('audit', $this->auditConfig);
    }
}
