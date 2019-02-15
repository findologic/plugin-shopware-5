<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleDBAL\ConditionHandler;

use FinSearchUnified\Bundle\SearchBundle\Condition\IsActiveProductCondition;
use FinSearchUnified\Bundle\SearchBundleDBAL\ConditionHandler\IsActiveProductConditionHandler;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Components\Test\Plugin\TestCase;

class IsActiveProductConditionHandlerTest extends TestCase
{
    public function testGenerateCondition()
    {
        $factory = Shopware()->Container()->get('fin_search_unified.searchdbal.query_builder_factory');

        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        /** @var QueryBuilder $query */
        $query = $factory->createProductQuery(new Criteria(), $context);

        $handler = new IsActiveProductConditionHandler();
        $handler->generateCondition(new IsActiveProductCondition(), $query, $context);

        $parts = $query->getQueryParts();
        $this->assertArrayHasKey('where', $parts, 'WHERE clause is missing');
        $this->assertContains(
            'product.active = 1',
            $parts['where']->__toString(),
            'WHERE clause expects product to be active'
        );
    }
}
