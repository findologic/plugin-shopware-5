<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic;

use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class QueryBuilderFactory implements QueryBuilderFactoryInterface
{
    /**
     * Creates the product number search query for the provided
     * criteria and context.
     * Adds the sortings and conditions of the provided criteria.
     *
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return QueryBuilder
     */
    public function createQueryWithSorting(Criteria $criteria, ShopContextInterface $context)
    {
        // TODO: Implement createQueryWithSorting() method.
    }

    /**
     * Generates the product selection query of the product number search
     *
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return QueryBuilder
     */
    public function createProductQuery(Criteria $criteria, ShopContextInterface $context)
    {
        // TODO: Implement createProductQuery() method.
    }

    /**
     * Creates the product number search query for the provided
     * criteria and context.
     * Adds only the conditions of the provided criteria.
     *
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return QueryBuilder
     */
    public function createQuery(Criteria $criteria, ShopContextInterface $context)
    {
        // TODO: Implement createQuery() method.
    }

    /**
     * @return QueryBuilder
     */
    public function createQueryBuilder()
    {
        // TODO: Implement createQueryBuilder() method.
    }
}
