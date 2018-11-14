<?php

namespace FinSearchUnified;

use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\PersistentCollection;
use FINDOLOGIC\Export\Exporter;
use FinSearchUnified\BusinessLogic\FindologicArticleFactory;
use Shopware\Models\Article\Article;
use Shopware\Models\Config\Value;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Shop\Repository;
use Shopware\Models\Shop\Shop;
use Zend_Cache_Core;
use Shopware\Components\ProductStream\RepositoryInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Models\Category\Category;

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
     * @var \Zend_Cache_Core
     */
    protected $cache;

    /**
     * @var Shopware\Components\ProductStream\Repository
     */
    protected $productStreamRepository;

    public function __construct(Zend_Cache_Core $cache, RepositoryInterface $repository)
    {
        $this->cache = $cache;
        $this->productStreamRepository = $repository;
    }

    /**
     * @param string $selectedLanguage
     * @param int $start
     * @param int $count
     *
     * @return XmlInformation
     * @throws \Exception
     */
    public function getAllProductsAsXmlArray($selectedLanguage = 'de_DE', $start = 0, $count = 0)
    {
        $response = new XmlInformation();

        $baseCategory = $this->shop->getCategory();

        $this->customerRepository = Shopware()->Container()->get('models')->getRepository(Customer::class);
        $this->articleRepository = Shopware()->Container()->get('models')->getRepository(Article::class);

        if ($count > 0) {
            $countQuery = $this->articleRepository->createQueryBuilder('articles')
                                                    ->select('count(articles.id)')
                                                    ->where('articles.active = :active')
                                                    ->setParameter('active', true);

            $response->total = $countQuery->getQuery()->getScalarResult()[0][1];

            $articlesQuery = $this->articleRepository->createQueryBuilder('articles')
                                                    ->select('articles')
                                                    ->where('articles.active = :active')
                                                    ->orderBy('articles.id')
                                                    ->setMaxResults($count)
                                                    ->setFirstResult($start)
                                                    ->setParameter('active', true);
            /** @var array $allArticles */
            $allArticles = $articlesQuery->getQuery()->execute();
        } else {
            /** @var array $allArticles */
            $allArticles = $this->shop->getCategory()->getAllArticles();
            $response->total = count($allArticles);
        }

        // Own Model for XML extraction
        $findologicArticles = [];

        /** @var array $allUserGroups */
        $allUserGroups = $this->customerRepository->getCustomerGroupsQuery()->getResult();

        $findologicArticleFactory = Shopware()->Container()->get('fin_search_unified.article_model_factory');


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
            $findologicArticle = $findologicArticleFactory->create(
                $article,
                $this->shopKey,
                $allUserGroups,
                [],
                $baseCategory
            );

            if ($findologicArticle->shouldBeExported) {
                $findologicArticles[] = $findologicArticle->getXmlRepresentation();
            }
        }

        $response->items = $findologicArticles;
        $response->count = count($findologicArticles);

        return $response;
    }

    /**
     * @param string $lang
     * @param int $start
     * @param int $length
     * @param bool $save
     * @return null|string
     */
    public function getFindologicXml($lang = "de_DE", $start = 0, $length = 0, $save = false)
    {
        $xmlDocument = null;
        $exporter = Exporter::create(Exporter::TYPE_XML);

        try {
            $id = sprintf('%s_%s', Constants::CACHE_ID_PRODUCT_STREAMS, $this->shopKey);
            $lastModified = $this->cache->test($id);

            // Make a type safe check since \Zend_Cache_Core::test might actually return zero.
            if ($start === 0 || $lastModified === false) {
                $this->warmUpCache();
            } else {
                $extraLifetime = Constants::CACHE_LIFETIME_PRODUCT_STREAMS - (time() - $lastModified);
                $this->cache->touch($id, $extraLifetime);
            }

            $xmlArray = $this->getAllProductsAsXmlArray($lang, $start, $length);
        } catch (\Exception $e) {
            die($e->getMessage());
        }

        if ($save) {
            $exporter->serializeItemsToFile(__DIR__.'', $xmlArray->items, $start, $xmlArray->count, $xmlArray->total);
        } else {
            $xmlDocument = $exporter->serializeItems($xmlArray->items, $start, $xmlArray->count, $xmlArray->total);
        }

        return $xmlDocument;
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

    protected function warmUpCache()
    {
        $id = sprintf('%s_%s', Constants::CACHE_ID_PRODUCT_STREAMS, $this->shopKey);

        $this->cache->save(
            $this->parseProductStreams($this->shop->getCategory()->getChildren()),
            $id,
            ['FINDOLOGIC'],
            Constants::CACHE_LIFETIME_PRODUCT_STREAMS
        );
    }

    /**
     * Recursively parses Product Streams into an array with the respective product's ID as index and
     * an array of its categories as value.
     * Inactive categories will be skipped but active subcategories will still be parsed.
     *
     * @param PersistentCollection $categories List of categories to be checked for Product Streams
     * @param array &$articles List of affected products.
     *                         May be omitted when the method is called since it is used internally for the recursion
     * @return array
     */
    protected function parseProductStreams(PersistentCollection $categories, array &$articles = [])
    {
        /**
         * @var Category $category
         */
        foreach ($categories as $category) {
            if (!$category->isLeaf()) {
                $this->parseProductStreams($category->getChildren(), $articles);
            } elseif (!$category->getActive() || $category->getStream() === null) {
                continue;
            } else {
                $criteria = new Criteria();
                $criteria
                    ->limit(200)
                    ->offset(0);

                $this->productStreamRepository->prepareCriteria($criteria, $category->getStream()->getId());

                do {
                    $result = Shopware()->Modules()->Articles()->sGetArticlesByCategory($category->getId(), $criteria);

                    foreach ($result['sArticles'] as $sArticle) {
                        $articles[$sArticle['articleID']][] = $category;
                    }

                    $criteria->offset($criteria->getOffset() + $criteria->getLimit());
                } while ($criteria->getOffset() < $result['sNumberArticles']);
            }
        }

        return $articles;
    }
}
