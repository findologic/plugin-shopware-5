<?php

namespace FinSearchUnified\tests\Components\ProductStream;

use FinSearchUnified\Constants;
use Shopware\Components\Test\Plugin\TestCase;

class CriteriaFactoryTest extends TestCase
{
    /**
     * Provider for test cases to test CriteriaFactory
     *
     * @return array
     */
    public function providers()
    {
        return [
            "useShopSearch returns true" => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'module' => null,
                'expected' => Shopware()->Shop()->getCategory()->getId()
            ],
            "useShopSearch returns false but module is backend" => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'module' => 'backend',
                'expected' => Shopware()->Shop()->getCategory()->getId()
            ],
            "useShopSearch is false and module is not backend" => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'module' => null,
                'expected' => -1
            ]
        ];
    }

    /**
     * @dataProvider providers
     * @param bool $isActive
     * @param string $shopKey
     * @param bool $isActiveForCategory
     * @param bool $checkIntegration
     * @param bool $isSearchPage
     * @param bool $isCategoryPage
     * @param int|null $module
     * @param int $expected
     * @throws \Enlight_Exception
     */
    public function testCreateCriteria(
        $isActive,
        $shopKey,
        $isActiveForCategory,
        $checkIntegration,
        $isSearchPage,
        $isCategoryPage,
        $module,
        $expected
    ) {
        /** @var \FinSearchUnified\Components\ProductStream\CriteriaFactory $factory */
        $factory = Shopware()->Container()->get('fin_search_unified.product_stream.criteria_factory');

        /** @var \Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        // Create a Request object and set the parameters accordingly and then assign it to the Application Container
        $request = new \Enlight_Controller_Request_RequestHttp();
        $request->setModuleName($module);
        $request->setParam('sCategory', $expected);
        Shopware()->Front()->setRequest($request);

        $configArray = [
            ['ActivateFindologic', $isActive],
            ['ShopKey', $shopKey],
            ['ActivateFindologicForCategoryPages', $isActiveForCategory],
            ['IntegrationType', $checkIntegration ? Constants::INTEGRATION_TYPE_DI : Constants::INTEGRATION_TYPE_API]
        ];

        // Create mock object for Shopware Config and explicitly return the values
        $config = $this->getMockBuilder('\Shopware_Components_Config')
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
            ['findologicDI', $checkIntegration]
        ];

        // Create mock object for Shopware Session and explicitly return the values
        $session = $this->getMockBuilder('\Enlight_Components_Session_Namespace')
            ->setMethods(['offsetGet'])
            ->getMock();
        $session->method('offsetGet')
            ->willReturnMap($sessionArray);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('session', $session);

        /** @var \Shopware\Bundle\SearchBundle\Criteria $criteria */
        $criteria = $factory->createCriteria($request, $context);

        /** @var \Shopware\Bundle\SearchBundle\Condition\CategoryCondition $baseCondition */
        $baseCondition = $criteria->getCondition('category');

        $this->assertNotNull($baseCondition, "Category Condition expected to be NOT NULL, but NULL was returned");
        $categories = $baseCondition->getCategoryIds();
        $this->assertSame($expected, $categories[0]);
    }
}