<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandlerInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NewQueryBuilder;
use Shopware\Bundle\SearchBundle\Condition\SearchTermCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class SearchTermConditionHandler implements ConditionHandlerInterface
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
        return $condition instanceof SearchTermCondition;
    }

    /**
     * Handles the passed condition object.
     *
     * @param ConditionInterface $condition
     * @param NewQueryBuilder $query
     * @param ShopContextInterface $context
     *
     * @throws Exception
     */
    public function generateCondition(
        ConditionInterface $condition,
        NewQueryBuilder $query,
        ShopContextInterface $context
    ) {
        /** @var SearchTermCondition $condition */
        $query->addQuery($condition->getTerm());
    }
}
