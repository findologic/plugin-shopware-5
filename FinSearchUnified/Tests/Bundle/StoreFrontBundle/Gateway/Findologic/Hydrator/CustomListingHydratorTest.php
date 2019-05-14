<?php

namespace FinSearchUnified\Tests\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator;

use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Components\Test\Plugin\TestCase;
use SimpleXMLElement;

class CustomListingHydratorTest extends TestCase
{
    /**
     * @var CustomListingHydrator
     */
    private $hydrator;

    protected function setUp()
    {
        parent::setUp();

        $this->hydrator = new CustomListingHydrator();
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
                [
                    'type' => 'range-slider',
                    'name' => 'price',
                    'display' => 'Preis',
                    'select' => 'single'
                ],
                'price',
                'price',
                'product_attribute_price',
                'price',
                'Preis',
                ProductAttributeFacet::MODE_RANGE_RESULT
            ],
            'Color filter' => [
                [
                    'type' => 'color',
                    'name' => 'color',
                    'display' => 'Farbe',
                    'select' => 'multiselect'
                ],
                'color',
                'color',
                'product_attribute_color',
                'color',
                'Farbe',
                ProductAttributeFacet::MODE_VALUE_LIST_RESULT
            ],
            'Image filter' => [
                [
                    'type' => 'image',
                    'name' => 'vendor',
                    'display' => 'Marken',
                    'select' => 'multiple'
                ],
                'vendor',
                'vendor',
                'product_attribute_vendor',
                'vendor',
                'Marken',
                ProductAttributeFacet::MODE_VALUE_LIST_RESULT
            ],
            'Text filter supporting multiple values' => [
                [
                    'type' => 'label',
                    'name' => 'ingredients',
                    'display' => 'Zutaten',
                    'select' => 'multiple'
                ],
                'ingredients',
                'ingredients',
                'product_attribute_ingredients',
                'ingredients',
                'Zutaten',
                ProductAttributeFacet::MODE_VALUE_LIST_RESULT
            ],
            'Text filter supporting only one value' => [
                [
                    'type' => 'label',
                    'name' => 'ingredients',
                    'display' => 'Zutaten',
                    'select' => 'single'
                ],
                'ingredients',
                'ingredients',
                'product_attribute_ingredients',
                'ingredients',
                'Zutaten',
                ProductAttributeFacet::MODE_RADIO_LIST_RESULT
            ],
            'Dropdown filter for category' => [
                [
                    'type' => 'select',
                    'name' => 'cat',
                    'display' => 'Kategorie',
                    'select' => 'single'
                ],
                'cat',
                'cat',
                'product_attribute_cat',
                'cat',
                'Kategorie',
                ProductAttributeFacet::MODE_VALUE_LIST_RESULT
            ],
            'Filter with a single special character' => [
                [
                    'type' => 'select',
                    'name' => 'zoom factor',
                    'display' => 'Zoom Faktor',
                    'select' => 'single'
                ],
                'zoom factor',
                'zoom factor',
                'product_attribute_zoom factor',
                'zoom_factor',
                'Zoom Faktor',
                ProductAttributeFacet::MODE_VALUE_LIST_RESULT
            ],
            'Filter with multiple special characters' => [
                [
                    'type' => 'select',
                    'name' => 'special .characters',
                    'display' => 'Sonderzeichen',
                    'select' => 'single'
                ],
                'special .characters',
                'special .characters',
                'product_attribute_special .characters',
                'special_characters',
                'Sonderzeichen',
                ProductAttributeFacet::MODE_VALUE_LIST_RESULT
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
     * Data provider for testing removal of control characters
     *
     * @return array
     */
    public static function controlCharacterProvider()
    {
        return [
            'Strings with only letters and numbers' => [
                'Findologic123',
                'Findologic123',
                'Expected string to return unchanged'
            ],
            'String with control characters' => [
                "Findologic\n1\t2\r3",
                'Findologic123',
                'Expected control characters to be stripped way'
            ],
            'String with another set of control characters' => [
                "Findologic\xC2\x9F\xC2\x80 Rocks",
                'Findologic Rocks',
                'Expected control characters to be stripped way'
            ],
            'String with special characters' => [
                'Findologic&123',
                'Findologic&123',
                'Expected special characters to be returned as they are'
            ],
            'String with umlauts' => [
                'Findolögic123',
                'Findolögic123',
                'Expected umlauts to be left unaltered.'
            ]
        ];
    }

    /**
     * @dataProvider controlCharacterProvider
     *
     * @param string $text
     * @param string $expected
     * @param string $errorMessage
     */
    public function testControlCharacterMethod($text, $expected, $errorMessage)
    {
        $result = $this->hydrator->getFormFieldName($text);
        $this->assertEquals($expected, $result, $errorMessage);
    }
}
