<?php

namespace FinSearchUnified\Bundle\SearchBundleDBAL\ConditionHandler;

use FinSearchUnified\Bundle\SearchBundle\Condition\HasActiveCategoryCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundleDBAL\ConditionHandlerInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class HasActiveCategoryConditionHandler implements ConditionHandlerInterface
{
    /**
     * @param ConditionInterface $condition
     *
     * @return bool
     */
    public function supportsCondition(ConditionInterface $condition)
    {
        return ($condition instanceof HasActiveCategoryCondition);
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
            FROM s_articles_categories_ro 
            LEFT JOIN s_categories ON s_categories.id = s_articles_categories_ro.categoryID 
            WHERE s_articles_categories_ro.articleID = product.id 
            AND s_categories.active = 1 
            AND s_articles_categories_ro.categoryID != :shopCategoryId) > 0'
        );

        /* @var $condition HasActiveCategoryCondition */
        $query->setParameter(
            ':shopCategoryId',
            $condition->getShopCategoryId()
        );
    }
}
