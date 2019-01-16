<?php

use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\CustomFacetGateway;
use FinSearchUnified\Constants;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use Shopware\Components\Test\Plugin\TestCase;

class CustomFacetGatewayTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        Shopware()->Container()->reset('front');
        Shopware()->Container()->load('front');
    }

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
                null,
                null,
                null,
                null
            ],
            'FINDOLOGIC search is triggered and response is null' => [
                $this->once(),
                $this->once(),
                $this->once(),
                $this->never(),
                null,
                [],
                null
            ],
            'FINDOLOGIC search is triggered and response is not OK' => [
                $this->once(),
                $this->once(),
                $this->once(),
                $this->never(),
                404,
                [
                    ['name' => 'price', 'display' => 'Preis', 'select' => 'single', 'type' => 'range-slider']
                ],
                null
            ],
            'FINDOLOGIC search is triggered and response is OK' => [
                $this->once(),
                $this->once(),
                $this->never(),
                $this->once(),
                200,
                [
                    ['name' => 'price', 'display' => 'Preis', 'select' => 'single', 'type' => 'range-slider']
                ],
                [
                    [
                        'name' => 'price',
                        'uniqueKey' => 'price',
                        'attribute_label' => 'Preis',
                        'attribute_name' => 'product_attribute_price',
                        'attribute_formfield_name' => 'price',
                        'attribute_mode' => ProductAttributeFacet::MODE_RANGE_RESULT,
                    ]
                ]
            ],
            'No facets are returned' => [
                $this->once(),
                $this->once(),
                $this->never(),
                $this->never(),
                200,
                [],
                []
            ],
            'Single CustomFacet with Price' => [
                $this->once(),
                $this->once(),
                $this->never(),
                $this->once(),
                200,
                [
                    ['name' => 'price', 'display' => 'Preis', 'select' => 'single', 'type' => 'range-slider']
                ],
                [
                    [
                        'name' => 'price',
                        'uniqueKey' => 'price',
                        'attribute_label' => 'Preis',
                        'attribute_name' => 'product_attribute_price',
                        'attribute_formfield_name' => 'price',
                        'attribute_mode' => ProductAttributeFacet::MODE_RANGE_RESULT,
                    ]
                ]
            ],
            'Two CustomFacet with Price and Color' => [
                $this->once(),
                $this->once(),
                $this->never(),
                $this->exactly(2), // No of filters in the XML
                200,
                [
                    ['name' => 'price', 'display' => 'Preis', 'select' => 'single', 'type' => 'range-slider'],
                    ['name' => 'color', 'display' => 'Farbe', 'select' => 'multiple', 'type' => 'label'],
                ],
                [
                    [
                        'name' => 'price',
                        'uniqueKey' => 'price',
                        'attribute_label' => 'Preis',
                        'attribute_name' => 'product_attribute_price',
                        'attribute_formfield_name' => 'price',
                        'attribute_mode' => ProductAttributeFacet::MODE_RANGE_RESULT,
                    ],
                    [
                        'name' => 'color',
                        'uniqueKey' => 'color',
                        'attribute_label' => 'Farbe',
                        'attribute_name' => 'product_attribute_color',
                        'attribute_formfield_name' => 'color',
                        'attribute_mode' => ProductAttributeFacet::MODE_VALUE_LIST_RESULT,
                    ]
                ]
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
     * @param int|null $responseCode
     * @param array $filterData
     * @param array $facetData
     *
     * @throws Zend_Http_Exception
     */
    public function testGetListForShopSearch(
        $expectedCustomerGroupInvoke,
        $expectedBuildCompleteFilterListInvoke,
        $expectedOriginalServiceGetListInvoke,
        $expectedHydrateFacetInvoke,
        $responseCode,
        $filterData,
        $facetData
    ) {
        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        // Custom request object to trigger findologic search
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setModuleName('frontend');

        // Create Mock object for Shopware Front Request
        $mockFront = $this->getMockBuilder('\Enlight_Controller_Front')
            ->disableOriginalConstructor()
            ->setMethods(['Request'])
            ->getMock();

        $mockFront->method('Request')->willReturn($request);

        // Assign mocked variable to application container
        Shopware()->Container()->set('front', $mockFront);

        $configArray = [
            ['ActivateFindologic', $expectedHydrateFacetInvoke !== null],
            ['ShopKey', 'ABCD0815'],
            ['ActivateFindologicForCategoryPages', false],
            ['IntegrationType', Constants::INTEGRATION_TYPE_API]
        ];

        // Create mock object for Shopware Config and explicitly return the values
        $mockConfig = $this->getMockBuilder('\Shopware_Components_Config')
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig->method('offsetGet')
            ->willReturnMap($configArray);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $mockConfig);

        $sessionArray = [
            ['isSearchPage', true],
            ['isCategoryPage', false],
            ['findologicDI', false]
        ];

        // Create mock object for Shopware Session and explicitly return the values
        $mockSession = $this->getMockBuilder('\Enlight_Components_Session_Namespace')
            ->setMethods(['offsetGet'])
            ->getMock();
        $mockSession->method('offsetGet')
            ->willReturnMap($sessionArray);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('session', $mockSession);

        $originalService = Shopware()->Container()->get('shopware_storefront.custom_facet_gateway');
        $mockOriginalService = $this->getMockBuilder(
            '\Shopware\Bundle\StoreFrontBundle\Gateway\DBAL\CustomFacetGateway'
        )
            ->setMethods(['getList'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockOriginalService->expects($expectedOriginalServiceGetListInvoke)->method('getList')->willReturn(
            $originalService->getList([3], $context)
        );

        $mockUrlBuilder = $this->getMockBuilder('\FinSearchUnified\Helper\UrlBuilder')->getMock();
        $mockUrlBuilder->expects($expectedCustomerGroupInvoke)->method('setCustomerGroup');
        $xmlResponse = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>');
        if ($responseCode !== null) {
            $results = $xmlResponse->addChild('results');
            $results->addChild('count', 2);
            $filters = $xmlResponse->addChild('filters');
            if (!empty($filterData)) {
                foreach ($filterData as $data) {
                    $filter = $filters->addChild('filter');
                    foreach ($data as $key => $value) {
                        $filter->addChild($key, $value);
                    }
                }
            }
            $mockUrlBuilder->expects($expectedBuildCompleteFilterListInvoke)
                ->method('buildCompleteFilterList')
                ->willReturn(new \Zend_Http_Response($responseCode, [], $xmlResponse->asXML()));
        }

        $originalHydrator = Shopware()->Container()->get('fin_search_unified.custom_listing_hydrator');

        $mockHydrator = $this->getMockBuilder(
            '\FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator'
        )
            ->setMethods([])
            ->getMock();
        if ($expectedHydrateFacetInvoke !== null) {
            $mockHydrator->expects($expectedHydrateFacetInvoke)
                ->method('hydrateFacet')
                ->willReturn(
                    $responseCode !== null ? $originalHydrator->hydrateFacet($xmlResponse->filters->filter) : []
                );
        }

        $facetGateway = new CustomFacetGateway(
            $mockOriginalService,
            $mockHydrator
        );

        $facetGateway->setUrlBuilder($mockUrlBuilder);

        $customFacets = $facetGateway->getList([3], $context);
        if ($facetData !== null) {
            /** @var CustomFacet $customFacet */
            foreach ($customFacets as $key => $customFacet) {
                $this->assertSame(
                    $facetData[$key]['name'],
                    $customFacet->getName(),
                    sprintf("Expected custom facet's name to be %s", $facetData[$key]['name'])
                );
                $this->assertSame(
                    $facetData[$key]['uniqueKey'],
                    $customFacet->getUniqueKey(),
                    sprintf("Expected custom facet's unique key to be %s", $facetData[$key]['uniqueKey'])
                );

                /** @var ProductAttributeFacet $productAttributeFacet */
                $productAttributeFacet = $customFacet->getFacet();

                $this->assertInstanceOf(
                    ProductAttributeFacet::class,
                    $productAttributeFacet,
                    sprintf(
                        "Expected custom facet's facet to be of type %s",
                        ProductAttributeFacet::class
                    )
                );
                $this->assertSame(
                    $facetData[$key]['attribute_name'],
                    $productAttributeFacet->getName(),
                    sprintf(
                        "Expected product attribute facet's name to be %s",
                        $facetData[$key]['attribute_name']
                    )
                );
                $this->assertSame(
                    $facetData[$key]['attribute_formfield_name'],
                    $productAttributeFacet->getFormFieldName(),
                    sprintf(
                        "Expected product attribute facet's form field name to be %s",
                        $facetData[$key]['attribute_formfield_name']
                    )
                );
                $this->assertSame(
                    $facetData[$key]['attribute_label'],
                    $productAttributeFacet->getLabel(),
                    sprintf(
                        "Expected product attribute facet's label to be %s",
                        $facetData[$key]['attribute_label']
                    )
                );
                $this->assertSame(
                    $facetData[$key]['attribute_mode'],
                    $productAttributeFacet->getMode(),
                    sprintf(
                        "Expected product attribute facet's mode to be %s",
                        $facetData[$key]['attribute_mode']
                    )
                );
            }
        }
    }
}
