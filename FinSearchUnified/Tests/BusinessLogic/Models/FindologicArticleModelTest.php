<?php

namespace FinSearchUnified\Tests\BusinessLogic\Models;

use Exception;
use FINDOLOGIC\Export\Data\Item;
use FinSearchUnified\BusinessLogic\FindologicArticleFactory;
use FinSearchUnified\BusinessLogic\Models\FindologicArticleModel;
use FinSearchUnified\Tests\Helper\Utility;
use FinSearchUnified\Tests\TestCase;
use ReflectionClass;
use ReflectionException;
use Shopware\Components\Api\Manager;
use Shopware\Components\Api\Resource\Article as ArticleResource;
use Shopware\Models\Article\Article;
use Shopware\Models\Category\Category;

class FindologicArticleModelTest extends TestCase
{
    /** @var Manager */
    private $manager;

    /** @var FindologicArticleFactory */
    private $articleFactory;

    protected function setUp()
    {
        parent::setUp();
        $this->manager = new Manager();
        $this->articleFactory = new FindologicArticleFactory();
    }

    protected function tearDown()
    {
        parent::tearDown();
        Utility::sResetArticles();
    }

    /**
     * Method to create test products for the export
     *
     * @param array $testProductConfiguration The configuration of the test product which is to be created.
     *
     * @return Article|null
     */
    private function createTestProduct(array $testProductConfiguration)
    {
        try {
            /** @var ArticleResource $resource */
            $resource = $this->manager->getResource('Article');
            $article = $resource->create($testProductConfiguration);

            return $article;
        } catch (Exception $e) {
            echo sprintf("Exception: %s", $e->getMessage());
        }

        return null;
    }

    public function articleSupplierProvider()
    {
        return [
            'Article with normal supplier name' => [
                [
                    'name' => 'FindologicArticle 1',
                    'active' => true,
                    'tax' => 19,
                    'supplier' => 'Findologic',
                    'categories' => [
                        ['id' => 3],
                        ['id' => 5],
                    ],
                    'images' => [
                        ['link' => 'https://via.placeholder.com/300/F00/fff.png'],
                        ['link' => 'https://via.placeholder.com/300/09f/000.png'],
                    ],
                    'mainDetail' => [
                        'number' => 'FINDOLOGIC1',
                        'active' => true,
                        'inStock' => 16,
                        'prices' => [
                            [
                                'customerGroupKey' => 'EK',
                                'price' => 99.34,
                            ],
                        ]
                    ],
                ]
            ],
            'Article with supplier name space' => [
                [
                    'name' => 'FindologicArticle 2',
                    'active' => true,
                    'tax' => 19,
                    'supplier' => " ",
                    'categories' => [
                        ['id' => 3],
                        ['id' => 5],
                    ],
                    'images' => [
                        ['link' => 'https://via.placeholder.com/300/F00/fff.png'],
                        ['link' => 'https://via.placeholder.com/300/09f/000.png'],
                    ],
                    'mainDetail' => [
                        'number' => 'FINDOLOGIC2',
                        'active' => true,
                        'inStock' => 16,
                        'prices' => [
                            [
                                'customerGroupKey' => 'EK',
                                'price' => 99.34,
                            ],
                        ]
                    ],
                ]
            ]
        ];
    }

    /**
     * Method to run the export test cases using the data provider,
     * to check if the suppliers with empty names are not being exported.
     *
     * @dataProvider articleSupplierProvider
     *
     * @param array $articleConfiguration The article configuration with the corresponding supplier.
     *
     * @throws Exception
     */
    public function testEmptySuppliersAreSkipped(array $articleConfiguration)
    {
        $baseCategory = new Category();
        $baseCategory->setId(100);

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCD0815',
            [],
            [],
            $baseCategory
        );
        $this->assertEquals(get_class($findologicArticle), FindologicArticleModel::class);
    }

    /**
     * @dataProvider articlesWithAttributeProvider
     *
     * @param array $articleConfiguration
     * @param string $expected
     *
     * @throws ReflectionException
     */
    public function testArticleWithAttributes(array $articleConfiguration, $expected)
    {
        $baseCategory = new Category();
        $baseCategory->setId(5);

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCD0815',
            [],
            [],
            $baseCategory
        );

        $xmlArticle = $findologicArticle->getXmlRepresentation();

        $reflector = new ReflectionClass(Item::class);
        $properties = $reflector->getProperty('properties');
        $properties->setAccessible(true);
        $properties = $properties->getValue($xmlArticle);
        $propertiesArray = current($properties);

        $this->assertArrayHasKey('attr1', $propertiesArray);
        $this->assertSame($expected, $propertiesArray['attr1']);
    }

    public function articlesWithAttributeProvider()
    {
        return [
            'Article Attribute' => [
                [
                    'name' => 'FindologicArticle 1',
                    'active' => true,
                    'tax' => 19,
                    'supplier' => 'Findologic',
                    'categories' => [
                        ['id' => 3],
                        ['id' => 5],
                    ],
                    'images' => [
                        ['link' => 'https://via.placeholder.com/300/F00/fff.png'],
                        ['link' => 'https://via.placeholder.com/300/09f/000.png'],
                    ],
                    'mainDetail' => [
                        'number' => 'FINDOLOGIC1',
                        'active' => true,
                        'inStock' => 16,
                        'prices' => [
                            [
                                'customerGroupKey' => 'EK',
                                'price' => 99.34,
                            ],
                        ]
                    ],
                    'attribute' => [
                        'attr1' => 'Article Attribute'
                    ]
                ],
                'Article Attribute'
            ],
            'Variant Attribute' => [
                [
                    'name' => 'FindologicArticle 2',
                    'active' => true,
                    'tax' => 19,
                    'supplier' => 'Findologic',
                    'categories' => [
                        ['id' => 3],
                        ['id' => 5],
                    ],
                    'images' => [
                        ['link' => 'https://via.placeholder.com/300/F00/fff.png'],
                        ['link' => 'https://via.placeholder.com/300/09f/000.png'],
                    ],
                    'mainDetail' => [
                        'number' => 'FINDOLOGIC2',
                        'active' => true,
                        'inStock' => 16,
                        'prices' => [
                            [
                                'customerGroupKey' => 'EK',
                                'price' => 99.34,
                            ],
                        ],
                        'attribute' => [
                            'attr1' => 'Variant Attribute'
                        ]
                    ]
                ],
                'Variant Attribute'
            ]
        ];
    }
}
