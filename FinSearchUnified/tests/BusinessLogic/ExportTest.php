<?php

namespace FinSearchUnified\BusinessLogic;

use FinSearchUnified\XmlInformation;
use Shopware\Components\Api\Manager;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware\Models\Article\Article;

class ExportTest extends TestCase
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
        self::sResetArticles();
    }

    /**
     * Delete all articles
     *
     * @throws \Shopware\Components\Api\Exception\NotFoundException
     * @throws \Shopware\Components\Api\Exception\OrmException
     * @throws \Shopware\Components\Api\Exception\ParameterMissingException
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
     * Data provider for the export test cases with corresponding assertion message
     *
     * @return array
     */
    public function articleProvider()
    {
        return [
            'All active articles' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'expected' => 2,
                'Two articles were expected but %d were returned'
            ],
            '1 active and 1 inactive article' => [
                'Active' => [true, false],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'expected' => 1,
                'Only one article was expected but %d were returned'
            ],
            'All inactive articles' => [
                'Active' => [false, false],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => null,
                'expected' => 0,
                'No articles were expected but %d were returned'
            ],
            'Invalid shopkey' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD',
                'start' => 0,
                'count' => null,
                'expected' => 'UnexpectedValueException',
                'UnexpectedValueException was expected but exception was not thrown'
            ],
            'Return only first article' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 0,
                'count' => 1,
                'expected' => 2,
                '1 out of 2 valid articles were expected to be exported'
            ],
            'Skip first article and return all others' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 1,
                'count' => null,
                'expected' => 1,
                'All except the first article were expected to be exported'
            ],
            'Skip first article and return one other' => [
                'Active' => [true, true],
                'Shopkey' => 'ABCD0815',
                'start' => 1,
                'count' => 1,
                'expected' => 1,
                '1 out of 2 valid articles were expected to be exported'
            ]
        ];
    }

    /**
     * Method to run the export test cases using the data provider
     *
     * @dataProvider articleProvider
     *
     * @param array $isActive
     * @param string $shopkey
     * @param int $start
     * @param int $count
     * @param int|null $expected
     * @param string $errorMessage
     *
     * @throws \Exception
     */
    public function testArticleExport($isActive, $shopkey, $start, $count, $expected, $errorMessage)
    {
        // Create articles with the provided data to test the export functionality
        for ($i = 0; $i < count($isActive); $i++) {
            $this->createTestProduct($i, $isActive[$i]);
        }
        /** @var Export $exportService */
        $exportService = Shopware()->Container()->get('fin_search_unified.business_logic.export');

        /** @var XmlInformation $result */
        $result = $exportService->getXml($shopkey, $start, $count);
        if (is_string($expected)) {
            $this->expectException($expected);
        } else {
            $this->assertSame($expected, $result->count, $errorMessage);
        }
        //$this->assertEquals($expected, $actual, sprintf($errorMessage, $actual));
    }

    /**
     * Method to create test products for the export
     *
     * @param int $number
     * @param bool $isActive
     *
     * @return Article|null
     */
    private function createTestProduct($number, $isActive)
    {
        $testArticle = [
            'name' => 'FindologicArticle' . $number,
            'active' => $isActive,
            'tax' => 19,
            'supplier' => 'Findologic',
            'categories' => [['id' => 12]],
            'images' => [
                ['link' => 'https://via.placeholder.com/300/F00/fff.png'],
                ['link' => 'https://via.placeholder.com/300/09f/000.png'],
            ],
            'mainDetail' => [
                'number' => 'FINDOLOGIC' . $number,
                'active' => $isActive,
                'inStock' => 50,
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
}
