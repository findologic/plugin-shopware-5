<?php

namespace FinSearchUnified\Tests\Bundles\SearchBundleDBAL;

use FinSearchUnified\Bundles\SearchBundleDBAL\QueryBuilderFactory;
use FinSearchUnified\Tests\Helper\Utility;
use Shopware\Bundle\SearchBundle\Condition\ProductIdCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Components\Test\Plugin\TestCase;

class QueryBuilderFactoryTest extends TestCase
{
    /** @var QueryBuilderFactory */
    private $criteriaFactory;

    /** @var ContextServiceInterface $contextService */
    private $contextService;

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        Utility::sResetArticles();
    }

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        $manager = new \Shopware\Components\Api\Manager();
        $resource = $manager->getResource('Article');

        for ($i = 0; $i < 10; $i++) {
            $testArticle = [
                'name' => 'FindologicArticle' . $i,
                'active' => true,
                'tax' => 19,
                'lastStock' => true,
                'supplier' => 'Findologic',
                'categories' => [
                    ['id' => 5]
                ],
                'mainDetail' => [
                    'number' => 'FINDOLOGIC' . $i,
                    'active' => true,
                    'inStock' => 87,
                    'lastStock' => true,
                    'minPurchase' => 1,
                    'prices' => [
                        [
                            'customerGroupKey' => 'EK',
                            'price' => 99.34,
                        ],
                    ]
                ],
            ];

            try {
                $article = $resource->create($testArticle);

                return $article;
            } catch (\Exception $e) {
                echo sprintf("Exception: %s", $e->getMessage());
            }
        }
    }

    protected function setUp()
    {
        parent::setUp();

        $this->criteriaFactory = Shopware()->Container()->get(
            'fin_search_unified.searchdbal.query_builder_factory'
        );

        $this->contextService = Shopware()->Container()->get('shopware_storefront.context_service');
    }

    /**
     * @throws \Exception
     */
    public function testCreateQueryWithoutConditions()
    {
        $context = $this->contextService->getShopContext();

        $criteria = new Criteria();
        $query = $this->criteriaFactory->createQuery($criteria, $context);
        $parts = $query->getQueryParts();
        // from, left join
        $this->assertArrayHasKey('from', $parts);
        $this->assertSame('s_articles', $parts['from'][0]['table']);
        $this->assertSame('product', $parts['from'][0]['alias']);

        $this->assertArrayHasKey('join', $parts);
        $this->assertSame('left', $parts['join']['product'][0]['joinType']);
        $this->assertSame('s_articles_details', $parts['join']['product'][0]['joinTable']);
        $this->assertSame(
            'mainDetail.id = product.main_detail_id',
            $parts['join']['product'][0]['joinCondition']
        );

        $this->assertSame('left', $parts['join']['product'][1]['joinType']);
        $this->assertSame('s_articles_details', $parts['join']['product'][1]['joinTable']);
        $this->assertSame(
            'variant.articleID = product.id AND variant.id != product.main_detail_id',
            $parts['join']['product'][1]['joinCondition']
        );

        $this->assertNull($parts['where']);
        $this->assertEmpty($parts['groupBy']);
        $this->assertEmpty($parts['orderBy']);
    }

    /**
     * @throws \Exception
     */
    public function testCreateQueryWithProductIDCondition()
    {
        $context = $this->contextService->getShopContext();

        $criteria = new Criteria();
        $criteria->addCondition(new ProductIdCondition([1]));
        $query = $this->criteriaFactory->createQuery($criteria, $context);
        $parts = $query->getQueryParts();

        // from, left join, where is ProductIdConditionalHandler
        $this->assertArrayHasKey('from', $parts);
        $this->assertSame('s_articles', $parts['from'][0]['table']);
        $this->assertSame('product', $parts['from'][0]['alias']);

        $this->assertArrayHasKey('join', $parts);
        $this->assertSame('left', $parts['join']['product'][0]['joinType']);
        $this->assertSame('s_articles_details', $parts['join']['product'][0]['joinTable']);
        $this->assertSame(
            'mainDetail.id = product.main_detail_id',
            $parts['join']['product'][0]['joinCondition']
        );

        $this->assertSame('left', $parts['join']['product'][1]['joinType']);
        $this->assertSame('s_articles_details', $parts['join']['product'][1]['joinTable']);
        $this->assertSame(
            'variant.articleID = product.id AND variant.id != product.main_detail_id',
            $parts['join']['product'][1]['joinCondition']
        );

        $this->assertNull($parts['where']);
        $this->assertEmpty($parts['groupBy']);
        $this->assertEmpty($parts['orderBy']);
    }

    public function testCreateQueryWithSorting()
    {
        $context = $this->contextService->getShopContext();

        $criteria = new Criteria();
        $query = $this->criteriaFactory->createQueryWithSorting($criteria, $context);
        $parts = $query->getQueryParts();
        // order by
        $this->assertArrayHasKey('from', $parts);
        $this->assertSame('s_articles', $parts['from'][0]['table']);
        $this->assertSame('product', $parts['from'][0]['alias']);

        $this->assertArrayHasKey('join', $parts);
        $this->assertSame('left', $parts['join']['product'][0]['joinType']);
        $this->assertSame('s_articles_details', $parts['join']['product'][0]['joinTable']);
        $this->assertSame(
            'mainDetail.id = product.main_detail_id',
            $parts['join']['product'][0]['joinCondition']
        );

        $this->assertSame('left', $parts['join']['product'][1]['joinType']);
        $this->assertSame('s_articles_details', $parts['join']['product'][1]['joinTable']);
        $this->assertSame(
            'variant.articleID = product.id AND variant.id != product.main_detail_id',
            $parts['join']['product'][1]['joinCondition']
        );

        $this->assertNull($parts['where']);
        $this->assertEmpty($parts['groupBy']);

        $this->assertNotEmpty($parts['orderBy']);
        $this->assertSame('product.id ASC', $parts['orderBy'][0]);
    }

    public function offsetAndLimitProvider()
    {
        return [
            'with offset and limit' => [1, 5],
            'without offset or limit' => [null, null],
        ];
    }

    /**
     * @dataProvider offsetAndLimitProvider
     *
     * @param int $offset
     * @param int $limit
     */
    public function testCreateProductQuery($offset, $limit)
    {
        $context = $this->contextService->getShopContext();

        $criteria = new Criteria();
        if ($offset !== null) {
            $criteria->offset($offset);
        }
        if ($limit !== null) {
            $criteria->limit($limit);
        }

        $query = $this->criteriaFactory->createProductQuery($criteria, $context);
        $parts = $query->getQueryParts();

        $this->assertNotEmpty($parts['select']);
        $this->assertSame('SQL_CALC_FOUND_ROWS product.id AS __product_id', $parts['select'][0]);
        $this->assertSame('mainDetail.ordernumber AS __main_detail_number', $parts['select'][1]);
        $this->assertSame("GROUP_CONCAT(variant.ordernumber SEPARATOR ', ') AS __variant_numbers", $parts['select'][2]);

        $this->assertNotEmpty($parts['groupBy']);
        $this->assertSame('product.id, mainDetail.ordernumber', $parts['groupBy'][0]);
        $this->assertEquals($offset, $query->getFirstResult());
        $this->assertEquals($limit, $query->getMaxResults());
    }
}
