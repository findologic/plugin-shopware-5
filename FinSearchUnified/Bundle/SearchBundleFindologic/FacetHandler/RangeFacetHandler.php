<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use SimpleXMLElement;

class RangeFacetHandler implements PartialFacetHandlerInterface
{
    /**
     * @param FacetInterface $facet
     * @param Criteria $criteria
     * @param SimpleXMLElement $filter
     *
     * @return RangeFacetResult|null
     */
    public function generatePartialFacet(FacetInterface $facet, Criteria $criteria, SimpleXMLElement $filter)
    {
        $min = (float)$filter->attributes->totalRange->min;
        $max = (float)$filter->attributes->totalRange->max;

        if ($min === $max) {
            return null;
        }

        $activeMin = (float)$filter->attributes->selectedRange->min;
        $activeMax = (float)$filter->attributes->selectedRange->max;

        $conditionField = $facet->getField();
        $conditionName = $facet->getName();
        $minFieldName = 'min' . $conditionField;
        $maxFieldName = 'max' . $conditionField;

        if ((string)$filter->name === 'price') {
            $minFieldName = 'min';
            $maxFieldName = 'max';
            $conditionField = $conditionName = 'price';
        }

        $unit = !empty($filter->attributes->unit) ? (string)$filter->attributes->unit : null;
        return new RangeFacetResult(
            $conditionField,
            $criteria->hasCondition($conditionName),
            $facet->getLabel(),
            $min,
            $max,
            $activeMin,
            $activeMax,
            $minFieldName,
            $maxFieldName,
            [],
            $unit
        );
    }

    /**
     * @param SimpleXMLElement $filter
     *
     * @return bool
     */
    public function supportsFilter(SimpleXMLElement $filter)
    {
        return ((string)$filter->type === 'range-slider');
    }
}
