<?php

use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\CustomFacetGateway;
use FinSearchUnified\Constants;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Components\Test\Plugin\TestCase;

class CustomFacetGatewayTest extends TestCase
{
    /**
     * Provider for testing getList method of CustomFacetGateway
     *
     * @return array
     */
    public function getListProvider()
    {
        return [
            'Shop search is triggered' => [
                $this->never(),
                $this->never(),
                $this->once(),
                $this->any()
            ],
            'FINDOLOGIC search is triggered and response is null' => [
                $this->once(),
                $this->once(),
                $this->once(),
                $this->never()
            ],
            'FINDOLOGIC search is triggered and response is not OK' => [
                $this->once(),
                $this->once(),
                $this->once(),
                $this->never()
            ],
            'FINDOLOGIC search is triggered and response is OK' => [
                $this->once(),
                $this->once(),
                $this->never(),
                $this->once()
            ],
        ];
    }

    /**
     * @dataProvider getListProvider
     *
     * @param $expectedCustomerGroupInvoke
     * @param $expectedBuildCompleteFilterListInvoke
     * @param $expectedOriginalServiceGetListInvoke
     * @param $expectedHydrateFacetInvoke
     */
    public function testGetListForShopSearch(
        $expectedCustomerGroupInvoke,
        $expectedBuildCompleteFilterListInvoke,
        $expectedOriginalServiceGetListInvoke,
        $expectedHydrateFacetInvoke
    ) {
        $configArray = [
            ['ActivateFindologic', $expectedHydrateFacetInvoke !== null],
            ['ShopKey', 'ABCD0815'],
            ['ActivateFindologicForCategoryPages', false],
            ['IntegrationType', Constants::INTEGRATION_TYPE_API]
        ];

        // Create mock object for Shopware Config and explicitly return the values
        $config = $this->getMockBuilder('\Shopware_Components_Config')
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->method('offsetGet')
            ->willReturnMap($configArray);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        $sessionArray = [
            ['isSearchPage', true],
            ['isCategoryPage', false],
            ['findologicDI', false]
        ];

        // Create mock object for Shopware Session and explicitly return the values
        $session = $this->getMockBuilder('\Enlight_Components_Session_Namespace')
            ->setMethods(['offsetGet'])
            ->getMock();
        $session->method('offsetGet')
            ->willReturnMap($sessionArray);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('session', $session);

        $originalService = $this->getMockBuilder(
            '\Shopware\Bundle\StoreFrontBundle\Gateway\DBAL\CustomFacetGateway')
            ->disableOriginalConstructor()
            ->getMock();
        $originalService->expects($expectedOriginalServiceGetListInvoke)->method('getList');

        $hydrator = $this->getMockBuilder(
            '\FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator')
            ->getMock();
        $hydrator->expects($expectedHydrateFacetInvoke)->method('hydrateFacet');

        $urlBuilder = $this->getMockBuilder('\FinSearchUnified\Helper\UrlBuilder')->getMock();
        $urlBuilder->expects($expectedCustomerGroupInvoke)->method('setCustomerGroup');
        $urlBuilder->expects($expectedBuildCompleteFilterListInvoke)->method('buildCompleteFilterList');

        $facetGateway = new CustomFacetGateway(
            $originalService,
            $hydrator,
            $urlBuilder
        );

        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        $customFacets = $facetGateway->getList([3, 5], $context);
    }
}