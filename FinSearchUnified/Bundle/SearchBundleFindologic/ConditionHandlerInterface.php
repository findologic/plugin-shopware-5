<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic;

use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NewQueryBuilder;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

interface ConditionHandlerInterface
{
    /**
     * Checks if the passed condition can be handled by this class.
     *
     * @param ConditionInterface $condition
     *
     * @return bool
     */
    public function supportsCondition(ConditionInterface $condition);

    /**
     * Handles the passed condition object.
     *
     * @param ConditionInterface $condition
     * @param NewQueryBuilder $query
     * @param ShopContextInterface $context
     */
    public function generateCondition(
        ConditionInterface $condition,
        NewQueryBuilder $query,
        ShopContextInterface $context
    );
}
