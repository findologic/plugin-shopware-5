<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use Shopware;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundle\FacetResult\RangeFacetResult;
use SimpleXMLElement;

class RangeFacetHandler implements PartialFacetHandlerInterface
{
    const TEMPLATE_PATH = 'frontend/listing/filter/facet-range.tpl';

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
     * @param SimpleXMLElement $filter
     * @return string|null
     */
    private function getUnit(SimpleXMLElement $filter)
    {
        $shopwareVersion = Shopware()->Config()->get('version');

        if (version_compare($shopwareVersion, '5.3', '<')) {
            // Shopware >5.3.0 does not support units. In Shopware 5.2.x this argument is the template path.
            return self::TEMPLATE_PATH;
        }

        return !empty($filter->attributes->unit) ? (string)$filter->attributes->unit : null;
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
