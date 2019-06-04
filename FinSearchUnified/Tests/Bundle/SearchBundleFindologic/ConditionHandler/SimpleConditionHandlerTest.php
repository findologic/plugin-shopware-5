<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\SimpleConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SearchQueryBuilder;
use Shopware\Bundle\SearchBundle\Condition\SimpleCondition;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use Shopware\Components\Test\Plugin\TestCase;

class SimpleConditionHandlerTest extends TestCase
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
        $term = 'ye';
        $handler = new SimpleConditionHandler();
        $handler->generateCondition(
            new SimpleCondition($term),
            $this->querybuilder,
            $this->context
        );

        $params = $this->querybuilder->getParameters();

        $this->assertArrayHasKey('ye', $params, '"query" was not found in querybuilder parameters array');
        $this->assertSame(true, $params['ye'], 'Expected "query" parameter to contain "searchterm"');
    }
}
