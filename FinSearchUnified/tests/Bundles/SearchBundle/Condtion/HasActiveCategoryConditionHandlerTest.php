<?php

namespace FinSearchUnified\tests\Bundles\SearchBundles\Condition;

use FinSearchUnified\Bundles\SearchBundle\Condition\HasActiveCategoryCondition;
use FinSearchUnified\Bundles\SearchBundleDBAL\Condition\HasActiveCategoryConditionHandler;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Components\Test\Plugin\TestCase;

class HasActiveCategoryConditionHandlerTest extends TestCase
{
    public function testGenerateCondition()
    {
        $factory = Shopware()->Container()->get('shopware_searchdbal.dbal_query_builder_factory');

        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        /** @var QueryBuilder $query */
        $query = $factory->createProductQuery(new Criteria(), $context);

        $handler = new HasActiveCategoryConditionHandler();
        $handler->generateCondition(new HasActiveCategoryCondition(), $query, $context);

        // Get query part to test if the correct join is applied from our condition
        $join = $query->getQueryPart('join');
        $this->assertArrayHasKey('productSArticlesCategoriesRo', $join);
    }
}
