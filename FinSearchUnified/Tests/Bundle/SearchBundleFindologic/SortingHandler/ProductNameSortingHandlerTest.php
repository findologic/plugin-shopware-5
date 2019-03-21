<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\SortingHandler;

use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\ProductNameSortingHandler;
use Shopware\Bundle\SearchBundle\Sorting\ProductNameSorting;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use Shopware\Components\Test\Plugin\TestCase;

class ProductNameSortingHandlerTest extends TestCase
{
    /**
     * @var QueryBuilder
     */
    private $querybuilder;

    /**
     * @var ProductContextInterface
     */
    private $context = null;

    /**
     * @throws \Exception
     */
    protected function setUp()
    {
        parent::setUp();

        $this->querybuilder = new QueryBuilder(
            Shopware()->Container()->get('http_client'),
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
        $this->assertSame($expectedOrder, $parameters['order'], sprintf(
            'Expected sorting to be %s',
            $expectedOrder
        ));
    }
}
