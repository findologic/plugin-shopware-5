<?php

namespace FinSearchUnified\Bundles\SearchBundleDBAL\ConditionHandler;

use FinSearchUnified\Bundles\SearchBundle\Condition\HasActiveMainDetailCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundleDBAL\ConditionHandlerInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class HasActiveMainDetailConditionHandler implements ConditionHandlerInterface
{
    /**
     * @param ConditionInterface $condition
     *
     * @return bool
     */
    public function supportsCondition(ConditionInterface $condition)
    {
        return ($condition instanceof HasActiveMainDetailCondition);
    }

    /**
     * @param ConditionInterface $condition
     * @param QueryBuilder $query
     * @param ShopContextInterface $context
     */
    public function generateCondition(
        ConditionInterface $condition,
        QueryBuilder $query,
        ShopContextInterface $context
    ) {
        $query->andWhere(
            '(SELECT COUNT(*) 
            FROM s_articles_details 
            WHERE s_articles_details.id = product.main_detail_id 
            AND s_articles_details.active = 1) > 0'
        );
    }
}
