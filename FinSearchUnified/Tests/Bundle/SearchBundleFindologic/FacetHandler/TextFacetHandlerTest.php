<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\FacetHandler;

use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\ColorPickerFilter as ApiColorPickerFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\LabelTextFilter as ApiLabelTextFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\RangeSliderFilter as ApiRangeSliderFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\SelectDropdownFilter as ApiSelectDropdownFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\VendorImageFilter as ApiVendorImageFilter;
use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\TextFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Filter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\LabelTextFilter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values\FilterValue;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\SearchBundle\FacetResult\RadioFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListItem;
use Shopware\Bundle\SearchBundle\FacetResultInterface;
use SimpleXMLElement;

class TextFacetHandlerTest extends TestCase
{
    public function filterProvider()
    {
        return [
            'Filter with "select" type' => [ApiSelectDropdownFilter::class, true],
            'Filter with "label" type' => [ApiLabelTextFilter::class, true],
            'Filter with "image" type' => [ApiVendorImageFilter::class, false],
            'Filter with "range-slider" type' => [ApiRangeSliderFilter::class, false],
            'Filter with "color" type' => [ApiColorPickerFilter::class, false]
        ];
    }

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
        $facetHandler = new TextFacetHandler();
        $result = $facetHandler->supportsFilter(Filter::getInstance($filter));

        $this->assertSame($doesSupport, $result);
    }

    public function testFacetModeForBooleanResult()
    {
        $filter = new LabelTextFilter('free_shipping', 'Free Shipping');

        $facet = new ProductAttributeFacet(
            'field',
            ProductAttributeFacet::MODE_BOOLEAN_RESULT,
            'field',
            'Field'
        );

        $facetHandler = new TextFacetHandler();
        $result = $facetHandler->generatePartialFacet($facet, new Criteria(), $filter);

        $this->assertNull($result);
    }

    /**
     * @dataProvider valueListDataProvider
     * @dataProvider radioListDataProvider
     *
     * @param array $filterData
     * @param FacetResultInterface $facetResult
     * @param string $mode
     * @param ConditionInterface|null $condition
     */
    public function testGeneratesPartialFacetBasedOnFilterDataAndActiveConditions(
        array $filterData,
        FacetResultInterface $facetResult,
        $mode,
        ConditionInterface $condition = null
    ) {
        $facet = new ProductAttributeFacet(
            'vendor',
            $mode,
            'vendor',
            'Manufacturer'
        );
        $criteria = new Criteria();
        if ($condition !== null) {
            $criteria->addCondition($condition);
        }

        $filter = $this->generateFilter($filterData);

        $facetHandler = new TextFacetHandler();
        $result = $facetHandler->generatePartialFacet($facet, $criteria, $filter);

        $this->assertEquals($facetResult, $result);
    }

    public function valueListDataProvider()
    {
        return [
            'Facet with condition and filter' => [
                [
                    'name' => 'vendor',
                    'display' => 'Manufacturer',
                    'select' => 'single',
                    'type' => 'label',
                    'items' => [
                        [
                            'name' => 'FINDOLOGIC',
                            'frequency' => 42
                        ]
                    ]
                ],
                new ValueListFacetResult(
                    'product_attribute_vendor',
                    true,
                    'Manufacturer',
                    [
                        new ValueListItem(
                            'FINDOLOGIC',
                            'FINDOLOGIC',
                            true
                        )
                    ],
                    'vendor'
                ),
                ProductAttributeFacet::MODE_VALUE_LIST_RESULT,
                new ProductAttributeCondition('vendor', Operator::EQ, ['FINDOLOGIC']),
            ],
            'Facet with condition and missing filter value' => [
                [
                    'name' => 'vendor',
                    'display' => 'Manufacturer',
                    'select' => 'single',
                    'type' => 'label',
                    'items' => [
                        [
                            'name' => 'E-Bike',
                            'frequency' => 42
                        ]
                    ]
                ],
                new ValueListFacetResult(
                    'product_attribute_vendor',
                    true,
                    'Manufacturer',
                    [
                        new ValueListItem(
                            'E-Bike',
                            'E-Bike (42)',
                            false
                        ),
                        new ValueListItem(
                            'FINDOLOGIC',
                            'FINDOLOGIC',
                            true
                        )
                    ],
                    'vendor'
                ),
                ProductAttributeFacet::MODE_VALUE_LIST_RESULT,
                new ProductAttributeCondition('vendor', Operator::EQ, ['FINDOLOGIC']),
            ],
            'Facet with filter without condition' => [
                [
                    'name' => 'vendor',
                    'display' => 'Manufacturer',
                    'select' => 'single',
                    'type' => 'label',
                    'items' => [
                        [
                            'name' => 'E-Bike',
                            'frequency' => 42
                        ],
                        [
                            'name' => 'FINDOLOGIC'
                        ]
                    ]
                ],
                new ValueListFacetResult(
                    'product_attribute_vendor',
                    false,
                    'Manufacturer',
                    [
                        new ValueListItem(
                            'E-Bike',
                            'E-Bike (42)',
                            false
                        ),
                        new ValueListItem(
                            'FINDOLOGIC',
                            'FINDOLOGIC',
                            false
                        ),
                    ],
                    'vendor'
                ),
                ProductAttributeFacet::MODE_VALUE_LIST_RESULT,
            ]
        ];
    }

    public function radioListDataProvider()
    {
        return [
            'Facet with condition and filter' => [
                [
                    'name' => 'vendor',
                    'display' => 'Manufacturer',
                    'select' => 'single',
                    'type' => 'label',
                    'items' => [
                        [
                            'name' => 'FINDOLOGIC',
                            'frequency' => 42
                        ]
                    ]
                ],
                new RadioFacetResult(
                    'product_attribute_vendor',
                    true,
                    'Manufacturer',
                    [
                        new ValueListItem(
                            'FINDOLOGIC',
                            'FINDOLOGIC',
                            true
                        )
                    ],
                    'vendor'
                ),
                ProductAttributeFacet::MODE_RADIO_LIST_RESULT,
                new ProductAttributeCondition('vendor', Operator::EQ, 'FINDOLOGIC'),
            ],
            'Facet with condition and missing filter value' => [
                [
                    'name' => 'vendor',
                    'display' => 'Manufacturer',
                    'select' => 'single',
                    'type' => 'label',
                    'items' => [
                        [
                            'name' => 'E-Bike',
                            'frequency' => 42
                        ]
                    ]
                ],
                new RadioFacetResult(
                    'product_attribute_vendor',
                    true,
                    'Manufacturer',
                    [
                        new ValueListItem(
                            'E-Bike',
                            'E-Bike (42)',
                            false
                        ),
                        new ValueListItem(
                            'FINDOLOGIC',
                            'FINDOLOGIC',
                            true
                        )
                    ],
                    'vendor'
                ),
                ProductAttributeFacet::MODE_RADIO_LIST_RESULT,
                new ProductAttributeCondition('vendor', Operator::EQ, 'FINDOLOGIC'),
            ],
            'Facet with filter without condition' => [
                [
                    'name' => 'vendor',
                    'display' => 'Manufacturer',
                    'select' => 'single',
                    'type' => 'label',
                    'items' => [
                        [
                            'name' => 'E-Bike',
                            'frequency' => 42
                        ],
                        [
                            'name' => 'FINDOLOGIC'
                        ]
                    ]
                ],
                new RadioFacetResult(
                    'product_attribute_vendor',
                    false,
                    'Manufacturer',
                    [
                        new ValueListItem(
                            'E-Bike',
                            'E-Bike (42)',
                            false
                        ),
                        new ValueListItem(
                            'FINDOLOGIC',
                            'FINDOLOGIC',
                            false
                        ),
                    ],
                    'vendor'
                ),
                ProductAttributeFacet::MODE_RADIO_LIST_RESULT
            ]
        ];
    }

    /**
     * @param array $filterData
     *
     * @return LabelTextFilter
     */
    public function generateFilter(array $filterData)
    {
        $filter = new LabelTextFilter($filterData['name'], $filterData['display']);
        foreach ($filterData['items'] as $item) {
            $filterValue = new FilterValue($item['name'], $item['name']);
            if ($item['frequency']) {
                $filterValue->setFrequency($item['frequency']);
            }

            $filter->addValue($filterValue);
        }

        return $filter;
    }
}
