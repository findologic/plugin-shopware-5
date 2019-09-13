<?php

use FinSearchUnified\FinSearchUnified;
use FinSearchUnified\Tests\TestCase;
use PHPUnit\Framework\Assert;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\UpdateContext;
use Shopware\Models\Plugin\Plugin;

class FinSearchUnifiedTest extends TestCase
{
    /**
     * @var Plugin
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
     * @param int $invokeCount
     */
    public function testPluginUpdate($currentVersion, $invokeCount)
    {
        $updatedVersion = '8.5.0';
        $context = $this->createMock(UpdateContext::class);

        if ($invokeCount === 1) {
            $context->expects($this->exactly(1))
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
            'Update with old version' => ['version' => '8.0.0', 'invokeCount' => 2],
            'Update with new version' => ['version' => '8.5.0', 'invokeCount' => 1],
        ];
    }
}
