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
use Shopware\Models\Article\Detail;
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

    public function testArticleKeywords()
    {
        $articleConfiguration = [
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
            'keywords' => "I'm a simple string,\xC2\xBD,\xC2\x80"
        ];

        $expectedKeywords = ["I'm a simple string", "\xC2\xBD"];

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
        $properties = $reflector->getProperty('keywords');
        $properties->setAccessible(true);

        $keywords = [];

        foreach ($properties->getValue($xmlArticle)->getValues() as $value) {
            $keywords = array_merge($keywords, array_map(function ($item) {
                return $item->getValue();
            }, $value));
        }

        $this->assertSame($expectedKeywords, $keywords);
    }

    /**
     * @dataProvider articleSEOUrlProvider
     *
     * @param array $articleConfiguration
     * @param string $expectedUrl
     *
     * @throws ReflectionException
     */
    public function testArticleWithSEOUrl(array $articleConfiguration, $expectedUrl)
    {
        $baseCategory = new Category();
        $baseCategory->setId(5);

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $shop = Manager::getResource('Shop')->getRepository()->find(1);
        $shop->registerResources();

        Shopware()->Modules()->RewriteTable()->sInsertUrl(
            'sViewport=detail&sArticle=' . $articleFromConfiguration->getId(),
            $articleFromConfiguration->getName() . '/'
        );

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCD0815',
            [],
            [],
            $baseCategory
        );

        $xmlArticle = $findologicArticle->getXmlRepresentation();

        $reflector = new ReflectionClass(Item::class);
        $properties = $reflector->getProperty('url');
        $properties->setAccessible(true);
        $values = $properties->getValue($xmlArticle);
        $actualUrl = current($values->getValues());

        $this->assertSame($expectedUrl, $actualUrl);
    }

    public function articleSEOUrlProvider()
    {
        $host = Shopware()->Shop()->getHost();

        return [
            'SEO URL with special characters' => [
                [
                    'name' => 'abdrückklotz-für+butler reifenmontiergerät',
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
                        ]
                    ],
                ],
                sprintf('http://%s/abdr%%C3%%BCckklotz-f%%C3%%BCr%%2Bbutler%%20reifenmontierger%%C3%%A4t/', $host)
            ],
            'SEO URL without special characters' => [
                [
                    'name' => 'Reifenmontage',
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
                        ]
                    ],
                ],
                sprintf('http://%s/reifenmontage/', $host)
            ]
        ];
    }

    public function freeTextFieldArticleProvider()
    {
        return [
            'Both attribute and legacy attribute is empty' => [
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
                        ],
                        'attribute' => [
                            'attr1' => ''
                        ]
                    ],
                    'attribute' => [
                        'attr1' => ''
                    ]
                ],
                null
            ],
            'Both attribute and legacy attribute has value' => [
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
                            'attr1' => 'Attribute'
                        ]
                    ],
                    'attribute' => [
                        'attr1' => 'Legacy'
                    ]
                ],
                'Attribute'
            ],
            'Attribute is empty and only legacy attribute has value' => [
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
                            'attr1' => ''
                        ]
                    ],
                    'attribute' => [
                        'attr1' => 'Legacy'
                    ]
                ],
                'Legacy'
            ],
            'Attribute has value and legacy attribute is empty' => [
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
                            'attr1' => 'Attribute'
                        ]
                    ],
                    'attribute' => [
                        'attr1' => ''
                    ]
                ],
                'Attribute'
            ],
            'Attribute is null but legacy attribute has value' => [
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
                        ]
                    ],
                    'attribute' => [
                        'attr1' => 'Legacy'
                    ]
                ],
                'Legacy'
            ],
            'Attribute has value but legacy attribute is null' => [
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
                            'attr1' => 'Attribute'
                        ]
                    ]
                ],
                'Attribute'
            ]
        ];
    }

    /**
     * @dataProvider freeTextFieldArticleProvider
     *
     * @param array $articleConfiguration
     * @param string $expected
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function testArticleFreeTextFieldAttributes(array $articleConfiguration, $expected)
    {
        $legacyAttribute = $articleConfiguration['attribute']['attr1'];
        $variantAttribute = $articleConfiguration['mainDetail']['attribute']['attr1'];
        $baseCategory = new Category();
        $baseCategory->setId(100);

        // We will create the article in the database here for bypassing the setUpStruct method in model constructor
        // but will use the mock for actual attribute testing
        $this->createTestProduct($articleConfiguration);

        $article = $this->createMock(Article::class);
        $detail = $this->createMock(Detail::class);

        $articleAttribute = $this->createMock(\Shopware\Models\Attribute\Article::class);
        $articleAttribute->method('getAttr1')->willReturn($legacyAttribute);

        $detailAttribute = $this->createMock(\Shopware\Models\Attribute\Article::class);
        $detailAttribute->method('getAttr1')->willReturn($variantAttribute);

        $article->method('getAttribute')->willReturn($articleAttribute);
        $article->method('getId')->willReturn(1);

        $detail->method('getAttribute')->willReturn($detailAttribute);
        $detail->method('getId')->willReturn(1);

        $article->method('getMainDetail')->willReturn($detail);

        $findologicArticle = $this->articleFactory->create(
            $article,
            'ABCD0815',
            [],
            [],
            $baseCategory
        );

        $xmlArticle = $findologicArticle->getXmlRepresentation();

        $reflector = new ReflectionClass(Item::class);
        $attributes = $reflector->getProperty('attributes');
        $attributes->setAccessible(true);
        $values = $attributes->getValue($xmlArticle);

        if ($expected === null) {
            $this->assertArrayNotHasKey('attr1', $values);
        } else {
            $this->assertArrayHasKey('attr1', $values);
            $attr1 = $values['attr1'];
            $this->assertSame($expected, current($attr1->getValues()));
        }
    }
}
