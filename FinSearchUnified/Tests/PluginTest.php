<?php

namespace FinSearchUnified\Tests;

use FINDOLOGIC\Export\Helpers\EmptyValueNotAllowedException;
use FinSearchUnified\BusinessLogic\FindologicArticleFactory;
use FinSearchUnified\finSearchUnified as Plugin;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\Tests\Helper\Utility;

class PluginTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'FinSearchUnified' => [
            'ShopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD'
        ]
    ];

    protected function tearDown()
    {
        parent::tearDown();

        Shopware()->Container()->reset('fin_search_unified.article_model_factory');
        Shopware()->Container()->load('fin_search_unified.article_model_factory');

        Utility::sResetArticles();
    }

    public function testCanCreateInstance()
    {
        /** @var Plugin $plugin */
        $plugin = Shopware()->Container()->get('kernel')->getPlugins()['FinSearchUnified'];
        $this->assertInstanceOf(Plugin::class, $plugin);
    }

    public function testCalculateGroupkey()
    {
        $shopkey = 'ABCDABCDABCDABCDABCDABCDABCDABCD';
        $usergroup = 'at_rated';
        $hash = StaticHelper::calculateUsergroupHash($shopkey, $usergroup);
        $decrypted = StaticHelper::decryptUsergroupHash($shopkey, $hash);
        $this->assertEquals($usergroup, $decrypted);
    }

    /**
     * Data provider for the export test cases with corresponding assertion message
     *
     * @return array
     */
    public function articleProvider()
    {
        return [
            '2 active articles' => [
                'articlesActiveStatus' => [true, true],
                'expectedCount' => 2,
                'errorMessage' => 'Two articles were expected but %d were returned'
            ],
            '1 active and 1 inactive article' => [
                'articlesActiveStatus' => [true, false],
                'expectedCount' => 1,
                'errorMessage' => 'Only one article was expected but %d were returned'
            ],
            '2 inactive articles' => [
                'articlesActiveStatus' => [false, false],
                'expectedCount' => 0,
                'errorMessage' => 'No articles were expected but %d were returned'
            ],
        ];
    }

    /**
     * @dataProvider articleProvider
     *
     * @param array $articlesActiveStatus
     * @param int $expectedCount
     * @param string $errorMessage
     */
    public function testArticleExport($articlesActiveStatus, $expectedCount, $errorMessage)
    {
        // Create articles with the provided data to test the export functionality
        foreach ($articlesActiveStatus as $i => $iValue) {
            Utility::createTestProduct($i, $iValue);
        }
        $actual = Utility::runExportAndReturnCount();
        $this->assertEquals($expectedCount, $actual, sprintf($errorMessage, $actual));
    }

    /**
     * @return array
     */
    public function articleProviderWithId()
    {
        return [
            'No article for ID' => [
                'productId' => '20',
                'errorMessageOrProductCount' => json_encode([
                    'errors' => [
                        'general' => ['No article found with ID 20'],
                        'products' => []
                    ]
                ])
            ],
            '1 Article found by ID match' => [
                'productId' => '3',
                'errorMessageOrProductCount' => 1
            ],
            '5 Articles found by all possible matches' => [
                'productId' => '2',
                'errorMessageOrProductCount' => 5
            ],
            '1 Article with error by ID match' => [
                'productId' => '4',
                'errorMessageOrProductCount' => json_encode([
                    'errors' => [
                        'general' => [],
                        'products' => [
                            [
                                'id' => 4,
                                'errors' => [
                                    'Product is not active.',
                                    'Main Detail is not active or not available.',
                                    'shouldBeExported is false.'
                                ]
                            ]
                        ]
                    ]
                ])
            ],
            '3 Article without and 3 articles with error' => [
                'productId' => '1',
                'errorMessageOrProductCount' => json_encode([
                    'errors' => [
                        'general' => [],
                        'products' => [
                            [
                                'id' => 1,
                                'errors' => []
                            ],
                            [
                                'id' => 4,
                                'errors' => [
                                    'Product is not active.',
                                    'Main Detail is not active or not available.',
                                    'shouldBeExported is false.'
                                ]
                            ],
                            [
                                'id' => 5,
                                'errors' => [
                                    'Product is not active.',
                                    'All configured categories are inactive.',
                                    'shouldBeExported is false.'
                                ]
                            ],
                            [
                                'id' => 6,
                                'errors' => []
                            ],
                            [
                                'id' => 7,
                                'errors' => []
                            ],
                            [
                                'id' => 8,
                                'errors' => [
                                    'Main Detail is not active or not available.',
                                    'shouldBeExported is false.'
                                ]
                            ]
                        ]
                    ]
                ])
            ]
        ];
    }

    /**
     * @dataProvider articleProviderWithId
     *
     * @param string $productId
     * @param int|string $errorMessageOrProductCount
     */
    public function testProductIdExport($productId, $errorMessageOrProductCount)
    {
        Utility::createTestProductsWithIdAndVendor();

        $actual = Utility::runExportAndReturnCountOrErrors($productId);
        $this->assertEquals($errorMessageOrProductCount, $actual);
    }

    public function crossSellingCategoryProvider()
    {
        return [
            'No cross-sell categories configured' => [
                'crossSellingCategories' => [],
                'expectedCount' => 1
            ],
            'Article does not exist in cross-sell category configured' => [
                'crossSellingCategories' => ['Deutsch>Genusswelten'],
                'expectedCount' => 1
            ],
            'Article exists in one of the cross-sell categories configured' => [
                'crossSellingCategories' => ['Deutsch>Genusswelten', 'Deutsch>Wohnwelten'],
                'expectedCount' => 0
            ],
            'Article exists in all of cross-sell categories configured' => [
                'crossSellingCategories' => ['Deutsch>Genusswelten', 'Deutsch>Wohnwelten', 'Deutsch>Beispiele'],
                'expectedCount' => 0
            ],
        ];
    }

    /**
     * @dataProvider crossSellingCategoryProvider
     *
     * @param int[] $crossSellingCategories
     * @param int $expectedCount
     */
    public function testArticleExportWithCrossSellingCategories($crossSellingCategories, $expectedCount)
    {
        $assignedCategories = [8, 9, 10];
        Utility::createTestProduct('SOMENUMBER', true, $assignedCategories);
        Shopware()->Config()->CrossSellingCategories = $crossSellingCategories;
        $exportedCount = Utility::runExportAndReturnCount();
        unset(Shopware()->Config()->CrossSellingCategories);
        $this->assertSame($expectedCount, $exportedCount);
    }

    public function testEmptyValueNotAllowedExceptionIsThrownInExport()
    {
        // Create articles with the provided data to test the export functionality
        Utility::createTestProduct('SOMENUMBER', true);
        $findologicArticleFactoryMock = $this->createMock(FindologicArticleFactory::class);
        $findologicArticleFactoryMock->expects($this->once())->method('create')->willThrowException(
            new EmptyValueNotAllowedException()
        );

        Shopware()->Container()->set('fin_search_unified.article_model_factory', $findologicArticleFactoryMock);

        $exported = Utility::runExportAndReturnCount();
        $this->assertSame(0, $exported);
    }
}
