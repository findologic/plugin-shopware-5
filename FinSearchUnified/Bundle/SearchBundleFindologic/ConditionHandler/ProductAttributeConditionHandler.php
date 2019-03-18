<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\FindologicQueryBuilderInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use Shopware\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class ProductAttributeConditionHandler implements FindologicQueryBuilderInterface
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
        return $condition instanceof ProductAttributeCondition;
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
        /** @var ProductAttributeCondition $condition */
        $query->addFilter($condition->getField(), $condition->getValue());
    }
}
