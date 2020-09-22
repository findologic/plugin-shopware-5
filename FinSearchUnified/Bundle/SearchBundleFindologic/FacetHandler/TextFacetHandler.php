<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Filter\BaseFilter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\LabelTextFilter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\SelectDropdownFilter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values\FilterValue;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundle\FacetResult\RadioFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\ValueListItem;
use Shopware\Bundle\SearchBundle\FacetResultInterface;

class TextFacetHandler implements PartialFacetHandlerInterface
{
    /**
     * @param FacetInterface $facet
     * @param Criteria $criteria
     * @param BaseFilter $filter
     *
     * @return FacetResultInterface|null
     */
    public function generatePartialFacet(FacetInterface $facet, Criteria $criteria, BaseFilter $filter)
    {
        /** @var ProductAttributeFacet $facet */
        switch ($facet->getMode()) {
            case ProductAttributeFacet::MODE_VALUE_LIST_RESULT:
                $result = $this->createValueListFacetResult($facet, $criteria, $filter);
                break;
            case ProductAttributeFacet::MODE_RADIO_LIST_RESULT:
                $result = $this->createRadioFacetResult($facet, $criteria, $filter);
                break;
            default:
                $result = null;
        }

        return $result;
    }

    /**
     * @param BaseFilter $filter
     *
     * @return bool
     */
    public function supportsFilter(BaseFilter $filter)
    {
        return $filter instanceof LabelTextFilter || $filter instanceof SelectDropdownFilter;
    }

    /**
     * @param FacetInterface $facet
     * @param Criteria $criteria
     * @param FilterValue[] $filterItems
     *
     * @return ValueListItem[]
     */
    private function getValueListItems(FacetInterface $facet, Criteria $criteria, array $filterItems = [])
    {
        $items = [];
        $actives = [];

        /** @var ProductAttributeFacet $facet */
        $condition = $criteria->getCondition($facet->getName());

        if ($condition !== null) {
            $actives = $condition->getValue();
        }

        if (!is_array($actives)) {
            $actives = [$actives];
        }

        foreach ($filterItems as $filterItem) {
            $name = $filterItem->getName();
            $freq = $filterItem->getFrequency();
            $index = array_search($name, $actives);

            if ($index === false) {
                $active = false;
            } else {
                $active = true;
                unset($actives[$index]);
            }

            // Do not set filter item frequency if "Product & Filter live reloading" is enabled in the Shopware Backend.
            $filterReloadingEnabled = StaticHelper::isProductAndFilterLiveReloadingEnabled();

            if ($freq && !$active && !$filterReloadingEnabled) {
                $label = sprintf('%s (%d)', $name, $freq);
            } else {
                $label = $name;
            }

            $valueListItem = new ValueListItem(
                $name,
                $label,
                $active
            );

            $items[] = $valueListItem;
        }

        foreach ($actives as $element) {
            $valueListItem = new ValueListItem(
                $element,
                $element,
                true
            );

            $items[] = $valueListItem;
        }

        return $items;
    }

    /**
     * @param FacetInterface $facet
     * @param Criteria $criteria
     * @param BaseFilter $filter
     *
     * @return ValueListFacetResult
     */
    private function createValueListFacetResult(FacetInterface $facet, Criteria $criteria, BaseFilter $filter)
    {
        $values = $this->getValueListItems($facet, $criteria, $filter->getValues());
        $active = $criteria->hasCondition($facet->getName());

        return new ValueListFacetResult(
            $facet->getName(),
            $active,
            $facet->getLabel(),
            $values,
            $facet->getFormFieldName()
        );
    }

    /**
     * @param FacetInterface $facet
     * @param Criteria $criteria
     * @param BaseFilter $filter
     *
     * @return RadioFacetResult
     */
    private function createRadioFacetResult(FacetInterface $facet, Criteria $criteria, BaseFilter $filter)
    {
        $values = $this->getValueListItems($facet, $criteria, $filter->getValues());
        $active = $criteria->hasCondition($facet->getName());

        return new RadioFacetResult(
            $facet->getName(),
            $active,
            $facet->getLabel(),
            $values,
            $facet->getFormFieldName()
        );
    }
}
