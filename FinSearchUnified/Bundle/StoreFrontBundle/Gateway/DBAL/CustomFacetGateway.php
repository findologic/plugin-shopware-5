<?php


namespace FinSearchUnified\Bundle\StoreFrontBundle\Gateway\DBAL;

use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\CustomFacetGatewayInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class CustomFacetGateway implements CustomFacetGatewayInterface
{
    /**
     * @param int[] $ids
     * @param ShopContextInterface $context
     *
     * @return CustomFacet[]
     */
    public function getList(array $ids, ShopContextInterface $context)
    {
        return [];
    }

    /**
     * @param array $categoryIds
     * @param ShopContextInterface $context
     *
     * @return array indexed by category id, each element contains a list of CustomFacet
     */
    public function getFacetsOfCategories(array $categoryIds, ShopContextInterface $context)
    {
        return [];
    }

    /**
     * @param ShopContextInterface $context
     *
     * @return CustomFacet
     */
    public function getAllCategoryFacets(ShopContextInterface $context)
    {
        return [];
    }
}
