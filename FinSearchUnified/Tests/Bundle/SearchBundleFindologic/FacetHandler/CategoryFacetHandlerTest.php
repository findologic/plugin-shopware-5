<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\CategoryFacetHandler;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\SearchBundle\FacetResult\TreeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\TreeItem;
use Shopware\Bundle\SearchBundle\FacetResultInterface;
use SimpleXMLElement;

class CategoryFacetHandlerTest extends TestCase
{
    /**
     * @dataProvider filterProvider
     *
     * @param string $name
     * @param bool $doesSupport
     */
    public function testSupportsFilter($name, $doesSupport)
    {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $filter = new SimpleXMLElement($data);

        $filter->addChild('name', $name);

        $facetHandler = new CategoryFacetHandler();
        $result = $facetHandler->supportsFilter($filter);

        $this->assertSame($doesSupport, $result);
    }

    public function filterProvider()
    {
        return [
            'Category filter' => ['cat', true],
            'Vendor filter' => ['vendor', false],
        ];
    }

    /**
     * @dataProvider treeItemProvider
     *
     * @param array $filterData
     * @param string $mode
     * @param FacetResultInterface $facetResult
     * @param ConditionInterface|null $condition
     */
    public function testGeneratesPartialFacetBasedOnFilterDataAndActiveConditions(
        array $filterData,
        $mode,
        FacetResultInterface $facetResult,
        ConditionInterface $condition = null
    ) {
        $facet = new ProductAttributeFacet('cat', $mode, 'cat', 'Category');

        $criteria = new Criteria();
        if ($condition !== null) {
            $criteria->addCondition($condition);
        }

        $filter = $this->generateFilter($filterData);

        $facetHandler = new CategoryFacetHandler();
        $result = $facetHandler->generatePartialFacet($facet, $criteria, $filter);

        $this->assertEquals($facetResult, $result);
    }

    public function treeItemProvider()
    {
        return [
            'Facet with filter without condition' => [
                'Filter Data' =>
                    [
                        'name' => 'cat',
                        'display' => 'Category',
                        'select' => 'single',
                        'type' => 'label',
                        'items' => [
                            [
                                'name' => 'Category 1',
                                'frequency' => 2,
                                'items' => [
                                    [
                                        'name' => 'Child 1',
                                        'frequency' => 2,
                                        'items' => [
                                            [
                                                'name' => 'Child 2',
                                                'frequency' => 2,
                                                'items' => []
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            [
                                'name' => 'Category 2',
                                'frequency' => 34,
                                'items' => [
                                    [
                                        'name' => 'Child 3',
                                        'frequency' => 30,
                                        'items' => []
                                    ],
                                    [
                                        'name' => 'Child 4',
                                        'frequency' => 4,
                                        'items' => []
                                    ]
                                ]
                            ],
                            [
                                'name' => 'Category 3',
                                'frequency' => 42,
                                'items' => []
                            ],
                        ]
                    ],
                'Mode' =>
                    ProductAttributeFacet::MODE_RADIO_LIST_RESULT,
                'Facet Result' =>
                    new TreeFacetResult(
                        'product_attribute_cat',
                        'cat',
                        false,
                        'Category',
                        [
                            new TreeItem(
                                'Category 1',
                                'Category 1 (2)',
                                false,
                                [
                                    new TreeItem(
                                        'Category 1_Child 1',
                                        'Child 1 (2)',
                                        false,
                                        [
                                            new TreeItem(
                                                'Category 1_Child 1_Child 2',
                                                'Child 2 (2)',
                                                false,
                                                []
                                            )
                                        ]
                                    )
                                ]
                            ),
                            new TreeItem(
                                'Category 2',
                                'Category 2 (34)',
                                false,
                                [
                                    new TreeItem(
                                        'Category 2_Child 3',
                                        'Child 3 (30)',
                                        false,
                                        []
                                    ),
                                    new TreeItem(
                                        'Category 2_Child 4',
                                        'Child 4 (4)',
                                        false,
                                        []
                                    )
                                ]
                            ),
                            new TreeItem(
                                'Category 3',
                                'Category 3 (42)',
                                false,
                                []
                            )
                        ]
                    )
            ],
            'Facet with filter and category condition' => [
                'Filter Data' =>
                    [
                        'name' => 'cat',
                        'display' => 'Category',
                        'select' => 'single',
                        'type' => 'select',
                        'items' => [
                            [
                                'name' => 'Category 1',
                                'frequency' => 2,
                                'items' => [
                                    [
                                        'name' => 'Child 1',
                                        'frequency' => 2,
                                        'items' => [
                                            [
                                                'name' => 'Child 2',
                                                'frequency' => 2,
                                                'items' => []
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            [
                                'name' => 'Category 2',
                                'frequency' => 34,
                                'items' => [
                                    [
                                        'name' => 'Child 3',
                                        'frequency' => 30,
                                        'items' => []
                                    ],
                                    [
                                        'name' => 'Child 4',
                                        'frequency' => 4,
                                        'items' => []
                                    ]
                                ]
                            ],
                            [
                                'name' => 'Category 3',
                                'frequency' => 42,
                                'items' => []
                            ]
                        ]
                    ],
                'Mode' =>
                    ProductAttributeFacet::MODE_VALUE_LIST_RESULT,
                'Facet Result' =>
                    new TreeFacetResult(
                        'product_attribute_cat',
                        'cat',
                        true,
                        'Category',
                        [
                            new TreeItem(
                                'Category 1',
                                'Category 1 (2)',
                                false,
                                [
                                    new TreeItem(
                                        'Category 1_Child 1',
                                        'Child 1',
                                        true,
                                        [
                                            new TreeItem(
                                                'Category 1_Child 1_Child 2',
                                                'Child 2 (2)',
                                                false,
                                                []
                                            )
                                        ]
                                    )
                                ]
                            ),
                            new TreeItem(
                                'Category 2',
                                'Category 2 (34)',
                                false,
                                [
                                    new TreeItem(
                                        'Category 2_Child 3',
                                        'Child 3 (30)',
                                        false,
                                        []
                                    ),
                                    new TreeItem(
                                        'Category 2_Child 4',
                                        'Child 4 (4)',
                                        false,
                                        []
                                    )
                                ]
                            ),
                            new TreeItem(
                                'Category 3',
                                'Category 3',
                                true,
                                []
                            )
                        ]
                    ),
                'Condition' =>
                    new ProductAttributeCondition(
                        'cat',
                        Operator::EQ,
                        ['Category 1_Child 1', 'Category 3']
                    )
            ],
            'Facet with filter without frequency but with category condition' => [
                'Filter Data' =>
                    [
                        'name' => 'cat',
                        'display' => 'Category',
                        'select' => 'single',
                        'type' => 'select',
                        'items' => [
                            [
                                'name' => 'Category 1',
                                'items' => [
                                    [
                                        'name' => 'Child 1',
                                        'items' => [
                                            [
                                                'name' => 'Child 2',
                                                'items' => []
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            [
                                'name' => 'Category 2',
                                'items' => [
                                    [
                                        'name' => 'Child 3',
                                        'items' => []
                                    ],
                                    [
                                        'name' => 'Child 4',
                                        'items' => []
                                    ]
                                ]
                            ],
                            [
                                'name' => 'Category 3',
                                'items' => []
                            ]
                        ]
                    ],
                'Mode' =>
                    ProductAttributeFacet::MODE_VALUE_LIST_RESULT,
                'Facet Result' =>
                    new TreeFacetResult(
                        'product_attribute_cat',
                        'cat',
                        true,
                        'Category',
                        [
                            new TreeItem(
                                'Category 1',
                                'Category 1',
                                false,
                                [
                                    new TreeItem(
                                        'Category 1_Child 1',
                                        'Child 1',
                                        true,
                                        [
                                            new TreeItem(
                                                'Category 1_Child 1_Child 2',
                                                'Child 2',
                                                false,
                                                []
                                            )
                                        ]
                                    )
                                ]
                            ),
                            new TreeItem(
                                'Category 2',
                                'Category 2',
                                false,
                                [
                                    new TreeItem(
                                        'Category 2_Child 3',
                                        'Child 3',
                                        false,
                                        []
                                    ),
                                    new TreeItem(
                                        'Category 2_Child 4',
                                        'Child 4',
                                        false,
                                        []
                                    )
                                ]
                            ),
                            new TreeItem(
                                'Category 3',
                                'Category 3',
                                true,
                                []
                            )
                        ]
                    ),
                'Condition' =>
                    new ProductAttributeCondition(
                        'cat',
                        Operator::EQ,
                        ['Category 1_Child 1', 'Category 3']
                    )
            ]
        ];
    }

    /**
     * Helper method to generate filter XML based on the filter data
     *
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
                $this->addItems($filter, $value);
            } else {
                $filter->addChild($key, $value);
            }
        }

        return $filter;
    }

    /**
     * Helper method to recursively create child items in filter XML
     *
     * @param SimpleXMLElement $filter
     * @param $value
     */
    private function addItems(SimpleXMLElement $filter, $value)
    {
        $items = $filter->addChild('items');

        foreach ($value as $itemData) {
            $item = $items->addChild('item');
            foreach ($itemData as $k => $v) {
                if (is_array($v)) {
                    $this->addItems($item, $v);
                } else {
                    $item->addChild($k, $v);
                }
            }
        }
    }
}
