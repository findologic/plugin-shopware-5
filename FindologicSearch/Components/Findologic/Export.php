<?php

namespace FindologicSearch\Components\Findologic;

class Export
{
    /**
     * @var string
     */
    private $shopKey;

    /**
     * @var integer
     */
    private $count;

    /**
     * @var integer
     */
    private $start;

    /**
     * @var integer
     */
    private $total;

    /**
     * @var \Shopware\Models\Shop\Shop
     */
    private $shop;

    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    private $em;

    /**
     * @var \sArticles
     */
    private $sArticle;

    /**
     * Cached array of categories.
     *
     * @var array
     */
    private $categories = array();

    /**
     * Cached user groups.
     *
     * @var array
     */
    private $allUserGroups = array();

    /**
     * Export constructor.
     *
     * @param $shopKey
     * @param $start
     * @param $count
     */
    public function __construct($shopKey, $start, $count)
    {
        $this->em = Shopware()->Models();
        $this->shopKey = $shopKey;
        $this->start = $start;
        $this->count = $count;

        /* @var $sArticle \sArticles */
        $this->sArticle = Shopware()->Modules()->sArticles();

        $this->shop = $this->getShopIfExists();
        $this->validateInput();
        $this->allUserGroups = $this->getAllUserGroups();
        $this->prepareAllActiveCategoryIdsByShop();
    }

    /**
     * Get all valid products, return only ones that satisfy export criteria.
     *
     * @return array Array of \Shopware\Models\Article\Article objects.
     */
    public function getAllValidProducts()
    {
        $queryBuilder = $this->em->createQueryBuilder();
        $queryBuilder
            ->select('article')
            ->from('Shopware\Models\Article\Article', 'article')
            ->orderBy('article.id');

        $subQuery = $this->em->createQueryBuilder();
        $subQuery
            ->select('a')
            ->from('Shopware\Models\Article\Article', 'a')
            ->innerJoin('a.details', 'd')
            ->leftJoin('a.customerGroups', 'cg')
            ->innerJoin('a.categories', 'cat')
            ->andWhere('a.active = 1')
            ->andWhere("a.name != ''")
            // only items that are on stock
            ->andWhere('(d.inStock > 0 OR a.lastStock = 0)')
            ->andWhere('d.kind = 1') // meaning: field 'kind' represent variations
            // (value: 1 is for basic article and value: 2 for variant article ).
            ->andWhere('cat.id IN (' . implode(',', array_keys($this->categories)) . ')')
            ->groupBy('a.id')
            ->having('COUNT(cg.id) < :nr_of_all_groups'); // meaning: if all user group are selected
        // as avoid per article

        $sq = $subQuery->getQuery()->getDQL();
        $queryBuilder
            ->andWhere($queryBuilder->expr()->in('article', $sq))
            ->setParameter('nr_of_all_groups', count($this->allUserGroups));

        $countQueryBuilder = clone $queryBuilder;
        $this->total = $countQueryBuilder
            ->select('COUNT (DISTINCT article.id) as cnt')
            ->getQuery()
            ->getSingleScalarResult();

        $queryBuilder
            ->setFirstResult($this->start)
            ->setMaxResults($this->count);

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Validates whether all input parameters are supplied.
     */
    private function validateInput()
    {
        $message = '';
        if (!$this->shopKey) {
            $message = 'Parameter "shopkey" is missing! ';
        }

        if (!$this->shop) {
            $message .= 'Parameter "shopkey" is not configured for any store! ';
        }

        if ($this->start === false || $this->start < 0) {
            $message .= 'Parameter "start" is missing or less than 0! ';
        }

        if (!$this->count || $this->count < 1) {
            $message .= 'Parameter "count" is missing or less than 1!';
        }

        if ($message) {
            die($message);
        }
    }

    /**
     * Get all shop user groups.
     *
     * @return array User groups for current shop.
     */
    private function getAllUserGroups()
    {
        $builder = $this->em->createQueryBuilder();
        $builder->select(array('groups'))
            ->from('Shopware\Models\Customer\Group', 'groups')
            ->orderBy('groups.id');
        return $builder->getQuery()->getArrayResult();
    }

    /**
     * Gets shop from db if exists.
     *
     * @return \Shopware\Models\Shop\Shop Shop A Shop object for supplied shop key if exists; otherwise, null.
     */
    private function getShopIfExists()
    {
        $conf = $this->em->getRepository('Shopware\Models\Config\Value')->findOneBy(array('value' => $this->shopKey));

        return $conf ? $conf->getShop() : null;
    }

    /**
     * Gets all active categories for selected shop from database and puts it to $this->categories.
     */
    private function prepareAllActiveCategoryIdsByShop()
    {
        $queryBuilder = $this->em->createQueryBuilder();
        $queryBuilder->select(array(
            'o.id',
            'o.name',
            'o.path'
        ));
        $queryBuilder->from('Shopware\Models\Category\Category', 'o');
        $queryBuilder->where('o.active = 1');
        $categories = $queryBuilder->getQuery()->getResult();

        // Set categories by ids for keys and only pass categories for selected shop.
        $categoriesByIds = array();
        $shopCategoryId = $this->shop->getCategory()->getId();
        foreach ($categories as $category) {
            if ($category['id'] == $shopCategoryId) {
                $categoriesByIds[$category['id']] = $category;
            } else {
                $categoryPath = array_filter(explode('|', $category['path']));
                foreach ($categoryPath as $catId) {
                    if ($catId == $shopCategoryId) {
                        $categoriesByIds[$category['id']] = $category;
                        break;
                    }
                }
            }
        }

        $this->categories = $this->setCategoriesPathName($categoriesByIds);
    }

    /**
     * Sets category path names and depth count.
     *
     * @param array $categoriesByIds Array of categories that should contain 'id', 'name' and 'path' keys.
     * @param string $between Text or character to be placed between category names in full path for one category
     * @param string $space Character that should be placed instead of space (' ') in path.
     * @return array $categories Array of categories with keys 'depth', 'path', 'pathIds', 'name', 'id' and 'url'.
     */
    private function setCategoriesPathName($categoriesByIds, $between = '_', $space = '-')
    {
        $categories = array();
        $db = Shopware()->Db();
        foreach ($categoriesByIds as $category) {
            $categoryPath = array_reverse(array_filter(explode('|', $category['path'])));

            $path = '';
            if ($category['path'] !== null) {
                foreach ($categoryPath as $p) {
                    $categoryName = str_replace(' ', $space, $categoriesByIds[$p]['name']);
                    $path .= $categoryName . $between;
                }
            }

            $path .= $category['name'];

            $sql = 'SELECT path FROM s_core_rewrite_urls WHERE org_path =? AND main = 1';
            $url = $db->fetchOne($sql, array('sViewport=cat&sCategory=' . $category['id']));

            $categories[$category['id']] = array(
                'depth' => count($categoryPath),
                'path' => $path,
                'pathIds' => $category['path'],
                'name' => $category['name'],
                'id' => $category['id'],
                'url' => '/' . strtolower($url),
            );
        }

        return $categories;
    }
}