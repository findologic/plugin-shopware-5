<?php

namespace FinSearchUnified\Tests\Helper;

use Enlight_Components_Session_Namespace as Session;
use Enlight_Controller_Action as Action;
use Enlight_Controller_Front as Front;
use Enlight_Controller_Plugins_ViewRenderer_Bootstrap as ViewRenderer;
use Enlight_Controller_Request_RequestHttp;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use Enlight_Plugin_Namespace_Loader as Plugins;
use Enlight_View_Default as View;
use Exception;
use FinSearchUnified\Components\ConfigLoader;
use FinSearchUnified\Constants;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\Tests\TestCase;
use PHPUnit\Framework\Assert;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Components\Api\Exception\CustomValidationException;
use Shopware\Components\Api\Exception\NotFoundException;
use Shopware\Components\Api\Exception\ParameterMissingException;
use Shopware\Components\Api\Exception\ValidationException;
use Shopware\Components\Api\Manager;
use Shopware\Components\Api\Resource;
use Shopware\Components\HttpClient\GuzzleHttpClient;
use Shopware_Components_Config as Config;
use SimpleXMLElement;
use Zend_Cache_Core;
use Zend_Cache_Exception;

class StaticHelperTest extends TestCase
{
    /**
     * @var Resource\Category
     */
    private $categoryResource;

    protected function setUp()
    {
        parent::setUp();
        $this->categoryResource = Manager::getResource('Category');
    }

    protected function tearDown()
    {
        parent::tearDown();

        Shopware()->Container()->reset('front');
        Shopware()->Container()->load('front');
        Shopware()->Container()->reset('session');
        Shopware()->Container()->load('session');
        Shopware()->Container()->reset('config');
        Shopware()->Container()->load('config');
        Shopware()->Container()->reset('fin_search_unified.config_loader');
        Shopware()->Container()->load('fin_search_unified.config_loader');
    }

    public function isFindologicActiveDataprovider()
    {
        return [
            'FINDOLOGIC will not be active if it is deactivated' => [
                'activateFindologic' => false,
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
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
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
                'queryParameter' => null,
                'configStaging' => true,
                'expected' => false
            ],
            'FINDOLOGIC will be active if it is a staging shop with query parameter' => [
                'activateFindologic' => true,
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
                'queryParameter' => 'on',
                'configStaging' => true,
                'expected' => true
            ],
            'FINDOLOGIC will not be active if it is deactivated and it is not a staging shop' => [
                'activateFindologic' => false,
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
                'queryParameter' => null,
                'configStaging' => false,
                'expected' => false
            ],
            'FINDOLOGIC will be active if it is enabled and it is not a staging shop without query parameter' => [
                'activateFindologic' => true,
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
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
            ['ActivateFindologic', $activateFindologic],
            ['ShopKey', $shopKey],
        ];

        $request = new RequestHttp();
        $request->setModuleName('frontend');
        $request->setParam('findologic', $queryParameter);

        // Create Mock object for Shopware Front Request
        $front = $this->getMockBuilder(Front::class)
            ->disableOriginalConstructor()
            ->getMock();
        $front->expects($this->once())
            ->method('Request')
            ->willReturn($request);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('front', $front);

        // Create Mock object for Shopware Config
        $config = $this->createMock(Config::class);
        $config->method('offsetGet')
            ->willReturnMap($configArray);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        // Create Mock object for Shopware Session
        $session = $this->createMock(Session::class);
        $session->method('offsetGet')
            ->with('stagingFlag')
            ->willReturn($queryParameter === 'on');

        // Assign mocked session variable to application container
        Shopware()->Container()->set('session', $session);

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
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
                'activateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'fallbackSearchCookie' => null,
                'expected' => true
            ],
            'Shopkey is empty' => [
                'activateFindologic' => true,
                'shopKey' => '',
                'activateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'fallbackSearchCookie' => null,
                'expected' => true
            ],
            "Shopkey is 'Findologic Shopkey'" => [
                'activateFindologic' => true,
                'shopKey' => 'Findologic Shopkey',
                'activateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'fallbackSearchCookie' => null,
                'expected' => true
            ],
            'FINDOLOGIC is active but integration type is DI' => [
                'activateFindologic' => true,
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
                'activateFindologicForCategoryPages' => true,
                'findologicDI' => true,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'fallbackSearchCookie' => null,
                'expected' => true
            ],
            'FINDOLOGIC is active but the current page is neither the search nor a category page' => [
                'activateFindologic' => true,
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
                'activateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => false,
                'fallbackSearchCookie' => null,
                'expected' => true
            ],
            'FINDOLOGIC is not active on category pages' => [
                'activateFindologic' => true,
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
                'activateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'fallbackSearchCookie' => null,
                'expected' => true
            ],
            'FINDOLOGIC is active on search page' => [
                'activateFindologic' => true,
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
                'activateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'fallbackSearchCookie' => null,
                'expected' => false
            ],
            'FINDOLOGIC is active on category pages' => [
                'activateFindologic' => true,
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
                'activateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'fallbackSearchCookie' => null,
                'expected' => false
            ],
            'Cookie "fallback-search" is set and true' => [
                'activateFindologic' => true,
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
                'activateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'fallbackSearchCookie' => true,
                'expected' => true
            ],
            'Cookie "fallback-search" is set and its value is 1' => [
                'activateFindologic' => true,
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
                'activateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'fallbackSearchCookie' => 1,
                'expected' => true
            ],
            'Cookie "fallback-search" is set and false' => [
                'activateFindologic' => true,
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
                'activateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'fallbackSearchCookie' => false,
                'expected' => false
            ],
            'Cookie "fallback-search" is not set' => [
                'activateFindologic' => true,
                'shopKey' => '80AB18D4BE2654E78244106AD315DC2C',
                'activateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
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
     * @dataProvider shopSearchProvider
     *
     * @param bool $isActive
     * @param string $shopKey
     * @param bool $isActiveForCategory
     * @param bool $checkIntegration
     * @param bool $isSearchPage
     * @param bool $isCategoryPage
     * @param bool|null $fallbackCookie
     * @param bool $expected
     *
     * @throws Zend_Cache_Exception
     */
    public function testUseShopSearch(
        $isActive,
        $shopKey,
        $isActiveForCategory,
        $checkIntegration,
        $isSearchPage,
        $isCategoryPage,
        $fallbackCookie,
        $expected
    ) {
        $configArray = [
            ['ActivateFindologic', $isActive],
            ['ShopKey', $shopKey],
            ['ActivateFindologicForCategoryPages', $isActiveForCategory],
            ['IntegrationType', $checkIntegration ? Constants::INTEGRATION_TYPE_DI : Constants::INTEGRATION_TYPE_API]
        ];
        $request = new RequestHttp();
        $request->setModuleName('frontend');

        $_COOKIE['fallback-search'] = $fallbackCookie;

        // Create Mock object for Shopware Front Request
        $front = $this->createMock(Front::class);
        $front->method('Request')
            ->willReturn($request);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('front', $front);

        if ($isSearchPage !== null) {
            $sessionArray = [
                ['isSearchPage', $isSearchPage],
                ['isCategoryPage', $isCategoryPage],
                ['findologicDI', $checkIntegration]
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
            ->method('offsetGet')
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

    /**
     * @throws Zend_Cache_Exception
     */
    public function testUseShopSearchWhenRequestIsNull()
    {
        // Create Mock object for Shopware Front Request
        $front = $this->createMock(Front::class);
        $front->expects($this->atLeastOnce())
            ->method('Request')
            ->willReturn(null);

        Shopware()->Container()->set('front', $front);

        $result = StaticHelper::useShopSearch();
        $this->assertTrue($result, 'Expected shop search to be triggered but FINDOLOGIC was triggered instead');
    }

    /**
     * @throws Zend_Cache_Exception
     */
    public function testUseShopSearchInEmotion()
    {
        Shopware()->Session()->findologicDI = false;

        $request = new RequestHttp();
        $request->setModuleName('widgets')
            ->setControllerName('emotion')
            ->setActionName('emotionArticleSlider');

        // Create Mock object for Shopware Front Request
        $front = $this->getMockBuilder(Front::class)
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $front->expects($this->atLeastOnce())
            ->method('Request')
            ->willReturn($request);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('front', $front);

        $result = StaticHelper::useShopSearch();
        $this->assertTrue($result, 'Expected shop search to be triggered but FINDOLOGIC was triggered instead');
    }

    /**
     * @throws Zend_Cache_Exception
     */
    public function testUseShopSearchForBackendRequests()
    {
        $request = new RequestHttp();
        $request->setModuleName('backend');

        // Create Mock object for Shopware Front Request
        $front = $this->getMockBuilder(Front::class)
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $front->expects($this->atLeastOnce())
            ->method('Request')
            ->willReturn($request);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('front', $front);

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

        // Create Mock object for Shopware Front Request
        $front = $this->createMock(Front::class);
        $front->method('Request')
            ->willReturn($request);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('front', $front);

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
        $categoryModel = $this->categoryResource->update($categoryId, ['name' => $category]);
        $result = StaticHelper::buildCategoryName($categoryModel->getId());
        $this->categoryResource->update($categoryId, ['name' => trim($category)]);
        $this->assertSame($expected, $result, 'Expected category name to be trimmed but was not');
    }

    /**
     * Helper method to recursively update parent category name
     *
     * @param Category $parent
     * @param bool $restore
     *
     * @throws CustomValidationException
     * @throws NotFoundException
     * @throws ParameterMissingException
     * @throws ValidationException
     */
    private function updateParentCategoryName(Category $parent, $restore = true)
    {
        // Stop when Shopware's root category is reached. Changing it can and will break unrelated tests.
        if ($parent->getId() <= 3) {
            return;
        }

        if ($restore) {
            $name = trim($parent->getName());
        } else {
            $name = str_pad(
                $parent->getName(),
                strlen($parent->getName()) + 2,
                ' ',
                STR_PAD_BOTH
            );
        }

        $this->categoryResource->update(
            $parent->getId(),
            [
                'name' => $name
            ]
        );

        $this->updateParentCategoryName($parent->getParent(), $restore);
    }

    public function smartDidYouMeanProvider()
    {
        return [
            'didYouMeanQuery and originalQuery are not present' => [null, null, null, null, null, null],
            'Attribute type of queryString is forced' => [null, 'originalQuery', 'forced', null, null, null],
            'didYouMeanQuery is present but has no value' => ['', null, 'improved', null, null, null],
            'originalQuery is present but has no value' => [null, '', 'improved', null, null, null],
            'didYouMeanQuery is present' => [
                'didYouMeanQuery',
                'originalQuery',
                'improved',
                'did-you-mean',
                'didYouMeanQuery',
                ''
            ],
            'queryString type is improved' => [
                null,
                'originalQuery',
                'improved',
                'improved',
                'queryString',
                'originalQuery'
            ],
            'queryString type is corrected' => [
                null,
                'originalQuery',
                'corrected',
                'corrected',
                'queryString',
                'originalQuery'
            ],
        ];
    }

    /**
     * @dataProvider smartDidYouMeanProvider
     *
     * @param string $didYouMeanQuery
     * @param string $originalQuery
     * @param string $queryStringType
     * @param string $expectedType
     * @param string $expectedAlternativeQuery
     * @param string $expectedOriginalQuery
     */
    public function testParsesSmartDidYouMeanData(
        $didYouMeanQuery,
        $originalQuery,
        $queryStringType,
        $expectedType,
        $expectedAlternativeQuery,
        $expectedOriginalQuery
    ) {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $xmlResponse = new SimpleXMLElement($data);

        $query = $xmlResponse->addChild('query');
        $queryString = $query->addChild('queryString', 'queryString');

        if ($queryStringType !== null) {
            $queryString->addAttribute('type', $queryStringType);
        }

        if ($didYouMeanQuery !== null) {
            $query->addChild('didYouMeanQuery', $didYouMeanQuery);
        }
        if ($originalQuery !== null) {
            $query->addChild('originalQuery', $originalQuery);
        }

        $results = $xmlResponse->addChild('results');
        $results->addChild('count', 5);
        $products = $xmlResponse->addChild('products');

        for ($i = 1; $i <= 5; $i++) {
            $product = $products->addChild('product');
            $product->addAttribute('id', $i);
        }

        // Create mocked view
        $view = $this->createMock(View::class);
        if ($expectedType === null) {
            $view->expects($this->never())->method('assign');
        } else {
            $view->expects($this->once())
                ->method('assign')
                ->with(
                    $this->callback(
                        function ($data) use (
                            $expectedType,
                            $expectedAlternativeQuery,
                            $expectedOriginalQuery
                        ) {
                            Assert::assertArrayHasKey(
                                'finSmartDidYouMean',
                                $data,
                                '"finSmartDidYouMean" was not assigned to the view'
                            );

                            Assert::assertEquals(
                                [
                                    'type' => $expectedType,
                                    'alternative_query' => $expectedAlternativeQuery,
                                    'original_query' => $expectedOriginalQuery
                                ],
                                $data['finSmartDidYouMean']
                            );

                            return true;
                        }
                    )
                );
        }
        $action = $this->createMock(Action::class);
        $action->method('View')
            ->willReturn($view);

        $renderer = $this->createMock(ViewRenderer::class);
        $renderer->method('Action')
            ->willReturn($action);

        $plugin = $this->createMock(Plugins::class);
        $plugin->method('get')
            ->with('ViewRenderer')
            ->willReturn($renderer);

        $front = $this->createMock(Front::class);
        $front->method('Plugins')
            ->willReturn($plugin);

        Shopware()->Container()->set('front', $front);

        StaticHelper::setSmartDidYouMean($xmlResponse);
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

        // Create Mock object for Shopware Config
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

    public function queryInfoMessageProvider()
    {
        return [
            'Submitting an empty search' => [
                'queryString' => '',
                'queryStringType' => null,
                'params' => ['cat' => '', 'vendor' => ''],
                'queryInvokeCount' => $this->never(),
                'finSmartDidYouMean' => [],
                'filterName' => '',
                'cat' => '',
                'smartQuery' => '',
                'vendor' => '',
                'snippetType' => 'default'
            ],
            'Submitting an empty search with a selected category' => [
                'queryString' => '',
                'queryStringType' => null,
                'params' => ['cat' => 'Genusswelten', 'vendor' => ''],
                'queryInvokeCount' => $this->never(),
                'finSmartDidYouMean' => [],
                'filterName' => 'Kategorie',
                'cat' => 'Genusswelten',
                'smartQuery' => '',
                'vendor' => '',
                'snippetType' => 'cat'
            ],
            'Submitting an empty search with a selected sub-category' => [
                'queryString' => '',
                'queryStringType' => null,
                'params' => ['cat' => 'Genusswelten_Tees', 'vendor' => ''],
                'queryInvokeCount' => $this->never(),
                'finSmartDidYouMean' => [],
                'filterName' => 'Kategorie',
                'cat' => 'Tees',
                'smartQuery' => '',
                'vendor' => '',
                'snippetType' => 'cat'
            ],
            'Submitting an empty search with a selected vendor' => [
                'queryString' => '',
                'queryStringType' => null,
                'params' => ['cat' => '', 'vendor' => 'Shopware Food'],
                'queryInvokeCount' => $this->never(),
                'finSmartDidYouMean' => [],
                'filterName' => 'Hersteller',
                'cat' => '',
                'smartQuery' => '',
                'vendor' => 'Shopware Food',
                'snippetType' => 'vendor'
            ],
            'Submitting a search with some query' => [
                'queryString' => 'some query',
                'queryStringType' => null,
                'params' => ['cat' => '', 'vendor' => ''],
                'queryInvokeCount' => $this->never(),
                'finSmartDidYouMean' => [],
                'filterName' => '',
                'cat' => '',
                'smartQuery' => 'some query',
                'vendor' => '',
                'snippetType' => 'query'
            ],
            'Submitting a search with some query and a selected category and vendor filter' => [
                'queryString' => 'some query',
                'queryStringType' => null,
                'params' => ['cat' => 'Genusswelten', 'vendor' => 'Shopware Food'],
                'queryInvokeCount' => $this->never(),
                'finSmartDidYouMean' => [],
                'filterName' => '',
                'cat' => '',
                'smartQuery' => 'some query',
                'vendor' => '',
                'snippetType' => 'query'
            ],
            'Submitting a search where the response will have an improved query' => [
                'queryString' => 'special',
                'queryStringType' => 'improved',
                'params' => ['cat' => '', 'vendor' => ''],
                'queryInvokeCount' => $this->once(),
                'finSmartDidYouMean' => ['alternative_query' => 'very special'],
                'filterName' => '',
                'cat' => '',
                'smartQuery' => 'very special',
                'vendor' => '',
                'snippetType' => 'query'
            ],
            'Submitting a search where the response will have a corrected query' => [
                'queryString' => 'standord',
                'queryStringType' => 'improved',
                'params' => ['cat' => '', 'vendor' => ''],
                'queryInvokeCount' => $this->once(),
                'finSmartDidYouMean' => ['alternative_query' => 'standard'],
                'filterName' => '',
                'cat' => '',
                'smartQuery' => 'standard',
                'vendor' => '',
                'snippetType' => 'query'
            ],
        ];
    }

    /**
     * @dataProvider queryInfoMessageProvider
     *
     * @param string $queryString
     * @param string $queryStringType
     * @param array $params
     * @param $queryInvokeCount
     * @param array $finSmartDidYouMean
     * @param string $filterName
     * @param string $cat
     * @param string $smartQuery
     * @param string $vendor
     * @param string $snippetType
     */
    public function testQueryInfoMessage(
        $queryString,
        $queryStringType,
        $params,
        $queryInvokeCount,
        $finSmartDidYouMean,
        $filterName,
        $cat,
        $smartQuery,
        $vendor,
        $snippetType
    ) {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $xmlResponse = new SimpleXMLElement($data);

        $query = $xmlResponse->addChild('query');
        $queryString = $query->addChild('queryString', $queryString);
        $queryString->addAttribute('type', $queryStringType);

        $request = new Enlight_Controller_Request_RequestHttp();
        foreach ($params as $key => $value) {
            $request->setParam($key, $value);
        }

        // Create mocked view
        $view = $this->createMock(View::class);
        $view->expects($queryInvokeCount)->method('getAssign')
            ->with('finSmartDidYouMean')
            ->willReturn($finSmartDidYouMean);

        $expectedData = [
            'finQueryInfoMessage' => [
                'filter_name' => $filterName,
                'query' => $smartQuery,
                'cat' => $cat,
                'vendor' => $vendor
            ],
            'snippetType' => $snippetType
        ];

        $view->expects($this->once())
            ->method('assign')
            ->with(
                $this->callback(
                    static function ($data) use ($expectedData) {
                        Assert::assertArrayHasKey(
                            'finQueryInfoMessage',
                            $data,
                            '"finQueryInfoMessage" was not assigned to the view'
                        );
                        Assert::assertArrayHasKey(
                            'snippetType',
                            $data,
                            '"snippetType" was not assigned to the view'
                        );

                        Assert::assertEquals($expectedData, $data);

                        return true;
                    }
                )
            );
        $action = $this->createMock(Action::class);
        $action->method('View')
            ->willReturn($view);

        $renderer = $this->createMock(ViewRenderer::class);
        $renderer->method('Action')
            ->willReturn($action);

        $plugin = $this->createMock(Plugins::class);
        $plugin->method('get')
            ->with('ViewRenderer')
            ->willReturn($renderer);

        $front = $this->createMock(Front::class);
        $front->method('Plugins')
            ->willReturn($plugin);
        $front->method('Request')
            ->willReturn($request);

        Shopware()->Container()->set('front', $front);

        StaticHelper::setQueryInfoMessage($xmlResponse);
    }
}
