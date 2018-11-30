<?php

namespace FinSearchUnified\tests\Bundles\SearchBundles\Condition;

use FinSearchUnified\BusinessLogic\Export;
use FinSearchUnified\XmlInformation;
use Shopware\Components\Api\Exception\NotFoundException;
use Shopware\Components\Api\Exception\OrmException;
use Shopware\Components\Api\Exception\ParameterMissingException;
use Shopware\Components\Api\Manager;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware\Models\Article\Article;
use Shopware\Models\Category\Category;

class HasActiveCategoryConditionTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'FinSearchUnified' => [
            'ShopKey' => 'ABCD0815'
        ],
    ];

    public static function setUpBeforeClass()
    {
        self::sResetArticles();
    }

    protected function tearDown()
    {
        $categoryRepository = Shopware()->Models()->getRepository('Shopware\Models\Category\Category');

        $categoryModels = $categoryRepository->findBy(['id' => [3, 39]]);
        foreach ($categoryModels as $categoryModel) {
            $categoryModel->setActive(true);
            Shopware()->Models()->persist($categoryModel);
        }
        Shopware()->Models()->flush();

        self::sResetArticles();
    }

    /**
     * Delete all articles
     *
     * @throws NotFoundException
     * @throws OrmException
     * @throws ParameterMissingException
     */
    private static function sResetArticles()
    {
        $repo = Shopware()->Models()->getRepository(Article::class);
        $articles = $repo->findAll();
        $manger = new Manager();
        $resource = $manger->getResource('Article');
        foreach ($articles as $article) {
            $resource->delete($article->getId());
        }
        $resource->flush();
    }

    /**
     * Method to create test products for the export
     *
     * @param int $number
     * @param int $laststock
     * @param int $instock
     * @param int $minpurchase
     * @param array $category
     *
     * @return Article|null
     */
    private function createTestProduct($number, $laststock, $instock, $minpurchase, $category)
    {
        $categoryRepository = Shopware()->Models()->getRepository('Shopware\Models\Category\Category');

        /** @var Category $categoryModel */
        $categoryModel = $categoryRepository->find($category[0]);
        $this->assertInstanceOf('Shopware\Models\Category\Category', $categoryModel,
            'Could not find category for given ID');

        $categoryModel->setActive($category[1]);
        Shopware()->Models()->persist($categoryModel);

        $testArticle = [
            'name' => 'FindologicArticle' . $number,
            'active' => true,
            'tax' => 19,
            'supplier' => 'Findologic',
            'categories' => [
                ['id' => $categoryModel->getId()]
            ],
            'images' => [
                ['link' => 'https://via.placeholder.com/300/F00/fff.png'],
                ['link' => 'https://via.placeholder.com/300/09f/000.png'],
            ],
            'mainDetail' => [
                'number' => 'FINDOLOGIC' . $number,
                'active' => true,
                'inStock' => $instock,
                'lastStock' => $laststock,
                'minPurchase' => $minpurchase,
                'prices' => [
                    [
                        'customerGroupKey' => 'EK',
                        'price' => 99.34,
                    ],
                ]
            ],
        ];

        try {
            $manger = new Manager();
            $resource = $manger->getResource('Article');
            $article = $resource->create($testArticle);

            return $article;
        } catch (\Exception $e) {
            echo sprintf("Exception: %s", $e->getMessage());
        }

        return null;
    }

    /**
     * Data provider for the testing category conditions
     *
     * @return array
     */
    public function conditionsProvider()
    {
        $shopCategoryId = Shopware()->Shop()->getCategory()->getId();

        return [
            'Last stock is false and hideNoInStock is true' => [
                'hideNoInStock' => true,
                'laststock' => 0,
                'instock' => 10,
                'minpuchase' => 10,
                'expected' => 5,
                'categories' => [$shopCategoryId, true],
                'Expected variation to be returned but was not'
            ],
            'Last stock is true and hideNoInStock is true and stock is greater than min purchase' => [
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 5,
                'minpurchase' => 3,
                'expected' => 5,
                'categories' => [$shopCategoryId, true],
                'Expected variation to be returned but was not'
            ],
            'Last stock is true and hideNoInStock is true and stock is less than min purchase' => [
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 3,
                'minpurchase' => 5,
                'expected' => 0,
                'categories' => [$shopCategoryId, true],
                'Expected that variation is not returned'
            ],
            'Last stock is true and hideNoInStock is false and stock is less than min purchase' => [
                'hideNoInStock' => false,
                'laststock' => 1,
                'instock' => 3,
                'minpurchase' => 5,
                'expected' => 5,
                'categories' => [$shopCategoryId, true],
                'Expected that variation is returned'
            ],
            'Only current shop article are considered' => [
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 5,
                'minpurchase' => 3,
                'expected' => 5,
                'categories' => [$shopCategoryId, true],
                'Expected that variation is returned'
            ],
            'Other shop articles are not considered' => [
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 5,
                'minpurchase' => 3,
                'expected' => 5,
                'categories' => [39, true],
                'Expected that variation are not returned'
            ],
            'Only article with active category is considered' => [
                'hideNoInStock' => true,
                'laststock' => 1,
                'instock' => 5,
                'minpurchase' => 3,
                'expected' => 0,
                'categories' => [$shopCategoryId, false],
                'Expected that variation is not returned'
            ]
        ];
    }

    /**
     * @dataProvider conditionsProvider
     *
     * @param bool $hideNoInStock
     * @param bool $laststock
     * @param int $instock
     * @param int $minpurchase
     * @param bool $expected
     * @param array $category
     * @param string $errorMessage
     *
     * @throws \Exception
     */
    public function testIsAvailableCondition(
        $hideNoInStock,
        $laststock,
        $instock,
        $minpurchase,
        $expected,
        $category,
        $errorMessage
    ) {
        // Create articles for testing
        for ($i = 0; $i < 5; $i++) {
            $this->createTestProduct($i, $laststock, $instock, $minpurchase, $category);
        }

        Shopware()->Models()->flush();

        // Create Mock object for Shopware Config
        $config = $this->getMockBuilder('\Shopware_Components_Config')
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();
        $config->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnMap([
                ['hideNoInStock', $hideNoInStock],
                ['ShopKey', 'ABCD0815']
            ]);

        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $config);

        /** @var Export $exportService */
        $exportService = Shopware()->Container()->get('fin_search_unified.business_logic.export');

        /** @var XmlInformation $result */
        $result = $exportService->getXml('ABCD0815');

        $this->assertSame(
            $expected,
            $result->count,
            $errorMessage
        );
    }
}
