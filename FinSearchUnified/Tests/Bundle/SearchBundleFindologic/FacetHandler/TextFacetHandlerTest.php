<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\TextFacetHandler;
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
    /**
     * @dataProvider filterProvider
     *
     * @param string $name
     * @param string $type
     * @param bool $doesSupport
     */
    public function testSupportsFilter($name, $type, $doesSupport)
    {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $filter = new SimpleXMLElement($data);

        $filter->addChild('name', $name);
        $filter->addChild('type', $type);

        $facetHandler = new TextFacetHandler();
        $result = $facetHandler->supportsFilter($filter);

        $this->assertSame($doesSupport, $result);
    }

    public function filterProvider()
    {
        return [
            'Category filter with "select" type' => ['cat', 'select', false],
            'Category filter with "label" type' => ['cat', 'label', false],
            'Vendor filter with "select" type' => ['vendor', 'select', true],
            'Vendor filter with "label" type' => ['vendor', 'label', true],
        ];
    }

    public function testFacetModeForBooleanResult()
    {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $filter = new SimpleXMLElement($data);

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
     * @return SimpleXMLElement
     */
    public function generateFilter(array $filterData)
    {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $filter = new SimpleXMLElement($data);

        // Loop through the data to generate filter xml
        foreach ($filterData as $key => $value) {
            if (is_array($value)) {
                $items = $filter->addChild('items');
                foreach ($value as $itemData) {
                    $item = $items->addChild('item');
                    foreach ($itemData as $k => $v) {
                        $item->addChild($k, $v);
                    }
                }
            } else {
                $filter->addChild($key, $value);
            }
        }

        return $filter;
    }
}
