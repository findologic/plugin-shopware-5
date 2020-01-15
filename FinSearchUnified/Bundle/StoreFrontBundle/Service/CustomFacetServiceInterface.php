<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Service;

use FinSearchUnified\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

interface CustomFacetServiceInterface
{
    /**
     * @param int[] $ids
     *
     * @return CustomFacet[]
     */
    public function getList(array $ids, ShopContextInterface $context);

    /**
     * @param int[] $categoryIds
     *
     * @return array indexed by category id, each element contains an array of CustomFacet[]
     */
    public function getFacetsOfCategories(array $categoryIds, ShopContextInterface $context);

    /**
     * @return CustomFacet[] indexed by id, sorted by position
     */
    public function getAllCategoryFacets(ShopContextInterface $context);
}
