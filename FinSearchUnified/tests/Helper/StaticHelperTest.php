<?php

namespace FinSearchUnified\tests\Helper;

use FinSearchUnified\Constants;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\Test\Plugin\TestCase;

class StaticHelperTest extends TestCase
{
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
            'FINDOLOGIC is Active and CheckDirectIntegration is false; isCategoryPage is true and isSearchPage is false and Activate for Category is false' => [
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
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'expected' => false
            ],
            'FINDOLOGIC is Active and CheckDirectIntegration is false; isCategoryPage is true and isSearchPage is false and Active for Category Page is true' => [
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
     * @dataProvider configDataProvider
     * @param bool $isActive
     * @param string $shopKey
     * @param bool $isActiveForCategory
     * @param bool $checkIntegration
     * @param bool $isSearchPage
     * @param bool $isCategoryPage
     * @param bool $expected
     */
    function testUseShopSearch(
        $isActive,
        $shopKey,
        $isActiveForCategory,
        $checkIntegration,
        $isSearchPage,
        $isCategoryPage,
        $expected
    ) {
        $this->saveConfig('ActivateFindologic', $isActive);
        $this->saveConfig('ShopKey', $shopKey);
        $this->saveConfig('ActivateFindologicForCategoryPages', $isActiveForCategory);
        $this->saveConfig('IntegrationType',
            $checkIntegration ? Constants::INTEGRATION_TYPE_DI : Constants::INTEGRATION_TYPE_API);

        if ($isSearchPage !== null) {
            $map = [
                ['isSearchPage', $isSearchPage],
                ['isCategoryPage', $isCategoryPage],
                ['findologicDI', $checkIntegration]
            ];
            Shopware()->Session = $this->createMock('\Enlight_Components_Session_Namespace')
                ->method('offsetGet')
                ->willReturnMap($map);

//            Shopware()->Session()->offsetSet('isSearchPage', $isSearchPage);
//            Shopware()->Session()->offsetSet('isCategoryPage', $isCategoryPage);
//            Shopware()->Session()->offsetSet('findologicDI', $checkIntegration);
        }

        $result = StaticHelper::useShopSearch();
        if ($expected === true) {
            $this->assertTrue($result, "useShopSearch expected to return true, but false was returned");
        } else {
            $this->assertFalse($result, "useShopSearch expected to return false, but true was returned");
        }
    }

    private function saveConfig($key, $value)
    {
        /** @var InstallerService $pluginManager */
        $pluginManager = Shopware()->Container()->get('shopware_plugininstaller.plugin_manager');
        $plugin = $pluginManager->getPluginByName('FinSearchUnified');
        $config = $pluginManager->getPluginConfig($plugin);

        $config[$key] = $value;
        $pluginManager->savePluginConfig($plugin, $config);
    }
}