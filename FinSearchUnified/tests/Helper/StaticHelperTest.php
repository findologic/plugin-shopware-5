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
            'Shopkey is empty' => [
                'ActivateFindologic' => true,
                'ShopKey' => '',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'expected' => true
            ],
            "Shopkey is 'Findologic ShopKey'" => [
                'ActivateFindologic' => true,
                'ShopKey' => 'Findologic ShopKey',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'expected' => true
            ],
            'FINDOLOGIC is active but integration type is DI' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => true,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'expected' => true
            ],
            'FINDOLOGIC is active but the current page is neither the search nor a category page' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => false,
                'expected' => true
            ],
            'FINDOLOGIC is not active on category pages' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'expected' => true
            ],
            'FINDOLOGIC is active in search' => [
                'ActivateFindologic' => true,
                'ShopKey' => 'ABCD0815',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'expected' => false
            ],
            'FINDOLOGIC is active on category pages' => [
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
     * Data provider for testing removal of control characters
     *
     * @return array
     */
    public static function controlCharacterProvider()
    {
        return [
            'Strings with only letters and numbers' => ['Findologic123', 'Findologic123'],
            'String with control characters' => ["Findologic\n1\t2\r3", 'Findologic 1 2 3'],
            'String with special characters' => ['Findologic&123', 'Findologic&123'],
            'String with umlauts' => ['Findolögic123', 'Findolögic123']
        ];
    }

    /**
     * Data provider for testing cleanString method
     *
     * @return array
     */
    public static function cleanStringProvider()
    {
        return [
            'String with HTML tags' => ["<span>Findologic Rocks</span>", 'Findologic Rocks'],
            'String with single quotes' => ["Findologic's team rocks", 'Findologic\'s team rocks'],
            'String with double quotes' => [
                'Findologic "Rocks!"',
                "Findologic \"Rocks!\"",
            ],
            'String with back slashes' => [
                "Findologic\ Rocks!\\",
                "Findologic Rocks!"
            ],
            'String with preceding space' => [' Findologic Rocks ', 'Findologic Rocks']
        ];
    }

    /**
     * @dataProvider configDataProvider
     *
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
            ['ActivateFindologic', $isActive],
            ['ShopKey', $shopKey],
            ['ActivateFindologicForCategoryPages', $isActiveForCategory],
            ['IntegrationType', $checkIntegration ? Constants::INTEGRATION_TYPE_DI : Constants::INTEGRATION_TYPE_API]
        ];

        if ($isSearchPage !== null) {
            $sessionArray = [
                ['isSearchPage', $isSearchPage],
                ['isCategoryPage', $isCategoryPage],
                ['findologicDI', $checkIntegration]
            ];

            // Create Mock object for Shopware Session
            $session = $this->getMockBuilder('\Enlight_Components_Session_Namespace')
                ->setMethods(['offsetGet'])
                ->getMock();
            $session->expects($this->atLeastOnce())
                ->method('offsetGet')
                ->willReturnMap($sessionArray);

            // Assign mocked session variable to application container
            Shopware()->Container()->set('session', $session);
        }
        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder('\Shopware_Components_Config')
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($configArray);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        $result = StaticHelper::useShopSearch();
        $error = "Expected %s search to be triggered but it wasn't";
        $shop = $expectedResult ? "shop" : "FINDOLOGIC";
        $this->assertEquals($result, $expectedResult, sprintf($error, $shop));
    }

    /**
     * @dataProvider controlCharacterProvider
     *
     * @param string $text
     * @param string $expected
     */
    public function testControlCharacterMethod($text, $expected)
    {
        $result = StaticHelper::removeControlCharacters($text);
        $this->assertEquals($expected, $result, sprintf('%s was expected but %s was returned', $expected, $result));
    }

    /**
     * @dataProvider cleanStringProvider
     * @dataProvider controlCharacterProvider
     *
     * @param string $text
     * @param string $expected
     */
    public function testCleanStringMethod($text, $expected)
    {
        $result = StaticHelper::cleanString($text);
        $this->assertEquals($expected, $result, sprintf('%s was expected but %s was returned', $expected, $result));
    }
}