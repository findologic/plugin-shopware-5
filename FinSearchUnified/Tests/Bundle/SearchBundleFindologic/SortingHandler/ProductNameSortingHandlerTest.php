<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\SortingHandler;

use Enlight_Controller_Request_RequestHttp;
use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NewQueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NewSearchQueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\ProductNameSortingHandler;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Sorting\ProductNameSorting;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;

class ProductNameSortingHandlerTest extends TestCase
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

        $request = new Enlight_Controller_Request_RequestHttp();
        Shopware()->Front()->setRequest($request);

        // By default, the search page is true
        Shopware()->Session()->offsetSet('isSearchPage', true);
        Shopware()->Config()->ShopKey = 'ABCDABCDABCDABCDABCDABCDABCDABCD';

        $this->querybuilder = new NewSearchQueryBuilder(
            Shopware()->Container()->get('shopware_plugininstaller.plugin_manager'),
            Shopware()->Config()
        );
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $this->context = $contextService->getShopContext();
    }

    public function orderByDataProvider()
    {
        return [
            'Direction is ASC' => [SortingInterface::SORT_ASC, 'label ASC'],
            'Direction is DESC' => [SortingInterface::SORT_DESC, 'label DESC']
        ];
    }

    /**
     * @dataProvider orderByDataProvider
     *
     * @param string $direction
     * @param string $expectedOrder
     */
    public function testGenerateSorting($direction, $expectedOrder)
    {
        $handler = new ProductNameSortingHandler();
        $handler->generateSorting(
            new ProductNameSorting($direction),
            $this->querybuilder,
            $this->context
        );

        $parameters = $this->querybuilder->getParameters();

        $this->assertArrayHasKey('order', $parameters, 'Product Name Sorting was not applied');
        $this->assertSame(
            $expectedOrder,
            $parameters['order'],
            sprintf(
                'Expected sorting to be %s',
                $expectedOrder
            )
        );
    }
}
