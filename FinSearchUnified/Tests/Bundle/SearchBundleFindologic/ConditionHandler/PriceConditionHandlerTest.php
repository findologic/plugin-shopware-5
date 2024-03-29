<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Enlight_Controller_Request_RequestHttp;
use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\PriceConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\SearchQueryBuilder;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Condition\PriceCondition;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use Shopware_Components_Config as Config;

class PriceConditionHandlerTest extends TestCase
{
    /**
     * @var QueryBuilder
     */
    private $querybuilder;

    /**
     * @var ProductContextInterface
     */
    private $context;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $request = new Enlight_Controller_Request_RequestHttp();
        Shopware()->Front()->setRequest($request);

        // By default, the search page is true
        Shopware()->Session()->offsetSet('isSearchPage', true);
        $mockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getByNamespace', 'get'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig->expects($this->once())
            ->method('getByNamespace')
            ->with('FinSearchUnified', 'ShopKey', null)
            ->willReturn('ABCDABCDABCDABCDABCDABCDABCDABCD');
        Shopware()->Container()->set('config', $mockConfig);

        $this->querybuilder = new SearchQueryBuilder(
            Shopware()->Container()->get('shopware_plugininstaller.plugin_manager'),
            Shopware()->Config()
        );
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $this->context = $contextService->getShopContext();
    }

    public function priceDataProvider()
    {
        return [
            'Min price is 12, max price is 0' => [
                ['min' => 12, 'max' => 0],
                ['min' => 12, 'max' => PHP_INT_MAX]
            ],
            'Min price is 12.69, max price is 42' => [
                ['min' => 12.69, 'max' => 42],
                ['min' => 12.69, 'max' => 42]
            ],
        ];
    }

    /**
     * @dataProvider priceDataProvider
     *
     * @param array $prices
     * @param array $expectedPrices
     *
     * @throws Exception
     */
    public function testGenerateCondition(array $prices, array $expectedPrices)
    {
        $handler = new PriceConditionHandler();
        $handler->generateCondition(
            new PriceCondition($prices['min'], $prices['max']),
            $this->querybuilder,
            $this->context
        );

        $params = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('attrib', $params, 'Parameter "attrib" was not found in the parameters');
        $this->assertArrayHasKey('price', $params['attrib'], 'Prices are not set in the "attrib" parameter');
        $this->assertEquals($expectedPrices, $params['attrib']['price'], 'Expeced prices to be parsed correctly');
    }
}
