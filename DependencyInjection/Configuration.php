<?php

namespace Jul6Art\AuditBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder('audit');

        $node = $builder->getRootNode();

        $node
            ->children()
                ->scalarNode('enabled')->defaultTrue()->end()
            ->end();

        return $builder;
    }
}
