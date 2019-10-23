<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler;

use Exception;
use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandlerInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class ProductAttributeConditionHandler implements ConditionHandlerInterface
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
        if ($condition->getOperator() === Operator::BETWEEN) {
            $values = $condition->getValue();

            $query->addRangeFilter(
                $condition->getField(),
                isset($values['min']) ? $values['min'] : 0,
                isset($values['max']) ? $values['max'] : PHP_INT_MAX
            );
        } else {
            $query->addFilter($condition->getField(), $condition->getValue());
        }
    }
}
