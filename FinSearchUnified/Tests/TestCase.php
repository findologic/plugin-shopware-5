<?php

namespace FinSearchUnified\Tests;

use Exception;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;

/**
 * Ensures a given plugin is installed and sets configuration.
 * After the test is run the initial state is restored
 * protected static $ensureLoadedPlugins = array(
 *     'AdvancedMenu' => array(
 *         'show'    => 1,
 *         'levels'  => 3,
 *         'caching' => 0
 *     )
 * );
 *
 * @runInSeparateProcess
 * @category  Findologic
 * @copyright Copyright (c) FINDOLOGIC GmbH (https://www.findologic.com)
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    use OldPhpUnitVersionAware;

    /**
     * @var array
     */
    protected static $ensureLoadedPlugins = [];

    /**
     * @var InstallerService
     */
    private static $pluginManager;

    /**
     * @var array
     */
    private static $pluginStates = [];

    /**
     * @throws Exception
     */
    public static function setUpBeforeClass(): void
    {
        self::$pluginManager = Shopware()->Container()->get('shopware_plugininstaller.plugin_manager');
        $loadedPlugins = static::$ensureLoadedPlugins;

        foreach ($loadedPlugins as $key => $value) {
            if (is_array($value)) {
                $pluginName = $key;
                $config = $value;
            } else {
                $pluginName = $value;
                $config = [];
            }

            self::ensurePluginAvailable($pluginName, $config);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Shopware()->Container()->reset('config');
    }

    /**
     * @throws Exception
     */
    public static function tearDownAfterClass(): void
    {
        self::restorePluginStates();
        self::$pluginManager = null;
        Shopware()->Models()->clear();
    }

    /**
     * Ensures given $pluginName is installed and activated.
     *
     * @param string $pluginName
     * @param array $config
     *
     * @throws Exception
     */
    private static function ensurePluginAvailable($pluginName, array $config = [])
    {
        $plugin = self::$pluginManager->getPluginByName($pluginName);

        self::$pluginStates[$pluginName] = [
            'isInstalled' => (bool)$plugin->getInstalled(),
            'isActive' => (bool)$plugin->getActive(),
        ];

        self::$pluginManager->installPlugin($plugin);
        self::$pluginManager->activatePlugin($plugin);

        foreach ($config as $element => $value) {
            self::$pluginManager->saveConfigElement($plugin, $element, $value);
        }
    }

    /**
     * Restores initial plugin state
     *
     * @throws Exception
     */
    private static function restorePluginStates()
    {
        Shopware()->Models()->clear();
        foreach (self::$pluginStates as $pluginName => $status) {
            self::restorePluginState($pluginName, $status);
        }
    }

    /**
     * @param string $pluginName
     * @param array $status
     *
     * @throws Exception
     */
    private static function restorePluginState($pluginName, array $status)
    {
        $plugin = self::$pluginManager->getPluginByName($pluginName);

        if ($plugin->getInstalled() && !$status['isInstalled']) {
            self::$pluginManager->uninstallPlugin($plugin);

            return;
        }

        if ($plugin->getActive() && !$status['isActive']) {
            self::$pluginManager->deactivatePlugin($plugin);
        }
    }
}
