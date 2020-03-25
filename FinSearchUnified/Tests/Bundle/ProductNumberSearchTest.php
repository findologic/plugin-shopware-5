<?php

namespace FinSearchUnified\Tests\Bundle;

use Enlight_Controller_Action as Action;
use Enlight_Controller_Front as Front;
use Enlight_Controller_Plugins_ViewRenderer_Bootstrap as ViewRenderer;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use Enlight_Plugin_Namespace_Loader as Plugins;
use Enlight_View_Default as View;
use Exception;
use FinSearchUnified\Bundle\ProductNumberSearch;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilderFactory;
use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use FinSearchUnified\Components\ConfigLoader;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\Tests\Helper\Utility;
use FinSearchUnified\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionException;
use ReflectionObject;
use Shopware\Bundle\SearchBundle\Condition\PriceCondition;
use Shopware\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetResult\MediaListFacetResult;
use Shopware\Bundle\SearchBundleDBAL\ProductNumberSearch as OriginalProductNumberSearch;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\TreeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\TreeItem;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListItem;
use Shopware\Bundle\SearchBundle\FacetResultInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Shopware_Components_Config as Config;
use SimpleXMLElement;
use Zend_Cache_Core;

class ProductNumberSearchTest extends TestCase
{
    /**
     * @var ShopContextInterface
     */
    private $context;

    protected function setUp()
    {
        parent::setUp();

        $configArray = [
            ['ActivateFindologic', true],
            ['ShopKey', 'ABCDABCDABCDABCDABCDABCDABCDABCD'],
            ['ActivateFindologicForCategoryPages', false]
        ];
        // Create mock object for Shopware Config and explicitly return the values
        $mockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['offsetGet', 'get'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig->method('offsetGet')
            ->willReturnMap($configArray);
        $mockConfig->expects($this->any())
            ->method('get')
            ->willReturn(!empty(getenv('SHOPWARE_VERSION')) ? getenv('SHOPWARE_VERSION') : '5.6.4');

        Shopware()->Container()->set('config', $mockConfig);

        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $this->context = $contextService->getShopContext();
    }

    protected function tearDown()
    {
        parent::tearDown();

        Shopware()->Container()->reset('front');
        Shopware()->Container()->load('front');

        Shopware()->Container()->reset('config');
        Shopware()->Container()->load('config');
    }

    public function productNumberSearchProvider()
    {
        return [
            'Shopware internal search, unrelated to FINDOLOGIC' => [
                'isFetchCount' => false,
                'isUseShopSearch' => true,
                'invokationCount' => 0
            ],
            'Shopware internal search' => [
                'isFetchCount' => false,
                'isUseShopSearch' => false,
                'invokationCount' => 0
            ],
            'Shopware search, unrelated to FINDOLOGIC' => [
                'isFetchCount' => true,
                'isUseShopSearch' => true,
                'invokationCount' => 0
            ],
            'FINDOLOGIC search' => [
                'isFetchCount' => true,
                'isUseShopSearch' => false,
                'invokationCount' => 1
            ]
        ];
    }

    /**
     * @dataProvider productNumberSearchProvider
     *
     * @param bool $isFetchCount
     * @param bool $isUseShopSearch
     * @param int $invokationCount
     *
     * @throws Exception
     */
    public function testProductNumberSearchImplementation($isFetchCount, $isUseShopSearch, $invokationCount)
    {
        $criteria = new Criteria();
        if (!method_exists($criteria, 'setFetchCount')) {
            $this->markTestSkipped('Ignoring this test for Shopware 5.2.x');
        }

        $xmlResponse = Utility::getDemoXML();
        unset($xmlResponse->promotion);
        $response = $xmlResponse->asXML();

        $criteria->setFetchCount($isFetchCount);

        Shopware()->Session()->findologicDI = $isUseShopSearch;
        Shopware()->Session()->isSearchPage = !$isUseShopSearch;

        $mockedQuery = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods(['execute'])
            ->getMockForAbstractClass();

        $mockedQuery->expects($this->exactly($invokationCount))->method('execute')->willReturn($response);

        // Mock querybuilder factory method to check that custom implementation does not get called
        // as original implementation will be called in this case
        $mockQuerybuilderFactory = $this->createMock(QueryBuilderFactory::class);
        $mockQuerybuilderFactory->expects($this->exactly($invokationCount))
            ->method('createProductQuery')
            ->willReturn($mockedQuery);
        $mockQuerybuilderFactory->expects($this->any())
            ->method('createSearchNavigationQueryWithoutAdditionalFilters')
            ->willReturn($mockedQuery);

        $originalService = $this->createMock(OriginalProductNumberSearch::class);
        $mockedCache = $this->createMock(Zend_Cache_Core::class);

        $productNumberSearch = new ProductNumberSearch(
            $originalService,
            $mockQuerybuilderFactory,
            $mockedCache
        );

        $front = $this->getFrontViewMock();
        Shopware()->Container()->set('front', $front);

        $context = Shopware()->Container()->get('shopware_storefront.context_service')->getContext();
        $productNumberSearch->search($criteria, $context);
    }


    /**
     * @dataProvider allFiltersProvider
     * @dataProvider facetWithNoHandlerProvider
     * @dataProvider facetWithInvalidModeProvider
     * @dataProvider missingFilterProvider
     *
     * @param SimpleXMLElement $xmlResponse
     * @param array $expectedResults
     *
     * @throws Exception
     */
    public function testProductNumberSearchResultWithAllFilters(SimpleXMLElement $xmlResponse, array $expectedResults)
    {
        $xml = $xmlResponse->asXML();

        $criteria = new Criteria();
        if (method_exists($criteria, 'setFetchCount')) {
            $criteria->setFetchCount(true);
        }

        Shopware()->Session()->findologicDI = false;
        Shopware()->Session()->isSearchPage = true;

        $mockedQuery = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods(['execute'])
            ->getMockForAbstractClass();

        $mockedQuery->expects($this->once())->method('execute')->willReturn($xml);

        // Mock querybuilder factory method to check that custom implementation does not get called
        // as original implementation will be called in this case
        $mockQuerybuilderFactory = $this->createMock(QueryBuilderFactory::class);
        $mockQuerybuilderFactory->expects($this->once())
            ->method('createProductQuery')
            ->willReturn($mockedQuery);

        $front = $this->getFrontViewMock();
        Shopware()->Container()->set('front', $front);

        $mockedCache = $this->createMock(Zend_Cache_Core::class);

        $originalService = $this->createMock(ProductNumberSearch::class);
        $productNumberSearch = new ProductNumberSearch(
            $originalService,
            $mockQuerybuilderFactory,
            $mockedCache
        );

        $hydrator = Shopware()->Container()->get('fin_search_unified.custom_listing_hydrator');

        foreach ($xmlResponse->filters->filter as $filter) {
            $facet = $hydrator->hydrateFacet($filter);
            $criteria->addFacet($facet->getFacet());
        }

        $context = Shopware()->Container()->get('shopware_storefront.context_service')->getContext();
        $searchResult = $productNumberSearch->search($criteria, $context);
        $resultFacets = $searchResult->getFacets();

        foreach ($resultFacets as $key => $resultFacet) {
            $this->assertInstanceOf($expectedResults[$key], $resultFacet);
        }
    }

    public function allFiltersProvider()
    {
        $xmlResponse = Utility::getDemoXML();

        return [
            'Parse all filters' => [
                'xmlResponse' => $xmlResponse,
                'expectedResults' => [
                    TreeFacetResult::class,
                    RangeFacetResult::class,
                    MediaListFacetResult::class,
                    ValueListFacetResult::class,
                    ValueListFacetResult::class,
                    ValueListFacetResult::class,
                    ValueListFacetResult::class,
                    ValueListFacetResult::class,
                    ValueListFacetResult::class,
                    ValueListFacetResult::class,
                    ValueListFacetResult::class,
                    ValueListFacetResult::class
                ]
            ]
        ];
    }

    public function facetWithNoHandlerProvider()
    {
        $xmlResponse = Utility::getDemoXML();
        unset($xmlResponse->filters);
        $filters = $xmlResponse->addChild('filters');

        $this->setCategoryFilter($filters);
        $this->setVendorFilter($filters, 'unsupported');

        return [
            'Facet with no handler' => [
                'xmlResponse' => $xmlResponse,
                'expectedResults' => [
                    TreeFacetResult::class
                ]
            ]
        ];
    }

    public function facetWithInvalidModeProvider()
    {
        $xmlResponse = Utility::getDemoXML();
        unset($xmlResponse->filters);
        $filters = $xmlResponse->addChild('filters');

        $this->setCategoryFilter($filters);
        $this->setVendorFilter($filters, 'range-slider');

        return [
            'Cannot create facet result' => [
                'xmlResponse' => $xmlResponse,
                'expectedResults' => [
                    TreeFacetResult::class
                ]
            ]
        ];
    }

    public function missingFilterProvider()
    {
        $xmlResponse = Utility::getDemoXML();
        unset($xmlResponse->filters);
        $filters = $xmlResponse->addChild('filters');

        $this->setCategoryFilter($filters);
        $this->setVendorFilter($filters, 'select', false);

        return [
            'Filter is missing in response' => [
                'xmlResponse' => $xmlResponse,
                'expectedResults' => [
                    TreeFacetResult::class,
                    ValueListFacetResult::class
                ]
            ]
        ];
    }

    public function facetWithPriceFilterProvider()
    {
        $xmlResponse = Utility::getDemoXML();
        unset($xmlResponse->filters);
        $filters = $xmlResponse->addChild('filters');

        $this->setPriceFilter($filters);

        return [
            'Price filters which are selected only contain the value that was selected' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new RangeFacetResult(
                    'price',
                    true,
                    'Price',
                    0.0,
                    99.0,
                    4.2,
                    69.0,
                    'min',
                    'max'
                ),
                'condition' => new PriceCondition(4.2, 69.0)

            ],
            'All price filter values are displayed if no filters are selected' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new RangeFacetResult(
                    'price',
                    false,
                    'Price',
                    0.0,
                    99.0,
                    4.2,
                    69.0,
                    'min',
                    'max'
                ),
                'condition' => null
            ]
        ];
    }

    public function facetWithVendorFilterProvider()
    {
        $xmlResponse = Utility::getDemoXML();
        unset($xmlResponse->filters);
        $filters = $xmlResponse->addChild('filters');

        $this->setVendorFilter($filters);

        return [
            'All vendor filter values are displayed along with selected filter' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new ValueListFacetResult(
                    'product_attribute_vendor',
                    true,
                    'Brand',
                    [
                        new ValueListItem('Manufacturer', 'Manufacturer (40)', false),
                        new ValueListItem('FINDOLOGIC', 'FINDOLOGIC', true)
                    ],
                    'vendor'
                ),
                'condition' => new ProductAttributeCondition('vendor', '=', 'FINDOLOGIC')
            ],
            'All vendor filter values are displayed if no filters are selected' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new ValueListFacetResult(
                    'product_attribute_vendor',
                    false,
                    'Brand',
                    [
                        new ValueListItem('Manufacturer', 'Manufacturer (40)', false),
                        new ValueListItem('FINDOLOGIC', 'FINDOLOGIC (54)', false)
                    ],
                    'vendor'
                ),
                'condition' => null
            ]
        ];
    }

    public function facetWithCategoryFilterProvider()
    {
        $xmlResponse = Utility::getDemoXML();
        unset($xmlResponse->filters);
        $filters = $xmlResponse->addChild('filters');

        $this->setCategoryFilter($filters);

        return [
            'Category filters which are selected only contain the value that was selected' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new TreeFacetResult(
                    'product_attribute_cat',
                    'cat',
                    true,
                    'Category',
                    [
                        new TreeItem('Living Room', 'Living Room (10)', false, []),
                        new TreeItem('FINDOLOGIC', 'FINDOLOGIC', true, [])
                    ]
                ),
                'condition' => new ProductAttributeCondition('cat', '=', 'FINDOLOGIC')
            ],
            'All category filter values are displayed if no filters are selected' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new TreeFacetResult(
                    'product_attribute_cat',
                    'cat',
                    false,
                    'Category',
                    [
                        new TreeItem('Living Room', 'Living Room (10)', false, [])
                    ]
                ),
                'condition' => null
            ]
        ];
    }

    /**
     * @dataProvider facetWithPriceFilterProvider
     * @dataProvider facetWithVendorFilterProvider
     * @dataProvider facetWithCategoryFilterProvider
     *
     * @param SimpleXMLElement $xmlResponse
     * @param FacetResultInterface $expectedResult
     * @param ConditionInterface|null $condition
     *
     * @throws ReflectionException
     * @throws Enlight_Exception
     */
    public function testCreateFacets(
        SimpleXMLElement $xmlResponse,
        FacetResultInterface $expectedResult,
        ConditionInterface $condition = null
    ) {
        $request = new RequestHttp();
        $request->setRequestUri('/findologic?q=1');

        Shopware()->Front()->setRequest($request);
        $criteria = new Criteria();
        if (method_exists($criteria, 'setFetchCount')) {
            $criteria->setFetchCount(true);
        }
        if ($condition) {
            $criteria->addCondition($condition);
        }

        $hydrator = Shopware()->Container()->get('fin_search_unified.custom_listing_hydrator');

        foreach ($xmlResponse->filters->filter as $filter) {
            $facetResult = $hydrator->hydrateFacet($filter);
            $criteria->addFacet($facetResult->getFacet());
        }

        $originalService = $this->createMock(OriginalProductNumberSearch::class);

        $request = new RequestHttp();
        $request->setModuleName('frontend');
        $request->setRequestUri('/findologic');
        Shopware()->Front()->setRequest($request);

        // Filters are present in the XML response
        $filters = $xmlResponse->filters->filter;
        $response = $xmlResponse->asXML();

        $mockedCache = $this->createMock(Zend_Cache_Core::class);

        $productNumberSearch = new ProductNumberSearch(
            $originalService,
            Shopware()->Container()->get('fin_search_unified.query_builder_factory'),
            $mockedCache
        );

        $reflector = new ReflectionObject($productNumberSearch);
        $method = $reflector->getMethod('createFacets');
        $method->setAccessible(true);

        $result = $method->invokeArgs($productNumberSearch, [$criteria, $this->context, $filters]);

        $this->assertNotEmpty($result);

        $facetResult = current($result);

        $this->assertEquals($expectedResult, $facetResult);
    }

    /**
     * @param SimpleXMLElement $filters
     */
    private function setPriceFilter(SimpleXMLElement $filters)
    {
        // Price filter
        $price = $filters->addChild('filter');
        $price->addChild('type', 'range-slider');
        $price->addChild('select', 'single');
        $price->addChild('name', 'price');
        $price->addChild('display', 'Price');
        $attributes = $price->addChild('attributes');
        $selectedRange = $attributes->addChild('selectedRange');
        $selectedRange->addChild('min', 4.20);
        $selectedRange->addChild('max', 69.00);
        $totalRange = $attributes->addChild('totalRange');
        $totalRange->addChild('min', 0.00);
        $totalRange->addChild('max', 99.00);
    }

    /**
     * @param SimpleXMLElement $filters
     */
    private function setCategoryFilter(SimpleXMLElement $filters)
    {
        // Category filter
        $category = $filters->addChild('filter');
        $category->addChild('type', 'label');
        $category->addChild('select', 'single');
        $category->addChild('name', 'cat');
        $category->addChild('display', 'Category');
        $categoryItem = $category->addChild('items')->addChild('item');
        $categoryItem->addChild('name', 'Living Room');
        $categoryItem->addChild('frequency', 10);
    }

    /**
     * @param SimpleXMLElement $filters
     * @param string $type
     * @param bool $withFilterItems
     */
    private function setVendorFilter(SimpleXMLElement $filters, $type = 'select', $withFilterItems = true)
    {
        // Vendor filter
        $vendor = $filters->addChild('filter');
        $vendor->addChild('type', $type);
        $vendor->addChild('select', 'multiple');
        $vendor->addChild('name', 'vendor');
        $vendor->addChild('display', 'Brand');
        if ($withFilterItems) {
            $items = $vendor->addChild('items');
            $ventorItem = $items->addChild('item');
            $ventorItem->addChild('name', 'Manufacturer');
            $ventorItem->addChild('frequency', 40);
            $ventorItem = $items->addChild('item');
            $ventorItem->addChild('name', 'FINDOLOGIC');
            $ventorItem->addChild('frequency', 54);
        }
    }

    /**
     * @return Front|MockObject
     */
    private function getFrontViewMock()
    {
        $request = new RequestHttp();
        $request->setModuleName('frontend');

        // Create mocked view
        $view = $this->createMock(View::class);
        $action = $this->createMock(Action::class);
        $action->method('View')
            ->willReturn($view);

        $renderer = $this->createMock(ViewRenderer::class);
        $renderer->method('Action')
            ->willReturn($action);

        $plugin = $this->createMock(Plugins::class);
        $plugin->method('get')
            ->with('ViewRenderer')
            ->willReturn($renderer);

        $front = $this->createMock(Front::class);
        $front->method('Plugins')
            ->willReturn($plugin);
        $front->method('Request')
            ->willReturn($request);

        return $front;
    }

    public function facetWithCategoryFilterProviderWhenProductAndFilterLiveReloadingIsEnabled()
    {
        $xmlResponse = Utility::getDemoXML();
        unset($xmlResponse->filters);
        $filters = $xmlResponse->addChild('filters');

        $this->setCategoryFilter($filters);

        return [
            'No frequencies are set when live reloading is enabled' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new TreeFacetResult(
                    'product_attribute_cat',
                    'cat',
                    true,
                    'Category',
                    [
                        new TreeItem(
                            'Bekleidung',
                            'Bekleidung',
                            false,
                            [
                                new TreeItem('Bekleidung_Herren', 'Herren', false, []),
                                new TreeItem('Bekleidung_Damen', 'Damen', false, []),
                            ]
                        ),
                        new TreeItem('Freizeit & Elektro', 'Freizeit & Elektro', false, []),
                        new TreeItem(
                            'Lebensmittel',
                            'Lebensmittel',
                            false,
                            [
                                new TreeItem('Lebensmittel_Süßes', 'Süßes', false, []),
                                new TreeItem('Lebensmittel_Backwaren', 'Backwaren', false, []),
                                new TreeItem('Lebensmittel_Fisch', 'Fisch', false, []),
                            ]
                        ),
                        new TreeItem('FINDOLOGIC', 'FINDOLOGIC', true, [])
                    ]
                ),
                'condition' => new ProductAttributeCondition('cat', '=', 'FINDOLOGIC'),
                'config' => 'filter_ajax_reload'
            ],
            'Frequencies are set when live reloading is not enabled' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new TreeFacetResult(
                    'product_attribute_cat',
                    'cat',
                    true,
                    'Category',
                    [
                        new TreeItem(
                            'Living Room',
                            'Living Room (10)',
                            false,
                            []
                        ),
                        new TreeItem('FINDOLOGIC', 'FINDOLOGIC', true, [])
                    ]
                ),
                'condition' => new ProductAttributeCondition('cat', '=', 'FINDOLOGIC'),
                'config' => 'full_page_reload'
            ],
        ];
    }

    /**
     * @dataProvider facetWithCategoryFilterProviderWhenProductAndFilterLiveReloadingIsEnabled
     *
     * @param SimpleXMLElement $xmlResponse
     * @param FacetResultInterface $expectedResult
     * @param ConditionInterface|null $condition
     * @param string $listingMode
     *
     * @throws Enlight_Exception
     * @throws ReflectionException
     */
    public function testCreateFacetsWhenProductAndFilterLiveReloadingIsEnabled(
        SimpleXMLElement $xmlResponse,
        FacetResultInterface $expectedResult,
        ConditionInterface $condition,
        $listingMode
    ) {
        $configArray = [
            ['ActivateFindologic', true],
            ['ShopKey', 'ABCDABCDABCDABCDABCDABCDABCDABCD'],
            ['ActivateFindologicForCategoryPages', false],
            ['listingMode', $listingMode]
        ];

        // Create mock object for Shopware Config and explicitly return the values
        $mockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig->method('offsetGet')
            ->willReturnMap($configArray);

        Shopware()->Container()->set('config', $mockConfig);

        $request = new RequestHttp();
        $request->setRequestUri('/findologic?q=1');

        Shopware()->Front()->setRequest($request);
        $criteria = new Criteria();
        if (method_exists($criteria, 'setFetchCount')) {
            $criteria->setFetchCount(true);
        }
        if ($condition) {
            $criteria->addCondition($condition);
        }

        $configLoaderMock = $this->getMockBuilder(ConfigLoader::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();
        $hydrator = new CustomListingHydrator($configLoaderMock);

        foreach ($xmlResponse->filters->filter as $filter) {
            $facetResult = $hydrator->hydrateFacet($filter);
            $criteria->addFacet($facetResult->getFacet());
        }

        $originalService = $this->createMock(OriginalProductNumberSearch::class);

        $request = new RequestHttp();
        $request->setModuleName('frontend');
        $request->setRequestUri('/findologic');
        $request->setActionName('defaultSearch');
        $request->setControllerName('search');
        Shopware()->Front()->setRequest($request);

        // No filters are selected in the XML response
        $xml = clone $xmlResponse;
        unset($xml->filters);
        $filters = $xml->addChild('filters');

        $xmlResponse = Utility::getDemoXML();
        $response = $xmlResponse->asXML();

        $mockedQuery = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods(['execute'])
            ->getMockForAbstractClass();

        $mockQuerybuilderFactory = $this->createMock(QueryBuilderFactory::class);
        $mockedCache = $this->createMock(Zend_Cache_Core::class);

        if (StaticHelper::isProductAndFilterLiveReloadingEnabled()) {
            $mockedQuery->expects($this->once())->method('execute')->willReturn($response);

            $mockQuerybuilderFactory->expects($this->once())
                ->method('createSearchNavigationQueryWithoutAdditionalFilters')
                ->willReturn($mockedQuery);

            $cacheKey = sprintf('finsearch_%s', md5($request->getRequestUri()));

            $mockedCache->expects($this->once())
                ->method('load')
                ->with($cacheKey)
                ->willReturn(false);
        }

        $productNumberSearch = new ProductNumberSearch(
            $originalService,
            $mockQuerybuilderFactory,
            $mockedCache
        );

        $reflector = new ReflectionObject($productNumberSearch);
        $method = $reflector->getMethod('createFacets');
        $method->setAccessible(true);

        $result = $method->invokeArgs($productNumberSearch, [$criteria, $this->context, $filters]);

        $this->assertNotEmpty($result);

        $facetResult = current($result);

        $this->assertEquals($expectedResult, $facetResult);
    }

    public function testCreateFacetsWhenFiltersInResponseAreEmpty()
    {
        $criteria = new Criteria();
        $originalService = $this->createMock(OriginalProductNumberSearch::class);

        $configLoaderMock = $this->getMockBuilder(ConfigLoader::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();
        $hydrator = new CustomListingHydrator($configLoaderMock);

        $xmlResponse = Utility::getDemoXML();
        foreach ($xmlResponse->filters->filter as $filter) {
            $facetResult = $hydrator->hydrateFacet($filter);
            $criteria->addFacet($facetResult->getFacet());
        }

        unset($xmlResponse->filters);
        $filters = $xmlResponse->addChild('filters');

        if (method_exists($criteria, 'setFetchCount')) {
            $criteria->setFetchCount(true);
        }

        $request = new RequestHttp();
        $request->setModuleName('frontend');
        $request->setRequestUri('/findologic');
        $request->setActionName('defaultSearch');
        $request->setControllerName('search');
        Shopware()->Front()->setRequest($request);

        $mockQuerybuilderFactory = $this->createMock(QueryBuilderFactory::class);
        $mockedCache = $this->createMock(Zend_Cache_Core::class);

        $productNumberSearch = new ProductNumberSearch(
            $originalService,
            $mockQuerybuilderFactory,
            $mockedCache
        );

        $reflector = new ReflectionObject($productNumberSearch);
        $method = $reflector->getMethod('createFacets');
        $method->setAccessible(true);

        $result = $method->invokeArgs($productNumberSearch, [$criteria, $this->context, $filters]);

        $this->assertEmpty($result);
    }

    public function cacheResponseProvider()
    {
        $xmlResponse = Utility::getDemoXML();

        return [
            'Querybuilder is not used as response is provided from cache' => [
                'xmlResponse' => $xmlResponse,
                'cacheResponse' => $xmlResponse->asXML(),
                'cacheLoadCount' => 2,
                'queryCount' => 0
            ],
            'Querybuilder is used as response is not provided from cache' => [
                'xmlResponse' => $xmlResponse,
                'cacheResponse' => false,
                'cacheLoadCount' => 1,
                'queryCount' => 1
            ],
        ];
    }

    /**
     * @dataProvider cacheResponseProvider
     *
     * @param SimpleXMLElement $xmlResponse
     * @param string|bool $cacheResponse
     * @param int $cacheLoadCount
     * @param int $queryCount
     *
     * @throws Enlight_Exception
     * @throws ReflectionException
     */
    public function testCacheResponse($xmlResponse, $cacheResponse, $cacheLoadCount, $queryCount)
    {
        $response = $xmlResponse->asXML();

        $request = new RequestHttp();
        $request->setRequestUri('/findologic?q=1');

        Shopware()->Front()->setRequest($request);
        $criteria = new Criteria();
        if (method_exists($criteria, 'setFetchCount')) {
            $criteria->setFetchCount(true);
        }


        $configLoaderMock = $this->getMockBuilder(ConfigLoader::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();
        $hydrator = new CustomListingHydrator($configLoaderMock);

        foreach ($xmlResponse->filters->filter as $filter) {
            $facetResult = $hydrator->hydrateFacet($filter);
            $criteria->addFacet($facetResult->getFacet());
        }

        $originalService = $this->createMock(OriginalProductNumberSearch::class);

        $request = new RequestHttp();
        $request->setModuleName('frontend');
        $request->setRequestUri('/findologic');
        Shopware()->Front()->setRequest($request);

        $mockQuerybuilderFactory = $this->createMock(QueryBuilderFactory::class);

        $mockedCache = $this->createMock(Zend_Cache_Core::class);

        $productNumberSearch = new ProductNumberSearch(
            $originalService,
            $mockQuerybuilderFactory,
            $mockedCache
        );

        $reflector = new ReflectionObject($productNumberSearch);
        $method = $reflector->getMethod('createFacets');
        $method->setAccessible(true);

        $result = $method->invokeArgs($productNumberSearch, [$criteria, $this->context, $xmlResponse->filters->filter]);
        $this->assertNotEmpty($result);
    }
}
