<?php

namespace FinSearchUnified\BusinessLogic;

use Exception;
use FinSearchUnified\ShopwareProcess;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;

class ExportFactory
{
    /**
     * @var InstallerService
     */
    private $installerService;

    private $decorators = [
        'fin_search_unified.article_model_factory',
        'fin_search_unified.shopware_process'
    ];

    /**
     * ExportFactory constructor.
     *
     * @param InstallerService $installerService
     */
    public function __construct(InstallerService $installerService)
    {
        $this->installerService = $installerService;
    }

    /**
     * @return Export|ShopwareProcess
     */
    public function create()
    {
        if ($this->exportIsDecorated()) {
            return Shopware()->Container()->get('fin_search_unified.shopware_process');
        } else {
            return Shopware()->Container()->get('fin_search_unified.business_logic.export');
        }
    }

    /**
     * @param string $pluginPath
     *
     * @return string
     */
    public function getServiceDefinitions($pluginPath)
    {
        $file = $pluginPath . '/Resources/services.xml';
        $content = file_get_contents($file);

        return $content ?: '';
    }

    /**
     * @return bool
     */
    public function exportIsDecorated()
    {
        try {
            $extendedPlugin = $this->installerService->getPluginByName('ExtendFinSearchUnified');
            if (!$extendedPlugin->getActive()) {
                return false;
            }

            $path = $this->installerService->getPluginPath($extendedPlugin->getName());
            $serviceDefinitions = $this->getServiceDefinitions($path);

            $pattern = [];

            foreach ($this->decorators as $service) {
                $service = str_replace('.', '\.', $service);
                $pattern[] = sprintf('(decorates="%s")', $service);
            }

            $regex = implode("|", $pattern);

            $isDecorated = (bool)preg_match(sprintf("/%s/", $regex), $serviceDefinitions);
        } catch (Exception $e) {
            $isDecorated = false;
        }

        return $isDecorated;
    }
}
