<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic;

use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

interface FindologicSortingHandlerInterface
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
     * @param QueryBuilder $query
     * @param ShopContextInterface $context
     */
    public function generateSorting(
        SortingInterface $sorting,
        QueryBuilder $query,
        ShopContextInterface $context
    );
}
