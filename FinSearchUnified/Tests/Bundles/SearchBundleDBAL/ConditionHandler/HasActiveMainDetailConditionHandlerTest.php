<?php

namespace FinSearchUnified\Tests\Bundles\SearchBundleDBAL\ConditionHandler;

use FinSearchUnified\Bundles\SearchBundle\Condition\HasActiveMainDetailCondition;
use FinSearchUnified\Bundles\SearchBundleDBAL\ConditionHandler\HasActiveMainDetailConditionHandler;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Components\Test\Plugin\TestCase;

class HasActiveMainDetailConditionHandlerTest extends TestCase
{
    public function testGenerateCondition()
    {
        $factory = Shopware()->Container()->get('fin_search_unified.searchdbal.query_builder_factory');

        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        /** @var QueryBuilder $query */
        $query = $factory->createProductQuery(new Criteria(), $context);

        $handler = new HasActiveMainDetailConditionHandler();
        $handler->generateCondition(new HasActiveMainDetailCondition(), $query, $context);

        $parts = $query->getQueryParts();
        $this->assertArrayHasKey('where', $parts, 'WHERE clause is missing');
        $this->assertContains(
            '(SELECT COUNT(*) 
            FROM s_articles_details 
            WHERE s_articles_details.id = product.main_detail_id 
            AND s_articles_details.active = 1) > 0',
            $parts['where']->__toString(),
            'WHERE clause expects main detail article to be active'
        );
    }
}
