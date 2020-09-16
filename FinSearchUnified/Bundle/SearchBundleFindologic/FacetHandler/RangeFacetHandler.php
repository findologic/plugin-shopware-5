<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Filter\BaseFilter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\RangeSliderFilter;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;

class RangeFacetHandler implements PartialFacetHandlerInterface
{
    const TEMPLATE_PATH = 'frontend/listing/filter/facet-range.tpl';

    /**
     * @param FacetInterface $facet
     * @param Criteria $criteria
     * @param BaseFilter $filter
     *
     * @return RangeFacetResult|null
     */
    public function generatePartialFacet(FacetInterface $facet, Criteria $criteria, BaseFilter $filter)
    {
        /** @var RangeSliderFilter $filter */
        $min = $filter->getMin();
        $max = $filter->getMax();

        if ($min === $max) {
            return null;
        }

        $activeMin = $filter->getActiveMin();
        $activeMax = $filter->getActiveMax();

        $conditionField = $facet->getField();
        $conditionName = $facet->getName();
        $minFieldName = $filter->getMinKey();
        $maxFieldName = $filter->getMaxKey();

        if ($filter->getId() === 'price') {
            $minFieldName = 'min';
            $maxFieldName = 'max';
            $conditionField = $conditionName = 'price';
        }

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
            $this->getUnit($filter)
        );
    }

    /**
     * Fetches the unit from the filter. May return the template path if Shopware version is >5.3.0.
     *
     * @param BaseFilter $filter
     *
     * @return string
     */
    private function getUnit(BaseFilter $filter)
    {
        if (StaticHelper::isVersionLowerThan('5.3')) {
            // Shopware < 5.3.0 does not support units. In Shopware 5.2.x this argument is the template path.
            return self::TEMPLATE_PATH;
        }

        return $filter->getUnit();
    }

    /**
     * @param BaseFilter $filter
     *
     * @return bool
     */
    public function supportsFilter(BaseFilter $filter)
    {
        return $filter instanceof RangeSliderFilter;
    }
}
