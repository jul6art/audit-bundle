<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\DependencyInjection\Compiler;

use Jul6Art\AuditBundle\EventListener\AuditableListener;
use Jul6Art\AuditBundle\Service\ActorResolver;
use Jul6Art\AuditBundle\Service\AuditLogger;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Drops the actor resolver when no token storage is wired.
 *
 * The extension already checks that the security component is *installed*, but a project can
 * hold the package without a configured firewall, in which case `security.token_storage` does
 * not exist. Extensions cannot see that — they run before the other bundles have had their say
 * — so the check belongs in a compiler pass.
 *
 * Losing the resolver is not a failure: the trail is still written, with a null actor. Refusing
 * to boot would be the wrong trade — an audit row without an actor is worth more than no audit
 * at all.
 */
final class ActorResolverPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ActorResolver::class)) {
            return;
        }

        if ($container->has('security.token_storage')) {
            return;
        }

        $container->removeDefinition(ActorResolver::class);

        foreach ([AuditLogger::class => 3, AuditableListener::class => 2] as $service => $argumentIndex) {
            if ($container->hasDefinition($service)) {
                $container->getDefinition($service)->setArgument($argumentIndex, null);
            }
        }
    }
}
