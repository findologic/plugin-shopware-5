<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Enlight_Controller_Request_RequestHttp;
use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\PriceConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NewQueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\SearchQueryBuilder;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Condition\PriceCondition;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;

class PriceConditionHandlerTest extends TestCase
{
    /**
     * @var NewQueryBuilder
     */
    private $querybuilder;

    /**
     * @var ProductContextInterface
     */
    private $context;

    /**
     * @throws Exception
     */
    protected function setUp()
    {
        parent::setUp();

        $_SERVER['REMOTE_ADDR'] = '192.168.0.1';

        $request = new Enlight_Controller_Request_RequestHttp();
        Shopware()->Front()->setRequest($request);

        // By default, the search page is true
        Shopware()->Session()->offsetSet('isSearchPage', true);
        Shopware()->Config()->ShopKey = 'ABCDABCDABCDABCDABCDABCDABCDABCD';

        $this->querybuilder = new QueryBuilder\NewSearchQueryBuilder(
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
