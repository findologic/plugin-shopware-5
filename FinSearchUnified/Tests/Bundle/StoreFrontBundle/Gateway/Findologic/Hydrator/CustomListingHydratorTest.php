<?php

namespace FinSearchUnified\Tests\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator;

use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\ResponseParser;
use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use FinSearchUnified\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use FinSearchUnified\Components\ConfigLoader;
use FinSearchUnified\Tests\Helper\Utility;
use FinSearchUnified\Tests\TestCase;
use ReflectionException;
use ReflectionObject;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Zend_Cache_Exception;

class CustomListingHydratorTest extends TestCase
{
    /**
     * @var CustomListingHydrator
     */
    private $hydrator;

    protected function setUp()
    {
        parent::setUp();

        $this->hydrator = Shopware()->Container()->get('fin_search_unified.custom_listing_hydrator');
    }

    /**
     * Data provider for the hydrate facet method
     *
     * @return array
     */
    public function facetFilterProvider()
    {
        return [
            'Hydrate Facets from filters' => [
                [

                    [
                        'name' => 'cat',
                        'uniqueKey' => 'cat',
                        'attributeName' => 'product_attribute_cat',
                        'formFieldName' => 'cat',
                        'label' => 'Kategorie',
                        'mode' => ProductAttributeFacet::MODE_VALUE_LIST_RESULT
                    ],

                    [
                        'name' => 'vendor',
                        'uniqueKey' => 'vendor',
                        'attributeName' => 'product_attribute_vendor',
                        'formFieldName' => 'vendor',
                        'label' => 'Hersteller',
                        'mode' => ProductAttributeFacet::MODE_VALUE_LIST_RESULT
                    ],
                    [
                        'name' => 'price',
                        'uniqueKey' => 'price',
                        'attributeName' => 'product_attribute_price',
                        'formFieldName' => 'price',
                        'label' => 'Preis',
                        'mode' => ProductAttributeFacet::MODE_RANGE_RESULT
                    ],
                    [
                        'name' => 'Farbe',
                        'uniqueKey' => 'Farbe',
                        'attributeName' => 'product_attribute_Farbe',
                        'formFieldName' => 'Farbe',
                        'label' => 'Farbe',
                        'mode' => ProductAttributeFacet::MODE_VALUE_LIST_RESULT
                    ],
                    [
                        'name' => 'Material',
                        'uniqueKey' => 'Material',
                        'attributeName' => 'product_attribute_Material',
                        'formFieldName' => 'Material',
                        'label' => 'Material',
                        'mode' => ProductAttributeFacet::MODE_VALUE_LIST_RESULT
                    ],
                    [
                        'name' => 'special .characters',
                        'uniqueKey' => 'special .characters',
                        'attributeName' => 'product_attribute_special .characters',
                        'formFieldName' => 'special_characters',
                        'label' => 'Sonderzeichen',
                        'mode' => ProductAttributeFacet::MODE_VALUE_LIST_RESULT
                    ]
                ]
            ]
        ];
    }

    /**
     * @dataProvider facetFilterProvider
     *
     * @param array $expectedFields
     */
    public function testHydrateFacet(
        array $expectedFields
    ) {
        $customFacets = [];
        $response = Utility::getDemoResponse('demoResponseWithSpecialCharacters.xml');
        $filters = ResponseParser::getInstance($response)->getFilters();
        foreach ($filters as $filter) {
            $customFacets[] = $this->hydrator->hydrateFacet($filter);
        }

        foreach ($customFacets as $key => $customFacet) {
            $this->assertSame(
                $expectedFields[$key]['name'],
                $customFacet->getName(),
                sprintf("Expected custom facet's name to be %s", $expectedFields[$key]['name'])
            );
            $this->assertSame(
                $expectedFields[$key]['uniqueKey'],
                $customFacet->getUniqueKey(),
                sprintf("Expected custom facet's unique key to be %s", $expectedFields[$key]['uniqueKey'])
            );

            /** @var ProductAttributeFacet $productAttributeFacet */
            $productAttributeFacet = $customFacet->getFacet();
            $this->assertInstanceOf(
                ProductAttributeFacet::class,
                $productAttributeFacet,
                sprintf(
                    "Expected custom facet's facet to be of type %s",
                    ProductAttributeFacet::class
                )
            );
            $this->assertSame(
                $expectedFields[$key]['attributeName'],
                $productAttributeFacet->getName(),
                sprintf("Expected product attribute facet's name to be %s", $expectedFields[$key]['attributeName'])
            );
            $this->assertSame(
                $expectedFields[$key]['formFieldName'],
                $productAttributeFacet->getFormFieldName(),
                sprintf(
                    "Expected product attribute facet's form field name to be %s",
                    $expectedFields[$key]['formFieldName']
                )
            );
            $this->assertSame(
                $expectedFields[$key]['label'],
                $productAttributeFacet->getLabel(),
                sprintf("Expected product attribute facet's label to be %s", $expectedFields[$key]['label'])
            );
            $this->assertSame(
                $expectedFields[$key]['mode'],
                $productAttributeFacet->getMode(),
                sprintf("Expected product attribute facet's mode to be %s", $expectedFields[$key]['mode'])
            );
        }
    }

    /**
     * Data provider for escaping filter names
     *
     * @return array
     */
    public static function unescapedFilterNameProvider()
    {
        return [
            'Filter name with only letters and numbers' => [
                'Findologic123',
                'Findologic123',
                'Expected string to return unchanged'
            ],
            'Filter name with spaces' => [
                'Findologic 1 2 3',
                'Findologic_1_2_3',
                'Expected whitespaces to be stripped way'
            ],
            'Filter name with dots' => [
                'Findologic...Rocks',
                'Findologic_Rocks',
                'Expected dots to be stripped way'
            ],
            'Filter name with special characters' => [
                'Findologic&123',
                'Findologic&123',
                'Expected special characters to be returned as they are'
            ],
            'Filter name with non standard character' => [
                "Findologic\xC2\xAE 123",
                'Findologic_123',
                'Expected non standard characters to be stripped away'
            ],
            'Filter name with umlauts' => [
                'Findolögic123',
                'Findolögic123',
                'Expected umlauts to be left unaltered.'
            ]
        ];
    }

    /**
     * @dataProvider unescapedFilterNameProvider
     *
     * @param string $text
     * @param string $expected
     * @param string $errorMessage
     */
    public function testFilterNamesAreEscaped($text, $expected, $errorMessage)
    {
        $result = $this->hydrator->getFormFieldName($text);
        $this->assertEquals($expected, $result, $errorMessage);
    }

    /**
     * @throws ReflectionException
     */
    public function testCreateCustomFacet()
    {
        $name = 'Price';
        $label = 'Price';
        $mode = 'range-slider';
        $formFieldName = 'Price';

        $customFacet = new CustomFacet();
        $customFacet->setName($name);
        $customFacet->setUniqueKey($name);

        $productAttributeFacet = new ProductAttributeFacet($name, $mode, $formFieldName, $label);
        $customFacet->setFacet($productAttributeFacet);

        $reflector = new ReflectionObject($this->hydrator);
        $method = $reflector->getMethod('createCustomFacet');
        $method->setAccessible(true);
        $facet = $method->invokeArgs($this->hydrator, [$name, $mode, $label]);
        $this->assertEquals($customFacet, $facet);
    }

    /**
     * @throws Zend_Cache_Exception
     */
    public function testHydrateDefaultFacets()
    {
        $smartSuggestBlock = [
            'cat' => 'Category',
            'vendor' => 'Manufacturer'
        ];

        $configLoaderMock = $this->createMock(ConfigLoader::class);
        $configLoaderMock->method('getSmartSuggestBlocks')->willReturn($smartSuggestBlock);

        $hydrator = new CustomListingHydrator(
            $configLoaderMock
        );

        $defaultCategoryFacet = $this->getDefaultCategoryFacet();
        $defaultVendorFacet = $this->getDefaultVendorFacet();

        $this->assertEquals($defaultCategoryFacet, $hydrator->hydrateDefaultCategoryFacet());
        $this->assertEquals($defaultVendorFacet, $hydrator->hydrateDefaultVendorFacet());
    }

    /**
     * @return CustomFacet
     */
    private function getDefaultVendorFacet()
    {
        $name = 'vendor';
        $mode = 'radio';
        $formFieldName = 'vendor';
        $label = 'Manufacturer';

        $customFacet = new CustomFacet();
        $customFacet->setName($name);
        $customFacet->setUniqueKey($name);

        $productAttributeFacet = new ProductAttributeFacet($name, $mode, $formFieldName, $label);
        $customFacet->setFacet($productAttributeFacet);

        return $customFacet;
    }

    /**
     * @return CustomFacet
     */
    private function getDefaultCategoryFacet()
    {
        $name = 'cat';
        $mode = 'radio';
        $formFieldName = 'cat';
        $label = 'Category';

        $customFacet = new CustomFacet();
        $customFacet->setName($name);
        $customFacet->setUniqueKey($name);

        $productAttributeFacet = new ProductAttributeFacet($name, $mode, $formFieldName, $label);
        $customFacet->setFacet($productAttributeFacet);

        return $customFacet;
    }
}
