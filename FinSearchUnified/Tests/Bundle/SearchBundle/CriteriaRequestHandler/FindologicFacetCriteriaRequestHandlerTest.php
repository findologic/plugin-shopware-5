<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Enlight_Components_Session_Namespace as Session;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use FinSearchUnified\Bundle\SearchBundle\CriteriaRequestHandler\FindologicFacetCriteriaRequestHandler;
use FinSearchUnified\Bundle\StoreFrontBundle\Service\Core\CustomFacetService;
use FinSearchUnified\Constants;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\Tests\TestCase;
use Shopware_Components_Config as Config;

class FindologicFacetCriteriaRequestHandlerTest extends TestCase
{


    public function handleRequestDataProvider()
    {
        {
            return [
                'UseShopSearch is True' => [
                    'ActivateFindologic' => false,
                    'ShopKey' => '0000000000000000ZZZZZZZZZZZZZZZZ',
                    'ActivateFindologicForCategoryPages' => true,
                    'findologicDI' => false,
                    'isSearchPage' => null,
                    'isCategoryPage' => null,
                    'expected' => true,
                ],
                'UseShopSearch is False' => [
                    'ActivateFindologic' => true,
                    'ShopKey' => '0000000000000000ZZZZZZZZZZZZZZZZ',
                    'ActivateFindologicForCategoryPages' => false,
                    'findologicDI' => false,
                    'isSearchPage' => true,
                    'isCategoryPage' => false,
                    'expected' => false
                ],
            ];
        }
    }
    /**
     * @dataProvider handleRequestDataProvider
     *
     * @param bool $isActive
     * @param string $shopKey
     * @param bool $isActiveForCategory
     * @param bool $checkIntegration
     * @param bool $isSearchPage
     * @param bool $isCategoryPage
     * @param bool $expected
     */
    public function testHandleRequest(
        $isActive,
        $shopKey,
        $isActiveForCategory,
        $checkIntegration,
        $isSearchPage,
        $isCategoryPage,
        $expected){
        $configArray = [
            ['ActivateFindologic', $isActive],
            ['ShopKey', $shopKey],
            ['ActivateFindologicForCategoryPages', $isActiveForCategory],
            ['IntegrationType', $checkIntegration ? Constants::INTEGRATION_TYPE_DI : Constants::INTEGRATION_TYPE_API]
            ];

            $request = new RequestHttp();
            $request->setModuleName('frontend');
            $request->setParam('sSearch',$isSearchPage);

            // Create Mock object for Shopware Front Request
            $front = $this->getMockBuilder(Front::class)
                ->setMethods(['Request'])
                ->disableOriginalConstructor()
                ->getMock();
            $front->expects($this->any())
                ->method('Request')
                ->willReturn($request);

            // Assign mocked session variable to application container
            Shopware()->Container()->set('front', $front);

            if ($isSearchPage !== null) {
                $sessionArray = [
                    ['isSearchPage', $isSearchPage],
                    ['isCategoryPage', $isCategoryPage],
                    ['findologicDI', $checkIntegration]
                ];

                // Create Mock object for Shopware Session
                $session = $this->getMockBuilder(Session::class)
                    ->setMethods(['offsetGet'])
                    ->getMock();
                $session->expects($this->atLeastOnce())
                    ->method('offsetGet')
                    ->willReturnMap($sessionArray);

                // Assign mocked session variable to application container
                Shopware()->Container()->set('session', $session);
            }
            // Create Mock object for Shopware Config
            $config = $this->getMockBuilder(Config::class)
                ->setMethods(['offsetGet', 'offsetExists'])
                ->disableOriginalConstructor()
                ->getMock();
            $config->expects($this->atLeastOnce())
                ->method('offsetGet')
                ->willReturnMap($configArray);
            $config->expects($this->any())
                ->method('offsetExists')
                ->willReturn(true);

            // Assign mocked config variable to application container
            Shopware()->Container()->set('config', $config);

        $result = StaticHelper::useShopSearch();
        $error = 'Expected %s search to be triggered but it was not';
        $shop = $expected ? 'shop' : 'FINDOLOGIC';
        $this->assertEquals($expected, $result, sprintf($error, $shop));

            // Assign mocked CustomFacetService::getList method
            $customFacetServiceMock = $this->createMock(CustomFacetService::class);
            if($isSearchPage === null){
                $customFacetServiceMock->expects($this->never())->method('getList');
            }else{
                $customFacetServiceMock->expects($this->once())->method('getList')->with([]);
                $customFacetServiceMock->expects($this->never())->method('getFacetsOfCategories');
            }
            $FindologicFacet = new FindologicFacetCriteriaRequestHandler($customFacetServiceMock);
            $result =  $FindologicFacet->handleRequest(Request , Criteria , ShopContextInterface);

    }
}