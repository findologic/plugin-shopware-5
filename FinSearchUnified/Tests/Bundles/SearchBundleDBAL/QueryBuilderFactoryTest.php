<?php

namespace FinSearchUnified\Tests\Bundles\SearchBundleDBAL;

use Exception;
use FinSearchUnified\Bundles\SearchBundleDBAL\QueryBuilderFactory;
use Shopware\Bundle\SearchBundle\Condition\CloseoutCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Components\Test\Plugin\TestCase;

class QueryBuilderFactoryTest extends TestCase
{
    /**
     * @var QueryBuilderFactory
     */
    private $criteriaFactory;

    /**
     * @var ContextServiceInterface
     */
    private $contextService;

    protected function setUp()
    {
        parent::setUp();

        $this->criteriaFactory = Shopware()->Container()->get(
            'fin_search_unified.searchdbal.query_builder_factory'
        );

        $this->contextService = Shopware()->Container()->get('shopware_storefront.context_service');
    }

    /**
     * @throws Exception
     */
    public function testCreateQueryWithoutConditions()
    {
        $context = $this->contextService->getShopContext();

        $criteria = new Criteria();
        $query = $this->criteriaFactory->createQuery($criteria, $context);
        $parts = $query->getQueryParts();

        // FROM
        $this->assertArrayHasKey('from', $parts);
        $this->assertSame(
            's_articles',
            $parts['from'][0]['table'],
            'Expected table to be s_articles'
        );
        $this->assertSame(
            'product',
            $parts['from'][0]['alias'],
            'Expected table alias to be product'
        );

        // JOIN
        $this->assertArrayHasKey('join', $parts);
        $this->assertSame(
            'left',
            $parts['join']['product'][0]['joinType'],
            'Expected join to be LEFT JOIN'
        );
        $this->assertSame(
            's_articles_details',
            $parts['join']['product'][0]['joinTable'],
            'Expected join table to be s_article_details'
        );
        $this->assertSame(
            'mainDetail.id = product.main_detail_id',
            $parts['join']['product'][0]['joinCondition'],
            'First join condition is incorrect'
        );
        $this->assertSame('left', $parts['join']['product'][1]['joinType']);
        $this->assertSame('s_articles_details', $parts['join']['product'][1]['joinTable']);
        $this->assertSame(
            'variant.articleID = product.id AND variant.id != product.main_detail_id',
            $parts['join']['product'][1]['joinCondition'],
            'Second join condition is incorrect'
        );

        // WHERE
        $this->assertNull($parts['where'], 'WHERE clause is expected to be missing');

        // GROUP BY
        $this->assertEmpty($parts['groupBy'], 'GROUP BY is expected to be empty');

        // ORDER BY
        $this->assertEmpty($parts['orderBy'], 'ORDER BY is expected to be empty');
    }

    /**
     * @throws Exception
     */
    public function testCreateQueryWithCondition()
    {
        $context = $this->contextService->getShopContext();

        $criteria = new Criteria();
        $criteria->addCondition(new CloseoutCondition());
        $query = $this->criteriaFactory->createQuery($criteria, $context);
        $parts = $query->getQueryParts();

        // FROM
        $this->assertArrayHasKey('from', $parts);
        $this->assertSame(
            's_articles',
            $parts['from'][0]['table'],
            'Expected table to be s_articles'
        );
        $this->assertSame(
            'product',
            $parts['from'][0]['alias'],
            'Expected table alias to be product'
        );

        // JOIN
        $this->assertArrayHasKey('join', $parts);
        $this->assertSame(
            'left',
            $parts['join']['product'][0]['joinType'],
            'Expected join to be LEFT JOIN'
        );
        $this->assertSame(
            's_articles_details',
            $parts['join']['product'][0]['joinTable'],
            'Expected join table to be s_article_details'
        );
        $this->assertSame(
            'mainDetail.id = product.main_detail_id',
            $parts['join']['product'][0]['joinCondition'],
            'First join condition is incorrect'
        );
        $this->assertSame('left', $parts['join']['product'][1]['joinType']);
        $this->assertSame('s_articles_details', $parts['join']['product'][1]['joinTable']);
        $this->assertSame(
            'variant.articleID = product.id AND variant.id != product.main_detail_id',
            $parts['join']['product'][1]['joinCondition'],
            'Second join condition is incorrect'
        );

        // WHERE
        $this->assertNotNull($parts['where'], 'WHERE clause is expected to have expression');
        $this->assertContains(
            '.laststock = 1',
            $parts['where']->__toString(),
            'WHERE clause is not correct'
        );

        // GROUP BY
        $this->assertEmpty($parts['groupBy'], 'GROUP BY is expected to be empty');

        // ORDER BY
        $this->assertEmpty($parts['orderBy'], 'ORDER BY is expected to be empty');
    }

    /**
     * @throws Exception
     */
    public function testCreateQueryWithSorting()
    {
        $context = $this->contextService->getShopContext();

        $criteria = new Criteria();
        $query = $this->criteriaFactory->createQueryWithSorting($criteria, $context);
        $parts = $query->getQueryParts();

        // FROM
        $this->assertArrayHasKey('from', $parts);
        $this->assertSame(
            's_articles',
            $parts['from'][0]['table'],
            'Expected table to be s_articles'
        );
        $this->assertSame(
            'product',
            $parts['from'][0]['alias'],
            'Expected table alias to be product'
        );

        // JOIN
        $this->assertArrayHasKey('join', $parts);
        $this->assertSame(
            'left',
            $parts['join']['product'][0]['joinType'],
            'Expected join to be LEFT JOIN'
        );
        $this->assertSame(
            's_articles_details',
            $parts['join']['product'][0]['joinTable'],
            'Expected join table to be s_article_details'
        );
        $this->assertSame(
            'mainDetail.id = product.main_detail_id',
            $parts['join']['product'][0]['joinCondition'],
            'First join condition is incorrect'
        );
        $this->assertSame('left', $parts['join']['product'][1]['joinType']);
        $this->assertSame('s_articles_details', $parts['join']['product'][1]['joinTable']);
        $this->assertSame(
            'variant.articleID = product.id AND variant.id != product.main_detail_id',
            $parts['join']['product'][1]['joinCondition'],
            'Second join condition is incorrect'
        );

        // WHERE
        $this->assertNull($parts['where'], 'WHERE clause is expected to be missing');

        // GROUP BY
        $this->assertEmpty($parts['groupBy'], 'GROUP BY is expected to be empty');

        // ORDER BY
        $this->assertNotEmpty($parts['orderBy'], 'ORDER BY is expected to be available');
        $this->assertSame(
            'product.id ASC',
            $parts['orderBy'][0],
            'Expected ORDER BY to have ascending product ID'
        );
    }

    public function offsetAndLimitProvider()
    {
        return [
            'With offset and limit' => [1, 5],
            'Without offset or limit' => [null, null],
        ];
    }

    /**
     * @dataProvider offsetAndLimitProvider
     *
     * @param int $offset
     * @param int $limit
     *
     * @throws Exception
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

        // SELECT
        $this->assertNotEmpty($parts['select']);
        $this->assertSame('SQL_CALC_FOUND_ROWS product.id AS __product_id', $parts['select'][0]);
        $this->assertSame('mainDetail.ordernumber AS __main_detail_number', $parts['select'][1]);
        $this->assertSame(
            "GROUP_CONCAT(variant.ordernumber SEPARATOR ', ') AS __variant_numbers",
            $parts['select'][2]
        );

        // GROUP BY
        $this->assertNotEmpty($parts['groupBy'], 'Expected GROUP BY to be applied');
        $this->assertSame(
            'product.id, mainDetail.ordernumber',
            $parts['groupBy'][0],
            'GROUP BY is expected to be on product ID and order number'
        );

        // OFFSET
        $this->assertEquals($offset, $query->getFirstResult());

        // LIMIT
        $this->assertEquals($limit, $query->getMaxResults());
    }
}
