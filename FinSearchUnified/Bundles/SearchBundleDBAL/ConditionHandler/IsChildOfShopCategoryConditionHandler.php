<?php

namespace FinSearchUnified\Bundles\SearchBundleDBAL\ConditionHandler;

use FinSearchUnified\Bundles\SearchBundle\Condition\IsChildOfShopCategoryCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundleDBAL\ConditionHandlerInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class IsChildOfShopCategoryConditionHandler implements ConditionHandlerInterface
{
    /**
     * @param ConditionInterface $condition
     *
     * @return bool
     */
    public function supportsCondition(ConditionInterface $condition)
    {
        return ($condition instanceof IsChildOfShopCategoryCondition);
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
        $query->andWhere('
            (SELECT COUNT(*) 
            FROM s_articles_categories_ro 
            WHERE s_articles_categories_ro.articleID = product.id 
            AND s_articles_categories_ro.categoryID = :shopCategoryId) > 0');

        /* @var $condition IsChildOfShopCategoryCondition */
        $query->setParameter(
            ':shopCategoryId',
            $condition->getShopCategoryId()
        );
    }
}
