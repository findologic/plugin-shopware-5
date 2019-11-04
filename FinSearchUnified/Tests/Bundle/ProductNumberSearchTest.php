<?php

namespace FinSearchUnified\Tests\Bundle;

use Enlight_Controller_Front as Front;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use Exception;
use FinSearchUnified\Bundle\ProductNumberSearch;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilderFactory;
use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware_Components_Config as Config;
use SimpleXMLElement;

class ProductNumberSearchTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $configArray = [
            ['ActivateFindologic', true],
            ['ShopKey', 'ABCD0815'],
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

        Shopware()->Container()->reset('front');
        Shopware()->Container()->load('front');
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

        $filters = $xmlResponse->addChild('filters');
        $filters->addChild('filter');

        $xml = $xmlResponse->asXML();

        return [
            'Shopware internal search, unrelated to FINDOLOGIC' => [false, true, $xml, 0],
            'Shopware internal search' => [false, false, $xml, 0],
            'Shopware search, unrelated to FINDOLOGIC' => [true, true, $xml, 0],
            'FINDOLOGIC returns invalid response' => [true, false, null, 1],
            'FINDOLOGIC search' => [true, false, $xml, 1]
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

        $originalService = $this->createMock(\Shopware\Bundle\SearchBundleDBAL\ProductNumberSearch::class);

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

    /**
     * @throws Exception
     */
    public function testSearch()
    {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $xmlResponse = new SimpleXMLElement($data);

        $query = $xmlResponse->addChild('query');
        $queryString = $query->addChild('queryString', 'some string');
        $queryString->addAttribute('type', 'corrected');

        $filters = $xmlResponse->addChild('filters');
        $filter = $filters->addChild('filter');

        $category = $filter->addChild('type', 'label');
        $category->addChild('select', 'single');
        $category->addChild('name', 'cat');
        $category->addChild('display', 'Category');
        $citem = $category->addChild('items')->addChild('item');
        $citem->addChild('name', 'food');

        $price = $filter->addChild('type', 'range-slider');
        $price->addChild('select', 'single');
        $price->addChild('name', 'price');
        $price->addChild('display', 'Price');

        $attributes = $price->addChild('attributes');

        $selectedRange = $attributes->addChild('selectedRange');
        $selectedRange->addChild('min', 4.20);
        $selectedRange->addChild('max', 69.00);

        $totalRange = $attributes->addChild('totalRange');
        $totalRange->addChild('min', 4.20);
        $totalRange->addChild('max', 69.00);

        $Vendor = $filter->addChild('type', 'select');
        $Vendor->addChild('select', 'multiple');
        $Vendor->addChild('name', 'vendor');
        $Vendor->addChild('display', 'Brand');
        $ventorItem = $Vendor->addChild('items')->addChild('item');
        $ventorItem->addChild('name', 'manufacture');

        $xml = $xmlResponse->asXML();

        $criteria = new Criteria();
        $criteria->setFetchCount(true);

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

        $hydrator = new CustomListingHydrator();
        $customFacets = [];

        foreach ($xmlResponse->filters->filter as $filter) {
            $facet = $hydrator->hydrateFacet($filter);
            $customFacets[] = $facet->getFacet();
        }

        foreach ($customFacets as $customFacet) {
            $criteria->addFacet($customFacet);
        }

        // Assign mocked variable to application container
        Shopware()->Container()->set('front', $front);

        $context = Shopware()->Container()->get('shopware_storefront.context_service')->getContext();
        $productNumberSearch->search($criteria, $context);
    }
}
