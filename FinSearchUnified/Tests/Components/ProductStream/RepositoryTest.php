<?php

namespace FinSearchUnified\Tests\Components\ProductStream;

use Enlight_Components_Session_Namespace;
use Enlight_Controller_Request_RequestHttp;
use Enlight_Exception;
use FinSearchUnified\Components\ProductStream\Repository;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware_Components_Config;

class RepositoryTest extends TestCase
{
    protected function tearDown()
    {
        parent::tearDown();

        Shopware()->Container()->reset('config');
        Shopware()->Container()->load('config');
        Shopware()->Container()->reset('session');
        Shopware()->Container()->load('session');
    }

    /**
     * @return array
     */
    public function shopSearchSwitchProvider()
    {
        return [
            'Uses the original implementation' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'ActivateFindologicForCategoryPages' => false,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'prepareCriteria' => true
            ],
            'Uses the original implementation for backend' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'ActivateFindologicForCategoryPages' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'prepareCriteria' => false
            ],
            'Uses the custom implementation' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'ActivateFindologicForCategoryPages' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'prepareCriteria' => false,
            ]
        ];
    }

    /**
     * @dataProvider shopSearchSwitchProvider
     *
     * @param bool $isActive
     * @param string $shopKey
     * @param bool $isActiveForCategory
     * @param bool $isSearchPage
     * @param bool $isCategoryPage
     * @param bool $prepareCriteria
     *
     * @throws Enlight_Exception
     */
    public function testUsesOriginalOrDecoratedImplementation(
        $isActive,
        $shopKey,
        $isActiveForCategory,
        $isSearchPage,
        $isCategoryPage,
        $prepareCriteria
    ) {
        $request = new Enlight_Controller_Request_RequestHttp();
        Shopware()->Front()->setRequest($request);

        $configArray = [
            ['ActivateFindologic', $isActive],
            ['ShopKey', $shopKey],
            ['ActivateFindologicForCategoryPages', $isActiveForCategory]
        ];

        // Create mock object for Shopware Config and explicitly return the values
        $config = $this->getMockBuilder(Shopware_Components_Config::class)
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->method('offsetGet')
            ->willReturnMap($configArray);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        $sessionArray = [
            ['isSearchPage', $isSearchPage],
            ['isCategoryPage', $isCategoryPage],
            ['findologicDI', false]
        ];

        // Create mock object for Shopware Session and explicitly return the values
        $session = $this->getMockBuilder(Enlight_Components_Session_Namespace::class)
            ->setMethods(['offsetGet', 'offsetExists'])
            ->getMock();
        $session->method('offsetGet')->willReturnMap($sessionArray);
        $session->method('offsetExists')->with('findologicDI')->willReturn(true);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('session', $session);

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
