<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\SearchTermConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SearchQueryBuilder;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Condition\SearchTermCondition;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;

class SearchTermConditionHandlerTest extends TestCase
{
    /**
     * @var SearchQueryBuilder
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
        $this->querybuilder = new SearchQueryBuilder(
            Shopware()->Container()->get('http_client'),
            Shopware()->Container()->get('shopware_plugininstaller.plugin_manager'),
            Shopware()->Config()
        );
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $this->context = $contextService->getShopContext();
    }

    /**
     * @throws Exception
     */
    public function testGenerateCondition()
    {
        $term = 'searchterm';
        $handler = new SearchTermConditionHandler();
        $handler->generateCondition(
            new SearchTermCondition($term),
            $this->querybuilder,
            $this->context
        );

        $params = $this->querybuilder->getParameters();

        $this->assertArrayHasKey('query', $params, '"query" was not found in querybuilder parameters array');
        $this->assertSame($term, $params['query'], 'Expected "query" parameter to contain "searchterm"');
    }
}
