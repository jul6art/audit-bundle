<?php

declare(strict_types=1);

namespace Jul6Art\AuditBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration.
 */
class Configuration implements ConfigurationInterface
{
    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('audit');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('enabled')
                    ->info('Registers the Doctrine listener that executes #[Auditable]. Also exposed as the "audit.enabled" container parameter.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('log_class')
                    ->info('Concrete entity extending Jul6Art\AuditBundle\Entity\AuditLog. Required: the bundle ships a mapped superclass and cannot guess which entity holds your trail.')
                    ->defaultNull()
                ->end()
                ->arrayNode('ignored_fields')
                    ->info('Fields kept out of every update diff, unless an entity names its own list in #[Auditable(ignoredFields: …)] — which replaces this one rather than adding to it.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['updatedAt', 'updatedBy'])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
