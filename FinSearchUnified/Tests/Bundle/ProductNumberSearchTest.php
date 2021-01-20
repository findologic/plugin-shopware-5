<?php

namespace FinSearchUnified\Tests\Bundle;

use Enlight_Controller_Action as Action;
use Enlight_Controller_Front as Front;
use Enlight_Controller_Plugins_ViewRenderer_Bootstrap as ViewRenderer;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use Enlight_Exception;
use Enlight_Plugin_Namespace_Loader as Plugins;
use Enlight_View_Default as View;
use Exception;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FinSearchUnified\Bundle\ProductNumberSearch;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\RangeFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilderFactory;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\ResponseParser;
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
use Shopware\Bundle\SearchBundle\FacetResult\MediaListItem;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\TreeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\TreeItem;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResultInterface;
use Shopware\Bundle\SearchBundleDBAL\ProductNumberSearch as OriginalProductNumberSearch;
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
            ->willReturn(StaticHelper::getShopwareVersion());

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

        $response = Utility::getDemoResponse();
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
        $response = new Xml21Response($xml);
        $responseParser = ResponseParser::getInstance($response);

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

        $mockedQuery->expects($this->once())->method('execute')->willReturn($response);

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

        foreach ($responseParser->getFilters() as $filter) {
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
        $xmlResponse = Utility::getDemoXML('demoResponseWithAllFilterTypes.xml');

        return [
            'Parse all filters' => [
                'xmlResponse' => $xmlResponse,
                'expectedResults' => [
                    TreeFacetResult::class,
                    MediaListFacetResult::class,
                    RangeFacetResult::class,
                    MediaListFacetResult::class,
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
        $xmlResponse = Utility::getDemoXML('demoResponseWithPriceFilter.xml');
        $unit = StaticHelper::isVersionLowerThan('5.3') ? RangeFacetHandler::TEMPLATE_PATH : '€';

        return [
            'Price filters which are selected only contain the value that was selected' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new RangeFacetResult(
                    'price',
                    true,
                    'Preis',
                    0.39,
                    2239.1,
                    0.39,
                    2239.1,
                    'min',
                    'max',
                    [],
                    $unit
                ),
                'condition' => new PriceCondition(4.2, 69.0)

            ],
            'All price filter values are displayed if no filters are selected' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new RangeFacetResult(
                    'price',
                    false,
                    'Preis',
                    0.39,
                    2239.1,
                    0.39,
                    2239.1,
                    'min',
                    'max',
                    [],
                    $unit
                ),
                'condition' => null
            ]
        ];
    }

    public function facetWithVendorFilterProvider()
    {
        $xmlResponse = Utility::getDemoXML('demoResponseWithVendorFilter.xml');

        return [
            'All vendor filter values are displayed along with selected filter' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new MediaListFacetResult(
                    'product_attribute_vendor',
                    true,
                    'Hersteller',
                    [
                        new MediaListItem('Anderson, Gusikowski and Barton', 'Anderson, Gusikowski and Barton', true),
                        new MediaListItem('Bednar Ltd', 'Bednar Ltd', false)
                    ],
                    'vendor'
                ),
                'condition' => new ProductAttributeCondition('vendor', '=', 'Anderson, Gusikowski and Barton')
            ],
            'All vendor filter values are displayed if no filters are selected' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new MediaListFacetResult(
                    'product_attribute_vendor',
                    false,
                    'Hersteller',
                    [
                        new MediaListItem('Anderson, Gusikowski and Barton', 'Anderson, Gusikowski and Barton', false),
                        new MediaListItem('Bednar Ltd', 'Bednar Ltd', false)
                    ],
                    'vendor'
                ),
                'condition' => null
            ]
        ];
    }

    public function facetWithCategoryFilterProvider()
    {
        $xmlResponse = Utility::getDemoXML('demoResponseWithCatFilter.xml');

        return [
            'Category filters which are selected only contain the value that was selected' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new TreeFacetResult(
                    'product_attribute_cat',
                    'cat',
                    true,
                    'Kategorie',
                    [
                        new TreeItem(
                            'Buch',
                            'Buch (5)',
                            false,
                            [
                                new TreeItem('Buch_Beste Bücher', 'Beste Bücher', false, [])
                            ]
                        ),
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
                    'Kategorie',
                    [
                        new TreeItem(
                            'Buch',
                            'Buch (5)',
                            false,
                            [
                                new TreeItem('Buch_Beste Bücher', 'Beste Bücher', false, [])
                            ]
                        )
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
        $response = new Xml21Response($xmlResponse->asXML());
        $responseParser = ResponseParser::getInstance($response);
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

        foreach ($responseParser->getFilters() as $filter) {
            $facetResult = $hydrator->hydrateFacet($filter);
            $criteria->addFacet($facetResult->getFacet());
        }

        $filters = $responseParser->getFilters();
        $productNumberSearch = Shopware()->Container()->get('fin_search_unified.product_number_search');
        $reflector = new ReflectionObject($productNumberSearch);
        $method = $reflector->getMethod('createFacets');
        $method->setAccessible(true);

        $result = $method->invokeArgs(
            $productNumberSearch,
            [
                $criteria,
                $this->context,
                $filters
            ]
        );

        $this->assertNotEmpty($result);
        $facetResult = current($result);
        $this->assertEquals($expectedResult, $facetResult);
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
        return [
            'No frequencies are set when live reloading is enabled' => [
                'config' => 'filter_ajax_reload',
                'label' => 'Buch'
            ],
            'Frequencies are set when live reloading is not enabled' => [
                'config' => 'full_page_reload',
                'label' => 'Buch (5)'
            ],
        ];
    }

    /**
     * @dataProvider facetWithCategoryFilterProviderWhenProductAndFilterLiveReloadingIsEnabled
     *
     * @param string $config
     * @param string $label
     *
     * @throws Enlight_Exception
     * @throws ReflectionException
     */
    public function testCreateFacetsWhenProductAndFilterLiveReloadingIsEnabled($config, $label)
    {
        $xmlResponse = Utility::getDemoXML('demoResponseWithCatFilter.xml');

        $expectedResult = new TreeFacetResult(
            'product_attribute_cat',
            'cat',
            true,
            'Kategorie',
            [
                new TreeItem(
                    'Buch',
                    $label,
                    false,
                    [
                        new TreeItem('Buch_Beste Bücher', 'Beste Bücher', false, [])
                    ]
                ),
                new TreeItem('FINDOLOGIC', 'FINDOLOGIC', true, [])
            ]
        );
        $response = new Xml21Response($xmlResponse->asXML());
        $responseParser = ResponseParser::getInstance($response);
        $configArray = [
            ['ActivateFindologic', true],
            ['ShopKey', 'ABCDABCDABCDABCDABCDABCDABCDABCD'],
            ['ActivateFindologicForCategoryPages', false],
            ['listingMode', $config]
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

        $criteria->addCondition(new ProductAttributeCondition('cat', '=', 'FINDOLOGIC'));

        $configLoaderMock = $this->getMockBuilder(ConfigLoader::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();
        $hydrator = new CustomListingHydrator($configLoaderMock);

        foreach ($responseParser->getFilters() as $filter) {
            $facetResult = $hydrator->hydrateFacet($filter);
            $criteria->addFacet($facetResult->getFacet());
        }

        $originalService = $this->createMock(OriginalProductNumberSearch::class);

        $request = new RequestHttp();
        $request->setModuleName('frontend');
        $request->setRequestUri('/findologic?q=1');
        $request->setActionName('defaultSearch');
        $request->setControllerName('search');
        Shopware()->Front()->setRequest($request);

        $mockedQuery = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods(['execute'])
            ->getMockForAbstractClass();

        $mockQuerybuilderFactory = $this->createMock(QueryBuilderFactory::class);
        $mockedCache = $this->createMock(Zend_Cache_Core::class);

        if (StaticHelper::isProductAndFilterLiveReloadingEnabled()) {
            $response = Utility::getDemoResponse('demoResponseWithCatFilter.xml');
            $responseParser = ResponseParser::getInstance($response);
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

        $result = $method->invokeArgs(
            $productNumberSearch,
            [
                $criteria,
                $this->context,
                $responseParser->getFilters()
            ]
        );
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
        $response = new Xml21Response($xmlResponse->asXML());
        $responseParser = ResponseParser::getInstance($response);
        foreach ($responseParser->getFilters() as $filter) {
            $facetResult = $hydrator->hydrateFacet($filter);
            $criteria->addFacet($facetResult->getFacet());
        }

        unset($xmlResponse->filters);
        $xmlResponse->addChild('filters');
        $responseWithoutFilters = new Xml21Response($xmlResponse->asXML());
        $responseParserWithoutFilters = ResponseParser::getInstance($responseWithoutFilters);

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

        $result = $method->invokeArgs(
            $productNumberSearch,
            [$criteria, $this->context, $responseParserWithoutFilters->getFilters()]
        );

        $this->assertEmpty($result);
    }

    public function cacheResponseProvider()
    {
        $xmlResponse = Utility::getDemoXML();

        return [
            'Querybuilder is not used as response is provided from cache' => [
                'xmlResponse' => $xmlResponse
            ],
            'Querybuilder is used as response is not provided from cache' => [
                'xmlResponse' => $xmlResponse
            ],
        ];
    }

    /**
     * @dataProvider cacheResponseProvider
     *
     * @param SimpleXMLElement $xmlResponse
     *
     * @throws Enlight_Exception
     * @throws ReflectionException
     */
    public function testCacheResponse($xmlResponse)
    {
        $response = new Xml21Response($xmlResponse->asXML());
        $responseParser = ResponseParser::getInstance($response);

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

        foreach ($responseParser->getFilters() as $filter) {
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

        $result = $method->invokeArgs($productNumberSearch, [$criteria, $this->context, $responseParser->getFilters()]);
        $this->assertNotEmpty($result);
    }
}
