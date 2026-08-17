<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\Tests\Fixtures;

use Jul6Art\AuditBundle\AuditBundle;
use Jul6Art\CoreBundle\CoreBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
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
     */
    public function __construct(
        string $environment,
        private readonly array $auditConfig = [],
        private readonly bool $withCore = true,
        private readonly string $uniqueId = 'default',
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

        yield new AuditBundle();
    }

    #[\Override]
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load($this->configure(...));
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

        $container->loadFromExtension('audit', $this->auditConfig);
    }
}
