<?php

namespace FinSearchUnified\Tests\Components;

use Enlight_Components_Session_Namespace as Session;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use FinSearchUnified\Components\ConfigLoader;
use FinSearchUnified\Tests\TestCase;

class EnvironmentTest extends TestCase
{

    /**
     * Data provider for checking isStaging behavior
     *
     * @return array
     */

    public function StagingShopDataprovider()
    {
        return [
            'Shop is staging and no query parameter was submitted' => [
                'Staging' => true,
                'Param' => [],
            ],
            'Shop is staging and query parameter is findologic=off' => [
                    'Staging' => true,
                    'Param' => ['findologic' => 'off'],
            ],
            'Shop is staging and query parameter is findologic=disabled' => [
                'Staging' => true,
                'Param' => ['findologic' => 'disabled'],
            ]
        ];
    }

    public function NoStagingShopDataprovider()
    {
        return [
            'Shop is no staging and no query parameter was submitted' => [
                'Staging' => false,
                'Param' => [],
            ],
            'Shop is no staging and query parameter is findologic=off' => [
                'Staging' => false,
                'Param' => ['findologic' => 'off'],
            ],
            'Shop is no staging and query parameter is findologic=disabled' => [
                'Staging' => false,
                'Param' => ['findologic' => 'disabled'],
            ],
            'Shop is staging and query parameter is findologic=on' => [
                'Staging' => true,
                'Param' => ['findologic' => 'on'],
            ]
        ];
    }
    /**
     * @dataProvider NoStagingShopDataprovider
     * @param bool $staging
     * @param array $param
     */
    public function testisNotStaging(
        $staging,
        array $param
    ) {
        $request = new RequestHttp();
        $request->setModuleName('frontend');
        $request->setParams($param);
        // Create Mock object for Shopware Session
        $session = $this->getMockBuilder(Session::class)
            ->setMethods(['offsetGet'])
            ->getMock();
        $session->expects($this->once())
            ->method('offsetGet')
            ->with('stagingFlag')
            ->willReturn(true);

        $configloader = $this->createMock(ConfigLoader::class);
        $configloader->expects($this->once())
            ->method('isStagingShop')
            -> willReturn(false);

        Shopware()->Container()->set('fin_search_unified.config_loader', $configloader);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('session', $session);
        $isNotStagingMode = Shopware()->Container()->get('fin_search_unified.staging_manager');
        $isNotStagingMode = $isNotStagingMode->isStaging($request);
        $this->assertFalse($isNotStagingMode, 'plugin');
    }

    /**
     * @dataProvider StagingShopDataprovider
     * @param bool $staging
     * @param array $param
     */
    public function testisStaging(
        $staging,
        array $param
    ) {
        $request = new RequestHttp();
        $request->setModuleName('frontend');
        $request->setParams($param);
        // Create Mock object for Shopware Session
        $session = $this->getMockBuilder(Session::class)
            ->setMethods(['offsetGet'])
            ->getMock();
        $session->expects($this->once())
            ->method('offsetGet')
            ->with('stagingFlag')
            ->willReturn(false);

        $configloader = $this->createMock(ConfigLoader::class);
        $configloader->expects($this->once())
            ->method('isStagingShop')
            -> willReturn(true);

        Shopware()->Container()->set('fin_search_unified.config_loader', $configloader);
        // Assign mocked session variable to application container

        Shopware()->Container()->set('session', $session);
        $stagingManager = Shopware()->Container()->get('fin_search_unified.staging_manager');
        $isStagingMode = $stagingManager->isStaging($request);
        $this->assertTrue($isStagingMode, 'plugin');
    }
}
