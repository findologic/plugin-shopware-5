<?php

namespace FinSearchUnified;

use Shopware\Components\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Shopware-Plugin FinSearchUnified.
 */
class FinSearchUnified extends Plugin
{
    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        require __DIR__.'/vendor/autoload.php';
        parent::build($container);
        $container->setParameter('fin_search_unified.plugin_dir', $this->getPath());
    }
}
