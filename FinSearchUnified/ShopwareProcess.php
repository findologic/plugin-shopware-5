<?php

namespace FinSearchUnified;

use Doctrine\ORM\EntityNotFoundException;
use FINDOLOGIC\Export\Exporter;
use FinSearchUnified\BusinessLogic\FindologicArticleFactory;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Models\Article\Article;
use Shopware\Models\Category\Category;
use Shopware\Models\Config\Value;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Order\Order;
use Shopware\Models\Shop\Repository;
use Shopware\Models\Shop\Shop;
use FinSearchUnified\Helper\StaticHelper;

require __DIR__.'/vendor/autoload.php';

class ShopwareProcess
{
    /**
     * @var \Shopware\Bundle\StoreFrontBundle\Struct\ShopContext
     */
    protected $context;

    /**
     * @var \Shopware\Models\Customer\Repository
     */
    protected $customerRepository;

    /**
     * @var \Shopware\Models\Article\Repository
     */
    protected $articleRepository;

    /**
     * @var \Shopware\Models\Shop\Shop
     */
    public $shop;

    /**
     * @var string
     */
    public $shopKey;

    /**
     * @var \Shopware\Models\Order\Repository
     */
    public $orderRepository;

    /** @var \Zend_Cache_Core $cache */
    protected $cache;

    /** @var string */
    protected $productStreamKeyArticles;

    /** @var string */
    protected $productStreamKeyCategories;

    /**
     * @param string $selectedLanguage
     * @param int $start
     * @param int $count
     *
     * @return xmlInformation
     * @throws \Exception
     */
    public function getAllProductsAsXmlArray($selectedLanguage = 'de_DE', $start = 0, $count = 0)
    {
        $response = new xmlInformation();

        $baseCategory = $this->shop->getCategory();

        $this->customerRepository = Shopware()->Container()->get('models')->getRepository(Customer::class);
        $this->articleRepository = Shopware()->Container()->get('models')->getRepository(Article::class);
        $this->orderRepository = Shopware()->Container()->get('models')->getRepository(Order::class);
        $this->cache = Shopware()->Container()->get('cache');
        $this->productStreamKeyArticles = StaticHelper::getProductStreamKey() . 'articles';
        $this->productStreamKeyCategories = StaticHelper::getProductStreamKey() . 'categories';

        if ($count > 0) {
            $countQuery = $this->articleRepository->createQueryBuilder('articles')
                                                    ->select('count(articles.id)');

            $response->total = $countQuery->getQuery()->getScalarResult()[0][1];

            $articlesQuery = $this->articleRepository->createQueryBuilder('articles')
                                                    ->select('articles')
                                                    ->setMaxResults($count)
                                                    ->setFirstResult($start);
            /** @var array $allArticles */
            $allArticles = $articlesQuery->getQuery()->execute();
        } else {
            /** @var array $allArticles */
            $allArticles = $this->shop->getCategory()->getAllArticles();
            $response->total = count($allArticles);
        }

        //Sales Frequency
        $orderQuery = $this->orderRepository->createQueryBuilder('orders')
                                            ->leftJoin('orders.details', 'details')
                                            ->groupBy('details.articleId')
                                            ->select('details.articleId, sum(details.quantity)');

        $articleSales = $orderQuery->getQuery()->getArrayResult();

        // Own Model for XML extraction
        $findologicArticles = [];

        /** @var array $allUserGroups */
        $allUserGroups = $this->customerRepository->getCustomerGroupsQuery()->getResult();

        $findologicArticleFactory = Shopware()->Container()->get('fin_search_unified.article_model_factory');

        if ($start === 0 || !$this->cache->test($this->productStreamKeyArticles)) {
            $categoryRepository = Shopware()->Models()->getRepository(Category::class);
            $categoriesWithStreams = $categoryRepository->createQueryBuilder('categories')
                ->select('categories')
                ->where('categories.active = :active')
                ->andWhere('categories.streamId IS NOT NULL')
                ->setParameter('active', true);

            $results = $categoriesWithStreams->getQuery()->execute();

            if (!empty($results)) {
                $productStreamArticles = [];
                $productStreamCategories = [];

                /** @var \Shopware\Components\ProductStream\Repository $streamRepositoryService */
                $streamRepositoryService = Shopware()->Container()->get('shopware_product_stream.repository');

                /** @var Category $category */
                foreach ($results as $category) {
                    //Hide inactive categories
                    if (!$category->getActive() || !$category->isChildOf($baseCategory)) {
                        continue;
                    }

                    $streamCriteria = new Criteria();
                    $streamRepositoryService->prepareCriteria($streamCriteria, $category->getStream()->getId());

                    $streamArticles = Shopware()->Modules()->Articles()->sGetArticlesByCategory($category->getId(), $streamCriteria);
                    if (count($streamArticles['sArticles']) > 0) {
                        $productStreamCategories[$category->getId()] = $category;
                        foreach ($streamArticles['sArticles'] as $article) {
                            $productStreamArticles[$article['articleID']][] = $category->getId();
                        }
                    }
                }

                $this->addProductStreamDataToCache($productStreamArticles, $productStreamCategories);
            } else {
                $this->clearProductStreamData(true);
                $this->cache->save([], $this->productStreamKeyArticles, ['findologic']);
            }
        } else {
            $articlesFromCache = $this->cache->load($this->productStreamKeyArticles);
            if ($articlesFromCache && count($articlesFromCache) > 0) {
                $categoriesFromCache = $this->cache->load($this->productStreamKeyCategories);
                $this->loadDataIntoSession($articlesFromCache, $categoriesFromCache);
            }
        }

        /** @var Article $article */
        foreach ($allArticles as $article) {
            $inactiveCatCount = 0;
            $totalCatCount = 0;

            /** @var Category $category */
            foreach ($article->getCategories() as $category) {
                if (!$category->isChildOf($baseCategory)) {
                    continue;
                }

                if (!$category->getActive()) {
                    $inactiveCatCount++;
                }

                $totalCatCount++;
            }

            // Check if Article is Active and has active categories
            if (!$article->getActive() || $totalCatCount === $inactiveCatCount) {
                continue;
            }

            try {
                if ($article->getMainDetail() === null || $article->getMainDetail()->getActive() === 0) {
                    continue;
                }
            } catch (EntityNotFoundException $exception) {
                continue;
            }

            /** @var FindologicArticleFactory $findologicArticleFactory */
            $findologicArticle = $findologicArticleFactory->create($article, $this->shopKey, $allUserGroups, $articleSales, $baseCategory);

            if ($findologicArticle->shouldBeExported) {
                $findologicArticles[] = $findologicArticle->getXmlRepresentation();
            }
        }

        $response->items = $findologicArticles;
        $response->count = count($findologicArticles);

        $this->clearProductStreamData();

        return $response;
    }

    public function getFindologicXml($lang = "de_DE", $start = 0, $length = 0, $save = false)
    {
        $exporter = Exporter::create(Exporter::TYPE_XML);

        try {
            $xmlArray = $this->getAllProductsAsXmlArray($lang, $start, $length);
        } catch (\Exception $e) {
            die($e->getMessage());
        }
        if ($save) {
            $exporter->serializeItemsToFile(__DIR__.'', $xmlArray->items, $start, $xmlArray->count, $xmlArray->total);
        } else {
            $xmlDocument = $exporter->serializeItems($xmlArray->items, $start, $xmlArray->count, $xmlArray->total);

            return $xmlDocument;
        }
    }

    /**
     * @param string $shopKey
     */
    public function setShopKey($shopKey)
    {
        $this->shopKey = $shopKey;
        $this->shop = null;
        $configValue = Shopware()->Models()->getRepository(Value::class)->findOneBy(['value' => $shopKey]);

        if ($configValue && $configValue->getShop()) {
            $shopId = $configValue->getShop()->getId();

            if (Shopware()->Container()->has('shop') && $shopId === Shopware()->Shop()->getId()) {
                $this->shop = Shopware()->Shop();
            } else {
                /** @var Repository $shopRepository */
                $shopRepository = Shopware()->Container()->get('models')->getRepository(Shop::class);
                $this->shop = $shopRepository->getActiveById($shopId);

                if ($this->shop) {
                    $this->shop->registerResources();
                }
            }
        }

        if (!$this->shop) {
            throw new \RuntimeException('Provided shopkey not assigned to any shop!');
        }
    }

    /* HELPER FUNCTIONS */

    public static function calculateUsergroupHash($shopkey, $usergroup)
    {
        $hash = base64_encode($shopkey ^ $usergroup);

        return $hash;
    }

    public static function decryptUsergroupHash($shopkey, $hash)
    {
        return  $shopkey ^ base64_decode($hash);
    }

    private function addProductStreamDataToCache($articles, $categories)
    {
        $this->cache->save($articles, $this->productStreamKeyArticles, ['findologic']);
        $this->cache->save($categories, $this->productStreamKeyCategories, ['findologic']);
        $this->loadDataIntoSession($articles, $categories);
    }

    private function loadDataIntoSession($articles, $categories)
    {
        Shopware()->Session()->offsetSet($this->productStreamKeyArticles, $articles);
        Shopware()->Session()->offsetSet($this->productStreamKeyCategories, $categories);
    }

    private function clearProductStreamData($clearCachedData = false)
    {
        if ($clearCachedData && !$this->cache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_TAG, ['findologic'])) {
            $this->cache->remove($this->productStreamKeyArticles);
            $this->cache->remove($this->productStreamKeyCategories);
        }

        Shopware()->Session()->offsetUnset($this->productStreamKeyArticles);
        Shopware()->Session()->offsetUnset($this->productStreamKeyCategories);
    }
}

class xmlInformation
{
    /** @var int */
    public $count;
    /** @var int */
    public $total;
    /** @var array */
    public $items;
}
