<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\SimpleConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SearchQueryBuilder;
use Shopware\Bundle\SearchBundle\Condition\SimpleCondition;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use FinSearchUnified\Tests\TestCase;

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
        $name = 'ye';
        $handler = new SimpleConditionHandler();
        $handler->generateCondition(
            new SimpleCondition($name),
            $this->querybuilder,
            $this->context
        );

        $params = $this->querybuilder->getParameters();

        $this->assertArrayHasKey($name, $params, 'Expected parameter for simple condition not found');
        $this->assertSame(true, $params[$name], 'Expected parameter to be true');
    }
}
