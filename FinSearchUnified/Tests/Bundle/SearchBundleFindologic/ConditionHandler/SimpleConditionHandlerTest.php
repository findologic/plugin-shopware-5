<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Enlight_Controller_Request_RequestHttp;
use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\SimpleConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NewSearchQueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\SearchQueryBuilder;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Condition\SimpleCondition;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;

class SimpleConditionHandlerTest extends TestCase
{
    /**
     * @var NewSearchQueryBuilder
     */
    private $querybuilder;

    /**
     * @var ProductContextInterface
     */
    private $context;

    /**
     * @throws Exception
     */
    protected function setUp()
    {
        parent::setUp();

        $_SERVER['REMOTE_ADDR'] = '192.168.0.1';

        $request = new Enlight_Controller_Request_RequestHttp();
        Shopware()->Front()->setRequest($request);

        // By default, the search page is true
        Shopware()->Session()->offsetSet('isSearchPage', true);
        Shopware()->Config()->ShopKey = 'ABCDABCDABCDABCDABCDABCDABCDABCD';

        $this->querybuilder = new NewSearchQueryBuilder(
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

        $this->assertArrayHasKey('attrib', $params, 'Expected attrib parameter not found');
        $this->assertArrayHasKey($name, $params['attrib'], 'Expected attribute for simple condition not found');
        $this->assertSame('1', current($params['attrib'][$name]), 'Expected parameter to be "1"');
    }
}
