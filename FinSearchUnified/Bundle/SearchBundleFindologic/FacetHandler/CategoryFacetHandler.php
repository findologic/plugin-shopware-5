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
        $parsed = $this->parseCategories($filter->items->item, $categories);
        $categories = array_merge($categories, $parsed);

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

        $categories = $this->prepareCategoryTree($actives);

        return $categories;
    }

    /**
     * Helper method to recursively create category tree
     *
     * @param $actives
     * @param array $categories
     *
     * @return mixed
     */
    private function prepareCategoryTree($actives, array $categories = [])
    {
        foreach ($actives as $active) {
            list($parent, $child) = explode('_', $active);

            $categories[$parent] = [
                'active' => $child ? false : true,
                'children' => $child ? self::prepareCategoryTree([$child], $categories) : []
            ];
        }

        return $categories;
    }

    /**
     * Parse the filter items and build an array structure
     *
     * @param SimpleXMLElement $filterItems
     * @param array $actives
     *
     * @return array
     */
    private function parseCategories(SimpleXMLElement $filterItems, array $actives)
    {
        $inactives = [];

        foreach ($filterItems as $filterItem) {
            $name = (string)$filterItem->name;
            $frequency = (int)$filterItem->frequency;

            // Only add the items of $filterItems that are not already present in $actives
            $index = $this->key_exists($name, $actives);

            if ($index === false) {
                $inactives[$name] = [
                    'active' => false,
                    'children' => $filterItem->items->item ? self::parseCategories($filterItem->items->item,
                        $actives) : []
                ];

                if ($frequency) {
                    $inactives[$name]['frequency'] = $frequency;
                }
            }
        }

        return $inactives;
    }

    /**
     * Convert the array of categories to an array of tree items
     *
     * @param array $categories
     * @param string|null $parent
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

    private function key_exists($needle, array $array)
    {
        foreach ($array as $key => $value) {
            if ($key === $needle) {
                return $value;
            }
            if (is_array($value)) {
                if ($x = $this->key_exists($key, $value)) {
                    return $x;
                }
            }
        }

        return false;
    }
}
