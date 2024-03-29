<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Enlight_Controller_Request_RequestHttp;
use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\ManufacturerConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\SearchQueryBuilder;
use FinSearchUnified\Tests\Helper\Utility;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Condition\ManufacturerCondition;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use Shopware_Components_Config as Config;

class ManufacturerConditionHandlerTest extends TestCase
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
        Utility::sResetManufacturers();

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

    protected function tearDown(): void
    {
        parent::tearDown();

        Utility::sResetManufacturers();
    }

    public function manufacturerIdsDataProvider()
    {
        return [
            'Manufacturer ID of "6"' => [[6], ['FindologicVendor6']],
            'Manufacturer ID of "7"' => [[7], ['FindologicVendor7']]
        ];
    }

    /**
     * @dataProvider manufacturerIdsDataProvider
     *
     * @param array $manufacturerIds
     * @param array $expectedManufacturerNames
     *
     * @throws Exception
     */
    public function testGenerateCondition(array $manufacturerIds, array $expectedManufacturerNames)
    {
        Utility::createTestManufacturer([
            'id' => reset($manufacturerIds),
            'name' => reset($expectedManufacturerNames)
        ]);

        $handler = new ManufacturerConditionHandler();
        $handler->generateCondition(
            new ManufacturerCondition($manufacturerIds),
            $this->querybuilder,
            $this->context
        );

        $params = $this->querybuilder->getParameters();

        if (empty($expectedManufacturerNames)) {
            $this->assertArrayNotHasKey(
                'attrib',
                $params,
                'Expected parameters to not contain the, manufacturers attribute'
            );
        } else {
            $this->assertArrayHasKey('attrib', $params, 'Parameter "attrib" was not found in the parameters');
            $this->assertArrayHasKey('vendor', $params['attrib'], 'Vendor are not set in the "attrib" parameter');
            $this->assertEquals(
                reset($expectedManufacturerNames),
                reset($params['attrib']['vendor']),
                'Expected querybuilder to contain the name of the provided category ID'
            );
        }
    }
}
