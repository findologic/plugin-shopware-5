<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\FindologicQueryBuilderInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use Shopware\Bundle\SearchBundle\Condition\PriceCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class PriceConditionHandler implements FindologicQueryBuilderInterface
{
    /**
     * Checks if the passed condition can be handled by this class.
     *
     * @param ConditionInterface $condition
     *
     * @return bool
     */
    public function supportsCondition(ConditionInterface $condition)
    {
        return $condition instanceof PriceCondition;
    }

    /**
     * Handles the passed condition object.
     *
     * @param ConditionInterface $condition
     * @param QueryBuilder $query
     * @param ShopContextInterface $context
     *
     * @throws Exception
     */
    public function generateCondition(ConditionInterface $condition, QueryBuilder $query, ShopContextInterface $context)
    {
        /** @var PriceCondition $condition */
        $minPrice = $condition->getMinPrice();
        $maxPrice = $condition->getMaxPrice() ?: PHP_INT_MAX;

        $query->addPrice($minPrice, $maxPrice);
    }
}
