<?php

namespace findologicDI;

use Shopware\Components\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Shopware-Plugin findologicDI.
 */
class findologicDI extends Plugin
{

    /**
    * @param ContainerBuilder $container
    */
    public function build(ContainerBuilder $container)
    {
        require __DIR__ . '/vendor/autoload.php';
        parent::build($container);
        $container->setParameter('findologic_d_i.plugin_dir', $this->getPath());
    }

}
