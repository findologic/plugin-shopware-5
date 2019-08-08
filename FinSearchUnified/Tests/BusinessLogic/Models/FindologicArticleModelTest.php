<?php

use FinSearchUnified\BusinessLogic\FindologicArticleFactory;
use FinSearchUnified\BusinessLogic\Models\FindologicArticleModel;
use FinSearchUnified\Tests\Helper\Utility;
use FinSearchUnified\Tests\TestCase;
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
}
