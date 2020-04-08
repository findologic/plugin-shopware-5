<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;

use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

interface NewQueryBuilderFactoryInterface
{
    /**
     * Creates the product number search query for the provided
     * criteria and context.
     * Adds the sorting and conditions of the provided criteria.
     *
     * @return NewQueryBuilder
     */
    public function createQueryWithSorting(Criteria $criteria, ShopContextInterface $context);

    /**
     * Generates the product selection query of the product number search
     *
     * @return NewQueryBuilder
     */
    public function createProductQuery(Criteria $criteria, ShopContextInterface $context);

    /**
     * Creates the product number search query for the provided
     * criteria and context.
     * Adds only the conditions of the provided criteria.
     *
     * @return NewQueryBuilder
     */
    public function createQuery(Criteria $criteria, ShopContextInterface $context);

    /**
     * @return NewQueryBuilder
     */
    public function createQueryBuilder();
}
