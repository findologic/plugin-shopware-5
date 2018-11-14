<?php

namespace FinSearchUnified\tests\Helper;

use FinSearchUnified\Constants;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Components\Test\Plugin\TestCase;

class StaticHelperTest extends TestCase
{
    /**
     * Data provider for test cases
     *
     * @return array
     */
    public static function configDataProvider()
    {
        return [
            'FINDOLOGIC is inactive' => [
                'ActivateFindologic' => false,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'expected' => true
            ],
            'ShopKey is empty' => [
                'ActivateFindologic' => true,
                'ShopKey' => '',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'expected' => true
            ],
            'ShopKey is \'Findologic ShopKey\'' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'Findologic ShopKey',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'expected' => true
            ],
            'FINDOLOGIC is Active and CheckDirectIntegration is true' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => true,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'expected' => true
            ],
            'FINDOLOGIC is Active and CheckDirectIntegration is false; isCategoryPage and isSearchPage is false' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => false,
                'expected' => true
            ],
            'FINDOLOGIC is Active and CheckDirectIntegration is false; isCategoryPage is true and isSearchPage is false and ActivateFindologicForCategoryPages is false' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'expected' => true
            ],
            'FINDOLOGIC is Active and CheckDirectIntegration is false; IsCategoryPage is false and IsSearchPage is true' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'expected' => false
            ],
            'FINDOLOGIC is Active and CheckDirectIntegration is false; isCategoryPage is true and isSearchPage is false and ActivateFindologicForCategoryPages is true' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'expected' => false
            ]
        ];
    }

    /**
     * Method for testing useShopSearch method in StaticHelper class
     *
     * @dataProvider configDataProvider
     * @param bool $isActive
     * @param string $shopKey
     * @param bool $isActiveForCategory
     * @param bool $checkIntegration
     * @param bool $isSearchPage
     * @param bool $isCategoryPage
     * @param bool $expectedResult
     */
    function testUseShopSearch(
        $isActive,
        $shopKey,
        $isActiveForCategory,
        $checkIntegration,
        $isSearchPage,
        $isCategoryPage,
        $expectedResult
    ) {
        $configArray = [
            'ActivateFindologic' => $isActive,
            'ShopKey' => $shopKey,
            'ActivateFindologicForCategoryPages' => $isActiveForCategory,
            'IntegrationType' => $checkIntegration ? Constants::INTEGRATION_TYPE_DI : Constants::INTEGRATION_TYPE_API
        ];

        $sessionArray = [
            'isSearchPage' => $isSearchPage,
            'isCategoryPage' => $isCategoryPage,
            'findologicDI' => $checkIntegration
        ];

        // Create Mock object for Shopware Session
        Shopware()->Container()->set('session', $this->getMockBuilder('\Enlight_Components_Session_Namespace')
            ->setMethods(null)
            ->getMock());

        // Create Mock object for Shopware Config
        Shopware()->Container()->set('config', $this->getMockBuilder('\Shopware_Components_Config')
            ->setMethods(null)
            ->disableOriginalConstructor()
            ->getMock());

        if ($isSearchPage !== null) {
            $this->saveSession($sessionArray);
        }
        $this->saveConfig($configArray);

        $result = StaticHelper::useShopSearch();
        if ($expectedResult === true) {
            $this->assertTrue($result, "useShopSearch expected to return true, but false was returned");
        } else {
            $this->assertFalse($result, "useShopSearch expected to return false, but true was returned");
        }
    }

    /**
     * Helper method to save values in Shopware Session
     *
     * @param $values
     */
    private function saveSession($values)
    {
        foreach ($values as $key => $value) {
            Shopware()->Session()->offsetSet($key, $value);
        }
    }

    /**
     * Helper method to save configurations in Shopware Config
     *
     * @param $configs
     */
    private function saveConfig($configs)
    {
        foreach ($configs as $key => $value) {
            Shopware()->Config()->offsetSet($key, $value);
        }
    }
}