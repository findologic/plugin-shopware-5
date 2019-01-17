<?php

use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\CustomFacetGateway;
use FinSearchUnified\Constants;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use Shopware\Components\Test\Plugin\TestCase;

class CustomFacetGatewayTest extends TestCase
{
    public function tearDown()
    {
        parent::tearDown();
        Shopware()->Container()->reset('front');
        Shopware()->Container()->load('front');
    }

    /**
     * @throws Exception
     */
    public function testGetListMethodWhenShopSearchIsTriggered()
    {
        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        $mockOriginalService = $this->getMockBuilder(
            '\Shopware\Bundle\StoreFrontBundle\Gateway\DBAL\CustomFacetGateway'
        )
            ->setMethods(['getList'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockOriginalService->expects($this->once())->method('getList');

        $mockUrlBuilder = $this->getMockBuilder('\FinSearchUnified\Helper\UrlBuilder')->getMock();
        $mockUrlBuilder->expects($this->never())->method('setCustomerGroup');
        $mockUrlBuilder->expects($this->never())
            ->method('buildCompleteFilterList');

        $facetGateway = new CustomFacetGateway(
            $mockOriginalService,
            Shopware()->Container()->get('fin_search_unified.custom_listing_hydrator'),
            $mockUrlBuilder
        );

        $facetGateway->getList([3], $context);
    }

    public function getListProviderForInvalidResponse()
    {
        return [
            'FINDOLOGIC search is triggered and response is null' => [
                null,
                []
            ],
            'FINDOLOGIC search is triggered and response is not OK' => [
                404,
                [
                    ['name' => 'price', 'display' => 'Preis', 'select' => 'single', 'type' => 'range-slider']
                ]
            ],

        ];
    }

    /**
     * @dataProvider getListProviderForInvalidResponse
     *
     * @param int $responseCode
     * @param array $filterData
     *
     * @throws Zend_Http_Exception
     * @throws Exception
     */
    public function testGetListMethodWhenResponseIsNullOrNotOK($responseCode, $filterData)
    {
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
            ['ActivateFindologic', true],
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
        $mockOriginalService->expects($this->once())->method('getList')->willReturn(
            $originalService->getList([3], $context)
        );
        $xmlResponse = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>');

        $results = $xmlResponse->addChild('results');
        $results->addChild('count', 2);
        $filters = $xmlResponse->addChild('filters');
        foreach ($filterData as $data) {
            $filter = $filters->addChild('filter');
            foreach ($data as $key => $value) {
                $filter->addChild($key, $value);
            }
        }
        $mockUrlBuilder = $this->getMockBuilder('\FinSearchUnified\Helper\UrlBuilder')
            ->setMethods(['setCustomerGroup', 'buildCompleteFilterList'])
            ->getMock();
        $mockUrlBuilder->expects($this->once())->method('setCustomerGroup');
        $mockUrlBuilder->expects($this->once())->method('buildCompleteFilterList')
            ->willReturn(
                $responseCode !== null ? new \Zend_Http_Response(404, [], $xmlResponse->asXML()) : null
            );

        $mockHydrator = $this->getMockBuilder(
            '\FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator'
        )
            ->setMethods(['hydrateFacet'])
            ->getMock();
        $mockHydrator->expects($this->never())
            ->method('hydrateFacet');
        $facetGateway = new CustomFacetGateway(
            $mockOriginalService,
            $mockHydrator,
            $mockUrlBuilder
        );

        $facetGateway->getList([3], $context);
    }

    /**
     * @return array
     */
    public function getListProviderForFindologicSearch()
    {
        return [
            'FINDOLOGIC search is triggered and response is OK' => [
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
                [],
                []
            ],
            'Single CustomFacet with Price' => [
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
     * @dataProvider getListProviderForFindologicSearch
     *
     * @param array $filterData
     * @param array $facetData
     *
     * @throws Zend_Http_Exception
     * @throws Exception
     */
    public function testGetListMethodForFindologicSearch(
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
            ['ActivateFindologic', true],
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

        $mockOriginalService = $this->getMockBuilder(
            '\Shopware\Bundle\StoreFrontBundle\Gateway\DBAL\CustomFacetGateway'
        )
            ->setMethods(['getList'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockOriginalService->expects($this->never())->method('getList');

        $mockUrlBuilder = $this->getMockBuilder('\FinSearchUnified\Helper\UrlBuilder')->getMock();
        $mockUrlBuilder->expects($this->once())->method('setCustomerGroup');
        $xmlResponse = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>');

        $results = $xmlResponse->addChild('results');
        $results->addChild('count', 2);
        $filters = $xmlResponse->addChild('filters');
        foreach ($filterData as $data) {
            $filter = $filters->addChild('filter');
            foreach ($data as $key => $value) {
                $filter->addChild($key, $value);
            }
        }
        $mockUrlBuilder->expects($this->once())
            ->method('buildCompleteFilterList')
            ->willReturn(new \Zend_Http_Response(200, [], $xmlResponse->asXML()));

        $originalHydrator = Shopware()->Container()->get('fin_search_unified.custom_listing_hydrator');

        $mockHydrator = $this->getMockBuilder(
            '\FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator'
        )
            ->setMethods([])
            ->getMock();
        $facetArray = [];
        foreach ($xmlResponse->filters->filter as $filter) {
            $facetArray[] = $originalHydrator->hydrateFacet($filter);
        }
        $mockHydrator->expects($this->exactly(count($filterData)))
            ->method('hydrateFacet')
            ->willReturnOnConsecutiveCalls($facetArray);

        $facetGateway = new CustomFacetGateway(
            $mockOriginalService,
            $mockHydrator,
            $mockUrlBuilder
        );

        $customFacets = $facetGateway->getList([3], $context);

        /** @var CustomFacet $customFacet */
        foreach ($customFacets[0] as $key => $customFacet) {
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
