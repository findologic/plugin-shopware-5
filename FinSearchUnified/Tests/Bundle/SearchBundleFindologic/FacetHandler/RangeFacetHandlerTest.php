<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\RangeFacetHandler;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\Condition\PriceCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResultInterface;
use Shopware_Components_Config as Config;
use SimpleXMLElement;

class RangeFacetHandlerTest extends TestCase
{
    public function setUp()
    {
        $mockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig->expects($this->any())
            ->method('get')
            ->willReturn(!empty(getenv('SHOPWARE_VERSION')) ? getenv('SHOPWARE_VERSION') : '5.6.4');

        Shopware()->Container()->set('config', $mockConfig);
    }

    /**
     * @dataProvider filterProvider
     *
     * @param string $type
     * @param bool $doesSupport
     */
    public function testSupportsFilter($type, $doesSupport)
    {
        $data = '<?xml version="1.0" encoding="UTF-8"?><searchResult></searchResult>';
        $filter = new SimpleXMLElement($data);

        $filter->addChild('name', 'attr6');
        $filter->addChild('type', $type);

        $facetHandler = new RangeFacetHandler();
        $result = $facetHandler->supportsFilter($filter);

        $this->assertSame($doesSupport, $result);
    }

    public function filterProvider()
    {
        return [
            'Filter with "select" type' => ['select', false],
            'Filter with "label" type' => ['label', false],
            'Filter with "color" type' => ['color', false],
            'Filter with "image" type' => ['image', false],
            'Filter with "range-slider" type' => ['range-slider', true],
        ];
    }

    /**
     * @dataProvider rangeFacetResultProvider
     *
     * @param array $filterData
     * @param string $field
     * @param string $label
     * @param ConditionInterface|null $condition
     * @param FacetResultInterface|null $facetResult
     */
    public function testGeneratesPartialFacetBasedOnFilterDataAndActiveConditions(
        array $filterData,
        $field,
        $label,
        ConditionInterface $condition = null,
        FacetResultInterface $facetResult = null
    ) {
        $facet = new ProductAttributeFacet(
            $field,
            ProductAttributeFacet::MODE_RANGE_RESULT,
            $field,
            $label
        );
        $criteria = new Criteria();
        if ($condition !== null) {
            $criteria->addCondition($condition);
        }

        $filter = $this->generateFilter($filterData);

        $facetHandler = new RangeFacetHandler();
        $result = $facetHandler->generatePartialFacet($facet, $criteria, $filter);

        $this->assertEquals($facetResult, $result);
    }

    public function rangeFacetResultProvider()
    {
        $shopwareVersion = getenv('SHOPWARE_VERSION') ? getenv('SHOPWARE_VERSION') : '5.6.4';
        $supportsUnit = version_compare($shopwareVersion, '5.3', '<');

        // Shopware >5.3.0 does not support units.
        $expectedUnit = $supportsUnit ? RangeFacetHandler::TEMPLATE_PATH : '€';

        return [
            'Total range boundaries are the same' => [
                [
                    'name' => 'attr6',
                    'display' => 'Length',
                    'select' => 'single',
                    'type' => 'range-slider',
                    'attributes' => [
                        'totalRange' => [
                            'min' => 4.20,
                            'max' => 4.20
                        ]
                    ]
                ],
                'attr6',
                'Length',
                null,
                null
            ],
            'Price filter is not selected' => [
                [
                    'name' => 'price',
                    'display' => 'Preis',
                    'select' => 'single',
                    'type' => 'range-slider',
                    'attributes' => [
                        'totalRange' => [
                            'min' => 4.20,
                            'max' => 69.00
                        ],
                        'selectedRange' => [
                            'min' => 4.20,
                            'max' => 69.00
                        ],
                        'unit' => '€'
                    ]
                ],
                'price',
                'Preis',
                null,
                new RangeFacetResult(
                    'price',
                    false,
                    'Preis',
                    4.20,
                    69.00,
                    4.20,
                    69.00,
                    'min',
                    'max',
                    [],
                    $expectedUnit
                )
            ],
            'Price filter is selected' => [
                [
                    'name' => 'price',
                    'display' => 'Preis',
                    'select' => 'single',
                    'type' => 'range-slider',
                    'attributes' => [
                        'totalRange' => [
                            'min' => 4.20,
                            'max' => 69.00
                        ],
                        'selectedRange' => [
                            'min' => 4.20,
                            'max' => 69.00
                        ]
                    ]
                ],
                'price',
                'Preis',
                new PriceCondition(4.20, 69.00),
                new RangeFacetResult(
                    'price',
                    true,
                    'Preis',
                    4.20,
                    69.00,
                    4.20,
                    69.00,
                    'min',
                    'max'
                )
            ],
            'Range filter is active' => [
                [
                    'name' => 'attr6',
                    'display' => 'Length',
                    'select' => 'single',
                    'type' => 'range-slider',
                    'attributes' => [
                        'totalRange' => [
                            'min' => 4.20,
                            'max' => 69.00
                        ],
                        'selectedRange' => [
                            'min' => 4.20,
                            'max' => 6.09
                        ]
                    ]
                ],
                'attr6',
                'Length',
                new ProductAttributeCondition('attr6', Operator::EQ, ['min' => 4.20, 'max' => 6.09]),
                new RangeFacetResult(
                    'attr6',
                    true,
                    'Length',
                    4.20,
                    69.00,
                    4.20,
                    6.09,
                    'minattr6',
                    'maxattr6'
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
                $attributes = $filter->addChild($key);
                foreach ($value as $range => $itemData) {
                    if (is_array($itemData)) {
                        $range = $attributes->addChild($range);
                        foreach ($itemData as $k => $v) {
                            $range->addChild($k, $v);
                        }
                        continue;
                    }

                    $attributes->addChild($range, $itemData);
                }
            } else {
                $filter->addChild($key, $value);
            }
        }

        return $filter;
    }
}
