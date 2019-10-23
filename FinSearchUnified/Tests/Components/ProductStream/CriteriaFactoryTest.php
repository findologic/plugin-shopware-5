<?php

namespace FinSearchUnified\Tests\Components\ProductStream;

use Enlight_Components_Session_Namespace;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use Enlight_Exception;
use FinSearchUnified\Components\ProductStream\CriteriaFactory;
use FinSearchUnified\Constants;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware_Components_Config;

class CriteriaFactoryTest extends TestCase
{
    protected function tearDown()
    {
        parent::tearDown();

        Shopware()->Container()->reset('session');
        Shopware()->Container()->load('session');
        Shopware()->Container()->reset('config');
        Shopware()->Container()->load('config');
    }

    /**
     * Provider for test cases to test CriteriaFactory
     *
     * @return array
     */
    public function shopSearchSwitchProvider()
    {
        return [
            'Uses the original implementation' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'expected' => Shopware()->Shop()->getCategory()->getId()
            ],
            'Uses the original implementation for backend' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'expected' => Shopware()->Shop()->getCategory()->getId()
            ],
            'Uses the custom implementation' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'expected' => -1
            ]
        ];
    }

    /**
     * @dataProvider shopSearchSwitchProvider
     *
     * @param bool $isActive
     * @param string $shopKey
     * @param bool $isActiveForCategory
     * @param bool $checkIntegration
     * @param bool $isSearchPage
     * @param bool $isCategoryPage
     * @param int $expected
     *
     * @throws Enlight_Exception
     */
    public function testCreateCriteria(
        $isActive,
        $shopKey,
        $isActiveForCategory,
        $checkIntegration,
        $isSearchPage,
        $isCategoryPage,
        $expected
    ) {
        $factory = new CriteriaFactory(
            Shopware()->Container()->get('shopware_search.store_front_criteria_factory'),
            Shopware()->Container()->get('shopware_product_stream.criteria_factory')
        );

        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        // Create a Request object and set the parameters accordingly and then assign it to the Application Container
        $request = new RequestHttp();
        $request->setParam('sCategory', $expected);
        Shopware()->Front()->setRequest($request);

        $configArray = [
            ['ActivateFindologic', $isActive],
            ['ShopKey', $shopKey],
            ['ActivateFindologicForCategoryPages', $isActiveForCategory],
            ['IntegrationType', $checkIntegration ? Constants::INTEGRATION_TYPE_DI : Constants::INTEGRATION_TYPE_API]
        ];

        // Create mock object for Shopware Config and explicitly return the values
        $config = $this->getMockBuilder(Shopware_Components_Config::class)
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($configArray);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        $sessionArray = [
            ['isSearchPage', $isSearchPage],
            ['isCategoryPage', $isCategoryPage],
            ['findologicDI', $checkIntegration]
        ];

        // Create mock object for Shopware Session and explicitly return the values
        $session = $this->getMockBuilder(Enlight_Components_Session_Namespace::class)
            ->setMethods(['offsetGet', 'offsetExists'])
            ->getMock();
        $session->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($sessionArray);
        $session->expects($this->any())
            ->method('offsetExists')
            ->willReturn(true);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('session', $session);

        /** @var Criteria $criteria */
        $criteria = $factory->createCriteria($request, $context);

        /** @var CategoryCondition $categoryCondition */
        $categoryCondition = $criteria->getCondition('category');

        $this->assertNotNull(
            $categoryCondition,
            "Category Condition expected to be NOT NULL, but NULL was returned"
        );
        $categories = $categoryCondition->getCategoryIds();
        $this->assertSame($expected, $categories[0]);
    }
}
