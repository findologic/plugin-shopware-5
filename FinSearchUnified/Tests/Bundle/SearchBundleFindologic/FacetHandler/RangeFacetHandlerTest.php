<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\FacetHandler;

use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\ColorPickerFilter as ApiColorPickerFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\LabelTextFilter as ApiLabelTextFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\RangeSliderFilter as ApiRangeSliderFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\SelectDropdownFilter as ApiSelectDropdownFilter;
use FINDOLOGIC\Api\Responses\Xml21\Properties\Filter\VendorImageFilter as ApiVendorImageFilter;
use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\RangeFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Filter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\RangeSliderFilter;
use FinSearchUnified\Helper\StaticHelper;
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
    public function setUp(): void
    {
        $mockConfig = $this->getMockBuilder(Config::class)
            ->setMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfig->expects($this->any())
            ->method('get')
            ->willReturn(StaticHelper::getShopwareVersion());

        Shopware()->Container()->set('config', $mockConfig);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Shopware()->Container()->reset('config');
        Shopware()->Container()->load('config');
    }

    public function filterProvider()
    {
        return [
            'Filter with "select" type' => [ApiSelectDropdownFilter::class, false],
            'Filter with "label" type' => [ApiLabelTextFilter::class, false],
            'Filter with "image" type' => [ApiVendorImageFilter::class, false],
            'Filter with "range-slider" type' => [ApiRangeSliderFilter::class, true],
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
        $facetHandler = new RangeFacetHandler();
        $result = $facetHandler->supportsFilter(Filter::getInstance($filter));

        $this->assertSame($doesSupport, $result);
    }

    public function rangeFacetResultProvider()
    {
        $supportsUnit = StaticHelper::isVersionLowerThan('5.3');

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
                        'unit' => '£'
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
                    $supportsUnit ? RangeFacetHandler::TEMPLATE_PATH : '£'
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
                    'max',
                    [],
                    $supportsUnit ? RangeFacetHandler::TEMPLATE_PATH : null
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
                    'maxattr6',
                    [],
                    $supportsUnit ? RangeFacetHandler::TEMPLATE_PATH : null
                )
            ]
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

    /**
     * @param array $filterData
     *
     * @return RangeSliderFilter
     */
    public function generateFilter(array $filterData)
    {
        $filter = new RangeSliderFilter($filterData['name'], $filterData['display']);
        $selectedRange = $filterData['attributes']['selectedRange'];
        $totalRange = $filterData['attributes']['totalRange'];
        $unit = $filterData['attributes']['unit'];
        if ($selectedRange) {
            $filter->setActiveMin($selectedRange['min']);
            $filter->setActiveMax($selectedRange['max']);
        }
        if ($totalRange) {
            $filter->setMin($totalRange['min']);
            $filter->setMax($totalRange['max']);
        }
        if ($unit) {
            $filter->setUnit($unit);
        }

        return $filter;
    }
}
