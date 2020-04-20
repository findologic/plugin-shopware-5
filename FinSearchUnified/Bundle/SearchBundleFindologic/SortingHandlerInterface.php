<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic;

use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NewQueryBuilder;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

interface SortingHandlerInterface
{
    /**
     * Checks if the passed sorting can be handled by this class
     *
     * @param SortingInterface $sorting
     *
     * @return bool
     */
    public function supportsSorting(SortingInterface $sorting);

    /**
     * Handles the passed sorting object.
     *
     * @param SortingInterface $sorting
     * @param NewQueryBuilder $query
     * @param ShopContextInterface $context
     */
    public function generateSorting(
        SortingInterface $sorting,
        NewQueryBuilder $query,
        ShopContextInterface $context
    );
}
