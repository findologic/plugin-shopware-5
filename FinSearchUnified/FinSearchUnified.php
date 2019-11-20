<?php

namespace FinSearchUnified;

use Exception;
use FinSearchUnified\Subscriber\Frontend;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Models;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class FinSearchUnified extends Plugin
{
    public function build(ContainerBuilder $container)
    {
        // Required for Shopware 5.2.x compatibility.
        if (!$container->hasParameter($this->getContainerPrefix() . '.plugin_dir')) {
            $container->setParameter($this->getContainerPrefix() . '.plugin_dir', $this->getPath());
        }

        $frontendSubscriberDefinition = new Definition(Frontend::class);
        $container->addDefinitions(['fin_search_unified.subscriber.frontend' => $frontendSubscriberDefinition]);
    }

    public function deactivate(DeactivateContext $context)
    {
        $this->deactivateCustomizedPlugin();
        parent::deactivate($context);
    }

    public function uninstall(UninstallContext $context)
    {
        $this->deactivateCustomizedPlugin();
        $context->scheduleClearCache([UninstallContext::CACHE_TAG_THEME]);
        parent::uninstall($context);
    }

    public function install(InstallContext $context)
    {
        $context->scheduleClearCache([InstallContext::CACHE_TAG_THEME]);
        parent::install($context);
    }

    public function update(UpdateContext $context)
    {
        if (version_compare($context->getCurrentVersion(), $context->getUpdateVersion(), '<')) {
            $context->scheduleClearCache([UpdateContext::CACHE_TAG_THEME]);
        }
        parent::update($context);
    }

    /**
     * Try to deactivate any customization plugins of FINDOLOGIC
     */
    private function deactivateCustomizedPlugin()
    {
        /** @var InstallerService $pluginManager */
        $pluginManager = Shopware()->Container()->get('shopware_plugininstaller.plugin_manager');

        try {
            /** @var Models\Plugin\Plugin $plugin */
            $plugin = $pluginManager->getPluginByName('ExtendFinSearchUnified');
            if ($plugin->getActive()) {
                $pluginManager->deactivatePlugin($plugin);
            }
        } catch (Exception $exception) {
            Shopware()->PluginLogger()->info("ExtendFinSearchUnified plugin doesn't exist!");
        }
    }
}
