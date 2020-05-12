<?php

namespace FinSearchUnified;

use Exception;
use FinSearchUnified\Bundle\ControllerBundle\DependencyInjection\Compiler\RegisterControllerCompilerPass;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Components\Plugin\FormSynchronizer;
use Shopware\Components\Plugin\XmlConfigDefinitionReader;
use Shopware\Components\Plugin\XmlReader\XmlConfigReader;
use Shopware\Models;
use SimpleXMLElement;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class FinSearchUnified extends Plugin
{
    const CONFIG_TEMPLATE = '/Resources/config/config_template.xml';
    const CONFIG_FILE = '/Resources/config.xml';

    public function build(ContainerBuilder $container)
    {
        // Required for Shopware 5.2.x compatibility.
        if (!$container->hasParameter($this->getContainerPrefix() . '.plugin_dir')) {
            $container->setParameter($this->getContainerPrefix() . '.plugin_dir', $this->getPath());
        }
        if (!$container->hasParameter($this->getContainerPrefix() . '.plugin_name')) {
            $container->setParameter($this->getContainerPrefix() . '.plugin_name', $this->getName());
        }
        if (!$container->has('shopware_search_es.product_number_search_factory')) {
            $loader = new XmlFileLoader(
                $container,
                new FileLocator()
            );

            $loader->load($this->getPath() . '/Resources/shopware/searchBundleES.xml');
        }

        $container->addCompilerPass(new RegisterControllerCompilerPass([$this]));
        parent::build($container);
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

        $this->initializeConfiguration();
    }

    public function update(UpdateContext $context)
    {
        if (version_compare($context->getCurrentVersion(), $context->getUpdateVersion(), '<')) {
            // Initialize configuration when user updates the plugin, as we have removed the default
            // `config.xml` from the plugin structure due to dynamic creation of the config file
            $this->initializeConfiguration();
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

    /**
     * @return string
     */
    public function getContainerPrefix()
    {
        return $this->camelCaseToUnderscore($this->getName());
    }

    /**
     * @param string $string
     *
     * @return string
     */
    private function camelCaseToUnderscore($string)
    {
        return strtolower(ltrim(preg_replace('/[A-Z]/', '_$0', $string), '_'));
    }

    /**
     * @return array
     */
    private function loadConfig()
    {
        $template = $this->getPath() . self::CONFIG_TEMPLATE;
        $xml = new SimpleXMLElement($template, 0, true);

        $shopwareVersion = Shopware()->Config()->get('version');

        // For Shopware >= 5.2.17 we manually generate the `button` element in configuration as it is not available
        // for older versions.
        if (version_compare($shopwareVersion, '5.2.17', '>=')) {
            $buttonEl = $xml->elements->addChild('element');
            $buttonEl->addAttribute('type', 'button');
            $buttonEl->addAttribute('scope', 'shop');
            $buttonEl->addChild('name', 'StagingTestButton');
            $buttonEl->addChild('label', 'Findologic Test');
            $options = $buttonEl->addChild('options');
            $options->addChild('href', 'findologicStaging');
        }

        // From Shopware 5.6 `XmlConfigDefinitionReader` is removed and `XmlConfigReader` is used instead
        if (class_exists(XmlConfigReader::class)) {
            $xmlConfigReader = new XmlConfigReader();
        } else {
            $xmlConfigReader = new XmlConfigDefinitionReader();
        }

        $file = $this->getPath() . self::CONFIG_FILE;
        file_put_contents($file, $xml->asXML());

        return $xmlConfigReader->read($file);
    }

    private function initializeConfiguration()
    {
        // Manually load the config xml due to Shopware 5.2.0 compatibility
        $config = $this->loadConfig();

        /** @var InstallerService $pluginManager */
        $pluginManager = Shopware()->Container()->get('shopware_plugininstaller.plugin_manager');
        $plugin = $pluginManager->getPluginByName('FinSearchUnified');
        $formSynchronizer = new FormSynchronizer(Shopware()->Models());
        $formSynchronizer->synchronize($plugin, $config);
    }
}
