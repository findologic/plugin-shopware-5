<?php

namespace FinSearchUnified\BusinessLogic;

use FinSearchUnified\BusinessLogic\Models\FindologicArticleModel;
use Shopware\Models\Article\Article;
use Shopware\Models\Category\Category;

class FindologicArticleFactory
{
    /**
     * Create FindologicArticleModel instance.
     *
     * @param Article $shopwareArticle
     * @param string  $shopKey
     * @param array   $allUserGroups
     * @param array   $salesFrequency
     * @param Category $baseCategory
     * @return FindologicArticleModel
     *
     * @throws \Exception
     */
    public function create(
        Article $shopwareArticle,
        $shopKey,
        array $allUserGroups,
        array $salesFrequency,
        Category $baseCategory
    ) {
        return new FindologicArticleModel($shopwareArticle, $shopKey, $allUserGroups, $salesFrequency, $baseCategory);
    }
}
