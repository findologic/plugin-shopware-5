<?php

namespace FinSearchUnified;

use Exception;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Models;

class FinSearchUnified extends Plugin
{
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
