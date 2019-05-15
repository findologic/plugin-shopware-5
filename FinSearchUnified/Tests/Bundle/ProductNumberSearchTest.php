<?php

namespace FinSearchUnified\Tests\Bundle;

use FinSearchUnified\Bundle\ProductNumberSearch;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilderFactory;
use PHPUnit\Framework\MockObject\Matcher\InvokedCount;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Components\Test\Plugin\TestCase;

class ProductNumberSearchTest extends TestCase
{
    protected function tearDown()
    {
        parent::tearDown();
        Shopware()->Session()->offsetUnset('findologicDI');
    }

    /**
     * @dataProvider productNumberSearchProvider
     *
     * @param bool $isFetchCount
     * @param bool $isUseShopSearch
     * @param string|null $response
     * @param InvokedCount $invokationCount
     *
     * @throws \Exception
     */
    public function testProductNumberSearchImplementation($isFetchCount, $isUseShopSearch, $response, $invokationCount)
    {
        $criteria = new Criteria();
        $criteria->setFetchCount($isFetchCount);

        Shopware()->Session()->offsetSet('findologicDI', $isUseShopSearch);

        $mockedQuery = $this->createMock(QueryBuilder::class);
        $mockedQuery->expects($invokationCount)->method('execute')->willReturn($response);

        // Mock querybuilder factory method to check that custom implementation does not get called
        // as original implementation will be called in this case
        $mockQuerybuilderFactory = $this->createMock(QueryBuilderFactory::class);
        $mockQuerybuilderFactory->expects($invokationCount)->method('createProductQuery')->willReturn($mockedQuery);

        $originalService = $this->createMock(\Shopware\Bundle\SearchBundleDBAL\ProductNumberSearch::class);

        $productNumberSearch = new ProductNumberSearch(
            $originalService,
            $mockQuerybuilderFactory
        );

        $productNumberSearch->search($criteria,
            Shopware()->Container()->get('shopware_storefront.context_service')->getContext());
    }

    public function productNumberSearchProvider()
    {
        return [
            'Internal search is performed and findologic is not active' => [false, true, 'alive', $this->never()],
            'Internal search is performed and findologic is active' => [false, false, 'alive', $this->never()],
            'Explicit search is performed and findologic is not active' => [true, true, 'alive', $this->never()],
            'Explicit search is performed and findologic is active but response is null' => [
                true,
                false,
                null,
                $this->never()
            ],
            'Explicit search is performed and findologic is active' => [true, false, 'alive', $this->once()],

            'Explicit search is performed and findologic is active with valid response' => [
                true,
                false,
                null,
                $this->once()
            ],
        ];
    }
}
