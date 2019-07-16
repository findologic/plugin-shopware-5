<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundle\FacetResult\TreeFacetResult;
use Shopware\Bundle\SearchBundle\FacetResult\TreeItem;
use SimpleXMLElement;

class CategoryFacetHandler implements PartialFacetHandlerInterface
{
    /**
     * @param FacetInterface $facet
     * @param Criteria $criteria
     * @param SimpleXMLElement $filter
     *
     * @return TreeFacetResult
     */
    public function generatePartialFacet(FacetInterface $facet, Criteria $criteria, SimpleXMLElement $filter)
    {
        $categories = $this->getActives($facet, $criteria);
        $this->parseCategories($filter->items->item, $categories);

        return new TreeFacetResult(
            $facet->getField(),
            $facet->getFormFieldName(),
            $criteria->hasCondition($facet->getName()),
            $facet->getLabel(),
            $this->getTreeItems($categories)
        );
    }

    /**
     * @param SimpleXMLElement $filter
     *
     * @return bool
     */
    public function supportsFilter(SimpleXMLElement $filter)
    {
        return (string)$filter->name === 'cat';
    }

    /**
     * Parses the active values and returns an array
     *
     * @param FacetInterface $facet
     * @param Criteria $criteria
     *
     * @return array
     */
    private function getActives(FacetInterface $facet, Criteria $criteria)
    {
        $actives = [];

        $condition = $criteria->getCondition($facet->getName());

        if ($condition !== null) {
            $actives = $condition->getValue();
        }

        if (!is_array($actives)) {
            $actives = [$actives];
        }

        $categories = [];

        foreach ($actives as $active) {
            $categories[] = $this->prepareCategoryTree($active);
        }

        return $categories;
    }

    /**
     * Parse the filter items and build an array structure
     *
     * @param SimpleXMLElement $filterItems
     * @param array $actives
     */
    private function parseCategories(SimpleXMLElement $filterItems, array &$actives)
    {
        foreach ($filterItems as $filterItem) {
            $name = (string)$filterItem->name;
            $frequency = (int)$filterItem->frequency;

            // Only add the items of $filterItems that are not already present in $actives
            $index = array_search($name, $actives);
            if ($index === false) {
                $category = [
                    'active' => $filterItem->items->item ? false : true,
                    'children' => $filterItem->items->item ? self::parseCategories($filterItem, $actives) : []
                ];

                if ($frequency) {
                    $category['frequency'] = $frequency;
                }

                $actives[$name] = $category;
            }
        }
    }

    /**
     * Helper method to recursively create category tree
     *
     * @param $active
     *
     * @return mixed
     */
    private function prepareCategoryTree($active)
    {
        $categories = [];

        list($parent, $child) = explode('_', $active);

        $categories[$parent] = [
            'active' => $child ? false : true,
            'children' => $child ? self::prepareCategoryTree($child) : []
        ];

        return $categories;
    }

    /**
     * Convert the array of categories to an array of tree items
     *
     * @param array $categories
     * @param null $parent
     *
     * @return array
     */
    private function getTreeItems(array $categories, $parent = null)
    {
        $items = [];

        foreach ($categories as $key => $category) {
            if ($parent !== null) {
                $id = $parent . '_' . $key;
            } else {
                $id = $key;
            }

            $label = $key;

            if (array_key_exists('frequency', $category) && !$category['active']) {
                $label = sprintf('%s (%d)', $key, $category['frequency']);
            }

            $item = new TreeItem(
                $id,
                $label,
                $category['active'],
                self::getTreeItems($category['children'], $id)
            );

            $items[] = $item;
        }

        return $items;
    }
}
