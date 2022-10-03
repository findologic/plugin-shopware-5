<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Enlight_Controller_Request_RequestHttp;
use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\SimpleConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\SearchQueryBuilder;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Condition\SimpleCondition;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use Shopware_Components_Config as Config;

class SimpleConditionHandlerTest extends TestCase
{
    /**
     * @var SearchQueryBuilder
     */
    private $querybuilder;

    /**
     * @var ProductContextInterface
     */
    private $context;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $request = new Enlight_Controller_Request_RequestHttp();
        Shopware()->Front()->setRequest($request);

        // By default, the search page is true
        Shopware()->Session()->offsetSet('isSearchPage', true);
        $mockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['getByNamespace', 'get'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig->expects($this->once())
            ->method('getByNamespace')
            ->with('FinSearchUnified', 'ShopKey', null)
            ->willReturn('ABCDABCDABCDABCDABCDABCDABCDABCD');
        Shopware()->Container()->set('config', $mockConfig);

        $this->querybuilder = new SearchQueryBuilder(
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
