<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler;

use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use FinSearchUnified\Helper\StaticHelper;
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
        // Get active categories from criteria and parse it into structured array
        $actives = $this->getActives($facet, $criteria);

        // Put additional categories from filterItems into the active categories array
        $categories = array_replace_recursive($this->parseCategories($filter->items->item, $actives), $actives);

        return new TreeFacetResult(
            $facet->getName(),
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

        // Get active categories from condition
        if ($condition !== null) {
            $actives = $condition->getValue();
        }

        if (!is_array($actives)) {
            $actives = [$actives];
        }

        // Parse active array into structured category tree array
        $categories = $this->prepareCategoryTree($actives);

        return $categories;
    }

    /**
     * Helper method to recursively create category tree
     *
     * @param array $actives
     * @param array $categories
     *
     * @return mixed
     */
    private function prepareCategoryTree(array $actives, array $categories = [])
    {
        foreach ($actives as $active) {
            // Only the first child will be in the $child variable
            list($parent, $child) = explode('_', $active);

            // Create structured array and recursively create category tree
            $categories[$parent] = [
                'active' => !$child,
                'children' => $child ? $this->prepareCategoryTree([$child], $categories) : []
            ];
        }

        return $categories;
    }

    /**
     * Parse the filter items and build an array structure
     *
     * @param SimpleXMLElement|null $filterItems
     * @param array|null $actives
     *
     * @return array
     */
    private function parseCategories(SimpleXMLElement $filterItems = null, array $actives = null)
    {
        $categories = [];

        foreach ($filterItems as $filterItem) {
            $name = (string)$filterItem->name;
            $frequency = (int)$filterItem->frequency;

            // If category is in actives array, then set active to true and recursively parse child categories
            $isActive = $this->keyExists($name, $actives);
            $categories[$name] = [
                'active' => $isActive,
                'children' => $filterItem->items->item ? $this->parseCategories($filterItem->items->item, $actives) : []
            ];

            // Do not set filter item frequency if "Product & Filter live reloading" is enabled in the Shopware Backend.
            $filterReloadingEnabled = StaticHelper::isProductAndFilterLiveReloadingEnabled();

            if ($frequency && !$filterReloadingEnabled) {
                $categories[$name]['frequency'] = $frequency;
            }
        }

        return $categories;
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
                $this->getTreeItems($category['children'], $id)
            );

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Helper method to check if needle exists in array recursively
     *
     * @param mixed $needle
     * @param array $haystack
     *
     * @return bool
     */
    private function keyExists($needle, array $haystack)
    {
        foreach ($haystack as $key => $value) {
            if ($key === $needle) {
                return true;
            }

            if (is_array($value)) {
                return $this->keyExists($key, $value);
            }
        }

        return false;
    }
}
