<?php

namespace FinSearchUnified\Tests\BusinessLogic;

use Exception;
use FinSearchUnified\BusinessLogic\Export;
use FinSearchUnified\BusinessLogic\ExportFactory;
use FinSearchUnified\ShopwareProcess;
use FinSearchUnified\Tests\TestBase;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Models\Plugin\Plugin;
use SimpleXMLElement;

class ExportFactoryTest extends TestBase
{
    /**
     * @dataProvider extendedPluginAvailabilityProvider
     *
     * @param bool $isAvailable
     */
    public function testExtendedPluginAvailability($isAvailable)
    {
        $mockInstaller = $this->createMock(InstallerService::class);

        if ($isAvailable) {
            $extendedPluginMock = $this->createMock(Plugin::class);
            $extendedPluginMock->expects($this->once())->method('getActive')->willReturn(false);
            $mockInstaller->expects($this->once())->method('getPluginByName')->willReturn($extendedPluginMock);
        } else {
            $mockInstaller->expects($this->once())->method('getPluginByName')->willThrowException(new Exception());
        }

        $mockInstaller->expects($this->never())->method('getPluginPath');

        $mockExportFactory = $this->getMockBuilder(ExportFactory::class)
            ->setConstructorArgs([$mockInstaller])
            ->setMethodsExcept(['create', 'exportIsDecorated'])
            ->getMock();

        $mockExportFactory->expects($this->never())->method('getServiceDefinitions');

        /** @var ExportFactory $mockExportFactory */
        $result = $mockExportFactory->create();

        $this->assertInstanceOf(Export::class, $result);
    }

    /**
     * @dataProvider serviceDefinitionProvider
     *
     * @param string $expectedService
     * @param array $servicesXml
     */
    public function testServiceDefinitions($expectedService, array $servicesXml)
    {
        $mockInstaller = $this->createMock(InstallerService::class);

        $extendedPluginMock = $this->createMock(Plugin::class);
        $extendedPluginMock->expects($this->once())->method('getActive')->willReturn(true);
        $mockInstaller->expects($this->once())->method('getPluginByName')->willReturn($extendedPluginMock);

        $path = Shopware()->DocPath('custom_plugins_ExtendFinSearchUnified');
        $mockInstaller->expects($this->once())->method('getPluginPath')->willReturn($path);

        $mockExportFactory = $this->getMockBuilder(ExportFactory::class)
            ->setConstructorArgs([$mockInstaller])
            ->setMethodsExcept(['create', 'exportIsDecorated'])
            ->getMock();

        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><container></container>');
        if (!empty($servicesXml)) {
            $services = $xml->addChild('services');

            foreach ($servicesXml as $serviceXml) {
                $service = $services->addChild('service');
                foreach ($serviceXml as $key => $attribute) {
                    $service->addAttribute($key, $attribute);
                }
            }
        }

        $mockExportFactory->expects($this->once())
            ->method('getServiceDefinitions')
            ->with($path)
            ->willReturn($xml->asXML());

        /** @var ExportFactory $mockExportFactory */
        $result = $mockExportFactory->create();

        $this->assertInstanceOf($expectedService, $result);
    }

    public function extendedPluginAvailabilityProvider()
    {
        return [
            'Extended plugin is not available' => [false],
            'Extended plugin is available but inactive' => [true],
        ];
    }

    public function serviceDefinitionProvider()
    {
        return [
            'Empty file' => [Export::class, []],
            'Without service definitions' => [
                Export::class,
                [
                    []
                ]
            ],
            'Simple service without decorator' => [
                Export::class,
                [
                    ['id' => 'fin_search_unified.random_service']
                ]
            ],
            'Any other decorated service' => [
                Export::class,
                [
                    ['decorates' => 'fin_search_unified.random_service']
                ]
            ],
            'Decorated article model factory' => [
                ShopwareProcess::class,
                [
                    ['decorates' => 'fin_search_unified.article_model_factory']
                ]
            ],
            'Decorated shopware process service' => [
                ShopwareProcess::class,
                [
                    ['decorates' => 'fin_search_unified.shopware_process']
                ]
            ],
            'Decorated both services' => [
                ShopwareProcess::class,
                [
                    [
                        'decorates' => 'fin_search_unified.article_model_factory'
                    ],
                    [
                        'decorates' => 'fin_search_unified.shopware_process'
                    ]
                ]
            ],
        ];
    }
}
