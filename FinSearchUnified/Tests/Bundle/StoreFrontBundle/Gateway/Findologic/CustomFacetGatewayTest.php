<?php

namespace FinSearchUnified\Tests\Bundle\StoreFrontBundle\Gateway\Findologic;

use Enlight_Controller_Request_RequestHttp;
use Enlight_Exception;
use Exception;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilderFactory;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\ResponseParser;
use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\CustomFacetGateway;
use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use FinSearchUnified\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use FinSearchUnified\Tests\Helper\Utility;
use FinSearchUnified\Tests\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionException;
use ReflectionObject;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware_Components_Config;
use Zend_Cache_Exception;

class CustomFacetGatewayTest extends TestCase
{
    protected function tearDown()
    {
        parent::tearDown();

        Shopware()->Container()->reset('config');
        Shopware()->Container()->load('config');

        Shopware()->Session()->offsetUnset('isSearchPage');
        Shopware()->Session()->offsetUnset('isCategoryPage');
        Shopware()->Session()->offsetUnset('findologicDI');
    }

    /**
     * @throws Exception
     */
    public function testExceptionForUnsupportedResponse()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported response format');

        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        // Custom request object to trigger findologic search
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setModuleName('frontend');
        Shopware()->Front()->setRequest($request);

        $configArray = [
            ['ActivateFindologic', true],
            ['ShopKey', 'ABCDABCDABCDABCDABCDABCDABCDABCD'],
            ['ActivateFindologicForCategoryPages', false]
        ];
        // Create mock object for Shopware Config and explicitly return the values
        $mockConfig = $this->getMockBuilder(Shopware_Components_Config::class)
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig->method('offsetGet')
            ->willReturnMap($configArray);
        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $mockConfig);

        Shopware()->Session()->offsetSet('isSearchPage', true);
        Shopware()->Session()->offsetSet('isCategoryPage', false);
        Shopware()->Session()->offsetSet('findologicDI', false);

        $mockHydrator = $this->createMock(CustomListingHydrator::class);
        $mockHydrator->expects($this->never())
            ->method('hydrateFacet');

        $mockedQuery = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods(['execute'])
            ->getMockForAbstractClass();

        $responseMock = $this->createMock(Response::class);
        $responseMock->method('getRawResponse')->willReturn(null);
        $mockedQuery->expects($this->once())->method('execute')->willReturn($responseMock);

        // Mock querybuilder factory method to check that custom implementation does not get called
        // as original implementation will be called in this case
        $mockQuerybuilderFactory = $this->createMock(QueryBuilderFactory::class);
        $mockQuerybuilderFactory->expects($this->once())
            ->method('createSearchNavigationQueryWithoutAdditionalFilters')
            ->willReturn($mockedQuery);

        $facetGateway = new CustomFacetGateway(
            $mockHydrator,
            $mockQuerybuilderFactory
        );

        // Invoke the method to test response
        $facetGateway->getList([3], $context);
    }

    /**
     * @return array
     */
    public function findologicFilterProvider()
    {
        return [
            'Only default facets are returned' => [
                'response' => Utility::getDemoResponse('demoResponseWithoutFilters.xml'),
                'expectedFacetsData' => [
                    ['name' => 'cat', 'display' => 'Category', 'select' => 'single', 'type' => 'select'],
                    ['name' => 'vendor', 'display' => 'Manufacturer', 'select' => 'single', 'type' => 'select'],
                ],
                'expectedFacetResult' =>
                    [
                        ProductAttributeFacet::MODE_RADIO_LIST_RESULT,
                        ProductAttributeFacet::MODE_RADIO_LIST_RESULT,
                    ]
            ],
            'Single facet and two default' => [
                'response' => Utility::getDemoResponse('demoResponseWithoutDefaultFilters.xml'),
                'expectedFacetsData' => [
                    ['name' => 'Farbe', 'display' => 'Farbe', 'select' => 'multiselect', 'type' => 'color'],
                    ['name' => 'cat', 'display' => 'Category', 'select' => 'single', 'type' => 'select'],
                    ['name' => 'vendor', 'display' => 'Manufacturer', 'select' => 'single', 'type' => 'select'],
                ],
                'expectedFacetResult' => [
                    ProductAttributeFacet::MODE_VALUE_LIST_RESULT,
                    ProductAttributeFacet::MODE_RADIO_LIST_RESULT,
                    ProductAttributeFacet::MODE_RADIO_LIST_RESULT
                ]
            ]
        ];
    }

    /**
     * @dataProvider findologicFilterProvider
     *
     * @param Xml21Response $response
     * @param array $expectedFacetsData
     * @param array $expectedFacetResult
     *
     * @throws Enlight_Exception
     * @throws Zend_Cache_Exception
     */
    public function testCreatesShopwareFacetsFromFindologicFilters(
        Xml21Response $response,
        array $expectedFacetsData,
        array $expectedFacetResult
    ) {
        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        // Custom request object to trigger findologic search
        $request = new Enlight_Controller_Request_RequestHttp();
        $request->setModuleName('frontend');
        Shopware()->Front()->setRequest($request);

        $configArray = [
            ['ActivateFindologic', true],
            ['ShopKey', 'ABCDABCDABCDABCDABCDABCDABCDABCD'],
            ['ActivateFindologicForCategoryPages', false]
        ];
        // Create mock object for Shopware Config and explicitly return the values
        $mockConfig = $this->getMockBuilder(Shopware_Components_Config::class)
            ->setMethods(['offsetGet'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig->method('offsetGet')
            ->willReturnMap($configArray);
        // Assign mocked config variable to application container
        Shopware()->Container()->set('config', $mockConfig);

        Shopware()->Session()->isSearchPage = true;
        Shopware()->Session()->isCategoryPage = false;
        Shopware()->Session()->findologicDI = false;

        $originalHydrator = Shopware()->Container()->get('fin_search_unified.custom_listing_hydrator');

        $mockedQuery = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods(['execute'])
            ->getMockForAbstractClass();

        $mockedQuery->expects($this->once())->method('execute')->willReturn($response);

        // Mock querybuilder factory method to check that custom implementation does not get called
        // as original implementation will be called in this case
        $mockQuerybuilderFactory = $this->createMock(QueryBuilderFactory::class);
        $mockQuerybuilderFactory->expects($this->once())
            ->method('createSearchNavigationQueryWithoutAdditionalFilters')
            ->willReturn($mockedQuery);

        $facetGateway = new CustomFacetGateway(
            $originalHydrator,
            $mockQuerybuilderFactory
        );

        $customFacets = $facetGateway->getList([3], $context);

        $this->assertCount(
            count($expectedFacetsData),
            $customFacets,
            'Expected same number of facets to be returned as the number of filters'
        );

        foreach ($customFacets as $key => $customFacet) {
            $this->assertSame(
                $expectedFacetsData[$key]['name'],
                $customFacet->getName(),
                sprintf("Expected custom facet's name to be %s", $expectedFacetsData[$key]['name'])
            );
            $this->assertSame(
                $expectedFacetsData[$key]['name'],
                $customFacet->getUniqueKey(),
                sprintf("Expected custom facet's unique key to be %s", $expectedFacetsData[$key]['name'])
            );

            /** @var ProductAttributeFacet $productAttributeFacet */
            $productAttributeFacet = $customFacet->getFacet();

            $this->assertInstanceOf(
                ProductAttributeFacet::class,
                $productAttributeFacet,
                "Expected custom facet's facet to be of type ProductAttributeFacet"
            );
            $this->assertSame(
                sprintf('product_attribute_%s', $expectedFacetsData[$key]['name']),
                $productAttributeFacet->getName(),
                sprintf(
                    "Expected product attribute facet's name to be %s",
                    sprintf('product_attribute_%s', $expectedFacetsData[$key]['name'])
                )
            );
            $this->assertSame(
                $expectedFacetsData[$key]['name'],
                $productAttributeFacet->getFormFieldName(),
                sprintf(
                    "Expected product attribute facet's form field name to be %s",
                    $expectedFacetsData[$key]['name']
                )
            );
            $this->assertSame(
                $expectedFacetResult[$key],
                $productAttributeFacet->getMode(),
                sprintf("Expected product attribute facet's mode to be %s", $expectedFacetResult[$key])
            );
        }
    }

    /**
     * @return array
     */
    public function filterProviderForFacets()
    {
        $colorFacet = $this->createFacet('color', 'radio', 'Color');
        $categoryFacet = $this->createFacet('cat', 'radio', 'Kategorie');
        $defaultCategoryFacet = $this->createFacet('cat', 'radio', 'Category');
        $vendorFacet = $this->createFacet('vendor', 'value_list', 'Hersteller');
        $defaultVendorFacet = $this->createFacet('vendor', 'radio', 'Manufacturer');

        return [
            'Category and Vendor filter is present' => [
                'response' => Utility::getDemoResponse('demoResponseWithDefaultFilters.xml'),
                'hydrateFacets' => [$categoryFacet, $vendorFacet],
                'defaultCategoryFacet' => $defaultCategoryFacet,
                'defaultVendorFacet' => $defaultVendorFacet,
                'expectedFacets' => [
                    $categoryFacet,
                    $vendorFacet
                ]
            ],
            'Category and Vendor filter is not present' => [
                'response' => Utility::getDemoResponse('demoResponseWithoutDefaultFilters.xml'),
                'hydrateFacets' => [$colorFacet, $colorFacet],
                'defaultCategoryFacet' => $defaultCategoryFacet,
                'defaultVendorFacet' => $defaultVendorFacet,
                'expectedFacets' => [
                    $defaultCategoryFacet,
                    $defaultVendorFacet
                ]
            ]
        ];
    }

    /**
     * @dataProvider filterProviderForFacets
     *
     * @param Xml21Response $response
     * @param array $hydrateFacets
     * @param $defaultCategoryFacet
     * @param $defaultVendorFacet
     * @param CustomFacet[] $expectedFacets
     *
     * @throws ReflectionException
     */
    public function testDefaultHydrateFacets(
        Xml21Response $response,
        array $hydrateFacets,
        CustomFacet $defaultCategoryFacet,
        CustomFacet $defaultVendorFacet,
        array $expectedFacets
    ) {
        /** @var CustomListingHydrator|MockObject $mockHydrator */
        $mockHydrator = $this->createMock(CustomListingHydrator::class);
        $mockHydrator->method('hydrateDefaultCategoryFacet')->willReturn($defaultCategoryFacet);
        $mockHydrator->method('hydrateDefaultVendorFacet')->willReturn($defaultVendorFacet);
        $mockHydrator->method('hydrateFacet')->willReturnOnConsecutiveCalls($hydrateFacets[0], $hydrateFacets[1]);

        $mockQuerybuilderFactory = $this->createMock(QueryBuilderFactory::class);

        $facetGateway = new CustomFacetGateway(
            $mockHydrator,
            $mockQuerybuilderFactory
        );

        $reflector = new ReflectionObject($facetGateway);
        $hydrateMethod = $reflector->getMethod('hydrate');
        $hydrateMethod->setAccessible(true);
        $customFacets = $hydrateMethod->invoke($facetGateway, ResponseParser::getInstance($response)->getFilters());

        $defaultFacets = array_slice($customFacets, -2, 2);
        $this->assertEquals($expectedFacets, $defaultFacets);
    }

    /**
     * @param string $field
     * @param string $mode
     * @param string $label
     *
     * @return CustomFacet
     */
    private function createFacet($field, $mode, $label)
    {
        $customFacet = new CustomFacet();
        $customFacet->setName($field);
        $customFacet->setUniqueKey($field);
        $customFacet->setFacet(new ProductAttributeFacet($field, $mode, $field, $label));

        return $customFacet;
    }
}
