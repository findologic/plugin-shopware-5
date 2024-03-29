<?php

namespace FinSearchUnified\Tests\Helper;

use Enlight_Components_Session_Namespace as Session;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use Enlight_Exception;
use Exception;
use FinSearchUnified\Components\ConfigLoader;
use FinSearchUnified\Constants;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\Api\Exception\CustomValidationException;
use Shopware\Components\Api\Exception\NotFoundException;
use Shopware\Components\Api\Exception\ParameterMissingException;
use Shopware\Components\Api\Exception\ValidationException;
use Shopware\Components\Api\Manager;
use Shopware\Components\HttpClient\GuzzleHttpClient;
use Shopware_Components_Config;
use Shopware_Components_Config as Config;
use SimpleXMLElement;
use Zend_Cache_Core;
use Zend_Cache_Exception;

class StaticHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        Shopware()->Container()->reset('session');
        Shopware()->Container()->load('session');

        Shopware()->Container()->reset('config');
        Shopware()->Container()->load('config');

        Shopware()->Container()->reset('fin_search_unified.config_loader');
        Shopware()->Container()->load('fin_search_unified.config_loader');

        Utility::sResetManufacturers();
    }

    public function isFindologicActiveDataprovider()
    {
        return [
            'FINDOLOGIC will not be active if it is deactivated' => [
                'activateFindologic' => false,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'queryParameter' => null,
                'configStaging' => true,
                'expected' => false
            ],
            'FINDOLOGIC will not be active if shopkey is empty' => [
                'activateFindologic' => true,
                'shopKey' => '',
                'queryParameter' => null,
                'configStaging' => false,
                'expected' => false
            ],
            'FINDOLOGIC will not be active if it is a staging shop without query parameter' => [
                'activateFindologic' => true,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'queryParameter' => null,
                'configStaging' => true,
                'expected' => false
            ],
            'FINDOLOGIC will be active if it is a staging shop with query parameter' => [
                'activateFindologic' => true,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'queryParameter' => 'on',
                'configStaging' => true,
                'expected' => true
            ],
            'FINDOLOGIC will not be active if it is deactivated and it is not a staging shop' => [
                'activateFindologic' => false,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'queryParameter' => null,
                'configStaging' => false,
                'expected' => false
            ],
            'FINDOLOGIC will be active if it is enabled and it is not a staging shop without query parameter' => [
                'activateFindologic' => true,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'queryParameter' => null,
                'configStaging' => false,
                'expected' => true
            ],

        ];
    }

    /**
     * @dataProvider isFindologicActiveDataprovider
     *
     * @param bool $activateFindologic
     * @param string $shopKey
     * @param string $queryParameter
     * @param bool $configStaging
     * @param bool $expected
     *
     * @throws Zend_Cache_Exception
     */
    public function testIsFindologicActive(
        $activateFindologic,
        $shopKey,
        $queryParameter,
        $configStaging,
        $expected
    ) {
        $configArray = [
            ['FinSearchUnified', 'ActivateFindologic', null, $activateFindologic],
            ['FinSearchUnified', 'ShopKey', null, $shopKey],
        ];

        $request = new RequestHttp();
        $request->setModuleName('frontend');
        $request->setParam('findologic', $queryParameter);

        Shopware()->Front()->setRequest($request);

        // Create Mock object for Shopware Config
        $config = $this->createMock(Config::class);
        $config->method('getByNamespace')
            ->willReturnMap($configArray);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);
        Shopware()->Session()->stagingFlag = $queryParameter === 'on';

        $configloader = $this->createMock(ConfigLoader::class);
        $configloader->expects($this->once())
            ->method('isStagingShop')
            ->willReturn($configStaging);

        Shopware()->Container()->set('fin_search_unified.config_loader', $configloader);

        $result = StaticHelper::isFindologicActive();
        $this->assertEquals($expected, $result);
    }

    /**
     * Data provider for checking findologic behavior
     *
     * @return array
     */
    public static function shopSearchProvider()
    {
        return [
            'FINDOLOGIC is inactive' => [
                'activateFindologic' => false,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'activateFindologicForCategoryPages' => true,
                'integrationType'=> Constants::INTEGRATION_TYPE_API,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'isManufacturerPage' => null,
                'fallbackSearchCookie' => null,
                'expected' => true
            ],
            'Shopkey is empty' => [
                'activateFindologic' => true,
                'shopKey' => '',
                'activateFindologicForCategoryPages' => true,
                'integrationType'=> Constants::INTEGRATION_TYPE_API,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'isManufacturerPage' => null,
                'fallbackSearchCookie' => null,
                'expected' => true
            ],
            "Shopkey is 'Findologic Shopkey'" => [
                'activateFindologic' => true,
                'shopKey' => 'Findologic Shopkey',
                'activateFindologicForCategoryPages' => true,
                'integrationType'=> Constants::INTEGRATION_TYPE_API,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'isManufacturerPage' => null,
                'fallbackSearchCookie' => null,
                'expected' => true
            ],
            'FINDOLOGIC is active but integration type is DI' => [
                'activateFindologic' => true,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'activateFindologicForCategoryPages' => true,
                'integrationType'=> Constants::INTEGRATION_TYPE_DI,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'isManufacturerPage' => null,
                'fallbackSearchCookie' => null,
                'expected' => true
            ],
            'FINDOLOGIC is active but the current page is neither the search nor a category page' => [
                'activateFindologic' => true,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'activateFindologicForCategoryPages' => true,
                'integrationType'=> Constants::INTEGRATION_TYPE_API,
                'isSearchPage' => false,
                'isCategoryPage' => false,
                'isManufacturerPage' => false,
                'fallbackSearchCookie' => null,
                'expected' => true
            ],
            'FINDOLOGIC is not active on category pages' => [
                'activateFindologic' => true,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'activateFindologicForCategoryPages' => false,
                'integrationType'=> Constants::INTEGRATION_TYPE_API,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'isManufacturerPage' => false,
                'fallbackSearchCookie' => null,
                'expected' => true
            ],
            'FINDOLOGIC is active on search page' => [
                'activateFindologic' => true,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'activateFindologicForCategoryPages' => false,
                'integrationType'=> Constants::INTEGRATION_TYPE_API,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'isManufacturerPage' => false,
                'fallbackSearchCookie' => null,
                'expected' => false
            ],
            'FINDOLOGIC is active on category pages' => [
                'activateFindologic' => true,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'activateFindologicForCategoryPages' => true,
                'integrationType'=> Constants::INTEGRATION_TYPE_API,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'isManufacturerPage' => false,
                'fallbackSearchCookie' => null,
                'expected' => false
            ],
            'FINDOLOGIC is active on manufacturer pages' => [
                'activateFindologic' => true,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'activateFindologicForCategoryPages' => true,
                'integrationType'=> Constants::INTEGRATION_TYPE_API,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'isManufacturerPage' => true,
                'fallbackSearchCookie' => null,
                'expected' => false
            ],
            'Cookie "fallback-search" is set and true' => [
                'activateFindologic' => true,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'activateFindologicForCategoryPages' => false,
                'integrationType'=> Constants::INTEGRATION_TYPE_API,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'isManufacturerPage' => false,
                'fallbackSearchCookie' => true,
                'expected' => true
            ],
            'Cookie "fallback-search" is set and its value is 1' => [
                'activateFindologic' => true,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'activateFindologicForCategoryPages' => false,
                'integrationType'=> Constants::INTEGRATION_TYPE_API,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'isManufacturerPage' => false,
                'fallbackSearchCookie' => 1,
                'expected' => true
            ],
            'Cookie "fallback-search" is set and false' => [
                'activateFindologic' => true,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'activateFindologicForCategoryPages' => false,
                'integrationType'=> Constants::INTEGRATION_TYPE_API,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'isManufacturerPage' => false,
                'fallbackSearchCookie' => false,
                'expected' => false
            ],
            'Cookie "fallback-search" is not set' => [
                'activateFindologic' => true,
                'shopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD',
                'activateFindologicForCategoryPages' => false,
                'integrationType'=> Constants::INTEGRATION_TYPE_API,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'isManufacturerPage' => false,
                'fallbackSearchCookie' => null,
                'expected' => false
            ],
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
            'Strings with only letters and numbers' => [
                'Findologic123',
                'Findologic123',
                'Expected string to return unchanged'
            ],
            'String with control characters' => [
                "Findologic\n1\t2\r3",
                'Findologic123',
                'Expected control characters to be stripped way'
            ],
            'String with another set of control characters' => [
                "Findologic\xC2\x9F\xC2\x80 Rocks",
                'Findologic Rocks',
                'Expected control characters to be stripped way'
            ],
            'String with special characters' => [
                'Findologic&123',
                'Findologic&123',
                'Expected special characters to be returned as they are'
            ],
            'String with umlauts' => [
                'Findolögic123',
                'Findolögic123',
                'Expected umlauts to be left unaltered.'
            ]
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
            'String with HTML tags' => [
                '<span>Findologic Rocks</span>',
                'Findologic Rocks',
                'Expected HTML tags to be stripped away'
            ],
            'String with single quotes' => [
                "Findologic's team rocks",
                'Findologic\'s team rocks',
                'Expected single quotes to be escaped with back slash'
            ],
            'String with double quotes' => [
                'Findologic "Rocks!"',
                "Findologic \"Rocks!\"",
                'Expected double quotes to be escaped with back slash'
            ],
            'String with back slashes' => [
                "Findologic\ Rocks!\\",
                'Findologic Rocks!',
                'Expected back slashes to be stripped away'
            ],
            'String with preceding space' => [
                ' Findologic Rocks ',
                'Findologic Rocks',
                'Expected preceding and succeeding spaces to be stripped away'
            ],
            'Strings with only letters and numbers' => [
                'Findologic123',
                'Findologic123',
                'Expected string to return unchanged'
            ],
            'String with control characters' => [
                "Findologic\n1\t2\r3",
                'Findologic 1 2 3',
                'Expected control characters to be stripped way'
            ],
            'String with another set of control characters' => [
                "Findologic\xC2\x9F\xC2\x80 Rocks",
                'Findologic Rocks',
                'Expected control characters to be stripped way'
            ],
            'String with special characters' => [
                'Findologic&123!',
                'Findologic&123!',
                'Expected special characters to be returned as they are'
            ],
            'String with umlauts' => [
                'Findolögic123',
                'Findolögic123',
                'Expected umlauts to be left unaltered.'
            ],
            'String with html code' => [
                'Text &amp; normal space &gt; HTML codes',
                'Text & normal space > HTML codes',
                'Expected HTML code to be decoded.'
            ]
        ];
    }

    /**
     * Data provider for testing category names
     *
     * @return array
     */
    public function categoryNamesProvider()
    {
        return [
            'Root category name without children' => [5, ' Genusswelten ', 'Genusswelten'],
            'Category name with parent' => [12, ' Tees ', 'Genusswelten_Tees%20und%20Zubeh%C3%B6r_Tees'],
        ];
    }

    /**
     * Data provider for testing manufacturer names
     *
     * @return array
     */
    public function manufacturerNamesProvider()
    {
        return [
            'FindologicVendor ID 3' => [3, 'FindologicVendor3'],
            'FindologicVendor ID 4' => [4, 'FindologicVendor4'],
            'FindologicVendor ID 5' => [5, 'FindologicVendor5']
        ];
    }

    /**
     * @dataProvider shopSearchProvider
     *
     * @param bool $isActive
     * @param string $shopKey
     * @param bool $isActiveForCategory
     * @param string $integrationType
     * @param bool $isSearchPage
     * @param bool $isCategoryPage
     * @param bool $isManufacturerPage
     * @param bool|null $fallbackCookie
     * @param bool $expected
     *
     * @throws Zend_Cache_Exception
     */
    public function testUseShopSearch(
        $isActive,
        $shopKey,
        $isActiveForCategory,
        $integrationType,
        $isSearchPage,
        $isCategoryPage,
        $isManufacturerPage,
        $fallbackCookie,
        $expected
    ) {
        $configArray = [
            ['FinSearchUnified', 'ActivateFindologic', null, $isActive],
            ['FinSearchUnified', 'ShopKey', null, $shopKey],
            ['FinSearchUnified', 'ActivateFindologicForCategoryPages', null, $isActiveForCategory],
            ['FinSearchUnified', 'IntegrationType', $integrationType]
        ];
        $request = new RequestHttp();
        $request->setModuleName('frontend');

        $_COOKIE['fallback-search'] = $fallbackCookie;

        Shopware()->Front()->setRequest($request);

        if ($isSearchPage !== null) {
            $sessionArray = [
                ['isSearchPage', $isSearchPage],
                ['isCategoryPage', $isCategoryPage],
                ['isManufacturerPage', $isManufacturerPage],
            ];

            // Create Mock object for Shopware Session
            $session = $this->createMock(Session::class);
            $session->expects($this->atLeastOnce())
                ->method('offsetGet')
                ->willReturnMap($sessionArray);

            // Assign mocked session variable to application container
            Shopware()->Container()->set('session', $session);
        }

        // Create Mock object for Shopware Config
        $config = $this->createMock(Config::class);
        $config->expects($this->atLeastOnce())
            ->method('getByNamespace')
            ->willReturnMap($configArray);
        $config->method('offsetExists')
            ->willReturn(true);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        $result = StaticHelper::useShopSearch();
        $error = 'Expected %s search to be triggered but it was not';
        $shop = $expected ? 'shop' : 'FINDOLOGIC';
        $this->assertEquals($expected, $result, sprintf($error, $shop));
    }

    public function testDoNotUseShopSearchWhenCategoryPageAndSessionNotSet()
    {
        $configArray = [
            ['FinSearchUnified', 'ActivateFindologic', null, true],
            ['FinSearchUnified', 'ShopKey', null, 'ABCDABCDABCDABCDABCDABCDABCDABCD'],
            ['FinSearchUnified', 'ActivateFindologicForCategoryPages', null, true],
            ['IntegrationType', Constants::INTEGRATION_TYPE_API]
        ];
        $request = new RequestHttp();
        $request->setModuleName('frontend');
        $request->setControllerName('listing');
        $request->setParam('sCategory', '69');

        Shopware()->Front()->setRequest($request);

        // Create Mock object for Shopware Config
        $config = $this->createMock(Config::class);
        $config->expects($this->atLeastOnce())
            ->method('getByNamespace')
            ->willReturnMap($configArray);
        $config->method('getByNamespace')
            ->willReturn(true);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        Shopware()->Session()->offsetSet('isCategoryPage', false);

        $result = StaticHelper::useShopSearch();
        $this->assertFalse($result, 'Expected Findologic search to be triggered but it was not');
    }

    /**
     * @throws Enlight_Exception
     * @throws Zend_Cache_Exception
     */
    public function testUseShopSearchInEmotion()
    {
        $request = new RequestHttp();
        $request->setModuleName('widgets')
            ->setControllerName('emotion')
            ->setActionName('emotionArticleSlider');
        Shopware()->Front()->setRequest($request);

        $result = StaticHelper::useShopSearch();
        $this->assertTrue($result, 'Expected shop search to be triggered but FINDOLOGIC was triggered instead');
    }

    /**
     * @throws Zend_Cache_Exception
     * @throws Enlight_Exception
     */
    public function testUseShopSearchForBackendRequests()
    {
        $request = new RequestHttp();
        $request->setModuleName('backend');
        Shopware()->Front()->setRequest($request);

        $result = StaticHelper::useShopSearch();
        $this->assertTrue($result, 'Expected shop search to be triggered but FINDOLOGIC was triggered instead');
    }

    /**
     * @throws Zend_Cache_Exception
     */
    public function testUseShopSearchWhenShopIsNotAvailable()
    {
        $request = new RequestHttp();
        $request->setModuleName('backend');
        Shopware()->Front()->setRequest($request);

        $shop = Shopware()->Container()->get('shop');
        Shopware()->Container()->reset('shop');

        $result = StaticHelper::useShopSearch();
        $this->assertTrue($result, 'Expected shop search to be triggered but FINDOLOGIC was triggered instead');

        Shopware()->Container()->set('shop', $shop);
    }

    /**
     * @dataProvider controlCharacterProvider
     *
     * @param string $text
     * @param string $expected
     * @param string $errorMessage
     */
    public function testControlCharacterMethod($text, $expected, $errorMessage)
    {
        $result = StaticHelper::removeControlCharacters($text);
        $this->assertEquals($expected, $result, $errorMessage);
    }

    /**
     * @dataProvider cleanStringProvider
     *
     * @param string $text
     * @param string $expected
     * @param string $errorMessage
     */
    public function testCleanStringMethod($text, $expected, $errorMessage)
    {
        $result = StaticHelper::cleanString($text);
        $this->assertEquals($expected, $result, $errorMessage);
    }

    /**
     * @dataProvider categoryNamesProvider
     *
     * @param int $categoryId
     * @param string $category
     * @param string $expected
     *
     * @throws CustomValidationException
     * @throws NotFoundException
     * @throws ParameterMissingException
     * @throws ValidationException
     */
    public function testBuildCategoryName($categoryId, $category, $expected)
    {
        $categoryResource = Manager::getResource('Category');

        $categoryModel = $categoryResource->update($categoryId, ['name' => $category]);
        $result = StaticHelper::buildCategoryName($categoryModel->getId());
        $categoryResource->update($categoryId, ['name' => trim($category)]);
        $this->assertSame($expected, $result, 'Expected category name to be trimmed but was not');
    }

    /**
     * @dataProvider manufacturerNamesProvider
     *
     * @param int $manufacturerId
     * @param string $expected
     */
    public function testBuildManufacturerName($manufacturerId, $expected)
    {
        Utility::createTestManufacturer([
            'id' => $manufacturerId,
            'name' => $expected
        ]);

        $result = StaticHelper::buildManufacturerName($manufacturerId);
        $this->assertSame($expected, $result, 'Expected correct manufacturer name by ID');
    }

    /**
     * @return array
     */
    public function discountFilterProvider()
    {
        return [
            '"discount" filter parameters do not exist' => [
                [
                    'name' => 'discount',
                    'display' => 'Discount',
                    'select' => 'single',
                    'attributes' => [
                        'selectedRange' => ['min' => 0.99, 'max' => 25],
                        'totalRange' => ['min' => 0, 'max' => 50]
                    ]
                ],
                'parameters' => [],
                false,
                'mindiscount',
                'maxdiscount'
            ],
            '"discount" filter parameters exist in request and values do not match' => [
                [
                    'name' => 'discount',
                    'display' => 'Discount',
                    'select' => 'single',
                    'attributes' => [
                        'selectedRange' => ['min' => 0.99, 'max' => 25],
                        'totalRange' => ['min' => 0, 'max' => 50]
                    ]
                ],
                'parameters' => ['mindiscount' => 12.69, 'maxdiscount' => 33.5],
                false,
                'mindiscount',
                'maxdiscount'
            ],
            '"discount" filter parameters exist in request and values match' => [
                [
                    'name' => 'discount',
                    'display' => 'Discount',
                    'select' => 'single',
                    'attributes' => [
                        'selectedRange' => ['min' => 0.99, 'max' => 25],
                        'totalRange' => ['min' => 0, 'max' => 50]
                    ]
                ],
                'parameters' => ['mindiscount' => 0.99, 'maxdiscount' => 25],
                true,
                'mindiscount',
                'maxdiscount'
            ]
        ];
    }

    /**
     * @return array
     */
    public function priceFilterProvider()
    {
        return [
            '"price" filter parameters do not exist' => [
                [
                    'name' => 'price',
                    'display' => 'Preis',
                    'select' => 'single',
                    'attributes' => [
                        'selectedRange' => ['min' => 0.99, 'max' => 25],
                        'totalRange' => ['min' => 0, 'max' => 50]
                    ]
                ],
                'parameters' => [],
                false,
                'min',
                'max'
            ],
            '"price" filter parameters exist in request and values do not match' => [
                [
                    'name' => 'price',
                    'display' => 'Preis',
                    'select' => 'single',
                    'attributes' => [
                        'selectedRange' => ['min' => 0.99, 'max' => 110.65],
                        'totalRange' => ['min' => 0, 'max' => 950]
                    ]
                ],
                'parameters' => ['min' => 12.25, 'max' => 51],
                false,
                'min',
                'max'
            ],
            '"price" filter parameters exist in request and values match' => [
                [
                    'name' => 'price',
                    'display' => 'Preis',
                    'select' => 'single',
                    'attributes' => [
                        'selectedRange' => ['min' => 0.99, 'max' => 150],
                        'totalRange' => ['min' => 0.99, 'max' => 150]
                    ]
                ],
                'parameters' => ['min' => 0.99, 'max' => 150],
                true,
                'min',
                'max'
            ]
        ];
    }

    /**
     * @dataProvider directIntegrationProvider
     *
     * @param string $integrationType
     * @param string $expectedResult
     * @param bool $directIntegration
     *
     * @throws Exception
     */
    public function testDirectIntegration($integrationType, $expectedResult, $directIntegration)
    {
        // Create mock client to avoid accidentally performing a real HTTP request
        $httpClientMock = $this->createMock(GuzzleHttpClient::class);

        /** @var Shopware_Components_Config|MockObject $mockConfig */
        $mockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();

        $configArray = [
            ['IntegrationType', $integrationType]
        ];

        $mockedCache = $this->createMock(Zend_Cache_Core::class);

        $config = ['directIntegration' => ['enabled' => $directIntegration]];

        $mockedCache->expects($this->once())
            ->method('load')
            ->willReturn($config);

        $mockConfig->method('offsetGet')->willReturnMap($configArray);
        $configLoader = new ConfigLoader(
            $mockedCache,
            $httpClientMock,
            $mockConfig
        );

        Shopware()->Container()->set('fin_search_unified.config_loader', $configLoader);
        Shopware()->Container()->set('config', $mockConfig);

        $isDirectIntegration = StaticHelper::checkDirectIntegration();

        $this->assertSame($directIntegration, $isDirectIntegration);

        /** @var InstallerService $pluginManager */
        $pluginManager = Shopware()->Container()->get('shopware_plugininstaller.plugin_manager');
        $plugin = $pluginManager->getPluginByName('FinSearchUnified');

        // Fetch the config to assert the integration type config after calling checkDirectIntegration
        $config = $pluginManager->getPluginConfig($plugin);

        $this->assertSame($expectedResult, $config['IntegrationType']);

        // Reset this service directly here as it is being overridden only for this test
        Shopware()->Container()->reset('fin_search_unified.config_loader');
        Shopware()->Container()->load('fin_search_unified.config_loader');
    }

    public function directIntegrationProvider()
    {
        return [
            'Integration type is API and DI is not enabled' => ['API', 'API', false],
            'Integration type is DI and DI is enabled' => ['Direct Integration', 'Direct Integration', true],
            'Integration type is API but DI is enabled' => ['API', 'Direct Integration', true],
            'Integration type is DI but DI is not enabled' => ['Direct Integration', 'API', false],
        ];
    }

    public function nonEmptyValueProvider()
    {
        return [
            [' i am not empty'],
            [new SimpleXMLElement('<notEmpty/>')],
            [23],
            [1],
            ['_'],
            ['not empty at all' => 'really']
        ];
    }

    /**
     * @dataProvider nonEmptyValueProvider
     */
    public function testValuesThatAreNotEmptyAreReturnedAsSuch($value)
    {
        $this->assertFalse(StaticHelper::isEmpty($value));
    }

    public function emptyValueProvider()
    {
        return [
            [''],
            [' '],
            ['     '],
            ['          '],
            [[]],
            [['']]
        ];
    }

    /**
     * @dataProvider emptyValueProvider
     */
    public function testValuesThatArEmptyAreReturnedAsSuch($value)
    {
        $this->assertTrue(StaticHelper::isEmpty($value));
    }

    public function urlWithSpecialCharactersProvider()
    {
        return [
            'image path with https' => [
                'value' => 'https://example.com/a7/das8/test! Üä°´.png',
                'expectedValue' => 'https://example.com/a7/das8/test%21%20%C3%9C%C3%A4%C2%B0%C2%B4.png',
            ],
            'image path with http' => [
                'value' => 'http://example.com/a7/das8/test! Üä°´.png',
                'expectedValue' => 'http://example.com/a7/das8/test%21%20%C3%9C%C3%A4%C2%B0%C2%B4.png',
            ],
            'image path with https and subdomain' => [
                'value' => 'https://staging.example.com/a7/das8/test! Üä°´.png',
                'expectedValue' => 'https://staging.example.com/a7/das8/test%21%20%C3%9C%C3%A4%C2%B0%C2%B4.png',
            ]
        ];
    }

    /**
     * @dataProvider urlWithSpecialCharactersProvider
     */
    public function testUrlsWithSpecialCharacterAreReturnedEncoded($value, $expectedValue)
    {
        $this->assertSame($expectedValue, StaticHelper::encodeUrlPath($value));
    }

    public function preferredSizeAndThumbnailsProvider()
    {
        return [
            'Thumbnails array has preferred size' => [
                'preferredExportWidth' => 600,
                'preferredExportHeight' => 600,
                'thumbnails' => [
                    '100x100' => 'image_100x100.jpg',
                    '600x600' => 'image_600x600.jpg',
                    '900x900' => 'image_900x900.jpg'
                ],
                'imageSize' => 0,
                'imageUrl' => 'image.jpg',
                'expectedImageUrl' => 'image_600x600.jpg',
            ],
            'Thumbnails array does not have preferred size, but main image is equal to preferred' => [
                'preferredExportWidth' => 600,
                'preferredExportHeight' => 600,
                'thumbnails' => [
                    '100x100' => 'image_100x100.jpg',
                    '300x300' => 'image_300x300.jpg',
                    '900x900' => 'image_900x900.jpg'
                ],
                'imageSize' => 360000,
                'imageUrl' => 'image.jpg',
                'expectedImageUrl' => 'image.jpg',
            ],
            'Thumbnails array does not have preferred size, but main image is bigger than preferred' => [
                'preferredExportWidth' => 600,
                'preferredExportHeight' => 600,
                'thumbnails' => [
                    '100x100' => 'image_100x100.jpg',
                    '300x300' => 'image_300x300.jpg',
                    '900x900' => 'image_900x900.jpg'
                ],
                'imageSize' => 360001,
                'imageUrl' => 'image.jpg',
                'expectedImageUrl' => 'image.jpg',
            ],
            'Thumbnails array does not have preferred size and main image is smaller than preferred' => [
                'preferredExportWidth' => 600,
                'preferredExportHeight' => 600,
                'thumbnails' => [
                    '100x100' => 'image_100x100.jpg',
                    '300x300' => 'image_300x300.jpg',
                    '900x900' => 'image_900x900.jpg'
                ],
                'imageSize' => 359999,
                'imageUrl' => 'image.jpg',
                'expectedImageUrl' => 'image_900x900.jpg',
            ],
            'Thumbnail array doesnt have preferred size and main image is smaller than preferred, use next biggest' => [
                'preferredExportWidth' => 600,
                'preferredExportHeight' => 600,
                'thumbnails' => [
                    '100x100' => 'image_100x100.jpg',
                    '300x300' => 'image_300x300.jpg',
                    '800x800' => 'image_800x800.jpg',
                    '900x900' => 'image_900x900.jpg'
                ],
                'imageSize' => 359999,
                'imageUrl' => 'image.jpg',
                'expectedImageUrl' => 'image_800x800.jpg',
            ]
        ];
    }

    /**
     * @dataProvider preferredSizeAndThumbnailsProvider
     *
     * @param int $preferredExportWidth
     * @param int $preferredExportHeight
     * @param array $thumbnails
     * @param int $imageSize
     * @param string $imageUrl
     * @param string $expectedImageUrl
     */
    public function testGetPreferredImage(
        $preferredExportWidth,
        $preferredExportHeight,
        $thumbnails,
        $imageSize,
        $imageUrl,
        $expectedImageUrl
    ) {
        $actualImageUrl = StaticHelper::getPreferredImage(
            $imageUrl,
            $thumbnails,
            $imageSize,
            $preferredExportWidth,
            $preferredExportHeight
        );

        $this->assertEquals(
            $expectedImageUrl,
            $actualImageUrl
        );
    }
}
