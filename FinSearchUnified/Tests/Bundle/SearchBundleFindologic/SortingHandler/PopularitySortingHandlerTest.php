<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\SortingHandler;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\SearchQueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\PopularitySortingHandler;
use Shopware\Bundle\SearchBundle\Sorting\PopularitySorting;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use FinSearchUnified\Tests\TestCase;

class PopularitySortingHandlerTest extends TestCase
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
    protected function setUp():void
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
            'Direction is ASC' => [SortingInterface::SORT_ASC, 'salesfrequency ASC'],
            'Direction is DESC' => [SortingInterface::SORT_DESC, 'salesfrequency DESC']
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
        $handler = new PopularitySortingHandler();
        $handler->generateSorting(
            new PopularitySorting($direction),
            $this->querybuilder,
            $this->context
        );

        $parameters = $this->querybuilder->getParameters();

        $this->assertArrayHasKey('order', $parameters, 'Popularity Sorting was not applied');
        $this->assertSame($expectedOrder, $parameters['order'], sprintf(
            'Expected sorting to be %s',
            $expectedOrder
        ));
    }
}
