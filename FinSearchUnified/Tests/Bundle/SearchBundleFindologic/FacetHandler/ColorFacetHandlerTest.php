<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\FacetHandler;

use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\ColorPickerFilter as ApiColorPickerFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\LabelTextFilter as ApiLabelTextFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\RangeSliderFilter as ApiRangeSliderFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\SelectDropdownFilter as ApiSelectDropdownFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\VendorImageFilter as ApiVendorImageFilter;
use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Bundle\SearchBundle\FacetResult\ColorListItem;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\ColorFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\ColorPickerFilter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Filter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values\ColorFilterValue;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\SearchBundle\FacetResult\MediaListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResultInterface;
use SimpleXMLElement;

class ColorFacetHandlerTest extends TestCase
{
    /**
     * @dataProvider filterProvider
     *
     * @param string $apiFilter
     * @param bool $doesSupport
     */
    public function testSupportsFilter($apiFilter, $doesSupport)
    {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $filter = new $apiFilter(new SimpleXMLElement($data));
        $facetHandler = new ColorFacetHandler();
        $result = $facetHandler->supportsFilter($filter);

        $this->assertSame($doesSupport, $result);
    }

    public function filterProvider()
    {
        return [
            'Filter with "select" type' => [ApiSelectDropdownFilter::class, false],
            'Filter with "label" type' => [ApiLabelTextFilter::class, false],
            'Filter with "image" type' => [ApiVendorImageFilter::class, false],
            'Filter with "range-slider" type' => [ApiRangeSliderFilter::class, false],
            'Filter with "color" type' => [ApiColorPickerFilter::class, true]
        ];
    }

    /**
     * @dataProvider facetResultProvider
     *
     * @param Filter $filter
     * @param ConditionInterface|null $condition
     * @param FacetResultInterface|null $facetResult
     */
    public function testGeneratesPartialFacetBasedOnFilterDataAndActiveConditions(
        Filter $filter,
        ConditionInterface $condition = null,
        FacetResultInterface $facetResult = null
    ) {
        $facet = new ProductAttributeFacet(
            'color',
            ProductAttributeFacet::MODE_VALUE_LIST_RESULT,
            'color',
            'Farbe'
        );
        $criteria = new Criteria();
        if ($condition !== null) {
            $criteria->addCondition($condition);
        }

        $facetHandler = new ColorFacetHandler();
        $result = $facetHandler->generatePartialFacet($facet, $criteria, $filter);

        $this->assertEquals($facetResult, $result);
    }

    public function facetResultProvider()
    {
        return [
            'Color filter without condition' => [
                'Filter data' => (new ColorPickerFilter(
                    'color',
                    'Farbe'
                ))
                    ->addValue((new ColorFilterValue('Red', 'Red'))->setColorHexCode('#ff0000'))
                    ->addValue((new ColorFilterValue('Green', 'Green'))->setColorHexCode('#00ff00')),
                'Condition' => null,
                'Facet Result' => new MediaListFacetResult(
                    'product_attribute_color',
                    false,
                    'Farbe',
                    [
                        new ColorListItem('Red', 'Red', false, '#ff0000'),
                        new ColorListItem('Green', 'Green', false, '#00ff00')
                    ],
                    'color',
                    [],
                    'frontend/listing/filter/facet-color-list.tpl'
                )
            ],
            'Color filter without color element' => [
                'Filter data' => (new ColorPickerFilter(
                    'color',
                    'Farbe'
                ))
                    ->addValue((new ColorFilterValue('Red', 'Red'))->setColorHexCode('#ff0000'))
                    ->addValue((new ColorFilterValue('Green', 'Green'))),
                'Condition' => null,
                'Facet Result' => new MediaListFacetResult(
                    'product_attribute_color',
                    false,
                    'Farbe',
                    [
                        new ColorListItem('Red', 'Red', false, '#ff0000'),
                        new ColorListItem('Green', 'Green', false, null)
                    ],
                    'color',
                    [],
                    'frontend/listing/filter/facet-color-list.tpl'
                )
            ],
            'Color filter with condition' => [
                'Filter data' => (new ColorPickerFilter(
                    'color',
                    'Farbe'
                ))
                    ->addValue((new ColorFilterValue('Red', 'Red'))->setColorHexCode('#ff0000'))
                    ->addValue((new ColorFilterValue('Green', 'Green'))->setColorHexCode('#00ff00')),
                'Condition' => new ProductAttributeCondition(
                    'color',
                    Operator::EQ,
                    ['Red', 'Green']
                ),
                'Facet Result' => new MediaListFacetResult(
                    'product_attribute_color',
                    true,
                    'Farbe',
                    [
                        new ColorListItem('Red', 'Red', true, '#ff0000'),
                        new ColorListItem('Green', 'Green', true, '#00ff00')
                    ],
                    'color',
                    [],
                    'frontend/listing/filter/facet-color-list.tpl'
                )
            ],
            'Color filter with empty color condition' => [
                'Filter data' => (new ColorPickerFilter(
                    'color',
                    'Farbe'
                ))
                    ->addValue((new ColorFilterValue('Red', 'Red'))->setColorHexCode('#ff0000'))
                    ->addValue((new ColorFilterValue('Green', 'Green'))->setColorHexCode('#00ff00')),
                'Condition' => new ProductAttributeCondition(
                    'color',
                    Operator::EQ,
                    ['Zima Blue']
                ),
                'Facet Result' => new MediaListFacetResult(
                    'product_attribute_color',
                    true,
                    'Farbe',
                    [
                        new ColorListItem('Red', 'Red', false, '#ff0000'),
                        new ColorListItem('Green', 'Green', false, '#00ff00'),
                        new ColorListItem('Zima Blue', 'Zima Blue', true, null)
                    ],
                    'color',
                    [],
                    'frontend/listing/filter/facet-color-list.tpl'
                )
            ]
        ];
    }
}
