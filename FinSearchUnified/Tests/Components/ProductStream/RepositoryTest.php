<?php

namespace FinSearchUnified\Tests\Components\ProductStream;

use FinSearchUnified\Components\ProductStream\Repository;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Components\Test\Plugin\TestCase;

class RepositoryTest extends TestCase
{
    /**
     * @return array
     */
    public function shopSearchSwitchProvider()
    {
        return [
            'Uses the original implementation' => [
                'ActivateFindologic' => true,
                'ShopKey' => '8D6CA2E49FB7CD09889CC0E2929F86B0',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'prepareCriteria' => true
            ],
            'Uses the original implementation for backend' => [
                'ActivateFindologic' => true,
                'ShopKey' => '8D6CA2E49FB7CD09889CC0E2929F86B0',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'prepareCriteria' => false
            ],
            'Uses the custom implementation' => [
                'ActivateFindologic' => true,
                'ShopKey' => '8D6CA2E49FB7CD09889CC0E2929F86B0',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'prepareCriteria' => false,
            ]
        ];
    }

    /**
     * @dataProvider shopSearchSwitchProvider
     *
     * @param bool $prepareCriteria
     */
    public function testUsesOriginalOrDecoratedImplementation($prepareCriteria)
    {
        $mockedRepository = $this->getMockBuilder('\Shopware\Components\ProductStream\Repository')
            ->setMethods(['prepareCriteria'])
            ->disableOriginalConstructor()
            ->getMock();
        if ($prepareCriteria) {
            $mockedRepository->expects($this->once())
                ->method('prepareCriteria');
        } else {
            $mockedRepository->expects($this->never())
                ->method('prepareCriteria');
        }

        $repository = new Repository($mockedRepository);
        $repository->prepareCriteria(new Criteria(), 1);
    }
}
