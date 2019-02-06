<?php

namespace FinSearchUnified\Tests\Bundles\SearchBundleDBAL\ConditionHandler;

use Assert\AssertionFailedException;
use FinSearchUnified\Bundles\SearchBundle\Condition\IsChildOfShopCategoryCondition;
use FinSearchUnified\Bundles\SearchBundleDBAL\ConditionHandler\IsChildOfShopCategoryConditionHandler;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Components\Test\Plugin\TestCase;

class IsChildOfShopCategoryConditionHandlerTest extends TestCase
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

        $handler = new IsChildOfShopCategoryConditionHandler();
        $handler->generateCondition(
            new IsChildOfShopCategoryCondition(
                $shopCategoryId
            ),
            $query,
            $context
        );

        $this->assertArrayHasKey('where', $query->getQueryParts(), 'WHERE clause is not applied');
        // Get query part to test if the correct where clause is applied
        $where = $query->getQueryPart('where');
        $this->assertContains(
            'WHERE s_articles_categories_ro.articleID = product.id 
            AND s_articles_categories_ro.categoryID = :shopCategoryId',
            $where->__toString(),
            '"IsChildOfShopCategoryCondition" is not applied correctly'
        );
    }
}
