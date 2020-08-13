<?php

namespace FinSearchUnified\Tests\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator;

use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use FinSearchUnified\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use FinSearchUnified\Components\ConfigLoader;
use FinSearchUnified\Tests\TestCase;
use ReflectionException;
use ReflectionObject;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use SimpleXMLElement;
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
            'Price filter' => [
                'filterArray' => [
                    'type' => 'range-slider',
                    'name' => 'price',
                    'display' => 'Preis',
                    'select' => 'single'
                ],
                'expectedName' => 'price',
                'expectedUniqueKey' => 'price',
                'expectedAttributeName' => 'product_attribute_price',
                'expectedAttributeFormFieldName' => 'price',
                'expectedAttributeLabel' => 'Preis',
                'expectedAttributeMode' => ProductAttributeFacet::MODE_RANGE_RESULT
            ],
            'Color filter' => [
                'filterArray' => [
                    'type' => 'color',
                    'name' => 'color',
                    'display' => 'Farbe',
                    'select' => 'multiselect'
                ],
                'expectedName' => 'color',
                'expectedUniqueKey' => 'color',
                'expectedAttributeName' => 'product_attribute_color',
                'expectedAttributeFormFieldName' => 'color',
                'expectedAttributeLabel' => 'Farbe',
                'expectedAttributeMode' => ProductAttributeFacet::MODE_VALUE_LIST_RESULT
            ],
            'Image filter' => [
                'filterArray' => [
                    'type' => 'image',
                    'name' => 'vendor',
                    'display' => 'Marken',
                    'select' => 'multiple'
                ],
                'expectedName' => 'vendor',
                'expectedUniqueKey' => 'vendor',
                'expectedAttributeName' => 'product_attribute_vendor',
                'expectedAttributeFormFieldName' => 'vendor',
                'expectedAttributeLabel' => 'Marken',
                'expectedAttributeMode' => ProductAttributeFacet::MODE_VALUE_LIST_RESULT
            ],
            'Text filter supporting multiple values' => [
                'filterArray' => [
                    'type' => 'label',
                    'name' => 'ingredients',
                    'display' => 'Zutaten',
                    'select' => 'multiple'
                ],
                'expectedName' => 'ingredients',
                'expectedUniqueKey' => 'ingredients',
                'expectedAttributeName' => 'product_attribute_ingredients',
                'expectedAttributeFormFieldName' => 'ingredients',
                'expectedAttributeLabel' => 'Zutaten',
                'expectedAttributeMode' => ProductAttributeFacet::MODE_VALUE_LIST_RESULT
            ],
            'Text filter supporting only one value' => [
                'filterArray' => [
                    'type' => 'label',
                    'name' => 'ingredients',
                    'display' => 'Zutaten',
                    'select' => 'single'
                ],
                'expectedName' => 'ingredients',
                'expectedUniqueKey' => 'ingredients',
                'expectedAttributeName' => 'product_attribute_ingredients',
                'expectedAttributeFormFieldName' => 'ingredients',
                'expectedAttributeLabel' => 'Zutaten',
                'expectedAttributeMode' => ProductAttributeFacet::MODE_RADIO_LIST_RESULT
            ],
            'Dropdown filter for category' => [
                'filterArray' => [
                    'type' => 'select',
                    'name' => 'cat',
                    'display' => 'Kategorie',
                    'select' => 'single'
                ],
                'expectedName' => 'cat',
                'expectedUniqueKey' => 'cat',
                'expectedAttributeName' => 'product_attribute_cat',
                'expectedAttributeFormFieldName' => 'cat',
                'expectedAttributeLabel' => 'Kategorie',
                'expectedAttributeMode' => ProductAttributeFacet::MODE_RADIO_LIST_RESULT
            ],
            'Filter with a single special character' => [
                'filterArray' => [
                    'type' => 'select',
                    'name' => 'zoom factor',
                    'display' => 'Zoom Faktor',
                    'select' => 'single'
                ],
                'expectedName' => 'zoom factor',
                'expectedUniqueKey' => 'zoom factor',
                'expectedAttributeName' => 'product_attribute_zoom factor',
                'expectedAttributeFormFieldName' => 'zoom_factor',
                'expectedAttributeLabel' => 'Zoom Faktor',
                'expectedAttributeMode' => ProductAttributeFacet::MODE_RADIO_LIST_RESULT
            ],
            'Filter with multiple special characters' => [
                'filterArray' => [
                    'type' => 'select',
                    'name' => 'special .characters',
                    'display' => 'Sonderzeichen',
                    'select' => 'single'
                ],
                'expectedName' => 'special .characters',
                'expectedUniqueKey' => 'special .characters',
                'expectedAttributeName' => 'product_attribute_special .characters',
                'expectedAttributeFormFieldName' => 'special_characters',
                'expectedAttributeLabel' => 'Sonderzeichen',
                'expectedAttributeMode' => ProductAttributeFacet::MODE_RADIO_LIST_RESULT
            ],
            'Filter with multi-byte characters' => [
                'filterArray' => [
                    'type' => 'select',
                    'name' => 'TISCHWÄSCHE - Ausführung',
                    'display' => 'Multibyte - TISCHWÄSCHE - Ausführung',
                    'select' => 'single'
                ],
                'expectedName' => 'TISCHWÄSCHE - Ausführung',
                'expectedUniqueKey' => 'TISCHWÄSCHE - Ausführung',
                'expectedAttributeName' => 'product_attribute_TISCHWÄSCHE - Ausführung',
                'expectedAttributeFormFieldName' => 'TISCHW_SCHE_-_Ausführung',
                'expectedAttributeLabel' => 'Multibyte - TISCHWÄSCHE - Ausführung',
                'expectedAttributeMode' => ProductAttributeFacet::MODE_RADIO_LIST_RESULT
            ]
        ];
    }

    /**
     * @dataProvider facetFilterProvider
     *
     * @param array $filterArray
     * @param string $expectedName
     * @param string $expectedUniqueKey
     * @param string $expectedAttributeName
     * @param string $expectedAttributeFormFieldName
     * @param string $expectedAttributeLabel
     * @param string $expectedAttributeMode
     */
    public function testHydrateFacet(
        array $filterArray,
        $expectedName,
        $expectedUniqueKey,
        $expectedAttributeName,
        $expectedAttributeFormFieldName,
        $expectedAttributeLabel,
        $expectedAttributeMode
    ) {
        // Create custom XML object corresponding the xmlResponse
        $data = '<?xml version="1.0" encoding="utf-8"?><searchResult></searchResult>';
        $xmlResponse = new SimpleXMLElement($data);
        $filters = $xmlResponse->addChild('filters');
        $filter = $filters->addChild('filter');

        foreach ($filterArray as $name => $value) {
            $filter->addChild($name, $value);
        }

        $customFacets = [];

        foreach ($xmlResponse->filters->filter as $filter) {
            $customFacets[] = $this->hydrator->hydrateFacet($filter);
        }

        foreach ($customFacets as $customFacet) {
            $this->assertSame(
                $expectedName,
                $customFacet->getName(),
                sprintf("Expected custom facet's name to be %s", $expectedName)
            );
            $this->assertSame(
                $expectedUniqueKey,
                $customFacet->getUniqueKey(),
                sprintf("Expected custom facet's unique key to be %s", $expectedUniqueKey)
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
                $expectedAttributeName,
                $productAttributeFacet->getName(),
                sprintf("Expected product attribute facet's name to be %s", $expectedAttributeName)
            );
            $this->assertSame(
                $expectedAttributeFormFieldName,
                $productAttributeFacet->getFormFieldName(),
                sprintf("Expected product attribute facet's form field name to be %s", $expectedAttributeFormFieldName)
            );
            $this->assertSame(
                $expectedAttributeLabel,
                $productAttributeFacet->getLabel(),
                sprintf("Expected product attribute facet's label to be %s", $expectedAttributeLabel)
            );
            $this->assertSame(
                $expectedAttributeMode,
                $productAttributeFacet->getMode(),
                sprintf("Expected product attribute facet's mode to be %s", $expectedAttributeMode)
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
