<?php

namespace FinSearchUnified\Tests\Bundle;

use Enlight_Controller_Request_RequestHttp as RequestHttp;
use Enlight_Exception;
use Exception;
use FinSearchUnified\Bundle\ProductNumberSearch;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilderFactory;
use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use FinSearchUnified\Tests\TestCase;
use ReflectionException;
use ReflectionObject;
use Shopware\Bundle\SearchBundle\Condition\PriceCondition;
use Shopware\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\ProductNumberSearch as OriginalProductNumberSearch;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\TreeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\TreeItem;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListItem;
use Shopware\Bundle\SearchBundle\FacetResultInterface;
use Shopware_Components_Config as Config;
use SimpleXMLElement;

class ProductNumberSearchTest extends TestCase
{
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
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig->method('offsetGet')
            ->willReturnMap($configArray);

        Shopware()->Container()->set('config', $mockConfig);
    }

    protected function tearDown()
    {
        parent::tearDown();

        Shopware()->Container()->reset('config');
        Shopware()->Container()->load('config');
    }

    public function productNumberSearchProvider()
    {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $xmlResponse = new SimpleXMLElement($data);

        $query = $xmlResponse->addChild('query');
        $query->addChild('queryString', 'queryString');

        $results = $xmlResponse->addChild('results');
        $results->addChild('count', 5);
        $products = $xmlResponse->addChild('products');

        for ($i = 1; $i <= 5; $i++) {
            $product = $products->addChild('product');
            $product->addAttribute('id', $i);
        }

        $xml = $xmlResponse->asXML();

        return [
            'Shopware internal search, unrelated to FINDOLOGIC' => [
                'isFetchCount' => false,
                'isUseShopSearch' => true,
                'response' => $xml,
                'invokationCount' => 0
            ],
            'Shopware internal search' => [
                'isFetchCount' => false,
                'isUseShopSearch' => false,
                'response' => $xml,
                'invokationCount' => 0
            ],
            'Shopware search, unrelated to FINDOLOGIC' => [
                'isFetchCount' => true,
                'isUseShopSearch' => true,
                'response' => $xml,
                'invokationCount' => 0
            ],
            'FINDOLOGIC search' => [
                'isFetchCount' => true,
                'isUseShopSearch' => false,
                'response' => $xml,
                'invokationCount' => 1
            ]
        ];
    }

    /**
     * @dataProvider productNumberSearchProvider
     *
     * @param bool $isFetchCount
     * @param bool $isUseShopSearch
     * @param string|null $response
     * @param int $invokationCount
     *
     * @throws Exception
     */
    public function testProductNumberSearchImplementation($isFetchCount, $isUseShopSearch, $response, $invokationCount)
    {
        $criteria = new Criteria();
        if (!method_exists($criteria, 'setFetchCount')) {
            $this->markTestSkipped('Ignoring this test for Shopware 5.2.x');
        }
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

        $originalService = $this->createMock(OriginalProductNumberSearch::class);

        $productNumberSearch = new ProductNumberSearch(
            $originalService,
            $mockQuerybuilderFactory
        );

        $request = new RequestHttp();
        $request->setModuleName('frontend');

        // Create Mock object for Shopware Front Request
        $front = $this->getMockBuilder(Front::class)
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $front->expects($this->any())
            ->method('Request')
            ->willReturn($request);

        // Assign mocked variable to application container
        Shopware()->Container()->set('front', $front);

        $context = Shopware()->Container()->get('shopware_storefront.context_service')->getContext();
        $productNumberSearch->search($criteria, $context);
    }

    public function facetWithPriceFilterProvider()
    {
        $xmlResponse = $this->getXmlResponse();
        $filters = $xmlResponse->addChild('filters');

        $this->setPriceFilter($filters);

        return [
            'Price filters which are selected only contain the value that was selected' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new RangeFacetResult(
                    'price',
                    true,
                    'Price',
                    66.20,
                    99.0,
                    66.20,
                    99.0,
                    'min',
                    'max'
                ),
                'condition' => new PriceCondition(66.20, 99.00)

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
        if (!method_exists($criteria, 'setFetchCount')) {
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

        $originalService = $this->createMock(ProductNumberSearch::class);
        $productNumberSearch = new ProductNumberSearch($originalService, $mockQuerybuilderFactory);

        $request = new RequestHttp();
        $request->setModuleName('frontend');

        // Create Mock object for Shopware Front Request
        $front = $this->getMockBuilder(Front::class)
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $front->method('Request')->willReturn($request);

        $hydrator = new CustomListingHydrator();

        foreach ($xmlResponse->filters->filter as $filter) {
            $facet = $hydrator->hydrateFacet($filter);
            $criteria->addFacet($facet->getFacet());
        }

        // Assign mocked variable to application container
        Shopware()->Container()->set('front', $front);

        $context = Shopware()->Container()->get('shopware_storefront.context_service')->getContext();
        $searchResult = $productNumberSearch->search($criteria, $context);
        $resultFacets = $searchResult->getFacets();

        foreach ($resultFacets as $key => $resultFacet) {
            $this->assertInstanceOf($expectedResults[$key], $resultFacet);
        }
    }

    public function allFiltersProvider()
    {
        $xmlResponse = $this->getXmlResponse();
        $filters = $xmlResponse->addChild('filters');

        $this->setPriceFilter($filters);
        $this->setCategoryFilter($filters);
        $this->setVendorFilter($filters);

        return [
            'Parse all filters' => [
                'xmlResponse' => $xmlResponse,
                'expectedResults' => [
                    RangeFacetResult::class,
                    TreeFacetResult::class,
                    ValueListFacetResult::class
                ]
            ]
        ];
    }

    public function facetWithNoHandlerProvider()
    {
        $xmlResponse = $this->getXmlResponse();
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
        $xmlResponse = $this->getXmlResponse();
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
        $xmlResponse = $this->getXmlResponse();
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

    public function facetWithVendorFilterProvider()
    {
        $xmlResponse = $this->getXmlResponse();
        $filters = $xmlResponse->addChild('filters');

        $this->setVendorFilter($filters);

        return [
            'Vendor filters which are selected only contain the value that was selected' => [
                'xmlResponse' => $xmlResponse,
                'expectedResult' => new ValueListFacetResult(
                    'product_attribute_vendor',
                    true,
                    'Brand',
                    [
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
        $xmlResponse = $this->getXmlResponse();
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
     */
    public function testCreateFacets(
        SimpleXMLElement $xmlResponse,
        FacetResultInterface $expectedResult,
        ConditionInterface $condition = null
    ) {
        $criteria = new Criteria();
        $criteria->setFetchCount(true);
        if ($condition) {
            $criteria->addCondition($condition);
        }

        $hydrator = new CustomListingHydrator();

        foreach ($xmlResponse->filters->filter as $filter) {
            $facetResult = $hydrator->hydrateFacet($filter);
            $criteria->addFacet($facetResult->getFacet());
        }

        $productNumberSearch = Shopware()->Container()->get('fin_search_unified.product_number_search');
        $reflector = new ReflectionObject($productNumberSearch);
        $method = $reflector->getMethod('createFacets');
        $method->setAccessible(true);

        if ($condition) {
            // No filters are selected in the XML response
            $filters = $this->getXmlResponse()->addChild('filters');
        } else {
            // Filters are present in the XML response
            $filters = $xmlResponse->filters->filter;
        }

        $result = $method->invokeArgs($productNumberSearch, [$criteria, $filters]);

        $this->assertNotEmpty($result);

        $facetResult = current($result);

        $this->assertEquals($expectedResult, $facetResult);
    }

    /**
     * @return SimpleXMLElement
     */
    private function getXmlResponse()
    {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $xmlResponse = new SimpleXMLElement($data);

        $query = $xmlResponse->addChild('query');
        $queryString = $query->addChild('queryString', 'queryString');
        $queryString->addAttribute('type', 'corrected');

        $results = $xmlResponse->addChild('results');
        $results->addChild('count', 5);
        $products = $xmlResponse->addChild('products');

        for ($i = 1; $i <= 5; $i++) {
            $product = $products->addChild('product');
            $product->addAttribute('id', $i);
        }

        return $xmlResponse;
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
}
