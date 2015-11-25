<?php

namespace FindologicSearch\Components\Findologic;

class Helper
{

    /**
     * Get path for supplied category.
     *
     * @param int $catId
     * @return array[string]
     */
    public function getCategories($catId)
    {
        $category = Shopware()->Modules()->Categories()->sGetCategoriesByParent($catId);
        $articleCategories = array();

        foreach ($category as $cat) {
            $articleCategories[] = $cat['name'];
        }

        if (count($articleCategories) > 5) {
            $articleCat = array_slice($articleCategories, 0, 5);
        } else {
            $articleCat = $articleCategories;
        }

        return array_reverse($articleCat);
    }

    /**
     * Get path for main category of supplied article.
     *
     * @param int $articleId
     * @return array[string]
     */
    public function getCartCategories($articleId)
    {
        $sql = "SELECT categoryID FROM s_articles_categories where articleID =?";
        $categoryID = Shopware()->Db()->fetchOne($sql, array($articleId));

        return $this->getCategories($categoryID);
    }

    /**
     * Helper function, gets string between defined start and end string parts
     */
    public function getStringBetween($string, $start, $end)
    {
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) {
            return '';
        }

        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;

        return substr($string, $ini, $len);
    }

}
