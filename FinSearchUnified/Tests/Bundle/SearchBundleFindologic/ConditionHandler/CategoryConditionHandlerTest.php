<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\CategoryConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use Shopware\Components\Test\Plugin\TestCase;

class CategoryConditionHandlerTest extends TestCase
{
    /**
     * @var QueryBuilder
     */
    private $querybuilder;

    /**
     * @var ProductContextInterface
     */
    private $context = null;

    /**
     * @throws Exception
     */
    protected function setUp()
    {
        parent::setUp();
        $this->querybuilder = new QueryBuilder(
            Shopware()->Container()->get('http_client'),
            Shopware()->Container()->get('shopware_plugininstaller.plugin_manager'),
            Shopware()->Config()
        );
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $this->context = $contextService->getShopContext();
    }

    public function categoryIdsDataProvider()
    {
        return [
            'Single ID of a category without parents' => [[5], ['Genusswelten']],
            'One Category without parents and one category having parents' => [
                [5, 12],
                ['Genusswelten', 'Genusswelten_Tees und Zubehör_Tees']
            ],
            'Category ID of "1"' => [[1], []],
        ];
    }

    /**
     * @dataProvider categoryIdsDataProvider
     *
     * @param array $categoryIds
     * @param array $expectedCategoryNames
     *
     * @throws Exception
     */
    public function testGenerateCondition($categoryIds, $expectedCategoryNames)
    {
        $handler = new CategoryConditionHandler();
        $handler->generateCondition(
            new CategoryCondition($categoryIds),
            $this->querybuilder,
            $this->context
        );

        $params = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('attrib', $params, 'Parameter "attrib" was not found in the parameters');
        $this->assertArrayHasKey('cat', $params['attrib'], 'Categories are not set in the "attrib" parameter');
        $this->assertEquals(
            $expectedCategoryNames,
            $params['attrib']['cat'],
            sprintf('Expected querybuilder to %s the name
        of the provided category ID', !empty($expectedCategoryNames) ? 'contain' : 'not contain')
        );
    }
}
