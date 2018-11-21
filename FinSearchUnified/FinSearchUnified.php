<?php

namespace FinSearchUnified;

use FinSearchUnified\Helper\UrlBuilder;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Shopware\Models;
use FinSearchUnified\Helper\StaticHelper;

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
        parent::build($container);
        $container->setParameter('fin_search_unified.plugin_dir', $this->getPath());
    }

    public function deactivate(DeactivateContext $context)
    {
        $this->deactivateCustomizedPlugin();
        parent::deactivate($context);
    }

    public function uninstall(UninstallContext $context)
    {
        $this->deactivateCustomizedPlugin();
        parent::uninstall($context);
    }

    public function update(UpdateContext $context)
    {
        $this->storeIntegrationType();

        parent::update($context);
    }

    /**
     * Try to deactivate any customization plugins of FINDOLOGIC
     */
    private function deactivateCustomizedPlugin()
    {
        /** @var InstallerService $pluginManager */
        $pluginManager = $this->container->get('shopware_plugininstaller.plugin_manager');

        try {
            /** @var Models\Plugin\Plugin $plugin */
            $plugin = $pluginManager->getPluginByName('ExtendFinSearchUnified');
            if ($plugin->getActive()) {
                $pluginManager->deactivatePlugin($plugin);
            }
        } catch (\Exception $exception) {
            Shopware()->PluginLogger()->info("ExtendFinSearchUnified plugin doesn't exist!");
        }
    }

    private function storeIntegrationType()
    {
        $urlBuilder = new UrlBuilder();

        if ($urlBuilder->getConfigStatus()) {
            $integrationType = Constants::INTEGRATION_TYPE_DI;
        } else {
            $integrationType = Constants::INTEGRATION_TYPE_API;
        }

        StaticHelper::storeIntegrationType($integrationType);
    }
}
