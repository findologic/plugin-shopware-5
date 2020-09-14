<?php

namespace FinSearchUnified\Tests;

use Exception;
use FINDOLOGIC\Export\Helpers\EmptyValueNotAllowedException;
use FinSearchUnified\BusinessLogic\ExportErrorInformation;
use FinSearchUnified\BusinessLogic\FindologicArticleFactory;
use FinSearchUnified\finSearchUnified as Plugin;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\ShopwareProcess;
use FinSearchUnified\Tests\Helper\Utility;
use Shopware\Components\Api\Manager;
use Shopware\Models\Article\Article;
use SimpleXMLElement;

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
            $this->createTestProduct($i, $iValue);
        }
        $actual = $this->runExportAndReturnCount();
        $this->assertEquals($expectedCount, $actual, sprintf($errorMessage, $actual));
    }

    /**
     * Data provider for the export test cases with corresponding assertion message
     *
     * @return array
     */
    public function articleProviderWithId()
    {
        return [
            'No article for ID' => [
                'productId' => 20,
                'response' => json_encode([
                    'errors' => [
                        'general' => ['No article found with ID 20'],
                        'products' => []
                    ]
                ])
            ],
            'Article found by ID match' => [
                'productId' => 3,
                'response' => 1
            ],
            '2 Article found by ID and supplier match' => [
                'productId' => 2,
                'response' => 2
            ],
            'Article with error by ID match' => [
                'productId' => 4,
                'response' => json_encode([
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
            'One Article without and 2 articles with error' => [
                'productId' => 1,
                'response' => json_encode([
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
     * @param int $productId
     * @param int $expectedCount
     * @param string $errorMessage
     */
    public function testProductIdExport($productId, $expectedCount)
    {
        $this->createTestProductsWithIdAndVendor();

        $actual = $this->runExportAndReturnCountOrErrors($productId);
        $this->assertEquals($expectedCount, $actual);
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
        $this->createTestProduct('SOMENUMBER', true, $assignedCategories);
        Shopware()->Config()->CrossSellingCategories = $crossSellingCategories;
        $exportedCount = $this->runExportAndReturnCount();
        unset(Shopware()->Config()->CrossSellingCategories);
        $this->assertSame($expectedCount, $exportedCount);
    }

    /**
     * @param int|string $number
     * @param bool $isActive
     * @param array $categories
     *
     * @return Article|null
     */
    private function createTestProduct($number, $isActive, $categories = [])
    {
        $testArticle = [
            'name' => 'FindologicArticle' . $number,
            'active' => $isActive,
            'tax' => 19,
            'supplier' => 'Findologic',
            'categories' => [
                ['id' => 5]
            ],
            'images' => [
                ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                ['link' => 'https://via.placeholder.com/100/09f/000.png']
            ],
            'mainDetail' => [
                'number' => 'FINDOLOGIC' . $number,
                'active' => $isActive,
                'inStock' => 16,
                'prices' => [
                    [
                        'customerGroupKey' => 'EK',
                        'price' => 99.34,
                    ]
                ]
            ]
        ];

        if (!empty($categories)) {
            $assignedCategories = [];
            foreach ($categories as $category) {
                $assignedCategories[] = ['id' => $category];
            }
            $testArticle['categories'] = $assignedCategories;
        }

        try {
            $resource = Manager::getResource('Article');

            return $resource->create($testArticle);
        } catch (Exception $e) {
            echo sprintf('Exception: %s', $e->getMessage());
        }

        return null;
    }

    private function createTestProductsWithIdAndVendor()
    {
        $testArticles = [
            [
                'id' => 1,
                'name' => 'FindologicArticle1',
                'active' => true,
                'tax' => 19,
                'supplier' => 'FindologicVendor1',
                'categories' => [
                    ['id' => 5]
                ],
                'images' => [
                    ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                    ['link' => 'https://via.placeholder.com/100/09f/000.png']
                ],
                'mainDetail' => [
                    'number' => 'FINDOLOGIC1',
                    'active' => true,
                    'inStock' => 16,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 99.34
                        ]
                    ]
                ]
            ],
            [
                'id' => 2,
                'name' => 'FindologicArticle2',
                'active' => true,
                'tax' => 19,
                'supplier' => 'FindologicVendor2',
                'categories' => [
                    ['id' => 5]
                ],
                'images' => [
                    ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                    ['link' => 'https://via.placeholder.com/100/09f/000.png']
                ],
                'mainDetail' => [
                    'number' => 'FINDOLOGIC2',
                    'active' => true,
                    'inStock' => 16,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 99.34
                        ]
                    ]
                ]
            ],
            [
                'id' => 3,
                'name' => 'FindologicArticle3',
                'active' => true,
                'tax' => 19,
                'supplier' => 'FindologicVendor2',
                'categories' => [
                    ['id' => 5]
                ],
                'images' => [
                    ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                    ['link' => 'https://via.placeholder.com/100/09f/000.png']
                ],
                'mainDetail' => [
                    'number' => 'FINDOLOGIC3',
                    'active' => true,
                    'inStock' => 16,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 99.34
                        ]
                    ]
                ]
            ],
            [
                'id' => 4,
                'name' => 'FindologicArticle4',
                'active' => false,
                'tax' => 19,
                'supplier' => 'FindologicVendor1',
                'categories' => [
                    ['id' => 5]
                ],
                'images' => [
                    ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                    ['link' => 'https://via.placeholder.com/100/09f/000.png']
                ],
                'mainDetail' => [
                    'number' => 'FINDOLOGIC4',
                    'active' => false,
                    'inStock' => 16,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 99.34
                        ]
                    ]
                ]
            ],
            [
                'id' => 5,
                'name' => 'FindologicArticle5',
                'active' => false,
                'tax' => 19,
                'supplier' => 'FindologicVendor1',
                'categories' => [
                    ['id' => 75]
                ],
                'images' => [
                    ['link' => 'https://via.placeholder.com/100/F00/fff.png'],
                    ['link' => 'https://via.placeholder.com/100/09f/000.png']
                ],
                'mainDetail' => [
                    'number' => 'FINDOLOGIC5',
                    'active' => true,
                    'inStock' => 16,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 99.34
                        ]
                    ]
                ],
            ]
        ];

        foreach ($testArticles as $testArticle) {
            try {
                $resource = Manager::getResource('Article');

                $resource->create($testArticle);
            } catch (Exception $e) {
                echo sprintf('Exception: %s', $e->getMessage());
            }
        }
    }

    public function testEmptyValueNotAllowedExceptionIsThrownInExport()
    {
        // Create articles with the provided data to test the export functionality
        $this->createTestProduct('SOMENUMBER', true);
        $findologicArticleFactoryMock = $this->createMock(FindologicArticleFactory::class);
        $findologicArticleFactoryMock->expects($this->once())->method('create')->willThrowException(
            new EmptyValueNotAllowedException()
        );

        Shopware()->Container()->set('fin_search_unified.article_model_factory', $findologicArticleFactoryMock);

        $exported = $this->runExportAndReturnCount();
        $this->assertSame(0, $exported);
    }

    /**
     * Method to run the actual export functionality and parse the xml to return the
     * number of articles returned
     *
     * @return int
     */
    private function runExportAndReturnCount()
    {
        try {
            /** @var ShopwareProcess $shopwareProcess */
            $shopwareProcess = Shopware()->Container()->get('fin_search_unified.shopware_process');
            $shopwareProcess->setShopKey('ABCDABCDABCDABCDABCDABCDABCDABCD');
            $shopwareProcess->setUpExportService();
            $xmlDocument = $shopwareProcess->getFindologicXml(0, 20);

            // Parse the xml and return the count of the products exported
            $xml = new SimpleXMLElement($xmlDocument);

            return (int)$xml->items->attributes()->count;
        } catch (Exception $e) {
            echo sprintf('Exception: %s', $e->getMessage());
        }

        return 0;
    }

    /**
     * Method to run the actual export functionality with a productId and parse the xml or json to return the
     * number of articles or the error JSON string
     *
     * @param int $productId
     *
     * @return int|array<string[]|ExportErrorInformation[]>
     */
    private function runExportAndReturnCountOrErrors($productId = null)
    {
        try {
            /** @var ShopwareProcess $shopwareProcess */
            $shopwareProcess = Shopware()->Container()->get('fin_search_unified.shopware_process');
            $shopwareProcess->setShopKey('ABCDABCDABCDABCDABCDABCDABCDABCD');
            $shopwareProcess->setUpExportService();
            $document = $shopwareProcess->getProductsById($productId);

            if ($shopwareProcess->getExportService()->getErrorCount() > 0) {
                return json_encode([
                    'errors' => [
                        'general' => $shopwareProcess->getExportService()->getGeneralErrors(),
                        'products' => $shopwareProcess->getExportService()->getProductErrors()
                    ]
                ]);
            }

            // Parse the xml and return the count of the products exported
            $xml = new SimpleXMLElement($document);

            return (int)$xml->items->attributes()->count;
        } catch (Exception $e) {
            echo sprintf('Exception: %s', $e->getMessage());
        }

        return 0;
    }
}
