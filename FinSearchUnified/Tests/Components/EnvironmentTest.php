<?php

namespace FinSearchUnified\Tests\Components;

use Enlight_Components_Session_Namespace as Session;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use FinSearchUnified\Components\ConfigLoader;
use FinSearchUnified\Tests\TestCase;

class EnvironmentTest extends TestCase
{
    /**
     * @return array
     */
    public function stagingShopDataProvider()
    {
        return [
            'Shop is staging and FINDOLOGIC query parameter was not provided' => [
                'param' => null,
                'stagingFlag' => false,
                'configStaging' => true,
                'expected' => true
            ],
            'Shop is staging and FINDOLOGIC query parameter is "off"' => [
                'param' => 'off',
                'stagingFlag' => false,
                'configStaging' => true,
                'expected' => true
            ],
            'Shop is staging and FINDOLOGIC query parameter is "disabled"' => [
                'param' => 'disabled',
                'stagingFlag' => false,
                'configStaging' => true,
                'expected' => true
            ]
        ];
    }

    /**
     * @return array
     */
    public function noStagingShopDataProvider()
    {
        return [
            'Shop is live and FINDOLOGIC query parameter was not provided' => [
                'param' => null,
                'stagingFlag' => true,
                'configStaging' => false,
                'expected' => false
            ],
            'Shop is live and FINDOLOGIC query parameter is "off"' => [
                'param' => 'off',
                'stagingFlag' => true,
                'configStaging' => false,
                'expected' => false
            ],
            'Shop is live and FINDOLOGIC query parameter is "disabled"' => [
                'param' => 'disabled',
                'stagingFlag' => true,
                'configStaging' => false,
                'expected' => false
            ],
            'Shop is staging and FINDOLOGIC query parameter is "on"' => [
                'param' => 'on',
                'stagingFlag' => true,
                'configStaging' => false,
                'expected' => false
            ]
        ];
    }

    /**
     * @dataProvider stagingShopDataProvider
     * @dataProvider noStagingShopDataProvider
     *
     * @param string|null $param
     * @param bool $stagingFlag
     * @param bool $configStaging
     * @param bool $expected
     */
    public function testisStaging($param, $stagingFlag, $configStaging, $expected)
    {
        $request = new RequestHttp();
        $request->setModuleName('frontend');
        $request->setParam('findologic', $param);

        // Create Mock object for Shopware Session
        $session = $this->getMockBuilder(Session::class)
            ->setMethods(['offsetGet'])
            ->getMock();
        $session->expects($this->once())
            ->method('offsetGet')
            ->with('stagingFlag')
            ->willReturn($stagingFlag);

        $configloader = $this->createMock(ConfigLoader::class);
        $configloader->expects($this->once())
            ->method('isStagingShop')
            ->willReturn($configStaging);

        Shopware()->Container()->set('fin_search_unified.config_loader', $configloader);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('session', $session);
        $environment = Shopware()->Container()->get('fin_search_unified.environment');
        $isStaging = $environment->isStaging($request);
        $this->assertSame($expected, $isStaging);
    }
}
