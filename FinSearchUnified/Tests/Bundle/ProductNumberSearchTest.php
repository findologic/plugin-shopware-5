<?php

namespace FinSearchUnified\Tests\Bundle;

use Enlight_Controller_Front as Front;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use FinSearchUnified\Bundle\ProductNumberSearch;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilderFactory;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Components\Test\Plugin\TestCase;
use SimpleXMLElement;

class ProductNumberSearchTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'FinSearchUnified' => [
            'ShopKey' => '0000000000000000ZZZZZZZZZZZZZZZZ',
            'ActivateFindologic' => true
        ],
    ];

    protected function tearDown()
    {
        parent::tearDown();
        Shopware()->Container()->reset('front');
        Shopware()->Container()->load('front');
        Shopware()->Session()->offsetUnset('findologicDI');
        Shopware()->Session()->offsetUnset('isSearchPage');
    }

    /**
     * @dataProvider productNumberSearchProvider
     *
     * @param bool $isFetchCount
     * @param bool $isUseShopSearch
     * @param string|null $response
     * @param int $invokationCount
     *
     * @throws \Exception
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

        if ($response === 'xml') {
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
        } else {
            $xml = $response;
        }

        $mockedQuery->expects($this->exactly($invokationCount))->method('execute')->willReturn($xml);

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

    public function productNumberSearchProvider()
    {
        return [
            'Internal search is performed and findologic is not active' => [false, true, 'alive', 0],
            'Internal search is performed and findologic is active' => [false, false, 'alive', 0],
            'Explicit search is performed and findologic is not active' => [true, true, 'alive', 0],
            'Explicit search is performed and findologic is active but response is null' => [true, false, null, 1],
            'Explicit search is performed and findologic is active with valid XML' => [true, false, 'xml', 1]
        ];
    }
}
