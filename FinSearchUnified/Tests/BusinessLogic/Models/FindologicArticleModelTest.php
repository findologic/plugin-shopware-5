<?php

namespace FinSearchUnified\Tests\BusinessLogic\Models;

use Doctrine\ORM\EntityManager;
use Exception;
use FINDOLOGIC\Export\Data\Item;
use FinSearchUnified\BusinessLogic\FindologicArticleFactory;
use FinSearchUnified\BusinessLogic\Models\FindologicArticleModel;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\Tests\Helper\Utility;
use FinSearchUnified\Tests\TestCase;
use ReflectionClass;
use ReflectionException;
use Shopware\Bundle\MediaBundle\MediaService;
use Shopware\Components\Api\Manager;
use Shopware\Components\Api\Resource\Article as ArticleResource;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Detail;
use Shopware\Models\Attribute\Article as ArticleAttribute;
use Shopware\Models\Category\Category;
use Shopware_Components_Config as Config;

class FindologicArticleModelTest extends TestCase
{
    /** @var FindologicArticleFactory */
    private $articleFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->articleFactory = new FindologicArticleFactory();
    }

    protected function tearDown(): void
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
            $manager = Shopware()->Models();

            $resource = new ArticleResource();
            $resource->setManager($manager);

            if (!$manager->isOpen()) {
                $manager->create(
                    $manager->getConnection(),
                    $manager->getConfiguration()
                );
            }

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
        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            [],
            [],
            $articleFromConfiguration->getCategories()->first()
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
     * @dataProvider emptyAttributeValuesProvider
     *
     * @param array $articleConfiguration
     *
     * @throws ReflectionException
     */
    public function testEmptyValue(array $articleConfiguration)
    {
        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCD0815',
            [],
            [],
            $articleFromConfiguration->getCategories()->first()
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

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            [],
            [],
            $articleFromConfiguration->getCategories()->first()
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
            $articleFromConfiguration->getCategories()->first()
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
        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            [],
            [],
            $articleFromConfiguration->getCategories()->first()
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
        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            [],
            [],
            $articleFromConfiguration->getCategories()->first()
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
        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            [],
            [],
            $articleFromConfiguration->getCategories()->first()
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

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            [],
            [],
            $articleFromConfiguration->getCategories()->first()
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

    /**
     * @return array
     */
    public function variantPriceProvider()
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
                'prices' => [
                    [
                        'customerGroupKey' => 'EK',
                        'price' => 130,
                    ],
                ]
            ],
            'configuratorSet' => [
                'groups' => []
            ],
            'variants' => [
                [
                    'isMain' => false,
                    'number' => 'FINDOLOGIC1.1',
                    'active' => true,
                    'inStock' => 0,
                    'lastStock' => false,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 110,
                        ],
                    ],
                    'configuratorOptions' => []
                ],
                [
                    'isMain' => false,
                    'number' => 'FINDOLOGIC1.2',
                    'active' => true,
                    'inStock' => 5,
                    'lastStock' => true,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 120,
                        ],
                    ],
                    'configuratorOptions' => []
                ],
                [
                    'isMain' => false,
                    'number' => 'FINDOLOGIC1.3',
                    'active' => true,
                    'inStock' => 0,
                    'lastStock' => true,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 100,
                        ],
                    ],
                    'configuratorOptions' => []
                ]
            ],
            'filterGroupId' => 1,
        ];

        return [
            'Out of stock variants are ignored' => [
                'articleConfiguration' => $articleConfiguration,
            ]
        ];
    }

    /**
     * @dataProvider variantPriceProvider
     *
     * @param array $articleConfiguration
     *
     * @throws ReflectionException
     */
    public function testMainPriceNotConsideredWhenLastStock(array $articleConfiguration)
    {
        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        // Set lastStock value for Shopware <= 5.3
        if (!method_exists($articleFromConfiguration->getMainDetail(), 'getLastStock')) {
            /** @var Detail $detail*/
            foreach ($articleFromConfiguration->getDetails() as $index => $detail) {
                $article = (new Article())->setLastStock($articleConfiguration['variants'][$index]['lastStock']);
                $detail->setArticle($article);
            }
        }

        $mockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig
            ->method('get')
            ->willReturn(true);

        Shopware()->Container()->set('config', $mockConfig);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCD0815',
            [Shopware()->Shop()->getCustomerGroup()],
            [],
            $articleFromConfiguration->getCategories()->first()
        );

        $xmlArticle = $findologicArticle->getXmlRepresentation();
        $reflector = new ReflectionClass(Item::class);

        $prices = $reflector->getProperty('price');
        $prices->setAccessible(true);
        $price = (float)array_pop($prices->getValue($xmlArticle)->getValues());

        $this->assertEquals(110.00, $price);
    }

    public function testCurrencyFactorIsConsidered()
    {
        $article = Utility::createTestProduct('SOMENUMBER', true);

        Shopware()->Shop()->getCurrency()->setFactor(0.5);

        $findologicArticle = $this->articleFactory->create(
            $article,
            'ABCD0815',
            [Shopware()->Shop()->getCustomerGroup()],
            [],
            $article->getCategories()->first()
        );

        $xmlArticle = $findologicArticle->getXmlRepresentation();
        $reflector = new ReflectionClass(Item::class);

        $prices = $reflector->getProperty('price');
        $prices->setAccessible(true);
        $price = (float)array_pop($prices->getValue($xmlArticle)->getValues());

        $this->assertEquals(49.67, $price);
    }

    public function articleProvider()
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
            'filterGroupId' => 1,
        ];

        return [
            'Categories not in the base category are ignored' => [
                'articleConfiguration' => $articleConfiguration,
            ]
        ];
    }

    /**
     * @dataProvider articleProvider
     *
     * @param array $articleConfiguration
     *
     * @throws Exception
     */
    public function testCrossSellingCategoryNotInBaseCategory(array $articleConfiguration)
    {
        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $baseCategory = new Category();
        $baseCategory->setId(1337);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCD0815',
            [],
            [],
            $baseCategory
        );

        $this->assertNotNull($findologicArticle);
    }

    public function uppercaseCategoryProvider()
    {
        try {
            /** @var ArticleResource $resource */
            $resource = Manager::getResource('Category');
            $category = $resource->getRepository()->find(1337);

            if (!$category) {
                $category = $resource->create([
                    'id' => 1337,
                    'name' => 'Öl',
                    'parent' => '3'
                ]);
            }
        } catch (Exception $e) {
            echo sprintf('Exception: %s', $e->getMessage());
        }

        $articleConfiguration = [
            'name' => 'FindologicArticle 1',
            'active' => true,
            'tax' => 19,
            'supplier' => 'Findologic',
            'categories' => [
                ['id' => 3],
                ['id' => 5],
                ['id' => $category->getId()]
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
            'filterGroupId' => 1,
        ];

        return [
            'Category is exported in lowercase' => [
                'articleConfiguration' => $articleConfiguration,
            ]
        ];
    }

    /**
     * @dataProvider uppercaseCategoryProvider
     *
     * @param array $articleConfiguration
     *
     * @throws Exception
     */
    public function testMultiByteCharactersAreExportedInLowercase($articleConfiguration)
    {
        if (StaticHelper::getShopwareVersion() === '5.2.0') {
            $this->markTestSkipped('Deactivated until the fix in SW-528');
        }

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);
        $baseCategory = new Category();
        $baseCategory->setId(1);

        // Create mock object for Shopware Config and explicitly return the values
        $mockConfig = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig->expects($this->any())
            ->method('offsetGet')
            ->willReturnMap([
                ['routerToLower', true]
            ]);

        Shopware()->Container()->set('config', $mockConfig);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
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
        $cat_urls = $values['cat_url']->getValues();

        $this->assertEquals(
            [
                '/genusswelten',
                '/oel'
            ],
            $cat_urls
        );
    }

    public function categoriesWithSlashProvider()
    {
        try {
            /** @var ArticleResource $resource */
            $resource = Manager::getResource('Category');

            if (!$resource->getRepository()->find(2000)) {
                $resource->create([
                    'id' => 2000,
                    'name' => 'PC / Parts',
                    'parent' => '3',
                ]);
            }
            if (!$resource->getRepository()->find(3000)) {
                $resource->create([
                    'id' => 3000,
                    'name' => 'Processor/Mainboards',
                    'parent' => '2000'
                ]);
            }
        } catch (Exception $e) {
            echo sprintf('Exception: %s', $e->getMessage());
        }

        $articleConfiguration = [
            'name' => 'FindologicArticle 1',
            'active' => true,
            'tax' => 19,
            'supplier' => 'Findologic',
            'categories' => [
                ['id' => 3],
                ['id' => 5],
                ['id' => 3000]
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
            'filterGroupId' => 1,
        ];

        return [
            'Category is exported correctly' => [
                'articleConfiguration' => $articleConfiguration,
            ]
        ];
    }

    /**
     * @dataProvider categoriesWithSlashProvider
     *
     * @param array $articleConfiguration
     *
     * @throws Exception
     */
    public function testCategoryNamesWithSlashesAreExportedCorrectly($articleConfiguration)
    {
        if (StaticHelper::getShopwareVersion() === '5.2.0') {
            $this->markTestSkipped('Deactivated until the fix in SW-528');
        }

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);
        $baseCategory = new Category();
        $baseCategory->setId(1);

        // Create mock object for Shopware Config and explicitly return the values
        $mockConfig = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig->expects($this->any())
            ->method('offsetGet')
            ->willReturnMap([
                ['routerToLower', true]
            ]);

        Shopware()->Container()->set('config', $mockConfig);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
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
        $cat_urls = $values['cat_url']->getValues();

        $this->assertEquals(
            [
                '/genusswelten',
                '/pc-parts/processormainboards',
                '/pc-parts'
            ],
            $cat_urls
        );
    }

    public function productsWithPseudoSalesProvider()
    {
        return [
            'Pseudo sale value of 0 is not exported' => [
                'pseudoSales' => 0,
                'expectedSalesFrequency' => 1
            ],
            'Pseudo sale value of 10 is exported' => [
                'pseudoSales' => 10,
                'expectedSalesFrequency' => 10
            ]
        ];
    }

    /**
     * @dataProvider productsWithPseudoSalesProvider
     *
     * @param int $pseudoSales
     * @param int $expectedSalesFrequency
     *
     */
    public function testPseudoSalesAreExportedCorrectly($pseudoSales, $expectedSalesFrequency)
    {
        $articleConfiguration = [
            'name' => 'FindologicArticle 1',
            'active' => true,
            'tax' => 19,
            'supplier' => 'Findologic',
            'categories' => [
                ['id' => 3],
                ['id' => 5]
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
            'pseudoSales' => $pseudoSales
        ];

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $baseCategory = new Category();
        $baseCategory->setId(1);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCD0815',
            [],
            [],
            $baseCategory
        );

        $xmlArticle = $findologicArticle->getXmlRepresentation();

        $reflector = new ReflectionClass(Item::class);
        $salesFrequency = $reflector->getProperty('salesFrequency');
        $salesFrequency->setAccessible(true);
        $values = $salesFrequency->getValue($xmlArticle);

        $this->assertEquals(
            ['' => $expectedSalesFrequency],
            $values->getValues()
        );
    }

    public function testCoverImageIsExportedAsFirstImage()
    {
        $expectedCoverImageUrl = 'http://localhost/media/image/a7/60/e2/Muensterlaender_Lagerkorn_Imagefoto.jpg';
        $articleConfiguration = [
            'name' => 'FindologicArticle 1',
            'active' => true,
            'tax' => 19,
            'supplier' => 'Findologic',
            'categories' => [
                ['id' => 3],
                ['id' => 5]
            ],
            // Media IDs are from the Shopware Test data
            'images' => [
                [
                    'mediaId' => 2, // Muensterlaender_Lagerkorn_Ballons_Hochformat
                    'main' => 0,
                    'position' => 1,
                ],
                [
                    'mediaId' => 3, // Muensterlaender_Lagerkorn_Imagefoto
                    'main' => 1,
                    'position' => 2,
                ],
                [
                    'mediaId' => 4, // Muensterlaender_Lagerkorn_Produktion
                    'main' => 0,
                    'position' => 3,
                ],
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
        ];

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $baseCategory = new Category();
        $baseCategory->setId(1);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCD0815',
            [],
            [],
            $baseCategory
        );

        $xmlArticle = $findologicArticle->getXmlRepresentation();

        $reflector = new ReflectionClass(Item::class);
        $imagesProperty = $reflector->getProperty('images');
        $imagesProperty->setAccessible(true);
        $images = $imagesProperty->getValue($xmlArticle);
        $images = array_pop($images);

        $this->assertEquals(
            [
                $expectedCoverImageUrl,
                'http://localhost/media/image/88/ee/bf/Muensterlaender_Lagerkorn_Imagefoto_200x200.jpg',
                'http://localhost/media/image/ab/7f/4f/Muensterlaender_Lagerkorn_Ballons_Hochformat.jpg',
                'http://localhost/media/image/70/db/7d/Muensterlaender_Lagerkorn_Ballons_Hochformat_200x200.jpg',
                'http://localhost/media/image/79/79/5b/Muensterlaender_Lagerkorn_Produktion.jpg',
                'http://localhost/media/image/ca/d6/a9/Muensterlaender_Lagerkorn_Produktion_200x200.jpg'
            ],
            array_map(function ($image) {
                return $image->getUrl();
            }, $images)
        );
    }

    public function testImageUrlsWithSpecialCharactersAreEncoded()
    {
        $actualImageUrl = 'https://via.placeholder.com/300/F00/fff/test²!Üä´°.png';
        $expectedImageUrl = 'https://via.placeholder.com/300/F00/fff/test%C2%B2%21%C3%9C%C3%A4%C2%B4%C2%B0.png';
        $expectedThumbnailUrl = $expectedImageUrl;
        $articleConfiguration = [
            'name' => 'FindologicArticle 1',
            'active' => true,
            'tax' => 19,
            'supplier' => 'Findologic',
            'categories' => [
                ['id' => 3],
                ['id' => 5]
            ],
            'images' => [
                ['link' => $actualImageUrl],
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
        ];

        $articleFromConfiguration = $this->createTestProduct($articleConfiguration);

        $baseCategory = new Category();
        $baseCategory->setId(1);

        $mockMediaService = $this->getMockBuilder(MediaService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockMediaService
            ->method('getUrl')
            ->willReturn($actualImageUrl);

        Shopware()->Container()->set('shopware_media.media_service', $mockMediaService);

        $findologicArticle = $this->articleFactory->create(
            $articleFromConfiguration,
            'ABCD0815',
            [],
            [],
            $baseCategory
        );

        $xmlArticle = $findologicArticle->getXmlRepresentation();

        $reflector = new ReflectionClass(Item::class);
        $imagesProperty = $reflector->getProperty('images');
        $imagesProperty->setAccessible(true);
        $images = $imagesProperty->getValue($xmlArticle);
        $images = array_pop($images);

        $this->assertEquals(
            [
                $expectedImageUrl,
                $expectedThumbnailUrl
            ],
            array_map(function ($image) {
                return $image->getUrl();
            }, $images)
        );
    }
}
