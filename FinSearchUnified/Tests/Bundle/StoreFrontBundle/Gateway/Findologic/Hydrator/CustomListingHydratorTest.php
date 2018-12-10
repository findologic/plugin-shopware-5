<?php

namespace FinSearchUnified\Tests\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator;

use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
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
            'Price' => [
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
            'Color' => [
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
            'Vendor' => [
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
            'Ingredients with multiple filter' => [
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
            'Ingredients with single filter' => [
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
            'Category' => [
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
            'Zoom Factor' => [
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
            'Special Characters' => [
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
     * @param string $getName
     * @param string $getUniqueKey
     * @param string $attributeGetName
     * @param string $attributeFormFieldName
     * @param string $attributeLabel
     * @param string $attributeMode
     */
    public function testHydrateFacet(
        array $filterArray,
        $getName,
        $getUniqueKey,
        $attributeGetName,
        $attributeFormFieldName,
        $attributeLabel,
        $attributeMode
    ) {
        // Create custom XML object corresponding the xmlResponse
        $data = '<?xml version="1.0" encoding="utf-8"?><searchResult></searchResult>';
        $xmlResponse = new SimpleXMLElement($data);
        $filters = $xmlResponse->addChild('filters');
        $filter = $filters->addChild('filter');

        foreach ($filterArray as $name => $value) {
            $filter->addChild($name, $value);
        }

        $customFacets = $this->hydrator->hydrateFacet($xmlResponse);

        /** @var CustomFacet $customFacet */
        foreach ($customFacets as $customFacet) {
            $this->assertSame(
                $getName,
                $customFacet->getName(),
                sprintf('Expected getName to be %s', $getName)
            );
            $this->assertSame(
                $getUniqueKey,
                $customFacet->getUniqueKey(),
                sprintf('Expected getUniqueKey to be %s', $getUniqueKey)
            );

            /** @var ProductAttributeFacet $productAttributeFacet */
            $productAttributeFacet = $customFacet->getFacet();

            $this->assertInstanceOf(
                ProductAttributeFacet::class,
                $productAttributeFacet,
                sprintf(
                    'Expected getFacet to be an instance of %s',
                    ProductAttributeFacet::class
                )
            );
            $this->assertSame(
                $attributeGetName,
                $productAttributeFacet->getName(),
                sprintf('Expected ProductAttributeFacet::getName to be %s', $attributeGetName)
            );
            $this->assertSame(
                $attributeFormFieldName,
                $productAttributeFacet->getFormFieldName(),
                sprintf('Expected ProductAttributeFacet::getFormFieldName to be %s', $attributeFormFieldName)
            );
            $this->assertSame(
                $attributeLabel,
                $productAttributeFacet->getLabel(),
                sprintf('Expected ProductAttributeFacet::getLabel to be %s', $attributeLabel)
            );
            $this->assertSame(
                $attributeMode,
                $productAttributeFacet->getMode(),
                sprintf('Expected ProductAttributeFacet::getMode to be %s', $attributeMode)
            );
        }
    }
}
