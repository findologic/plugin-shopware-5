<?php

namespace FinSearchUnified\Tests\Bundles\SearchBundleDBAL\ConditionHandler;

use Assert\AssertionFailedException;
use FinSearchUnified\Bundles\SearchBundle\Condition\HasActiveChildOfShopCategoryCondition;
use FinSearchUnified\Bundles\SearchBundleDBAL\ConditionHandler\HasActiveChildOfShopCategoryConditionHandler;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Components\Test\Plugin\TestCase;

class HasActiveChildOfShopCategoryConditionHandlerTest extends TestCase
{
    /**
     * @throws AssertionFailedException
     */
    public function testGenerateCondition()
    {
        $factory = Shopware()->Container()->get('shopware_searchdbal.dbal_query_builder_factory');

        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        /** @var QueryBuilder $query */
        $query = $factory->createProductQuery(new Criteria(), $context);

        $handler = new HasActiveChildOfShopCategoryConditionHandler();
        $handler->generateCondition(
            new HasActiveChildOfShopCategoryCondition(
                Shopware()->Shop()->getCategory()->getId()
            ),
            $query,
            $context
        );

        // Get query part to test if the correct join is applied from our condition
        $join = $query->getQueryPart('join');
        $this->assertArrayHasKey('product_s_articles_categories_ro', $join);
    }
}
