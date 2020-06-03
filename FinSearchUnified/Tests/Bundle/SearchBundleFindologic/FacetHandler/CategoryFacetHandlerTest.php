<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\CategoryFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\CategoryFilter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Filter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values\CategoryFilterValue;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\VendorImageFilter;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\SearchBundle\FacetResult\TreeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\TreeItem;
use Shopware\Bundle\SearchBundle\FacetResultInterface;

class CategoryFacetHandlerTest extends TestCase
{
    /**
     * @dataProvider filterProvider
     *
     * @param Filter $filter
     * @param bool $doesSupport
     */
    public function testSupportsFilter(Filter $filter, $doesSupport)
    {
        $facetHandler = new CategoryFacetHandler();
        $result = $facetHandler->supportsFilter($filter);

        $this->assertSame($doesSupport, $result);
    }

    public function filterProvider()
    {
        return [
            'Category filter' => [new CategoryFilter('cat', 'Category'), true],
            'Vendor filter' => [new VendorImageFilter('vendor', 'Vendor'), false],
        ];
    }

    public function treeItemProvider()
    {
        $filterWithFrequencies = $this->createCatFilterWithFrequencies();

        return [
            'Facet with filter without condition' => [
                'Filter' => $filterWithFrequencies,
                'Mode' => ProductAttributeFacet::MODE_RADIO_LIST_RESULT,
                'Facet Result' => new TreeFacetResult(
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
                ),
                'Condition' => null
            ],
            'Facet with filter and category condition' => [
                'Filter' => $filterWithFrequencies,
                'Mode' => ProductAttributeFacet::MODE_VALUE_LIST_RESULT,
                'Facet Result' => new TreeFacetResult(
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
                'Condition' => new ProductAttributeCondition(
                    'cat',
                    Operator::EQ,
                    ['Category 1_Child 1', 'Category 3']
                )
            ],
            'Facet with filter without frequency but with category condition' => [
                'Filter Data' => $this->createCatFilterWithoutFrequencies(),
                'Mode' => ProductAttributeFacet::MODE_VALUE_LIST_RESULT,
                'Facet Result' => new TreeFacetResult(
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
                'Condition' => new ProductAttributeCondition(
                    'cat',
                    Operator::EQ,
                    ['Category 1_Child 1', 'Category 3']
                )
            ]
        ];
    }

    /**
     * @dataProvider treeItemProvider
     *
     * @param Filter $filter
     * @param string $mode
     * @param FacetResultInterface $facetResult
     * @param ConditionInterface|null $condition
     */
    public function testGeneratesPartialFacetBasedOnFilterDataAndActiveConditions(
        Filter $filter,
        $mode,
        FacetResultInterface $facetResult,
        ConditionInterface $condition = null
    ) {
        $facet = new ProductAttributeFacet('cat', $mode, 'cat', 'Category');

        $criteria = new Criteria();
        if ($condition !== null) {
            $criteria->addCondition($condition);
        }

        $facetHandler = new CategoryFacetHandler();
        $result = $facetHandler->generatePartialFacet($facet, $criteria, $filter);

        $this->assertEquals($facetResult, $result);
    }

    /**
     * @return CategoryFilter
     */
    private function createCatFilterWithFrequencies()
    {
        $filter = new CategoryFilter('cat', 'Category');
        $filter->addValue(
            (new CategoryFilterValue('Category 1', 'Category 1'))->setFrequency(2)
                ->addValue(
                    (new CategoryFilterValue('Child 1', 'Child 1'))->setFrequency(2)
                        ->addValue(
                            (new CategoryFilterValue('Child 2', 'Child 2'))->setFrequency(2)
                        )
                )
        );
        $filter->addValue(
            (new CategoryFilterValue('Category 2', 'Category 2'))->setFrequency(34)
                ->addValue((new CategoryFilterValue('Child 3', 'Child 3'))->setFrequency(30))
                ->addValue((new CategoryFilterValue('Child 4', 'Child 4'))->setFrequency(4))
        );
        $filter->addValue((new CategoryFilterValue('Category 3', 'Category 3'))->setFrequency(42));

        return $filter;
    }

    /**
     * @return CategoryFilter
     */
    private function createCatFilterWithoutFrequencies()
    {
        $filter = new CategoryFilter('cat', 'Category');
        $filter->addValue(
            (new CategoryFilterValue('Category 1', 'Category 1'))
                ->addValue(
                    (new CategoryFilterValue('Child 1', 'Child 1'))
                        ->addValue(
                            (new CategoryFilterValue('Child 2', 'Child 2'))
                        )
                )
        );
        $filter->addValue(
            (new CategoryFilterValue('Category 2', 'Category 2'))
                ->addValue(new CategoryFilterValue('Child 3', 'Child 3'))
                ->addValue(new CategoryFilterValue('Child 4', 'Child 4'))
        );
        $filter->addValue(new CategoryFilterValue('Category 3', 'Category 3'));

        return $filter;
    }
}
