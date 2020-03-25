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
use Shopware\Models\Attribute\Article as ArticleAttribute;
use Shopware\Models\Category\Category;

class FindologicArticleModelTest extends TestCase
{
    /** @var FindologicArticleFactory */
    private $articleFactory;

    protected function setUp()
    {
        parent::setUp();
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
            $resource = Manager::getResource('Article');

            return $resource->create($testProductConfiguration);
        } catch (Exception $e) {
            echo sprintf('Exception: %s', $e->getMessage());
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
                        'number' => 'FINDOLOGIC6',
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
            'Article with pseudo empty supplier name' => [
                [
                    'name' => 'FindologicArticle 2',
                    'active' => true,
                    'tax' => 19,
                    'supplier' => ' ',
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
            ],
            'Article with multiple empty spaces in supplier name' => [
                [
                    'name' => 'FindologicArticle 2',
                    'active' => true,
                    'tax' => 19,
                    'supplier' => '   ',
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
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            [],
            [],
            $baseCategory
        );
        $this->assertEquals(get_class($findologicArticle), FindologicArticleModel::class);
    }

    public function emptyAttributeValuesProvider()
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
            'configuratorSet' => [
                'groups' => [
                    [
                        'name' => 'Size',
                        "options" => [
                            [
                                "name" => "S"
                            ],
                            [
                                "name" => "M"
                            ],
                            [
                                "name" => "L"
                            ]
                        ]
                    ]
                ]
            ],
            'variants' => [
                [
                    'isMain' => false,
                    'number' => 'FINDOLOGIC1.1',
                    'inStock' => 2,
                    'active' => true,
                    'configuratorOptions' => [
                        [
                            'group' => 'Size',
                            'option' => 'L'
                        ]
                    ]
                ],
                [
                    'isMain' => false,
                    'number' => 'FINDOLOGIC1.2',
                    'inStock' => 5,
                    'active' => true,
                    'configuratorOptions' => [
                        [
                            'group' => 'Size',
                            'option' => 'M'
                        ]
                    ]
                ],
                [
                    'isMain' => false,
                    'number' => 'FINDOLOGIC1.3',
                    'inStock' => 7,
                    'active' => false,
                    'configuratorOptions' => [
                        [
                            'group' => 'Size',
                            'option' => 'S'
                        ]
                    ]
                ]
            ],
            'filterGroupId' => 1,
            'propertyValues' => [
                [
                    'option' => [
                        'name' => 'size',
                        'filterable' => true
                    ],
                    'value' => ' '
                ],
                [
                    'option' => [
                        'name' => 'color',
                        'filterable' => true
                    ],
                    'value' => ''
                ],
                [
                    'option' => [
                        'name' => 'awesomeness',
                        'filterable' => true
                    ],
                    'value' => '70%'
                ]
            ]
        ];

        return [
            'Empty and pseudo empty option values are not exported' => [
                'articleConfiguration' => $articleConfiguration,
            ]
        ];
    }

    /**
     * @dataProvider emptyAttributeValuesProvider*
     *
     * @param array $articleConfiguration
     *
     * @throws ReflectionException
     */
    public function testEmptyValue(array $articleConfiguration)
    {
        $baseCategory = new Category();
        $baseCategory->setId(100);

        $article = $this->createTestProduct($articleConfiguration);

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

        $this->assertArrayNotHasKey('color', $values);
        $this->assertArrayNotHasKey('size', $values);
        $this->assertArrayHasKey('awesomeness', $values);

        $xmlOrdernumbers = $reflector->getProperty('ordernumbers');
        $xmlOrdernumbers->setAccessible(true);
        $ordernumbers = array_pop($xmlOrdernumbers->getValue($xmlArticle)->getValues());

        $this->assertCount(2, $ordernumbers);
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
            'keywords' => "I'm a simple string,\xC2\xBD,\xC2\x80"
        ];

        $expectedKeywords = ["I'm a simple string", "\xC2\xBD"];

        $baseCategory = new Category();
        $baseCategory->setId(5);

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
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
            $keywords = array_merge(
                $keywords,
                array_map(
                    function ($item) {
                        return $item->getValue();
                    },
                    $value
                )
            );
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
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
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
                        'number' => 'FINDOLOGIC3',
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
        $testArticle = $this->createTestProduct($articleConfiguration);

        $article = $this->createMock(Article::class);
        $detail = $this->createMock(Detail::class);

        // While the method \Shopware\Models\Article\Article::getAttribute does exist until Shopware 5.5,
        // it will only return attributes if they were configured before the upgrade to Shopware 5.3.
        // Since Shopware 5.3, attributes are assigned to the articles main details. Already existing ones aren't
        // touched.
        // \Shopware\Models\Article\Article::getAttribute has been removed in Shopware 5.6 entirely.
        if (is_callable([$testArticle, 'getAttribute'])) {
            $articleAttribute = $this->createMock(ArticleAttribute::class);
            $articleAttribute->expects($this->once())->method('getAttr1')->willReturn($legacyAttribute);
            $article->expects($this->exactly(2))->method('getAttribute')->willReturn($articleAttribute);
        }

        $article->method('getId')->willReturn(1);

        $detailAttribute = $this->createMock(ArticleAttribute::class);
        $detailAttribute->expects($this->once())->method('getAttr1')->willReturn($variantAttribute);

        $detail->expects($this->once())->method('getAttribute')->willReturn($detailAttribute);
        $detail->method('getId')->willReturn(1);

        $article->method('getMainDetail')->willReturn($detail);

        $findologicArticle = $this->articleFactory->create(
            $article,
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
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
        } elseif (is_callable([$article, 'getAttribute'])) {
            $this->assertArrayHasKey('attr1', $values);
            $attr1 = $values['attr1'];
            $this->assertSame($expected, current($attr1->getValues()));
        } elseif (!$variantAttribute) {
            // Since \Shopware\Models\Article\Article::getAttribute has been removed in Shopware 5.6
            // we will not have the attribute in array if the variant attribute is empty or null
            $this->assertArrayNotHasKey('attr1', $values);
        }
    }

    public function emptyValuesDataProvider()
    {
        return [
            'Summary and Description values are empty' => [
                [
                    'name' => 'abdrückklotz-für+butler reifenmontiergerät',
                    'active' => true,
                    'tax' => 19,
                    'supplier' => 'Findologic',
                    'description' => '',
                    'descriptionLong' => '',
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
            ],
        ];
    }

    /**
     * @dataProvider emptyValuesDataProvider
     *
     * @param array $articleConfiguration
     *
     * @throws Exception
     */
    public function testEmptyValuesAreNotExported(array $articleConfiguration)
    {
        $baseCategory = new Category();
        $baseCategory->setId(5);

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            [],
            [],
            $baseCategory
        );

        $xmlArticle = $findologicArticle->getXmlRepresentation();
        $actualSummary = $xmlArticle->getSummary();
        $summary = $actualSummary->getValues();
        $this->assertEmpty($summary);
        $this->assertCount(0, $summary);

        $actualDescription = $xmlArticle->getDescription();
        $description = $actualDescription->getValues();
        $this->assertEmpty($description);
        $this->assertCount(0, $description);
    }

    public function emptyPropertyValueProvider()
    {
        return [
            'Empty properties are not exported' => [
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
                        'shippingtime' => ' ',
                        'prices' => [
                            [
                                'customerGroupKey' => 'EK',
                                'price' => 99.34,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider emptyPropertyValueProvider
     *
     * @param array $articleConfiguration
     *
     * @throws Exception
     */
    public function testEmptyPropertyValue(array $articleConfiguration)
    {
        $baseCategory = new Category();
        $baseCategory->setId(5);

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            [],
            [],
            $baseCategory
        );

        $xmlArticle = $findologicArticle->getXmlRepresentation();

        $reflector = new ReflectionClass(Item::class);
        $properties = $reflector->getProperty('properties');
        $properties->setAccessible(true);
        $values = $properties->getValue($xmlArticle);
        $final = current($values);

        $this->assertArrayNotHasKey('shippingtime', $final);
    }

    public function emptyAttributeValueProvider()
    {
        return [
            'Empty attributes are not exported' => [
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
                        ],
                        'attribute' => [
                            'attr1' => ' '
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider emptyAttributeValueProvider
     *
     * @param array $articleConfiguration
     *
     * @throws Exception
     */
    public function testEmptyAttributeValues(array $articleConfiguration)
    {
        $baseCategory = new Category();
        $baseCategory->setId(5);

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            [],
            [],
            $baseCategory
        );

        $xmlArticle = $findologicArticle->getXmlRepresentation();

        $reflector = new ReflectionClass(Item::class);
        $properties = $reflector->getProperty('attributes');
        $properties->setAccessible(true);
        $values = $properties->getValue($xmlArticle);

        $this->assertArrayNotHasKey('attr1', $values);
    }

    public function booleanValueProvider()
    {
        return [
            'Boolean true should be translated in de_DE language' => [
                'highlight' => true,
                'locale' => 1,
                'expectedValue' => 'Ja'
            ],
            'Boolean true should be translated in en_GB language' => [
                'highlight' => true,
                'locale' => 2,
                'expectedValue' => 'Yes'
            ],
            'Boolean false should be translated in de_DE language' => [
                'highlight' => false,
                'locale' => 1,
                'expectedValue' => 'Nein'
            ],
            'Boolean false should be translated in en_GB language' => [
                'highlight' => false,
                'locale' => 2,
                'expectedValue' => 'No'
            ],
        ];
    }

    /**
     * @dataProvider booleanValueProvider
     *
     * @param bool $highlight
     * @param int $locale
     * @param string $expectedValue
     *
     * @throws ReflectionException
     */
    public function testTranslatedBooleanProperties($highlight, $locale, $expectedValue)
    {
        $articleConfiguration = [
            'name' => 'Sample Article',
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
            'highlight' => $highlight
        ];

        $shop = Manager::getResource('Shop')->getRepository()->find($locale);
        Shopware()->Snippets()->setShop($shop);

        $baseCategory = new Category();
        $baseCategory->setId(5);

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            [],
            [],
            $baseCategory
        );

        $xmlArticle = $findologicArticle->getXmlRepresentation();

        $reflector = new ReflectionClass(Item::class);
        $properties = $reflector->getProperty('properties');
        $properties->setAccessible(true);
        $values = $properties->getValue($xmlArticle);
        $values = current($values);

        $this->assertArrayHasKey('highlight', $values);
        $this->assertSame($expectedValue, $values['highlight']);
    }
}
