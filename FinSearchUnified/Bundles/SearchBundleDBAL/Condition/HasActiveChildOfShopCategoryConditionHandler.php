<?php

namespace FinSearchUnified\Bundles\SearchBundleDBAL\Condition;

use FinSearchUnified\Bundles\SearchBundle\Condition\HasActiveChildOfShopCategoryCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundleDBAL\ConditionHandlerInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class HasActiveChildOfShopCategoryConditionHandler implements ConditionHandlerInterface
{
    /**
     * @param ConditionInterface $condition
     *
     * @return bool
     */
    public function supportsCondition(ConditionInterface $condition)
    {
        return ($condition instanceof HasActiveChildOfShopCategoryCondition);
    }

    /**
     * @param ConditionInterface $condition
     * @param QueryBuilder $query
     * @param ShopContextInterface $context
     *
     * @return void
     */
    public function generateCondition(
        ConditionInterface $condition,
        QueryBuilder $query,
        ShopContextInterface $context
    ) {
        $query->innerJoin(
            'product',
            's_articles_categories_ro',
            'product_s_articles_categories_ro',
            'product_s_articles_categories_ro.articleID = product.id
             AND product_s_articles_categories_ro.categoryID = (:category)'
        )->innerJoin(
            'product_s_articles_categories_ro',
            's_categories',
            'product_category_s_categories',
            'product_category_s_categories.id = product_s_articles_categories_ro.categoryID'
        )->andWhere('product_category_s_categories.active = true');

        /* @var $condition HasActiveChildOfShopCategoryCondition */
        $query->setParameter(
            ':category',
            $condition->getShopCategoryId()
        );
    }
}
