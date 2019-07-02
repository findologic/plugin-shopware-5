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

        $minFieldName = 'min' . $facet->name;
        $maxFieldName = 'max' . $facet->name;

        if ($facet->name === 'price') {
            $minFieldName = 'min';
            $maxFieldName = 'max';
        }

        return new RangeFacetResult(
            (string)$filter->name,
            $criteria->hasCondition($facet->getName()),
            (string)$filter->display,
            $min,
            $max,
            $activeMin,
            $activeMax,
            $minFieldName,
            $maxFieldName
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
