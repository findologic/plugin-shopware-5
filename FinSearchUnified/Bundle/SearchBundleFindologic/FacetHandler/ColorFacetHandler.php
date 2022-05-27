<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundle\FacetResult\ColorListItem;
use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Filter\BaseFilter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\ColorPickerFilter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values\ColorFilterValue;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundle\FacetResult\MediaListFacetResult;

class ColorFacetHandler implements PartialFacetHandlerInterface
{
    /**
     * @param FacetInterface $facet
     * @param Criteria $criteria
     * @param BaseFilter $filter
     *
     * @return MediaListFacetResult
     */
    public function generatePartialFacet(FacetInterface $facet, Criteria $criteria, BaseFilter $filter)
    {
        $actives = [];

        /** @var ProductAttributeFacet $facet */
        $condition = $criteria->getCondition($facet->getName());

        if ($condition !== null) {
            $actives = $condition->getValue();
        }

        if (!is_array($actives)) {
            $actives = [$actives];
        }

        return new MediaListFacetResult(
            $facet->getName(),
            $criteria->hasCondition($facet->getName()),
            $facet->getLabel(),
            $this->getColorItems($filter->getValues(), $actives),
            $facet->getFormFieldName(),
            ['multiselect' => $filter->getMode() === BaseFilter::MULTISELECT_TYPE],
            'frontend/listing/filter/facet-color-list.tpl'
        );
    }

    /**
     * @param BaseFilter $filter
     *
     * @return bool
     */
    public function supportsFilter(BaseFilter $filter)
    {
        return $filter instanceof ColorPickerFilter;
    }

    /**
     * @param ColorFilterValue[] $filterItems
     * @param array $actives
     *
     * @return array
     */
    private function getColorItems(array $filterItems, array $actives)
    {
        $items = [];

        foreach ($filterItems as $filterItem) {
            $active = false;
            $name = $filterItem->getName();
            $color = $filterItem->getColorHexCode();

            $index = array_search($name, $actives);

            if ($index !== false) {
                $active = true;
                unset($actives[$index]);
            }

            if ($image = $filterItem->getMedia()) {
                $imageUrl = $image->getUrl();
            }

            $items[] = new ColorListItem(
                $name,
                $name,
                $active,
                !empty($color) ? $color : null,
                !empty($imageUrl) ? $imageUrl : null
            );
        }

        foreach ($actives as $active) {
            $items[] = new ColorListItem(
                $active,
                $active,
                true
            );
        }

        return $items;
    }
}
