<?php

namespace FinSearchUnified\Tests\Bundles\SearchBundleDBAL\ConditionHandler;

use Assert\AssertionFailedException;
use FinSearchUnified\Bundles\SearchBundle\Condition\HasActiveCategoryCondition;
use FinSearchUnified\Bundles\SearchBundleDBAL\ConditionHandler\HasActiveCategoryConditionHandler;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Components\Test\Plugin\TestCase;

class HasActiveCategoryConditionHandlerTest extends TestCase
{
    /**
     * @throws AssertionFailedException
     */
    public function testGenerateCondition()
    {
        $shopCategoryId = 5;
        $factory = Shopware()->Container()->get('shopware_searchdbal.dbal_query_builder_factory');

        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        /** @var QueryBuilder $query */
        $query = $factory->createProductQuery(new Criteria(), $context);

        $handler = new HasActiveCategoryConditionHandler();
        $handler->generateCondition(
            new HasActiveCategoryCondition($shopCategoryId),
            $query,
            $context
        );

        $this->assertArrayHasKey('where', $query->getQueryParts(), 'WHERE clause is not applied');
        // Get query part to test if the correct WHERE clause is applied from our condition
        $where = $query->getQueryPart('where');
        $this->assertContains(
            'WHERE s_articles_categories_ro.articleID = product.id 
            AND s_categories.active = 1 
            AND s_articles_categories_ro.categoryID != :shopCategoryId',
            $where->__toString(),
            '"HasActiveCategoryCondition" is not applied correctly'
        );
    }
}
