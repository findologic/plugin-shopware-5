<?php

namespace FinSearchUnified\Bundles\SearchBundleDBAL\Condition;

use FinSearchUnified\Bundles\SearchBundle\Condition\HasActiveCategoryCondition;
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
            'productSArticlesCategoriesRo',
            'productSArticlesCategoriesRo.articleID = product.id'
        )->innerJoin(
            'productSArticlesCategoriesRo',
            's_categories',
            'category',
            'category.id = productSArticlesCategoriesRo.categoryID'
        )->andWhere('category.active = true');
    }
}
