<?php

namespace FinSearchUnified\Helper;

use Exception;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Models\Plugin\Plugin;

class HeaderHandler
{
    const
        SHOPWARE_HEADER = 'x-findologic-platform',
        PLUGIN_HEADER = 'x-findologic-plugin',
        EXTENSION_HEADER = 'x-findologic-extension-plugin',
        CONTENT_TYPE_HEADER = 'content-type',
        SHOPWARE_VERSION = 'Shopware/%s',
        PLUGIN_VERSION = 'Plugin-Shopware-5/%s',
        EXTENSION_PLUGIN_VERSION = 'Plugin-Shopware-5-Extension/%s';

    /**
     * @var InstallerService $pluginManager
     */
    private $pluginManager;

    /**
     * @var string
     */
    private $shopwareVersion;

    /**
     * @var string
     */
    private $pluginVersion;

    /**
     * @var string
     */
    private $extensionPluginVersion;

    /**
     * @var string
     */
    private $contentType;

    public function __construct()
    {
        $this->pluginManager = Shopware()->Container()->get('shopware_plugininstaller.plugin_manager');

        $this->shopwareVersion = $this->fetchShopwareVersion();
        $this->pluginVersion = $this->fetchPluginVersion();
        $this->extensionPluginVersion = $this->fetchExtensionPluginVersion();
    }

    /**
     * @return string
     */
    private function fetchShopwareVersion()
    {
        $shopwareVersion = Shopware()->Config()->offsetGet('version');

        return sprintf(self::SHOPWARE_VERSION, $shopwareVersion);
    }

    /**
     * @return string
     */
    private function fetchPluginVersion()
    {
        try {
            /** @var Plugin $plugin */
            $plugin = $this->pluginManager->getPluginByName('FinSearchUnified');
            if ($plugin !== null && $plugin->getActive()) {
                return sprintf(self::PLUGIN_VERSION, $plugin->getVersion());
            }
        } catch (Exception $ignored) {
        }

        return 'none';
    }

    /**
     * @return string
     */
    private function fetchExtensionPluginVersion()
    {
        try {
            /** @var Plugin $plugin */
            $plugin = $this->pluginManager->getPluginByName('ExtendFinSearchUnified');
            if ($plugin !== null && $plugin->getActive()) {
                return sprintf(self::EXTENSION_PLUGIN_VERSION, $plugin->getVersion());
            }
        } catch (Exception $ignored) {
        }

        return 'none';
    }

    /**
     * @return string[]
     */
    public function getHeaders()
    {
        $headers = [];
        $headers[self::CONTENT_TYPE_HEADER] = $this->contentType;
        $headers[self::SHOPWARE_HEADER] = $this->shopwareVersion;
        $headers[self::PLUGIN_HEADER] = $this->pluginVersion;
        $headers[self::EXTENSION_HEADER] = $this->extensionPluginVersion;

        return $headers;
    }

    /**
     * @param $key
     *
     * @return string
     */
    public function getHeader($key)
    {
        $headers = $this->getHeaders();
        if (array_key_exists($key, $headers)) {
            return $headers[$key];
        }

        return null;
    }

    /**
     * @param string $contentType
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
    }
}
