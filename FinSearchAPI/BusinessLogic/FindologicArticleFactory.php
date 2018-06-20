<?php

namespace FinSearchAPI\BusinessLogic;

use FinSearchAPI\BusinessLogic\Models\FindologicArticleModel;
use Shopware\Models\Article\Article;

class FindologicArticleFactory
{
    /**
     * FindologicArticleModel constructor.
     *
     * @param Article $shopwareArticle
     * @param string  $shopKey
     * @param array   $allUserGroups
     * @param array   $salesFrequency
     *
     * @throws \Exception
     */
    public function createFindologicArticle(Article $shopwareArticle, $shopKey, array $allUserGroups, array $salesFrequency)
    {
        return new FindologicArticleModel($shopwareArticle, $shopKey, $allUserGroups, $salesFrequency);
    }
}