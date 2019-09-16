<?php

use FinSearchUnified\FinSearchUnified;
use FinSearchUnified\Tests\TestCase;
use PHPUnit\Framework\Assert;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\Logger;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Models\Plugin\Plugin as PluginModel;

class FinSearchUnifiedTest extends TestCase
{
    /**
     * @var PluginModel
     */
    private $plugin;

    /**
     * @throws Exception
     */
    protected function setUp()
    {
        $this->plugin = new FinSearchUnified(true, 'FinSearchUnified');
        parent::setUp();
    }

    public function testPluginInstall()
    {
        $context = $this->createMock(InstallContext::class);
        $context->expects($this->once())->method('scheduleClearCache')->with([InstallContext::CACHE_TAG_THEME]);
        $this->plugin->install($context);
    }

    public function testPluginUninstall()
    {
        $context = $this->createMock(UninstallContext::class);

        $context->expects($this->at(0))
            ->method('scheduleClearCache')
            ->with([UninstallContext::CACHE_TAG_THEME]);
        $context->expects($this->at(1))
            ->method('scheduleClearCache')
            ->with(UninstallContext::CACHE_LIST_DEFAULT);

        $this->plugin->uninstall($context);
    }

    /**
     * @dataProvider versionProvider
     *
     * @param string $currentVersion
     * @param bool $clearThemeCache
     */
    public function testPluginUpdate($currentVersion, $clearThemeCache)
    {
        $updatedVersion = '8.5.0';
        $context = $this->createMock(UpdateContext::class);

        if (!$clearThemeCache) {
            $context->expects($this->once())
                ->method('scheduleClearCache')
                ->with(UpdateContext::CACHE_LIST_DEFAULT);
        } else {
            $context->expects($this->exactly(2))
                ->method('scheduleClearCache')
                ->will($this->onConsecutiveCalls(
                    $this->returnCallback(function ($cache) {
                        Assert::assertSame([UpdateContext::CACHE_TAG_THEME], $cache);
                    }),
                    $this->returnCallback(function ($cache) {
                        Assert::assertSame(UpdateContext::CACHE_LIST_DEFAULT, $cache);
                    })
                ));
        }

        $context->expects($this->once())->method('getUpdateVersion')->willReturn($updatedVersion);
        $context->expects($this->once())->method('getCurrentVersion')->willReturn($currentVersion);

        $this->plugin->update($context);
    }

    public function versionProvider()
    {
        return [
            'Update with old version' => ['version' => '8.0.0', 'clearThemeCache' => true],
            'Update with new version' => ['version' => '8.5.0', 'clearThemeCache' => false],
        ];
    }

    /**
     * @dataProvider customPluginProvider
     *
     * @param bool $isActive
     */
    public function testPluginDeactivateWithCustomPlugin($isActive)
    {
        // We use a custom class created below to replicate the custom plugin functionality
        $plugin = new PluginModel();
        $plugin->setName('ExtendFinSearchUnified');
        $plugin->setActive($isActive);

        $mockManager = $this->createMock(InstallerService::class);

        // We mock the plugin manager to return our custom plugin instance
        $mockManager->expects($this->once())
            ->method('getPluginByName')
            ->with('ExtendFinSearchUnified')
            ->willReturn($plugin);

        // Extend plugin should not be deactivated if it is not active
        $invokeCount = $isActive ? $this->once() : $this->never();
        $mockManager->expects($invokeCount)
            ->method('deactivatePlugin')
            ->with($plugin);

        Shopware()->Container()->set('shopware_plugininstaller.plugin_manager', $mockManager);

        $context = $this->createMock(DeactivateContext::class);
        $context->expects($this->once())->method('scheduleClearCache')->with(DeactivateContext::CACHE_LIST_DEFAULT);
        $this->plugin->deactivate($context);
    }

    public function testPluginDeactivationWithCustomPluginException()
    {
        // We check for the logger method being invoked instead of checking for exception as it is being handled
        // by the method internally
        $mockLogger = $this->createMock(Logger::class);
        $mockLogger->expects($this->once())->method('info');
        Shopware()->Container()->set('pluginlogger', $mockLogger);

        $context = $this->createMock(DeactivateContext::class);
        $context->expects($this->once())->method('scheduleClearCache')->with(DeactivateContext::CACHE_LIST_DEFAULT);
        $this->plugin->deactivate($context);
    }

    protected function tearDown()
    {
        Shopware()->Container()->reset('shopware_plugininstaller.plugin_manager');
        Shopware()->Container()->load('shopware_plugininstaller.plugin_manager');
        Shopware()->Container()->reset('pluginlogger');
        Shopware()->Container()->load('pluginlogger');
        parent::tearDown();
    }

    public function customPluginProvider()
    {
        return [
            'Custom plugin exists and active' => [true],
            'Custom plugin exists but not active' => [false]
        ];
    }
}
