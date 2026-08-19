<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle;

use Jul6Art\AuditBundle\DependencyInjection\Compiler\ActorResolverPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class AuditBundle.
 */
class AuditBundle extends Bundle
{
    #[\Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new ActorResolverPass());
    }
}
