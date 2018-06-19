<?php

namespace FinSearchAPI;

use Shopware\Components\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Shopware-Plugin FinSearchAPI.
 */
class FinSearchAPI extends Plugin
{
    /**
     * @param ContainerBuilder $container
     */
    public function build(ContainerBuilder $container)
    {
        require __DIR__ . '/vendor/autoload.php';
        parent::build($container);
        $container->setParameter('fin_search_api.plugin_dir', $this->getPath());
    }
}
