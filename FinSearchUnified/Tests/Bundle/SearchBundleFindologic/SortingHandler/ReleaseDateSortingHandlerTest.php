<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\SortingHandler;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\SearchQueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\ReleaseDateSortingHandler;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Sorting\ReleaseDateSorting;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;

class ReleaseDateSortingHandlerTest extends TestCase
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
     * @throws Exception
     */
    protected function setUp()
    {
        parent::setUp();

        $this->querybuilder = new SearchQueryBuilder(
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
            'Direction is ASC' => [SortingInterface::SORT_ASC, 'dateadded ASC'],
            'Direction is DESC' => [SortingInterface::SORT_DESC, 'dateadded DESC']
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
        $handler = new ReleaseDateSortingHandler();
        $handler->generateSorting(
            new ReleaseDateSorting($direction),
            $this->querybuilder,
            $this->context
        );

        $parameters = $this->querybuilder->getParameters();

        $this->assertArrayHasKey('order', $parameters, 'Release Date Sorting was not applied');
        $this->assertSame($expectedOrder, $parameters['order'], sprintf(
            'Expected sorting to be %s',
            $expectedOrder
        ));
    }
}
