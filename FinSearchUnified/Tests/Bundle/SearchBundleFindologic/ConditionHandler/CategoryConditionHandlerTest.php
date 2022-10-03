<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Enlight_Controller_Request_RequestHttp;
use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\CategoryConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\SearchQueryBuilder;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use Shopware_Components_Config as Config;

class CategoryConditionHandlerTest extends TestCase
{
    /**
     * @var QueryBuilder
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

    public function categoryIdsDataProvider()
    {
        return [
            'Single ID of a category without parents' => [[5], ['' => 'Genusswelten']],
            'One category without parents and one category having parents' => [
                [5, 12],
                ['' => 'Genusswelten_Genusswelten_Tees und Zubehör_Tees']
            ],
            'Root category ID of "3"' => [[3], []],
        ];
    }

    /**
     * @dataProvider categoryIdsDataProvider
     *
     * @param array $categoryIds
     * @param array $expectedCategoryNames
     *
     * @throws Exception
     */
    public function testGenerateCondition(array $categoryIds, array $expectedCategoryNames)
    {
        $handler = new CategoryConditionHandler();
        $handler->generateCondition(
            new CategoryCondition($categoryIds),
            $this->querybuilder,
            $this->context
        );

        $params = $this->querybuilder->getParameters();
        if (empty($expectedCategoryNames)) {
            $this->assertArrayNotHasKey(
                'attrib',
                $params,
                'Expected parameters to not contain the categories attribute'
            );
        } else {
            $this->assertArrayHasKey('attrib', $params, 'Parameter "attrib" was not found in the parameters');
            $this->assertArrayHasKey('cat', $params['attrib'], 'Categories are not set in the "attrib" parameter');
            $this->assertEquals(
                $expectedCategoryNames,
                $params['attrib']['cat'],
                'Expected querybuilder to contain the name of the provided category ID'
            );
        }
    }
}
