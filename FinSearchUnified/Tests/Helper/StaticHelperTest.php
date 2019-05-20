<?php

namespace FinSearchUnified\Tests\Helper;

use Enlight_Components_Session_Namespace as Session;
use Enlight_Controller_Action as Action;
use Enlight_Controller_Front as Front;
use Enlight_Controller_Plugins_ViewRenderer_Bootstrap as ViewRenderer;
use Enlight_Controller_Request_RequestHttp as RequestHttp;
use Enlight_Plugin_Namespace_Loader as Plugins;
use Enlight_View_Default as View;
use FinSearchUnified\Constants;
use FinSearchUnified\Helper\StaticHelper;
use PHPUnit\Framework\Assert;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Components\Api\Manager;
use Shopware\Components\Api\Resource;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware\Models\Category\Category;
use Shopware_Components_Config as Config;
use SimpleXMLElement;
use Zend_Http_Client_Exception;

class StaticHelperTest extends TestCase
{
    /**
     * @var Resource\Category
     */
    private $categoryResource;

    protected function setUp()
    {
        parent::setUp();

        $manager = new Manager();
        $this->categoryResource = $manager->getResource('Category');
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

        Shopware()->Session()->offsetUnset('findologicDI');
    }

    /**
     * Data provider for checking findologic behavior
     *
     * @return array
     */
    public static function configDataProvider()
    {
        return [
            'FINDOLOGIC is inactive' => [
                'ActivateFindologic' => false,
                'ShopKey' => '0000000000000000ZZZZZZZZZZZZZZZZ',
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
                'ShopKey' => '0000000000000000ZZZZZZZZZZZZZZZZ',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => true,
                'isSearchPage' => null,
                'isCategoryPage' => null,
                'expected' => true
            ],
            'FINDOLOGIC is active but the current page is neither the search nor a category page' => [
                'ActivateFindologic' => true,
                'ShopKey' => '0000000000000000ZZZZZZZZZZZZZZZZ',
                'ActivateFindologicForCategoryPages' => true,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => false,
                'expected' => true
            ],
            'FINDOLOGIC is not active on category pages' => [
                'ActivateFindologic' => true,
                'ShopKey' => '0000000000000000ZZZZZZZZZZZZZZZZ',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => false,
                'isCategoryPage' => true,
                'expected' => true
            ],
            'FINDOLOGIC is active in search' => [
                'ActivateFindologic' => true,
                'ShopKey' => '0000000000000000ZZZZZZZZZZZZZZZZ',
                'ActivateFindologicForCategoryPages' => false,
                'findologicDI' => false,
                'isSearchPage' => true,
                'isCategoryPage' => false,
                'expected' => false
            ],
            'FINDOLOGIC is active on category pages' => [
                'ActivateFindologic' => true,
                'ShopKey' => '0000000000000000ZZZZZZZZZZZZZZZZ',
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
                "<span>Findologic Rocks</span>",
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
     * @dataProvider configDataProvider
     *
     * @param bool $isActive
     * @param string $shopKey
     * @param bool $isActiveForCategory
     * @param bool $checkIntegration
     * @param bool $isSearchPage
     * @param bool $isCategoryPage
     * @param bool $expected
     */
    public function testUseShopSearch(
        $isActive,
        $shopKey,
        $isActiveForCategory,
        $checkIntegration,
        $isSearchPage,
        $isCategoryPage,
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

        // Create Mock object for Shopware Front Request
        $front = $this->getMockBuilder(Front::class)
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $front->expects($this->any())
            ->method('Request')
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
            $session = $this->getMockBuilder(Session::class)
                ->setMethods(['offsetGet'])
                ->getMock();
            $session->expects($this->atLeastOnce())
                ->method('offsetGet')
                ->willReturnMap($sessionArray);

            // Assign mocked session variable to application container
            Shopware()->Container()->set('session', $session);
        }
        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder(Config::class)
            ->setMethods(['offsetGet', 'offsetExists'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->atLeastOnce())
            ->method('offsetGet')
            ->willReturnMap($configArray);
        $config->expects($this->any())
            ->method('offsetExists')
            ->willReturn(true);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        $result = StaticHelper::useShopSearch();
        $error = 'Expected %s search to be triggered but it was not';
        $shop = $expected ? 'shop' : 'FINDOLOGIC';
        $this->assertEquals($expected, $result, sprintf($error, $shop));
    }

    public function testUseShopSearchWhenRequestIsNull()
    {
        // Create Mock object for Shopware Front Request
        $front = $this->getMockBuilder(Front::class)
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $front->expects($this->atLeastOnce())
            ->method('Request')
            ->willReturn(null);

        Shopware()->Container()->set('front', $front);

        $result = StaticHelper::useShopSearch();
        $this->assertTrue($result, 'Expected shop search to be triggered but FINDOLOGIC was triggered instead');
    }

    public function testUseShopSearchInEmotion()
    {
        Shopware()->Session()->findologicDI = false;

        $request = new RequestHttp();
        $request->setModuleName('widgets')->setControllerName('emotion')->setActionName('emotionArticleSlider');

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

        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder(Config::class)
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->never())
            ->method('offsetGet');

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        // Create Mock object for Shopware Session
        $session = $this->getMockBuilder(Session::class)
            ->setMethods(['offsetGet'])
            ->getMock();
        $session->expects($this->never())
            ->method('offsetGet');

        // Assign mocked session variable to application container
        Shopware()->Container()->set('session', $session);

        $result = StaticHelper::useShopSearch();
        $this->assertTrue($result, 'Expected shop search to be triggered but FINDOLOGIC was triggered instead');
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
     */
    public function testBuildCategoryName($categoryId, $category, $expected)
    {
        $categoryModel = $this->categoryResource->update($categoryId, [
            'name' => $category
        ]);
        $this->assertInstanceOf(Category::class, $categoryModel);

        $this->updateParentCategoryName($categoryModel->getParent(), false);

        $result = StaticHelper::buildCategoryName($categoryModel->getId());

        $this->categoryResource->update($categoryId, [
            'name' => trim($category)
        ]);

        $this->updateParentCategoryName($categoryModel->getParent());

        $this->assertSame($expected, $result, 'Expected category name to be trimmed but was not');
    }

    /**
     * Helper method to recursively update parent category name
     *
     * @param Category $parent
     * @param bool $restore
     */
    private function updateParentCategoryName(Category $parent, $restore = true)
    {
        // Stop when Shopware's root category is reached. Changing it can and will break unrelated tests.
        if ($parent->getId() === 1) {
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

        $this->categoryResource->update($parent->getId(), [
            'name' => $name
        ]);

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
                ->with($this->callback(function ($data) use (
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
                }));
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
     * @dataProvider discountFilterProvider
     * @dataProvider priceFilterProvider
     *
     * @param array $filterData
     * @param array $parameters
     * @param bool $expectedFacetState
     * @param string $expectedMinField
     * @param string $expectedMaxField
     *
     * @throws Zend_Http_Client_Exception
     */
    public function testCreateRangeSliderFacetMethod(
        array $filterData,
        array $parameters,
        $expectedFacetState,
        $expectedMinField,
        $expectedMaxField
    ) {
        // Minimal XML to be able to call createRangeSlideFacet
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $xmlResponse = new SimpleXMLElement($data);
        $filters = $xmlResponse->addChild('filters');
        $filter = $filters->addChild('filter');
        $filter->addChild('type', 'range-slider');
        $filter->addChild('items')->addChild('item');

        // Generate sample XML to mock the filters data
        foreach ($filterData as $key => $value) {
            if ($key === 'attributes') {
                $attributes = $filter->addChild('attributes');
                foreach ($value as $type => $ranges) {
                    $rangeType = $attributes->addChild($type);
                    foreach ($ranges as $minMax => $range) {
                        $rangeType->addChild($minMax, $range);
                    }
                }
            } else {
                $filter->addChild($key, $value);
            }
        }

        $request = new RequestHttp();
        $request->setModuleName('frontend');
        $request->setParams($parameters);

        // Create Mock object for Shopware Front Request
        $front = $this->getMockBuilder(Front::class)
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $front->expects($this->once())
            ->method('Request')
            ->willReturn($request);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('front', $front);

        $facets = StaticHelper::getFacetResultsFromXml($xmlResponse);

        /** @var RangeFacetResult $facet */
        foreach ($facets as $facet) {
            $this->assertInstanceOf(RangeFacetResult::class, $facet);
            $this->assertSame($expectedFacetState, $facet->isActive());
            $this->assertSame($expectedMinField, $facet->getMinFieldName());
            $this->assertSame($expectedMaxField, $facet->getMaxFieldName());
        }
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
}
