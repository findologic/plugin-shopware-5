<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Gateway;

use FinSearchUnified\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

interface CustomFacetGatewayInterface
{
    /**
     * @param int[] $ids
     *
     * @return CustomFacet[] indexed by id
     */
    public function getList(array $ids, ShopContextInterface $context);

    /**
     * @return array indexed by category id, each element contains a list of CustomFacet
     */
    public function getFacetsOfCategories(array $categoryIds, ShopContextInterface $context);

    /**
     * @return CustomFacet[]
     */
    public function getAllCategoryFacets(ShopContextInterface $context);
}
