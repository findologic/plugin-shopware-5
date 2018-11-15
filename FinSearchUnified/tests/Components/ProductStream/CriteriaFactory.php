<?php

namespace FinSearchUnified\tests\Components\ProductStream;

use FinSearchUnified\Constants;
use Shopware\Models\ProductStream\ProductStream;

class CriteriaFactory extends \Shopware\Components\Test\Plugin\TestCase
{
    /**
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public static function setUpBeforeClass()
    {
        $productStream = new ProductStream();
        $productStream->setType(2);
        $productStream->setName('Test Stream');
        Shopware()->Models()->persist($productStream);
        Shopware()->Shop()->getCategory()->setStream($productStream);
        Shopware()->Models()->flush();
    }

    /**
     * @throws \Zend_Db_Adapter_Exception
     */
    public static function tearDownAfterClass()
    {
        $sql = "SET foreign_key_checks = 0; TRUNCATE `s_product_streams`;";
        Shopware()->Shop()->getCategory()->setStream(null);
        Shopware()->Db()->exec($sql);
    }

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

        $request = new \Enlight_Controller_Request_RequestHttp();
        $request->setModuleName($module);
        Shopware()->Front()->setRequest($request);

        $configArray = [
            ['ActivateFindologic', $isActive],
            ['ShopKey', $shopKey],
            ['ActivateFindologicForCategoryPages', $isActiveForCategory],
            [
                'IntegrationType',
                $checkIntegration ? Constants::INTEGRATION_TYPE_DI : Constants::INTEGRATION_TYPE_API
            ]
        ];

        // Create Mock object for Shopware Config
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
        // Create Mock object for Shopware Session
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
        $baseCondition = $criteria->getBaseCondition('category');

        $this->assertNotNull($baseCondition, "Base condition expected to be NOT NULL, but NULL was returned");

        $categories = $baseCondition->getCategoryIds();
        $this->assertSame($expected, $categories[0]);
    }
}