<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundle\FacetResult\ColorListItem;
use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundle\FacetResult\MediaListFacetResult;
use SimpleXMLElement;

class ColorFacetHandler implements PartialFacetHandlerInterface
{
    public function generatePartialFacet(FacetInterface $facet, Criteria $criteria, SimpleXMLElement $filter)
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
            $facet->getField(),
            $criteria->hasCondition($facet->getName()),
            $facet->getLabel(),
            $this->getColorItems($filter->items->item, $actives),
            $facet->getFormFieldName(),
            [],
            'frontend/listing/filter/facet-color-list.tpl'
        );
    }

    public function supportsFilter(SimpleXMLElement $filter)
    {
        return (string)$filter->type === 'color' && !isset($filter->items->item[0]->image);
    }

    private function getColorItems(SimpleXMLElement $filterItems, array $actives)
    {
        $items = [];

        foreach ($filterItems as $filterItem) {
            $active = false;
            $name = (string)$filterItem->name;
            $color = (string)$filterItem->color;

            $index = array_search($name, $actives);

            if ($index !== false) {
                $active = true;
                unset($actives[$index]);
            }

            $items[] = new ColorListItem(
                $name,
                $name,
                $active,
                !empty($color) ? $color : null
            );
        }

        foreach ($actives as $active) {
            $colorListItem = new ColorListItem(
                $active,
                $active,
                true
            );

            $items[] = $colorListItem;
        }

        return $items;
    }
}
