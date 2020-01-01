<?php

namespace FinSearchUnified\Tests\Bundle;

use Enlight_Controller_Request_RequestHttp as RequestHttp;
use Enlight_Exception;
use Exception;
use FinSearchUnified\Bundle\ProductNumberSearch;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilderFactory;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\ProductNumberSearch as OriginalProductNumberSearch;
use Shopware_Components_Config;
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
        $mockConfig = $this->getMockBuilder(Shopware_Components_Config::class)
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

        Shopware()->Session()->offsetUnset('findologicDI');
        Shopware()->Session()->offsetUnset('isSearchPage');
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

        Shopware()->Front()->setRequest($request);

        $context = Shopware()->Container()->get('shopware_storefront.context_service')->getContext();
        $productNumberSearch->search($criteria, $context);
    }

    /**
     * @throws Enlight_Exception
     */
    public function testFallBackCookieIsSet()
    {
        $this->markTestSkipped('Skipped due to redirection');
        $isFetchCount = true;
        $isUseShopSearch = false;
        $response = '';
        $invokationCount = 1;

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

        $originalService = $this->createMock(OriginalProductNumberSearch::class);

        $productNumberSearch = new ProductNumberSearch(
            $originalService,
            $mockQuerybuilderFactory
        );

        $request = new RequestHttp();
        $request->setModuleName('frontend');
        $request->setRequestUri('http://google.com');
        Shopware()->Front()->setRequest($request);

        $context = Shopware()->Container()->get('shopware_storefront.context_service')->getContext();
        $result = $productNumberSearch->search($criteria, $context);
        $this->assertEmpty($result);
        $this->assertArrayHasKey('fallback-search', $_COOKIE);
    }
}
