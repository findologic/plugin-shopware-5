<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Bundle\SearchBundle\FacetResult\ColorListItem;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\ColorFacetHandler;
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
     * @param string $type
     * @param bool $doesSupport
     * @param bool $hasImageTag
     */
    public function testSupportsFilter($type, $doesSupport, $hasImageTag = false)
    {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $filter = new SimpleXMLElement($data);

        $filter->addChild('name', 'attr6');
        $filter->addChild('type', $type);

        if ($hasImageTag) {
            $filter->addChild('items')->addChild('item')->addChild('image');
        }
        $facetHandler = new ColorFacetHandler();
        $result = $facetHandler->supportsFilter($filter);

        $this->assertSame($doesSupport, $result);
    }

    public function filterProvider()
    {
        return [
            'Filter with "select" type' => ['select', false],
            'Filter with "label" type' => ['label', false],
            'Filter with "image" type' => ['image', false],
            'Filter with "range-slider" type' => ['range-slider', false],
            'Filter with "color" type without image tag' => ['color', true, false],
            'Filter with "color" type with image tag' => ['color', false, true],
        ];
    }

    /**
     * @dataProvider facetResultProvider
     *
     * @param array $filterData
     * @param ConditionInterface|null $condition
     * @param FacetResultInterface|null $facetResult
     */
    public function testGeneratesPartialFacetBasedOnFilterDataAndActiveConditions(
        array $filterData,
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

        $filter = $this->generateFilter($filterData);

        $facetHandler = new ColorFacetHandler();
        $result = $facetHandler->generatePartialFacet($facet, $criteria, $filter);

        $this->assertEquals($facetResult, $result);
    }

    public function facetResultProvider()
    {
        return [
            'Color filter without condition' => [
                'Filter data' => [
                    'name' => 'color',
                    'display' => 'Farbe',
                    'select' => 'multiselect',
                    'type' => 'color',
                    'items' => [
                        [
                            'name' => 'Red',
                            'color' => '#ff0000'
                        ],
                        [
                            'name' => 'Green',
                            'color' => '#00ff00'
                        ]
                    ]
                ],
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
                'Filter data' => [
                    'name' => 'color',
                    'display' => 'Farbe',
                    'select' => 'multiselect',
                    'type' => 'color',
                    'items' => [
                        [
                            'name' => 'Red',
                            'color' => '#ff0000'
                        ],
                        [
                            'name' => 'Green',
                            'color' => null
                        ]
                    ]
                ],
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
                'Filter data' => [
                    'name' => 'color',
                    'display' => 'Farbe',
                    'select' => 'multiselect',
                    'type' => 'color',
                    'items' => [
                        [
                            'name' => 'Red',
                            'color' => '#ff0000'
                        ],
                        [
                            'name' => 'Green',
                            'color' => '#00ff00'
                        ]
                    ]
                ],
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
                'Filter data' => [
                    'name' => 'color',
                    'display' => 'Farbe',
                    'select' => 'multiselect',
                    'type' => 'color',
                    'items' => [
                        [
                            'name' => 'Red',
                            'color' => '#ff0000'
                        ],
                        [
                            'name' => 'Green',
                            'color' => '#00ff00'
                        ]
                    ]
                ],
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
