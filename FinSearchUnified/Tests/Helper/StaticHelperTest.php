<?php

namespace FinSearchUnified\Tests\Helper;

use Enlight_Controller_Request_RequestHttp as RequestHttp;
use FinSearchUnified\Constants;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware\Models\Category\Category;

class StaticHelperTest extends TestCase
{
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
            'Root category name without children' => [1, ' Root ', 'Root'],
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
        $front = $this->getMockBuilder('\Enlight_Controller_Front')
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
        $error = 'Expected %s search to be triggered but it was not';
        $shop = $expected ? 'shop' : 'FINDOLOGIC';
        $this->assertEquals($expected, $result, sprintf($error, $shop));
    }

    public function testUseShopSearchWhenRequestIsNull()
    {
        // Create Mock object for Shopware Front Request
        $front = $this->getMockBuilder('\Enlight_Controller_Front')
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $front->expects($this->atLeastOnce())
            ->method('Request')
            ->willReturn(null);

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
        $front = $this->getMockBuilder('\Enlight_Controller_Front')
            ->setMethods(['Request'])
            ->disableOriginalConstructor()
            ->getMock();
        $front->expects($this->atLeastOnce())
            ->method('Request')
            ->willReturn($request);

        // Assign mocked session variable to application container
        Shopware()->Container()->set('front', $front);

        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder('\Shopware_Components_Config')
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->never())
            ->method('offsetGet');

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        // Create Mock object for Shopware Session
        $session = $this->getMockBuilder('\Enlight_Components_Session_Namespace')
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
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function testBuildCategoryName($categoryId, $category, $expected)
    {
        $categoryModel = Shopware()->Models()->getRepository('Shopware\Models\Category\Category')
            ->find($categoryId);
        $this->assertInstanceOf('Shopware\Models\Category\Category', $categoryModel);

        // Set category name with preceeding and succeeding spaces
        $categoryModel->setName($category);
        $parent = $categoryModel->getParent();

        // Set parent category name with preceeding and succeeding spaces
        if ($parent !== null) {
            $this->updateParentCategoryName($parent, $categoryModel);
        }
        // Persist changes to database
        Shopware()->Models()->flush();
        $result = StaticHelper::buildCategoryName($categoryModel->getId());

        // Revert category name back to correct state after test result
        $categoryModel->setName(trim($category));
        if ($parent !== null) {
            $this->updateParentCategoryName($parent, $categoryModel, true);
        }
        // Persist changes to database
        Shopware()->Models()->flush();
        $this->assertSame($expected, $result, 'Expected category name to be trimmed but was not');
    }

    /**
     * Helper method to recursively update parent category name
     *
     * @param Category $parent
     * @param Category $categoryModel
     * @param bool $restore
     */
    private function updateParentCategoryName(Category $parent, Category $categoryModel, $restore = false)
    {
        // Trim name here for restoring
        $parentName = trim($parent->getName());

        // Add spaces to name for testing if restore is false
        if (!$restore) {
            $parentName = str_pad(
                $parentName,
                strlen($parentName) + 2,
                ' ',
                STR_PAD_BOTH
            );
        }

        $parent->setName($parentName);
        Shopware()->Models()->persist($parent);

        $categoryModel->setParent($parent);
        Shopware()->Models()->persist($categoryModel);

        if ($parent->getParent() !== null) {
            $this->updateParentCategoryName($parent->getParent(), $parent, $restore);
        }
    }

    public function finSmartDidYouMeanProvider()
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
     * @dataProvider finSmartDidYouMeanProvider
     *
     * @param string $didYouMeanQuery
     * @param string $originalQuery
     * @param string $queryStringType
     * @param string $expectedType
     * @param string $expectedAlternativeQuery
     * @param string $expectedOriginalQuery
     */
    public function testFinSmartDidYouMean(
        $didYouMeanQuery,
        $originalQuery,
        $queryStringType,
        $expectedType,
        $expectedAlternativeQuery,
        $expectedOriginalQuery
    ) {
        $finSmartDidYouMean = null;

        // Replicate functionality of StaticHelper::setSmartDidYouMean
        if (((isset($originalQuery) && $originalQuery !== '') || (isset($didYouMeanQuery) && $didYouMeanQuery !== ''))
            && ($queryStringType && $queryStringType !== 'forced')) {
            $type = isset($didYouMeanQuery) ? 'did-you-mean' : $queryStringType;
            $finSmartDidYouMean = [
                'type' => $type,
                'alternative_query' => $type === 'did-you-mean' ? $didYouMeanQuery : 'queryString',
                'original_query' => $type === 'did-you-mean' ? '' : $originalQuery
            ];
        }

        // Create mocked view
        $view = $this->getMockBuilder('\Enlight_View_Default')
            ->setMethods(['getAssign'])
            ->disableOriginalConstructor()
            ->getMock();

        // Assign the finSmartDidYouMean variable to the mocked view
        $view->method('getAssign')->willReturnMap([
            ['finSmartDidYouMean', $finSmartDidYouMean]
        ]);

        $action = $this->getMockBuilder('\Enlight_Controller_Action')
            ->setMethods(['View'])
            ->disableOriginalConstructor()
            ->getMock();
        $action->method('View')
            ->willReturn($view);

        $renderer = $this->getMockBuilder('\Enlight_Controller_Plugins_ViewRenderer_Bootstrap')
            ->setMethods(['Action'])
            ->disableOriginalConstructor()
            ->getMock();
        $renderer->method('Action')
            ->willReturn($action);

        $plugin = $this->getMockBuilder('\Enlight_Plugin_Namespace_Loader')
            ->setMethods(['ViewRenderer'])
            ->disableOriginalConstructor()
            ->getMock();
        $plugin->method('ViewRenderer')
            ->willReturn($renderer);

        $front = $this->getMockBuilder('\Enlight_Controller_Front')
            ->setMethods(['Plugins'])
            ->disableOriginalConstructor()
            ->getMock();
        $front->method('Plugins')
            ->willReturn($plugin);

        Shopware()->Container()->set('front', $front);

        // Get assigned variable from mocked view object
        $finSmartDidYouMean = Shopware()
            ->Front()
            ->Plugins()
            ->ViewRenderer()
            ->Action()
            ->View()
            ->getAssign('finSmartDidYouMean');

        if ($expectedType === null) {
            $this->assertNull($finSmartDidYouMean);
        } else {
            $this->assertSame($expectedType, $finSmartDidYouMean['type'], sprintf(
                'Type was expected to be %s',
                $expectedType
            ));
            $this->assertSame(
                $expectedAlternativeQuery,
                $finSmartDidYouMean['alternative_query'],
                sprintf("alternative_query does not have %s's value", $expectedAlternativeQuery)
            );
            if ($expectedOriginalQuery === '') {
                $this->assertEmpty($finSmartDidYouMean['original_query'], 'original_query was not empty');
            } else {
                $this->assertSame(
                    $expectedOriginalQuery,
                    $finSmartDidYouMean['original_query'],
                    sprintf("original_query does not have %s's value", $expectedOriginalQuery)
                );
            }
        }
    }
}
