<?php

namespace FinSearchUnified;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\PersistentCollection;
use Enlight_Exception;
use Exception;
use FINDOLOGIC\Export\Exporter;
use FINDOLOGIC\Export\Helpers\EmptyValueNotAllowedException;
use FinSearchUnified\BusinessLogic\FindologicArticleFactory;
use RuntimeException;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\ProductNumberSearchInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Components\ProductStream\RepositoryInterface;
use Shopware\Models\Article\Article;
use Shopware\Models\Category\Category;
use Shopware\Models\Config\Value;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Shop\Repository;
use Shopware\Models\Shop\Shop;
use Zend_Cache_Core;
use Zend_Cache_Exception;

class ShopwareProcess
{
    /**
     * @var Shop
     */
    public $shop;

    /**
     * @var string
     */
    public $shopKey;

    /**
     * @var ContextServiceInterface
     */
    protected $contextService;

    /**
     * @var \Shopware\Models\Customer\Repository
     */
    protected $customerRepository;

    /**
     * @var \Shopware\Models\Article\Repository
     */
    protected $articleRepository;

    /**
     * @var Zend_Cache_Core
     */
    protected $cache;

    /**
     * @var \Shopware\Components\ProductStream\Repository
     */
    protected $productStreamRepository;

    /**
     * @var ProductNumberSearchInterface
     */
    protected $searchService;

    public function __construct(
        Zend_Cache_Core $cache,
        RepositoryInterface $repository,
        ContextServiceInterface $contextService,
        ProductNumberSearchInterface $productNumberSearch
    ) {
        $this->cache = $cache;
        $this->productStreamRepository = $repository;
        $this->contextService = $contextService;
        $this->searchService = $productNumberSearch;
    }

    /**
     * @param string $selectedLanguage
     * @param int $start
     * @param int $count
     *
     * @return XmlInformation
     * @throws Exception
     */
    public function getAllProductsAsXmlArray($selectedLanguage = 'de_DE', $start = 0, $count = 0)
    {
        $response = new XmlInformation();
        $baseCategory = $this->shop->getCategory();

        $this->customerRepository = Shopware()->Container()->get('models')->getRepository(Customer::class);
        $this->articleRepository = Shopware()->Container()->get('models')->getRepository(Article::class);

        $articlesQuery = $this->articleRepository->createQueryBuilder('articles')
            ->select('articles')
            ->where('articles.active = :active')
            ->orderBy('articles.id')
            ->setParameter('active', true);

        if ($count > 0) {
            $articlesQuery->setMaxResults($count)->setFirstResult($start);
        }

        $countQuery = $this->articleRepository->createQueryBuilder('articles')
            ->select('count(articles.id)')
            ->where('articles.active = :active')
            ->orderBy('articles.id')
            ->setParameter('active', true);

        $response->total = $countQuery->getQuery()->getScalarResult()[0][1];

        /** @var array $allArticles */
        $allArticles = $articlesQuery->getQuery()->execute();

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
                if ($article->getMainDetail() === null || !$article->getMainDetail()->getActive()) {
                    continue;
                }
            } catch (EntityNotFoundException $exception) {
                continue;
            }

            try {
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
            } catch (EmptyValueNotAllowedException $e) {
                Shopware()->Container()->get('pluginlogger')->info(
                    sprintf(
                        'Product with number "%s" could not be exported. ' .
                        'It appears to have empty values assigned to it. ' .
                        'If you see this message in your logs, please report this as a bug',
                        $article->getMainDetail()->getNumber()
                    )
                );
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
     *
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
        } catch (Exception $e) {
            die($e->getMessage());
        }

        if ($save) {
            $exporter->serializeItemsToFile(__DIR__ . '', $xmlArray->items, $start, $xmlArray->count, $xmlArray->total);
        } else {
            $xmlDocument = $exporter->serializeItems($xmlArray->items, $start, $xmlArray->count, $xmlArray->total);
        }

        return $xmlDocument;
    }

    /**
     * @param string $shopKey
     *
     * @throws Exception
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
            throw new RuntimeException('Provided shopkey not assigned to any shop!');
        }
    }

    /**
     * @throws Enlight_Exception
     * @throws Zend_Cache_Exception
     */
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
     *
     * @return array
     * @throws Enlight_Exception
     * @throws Exception
     */
    protected function parseProductStreams(PersistentCollection $categories, array &$articles = [])
    {
        /**
         * @var Category $category
         */
        foreach ($categories as $category) {
            if (!$category->isLeaf()) {
                $this->parseProductStreams($category->getChildren(), $articles);
            }

            if (!$category->getActive() || $category->getStream() === null) {
                continue;
            }

            $criteria = new Criteria();
            $criteria
                ->limit(200)
                ->offset(0);

            $this->productStreamRepository->prepareCriteria($criteria, $category->getStream()->getId());

            do {
                $result = $this->searchService->search($criteria, $this->contextService->getShopContext());

                foreach ($result->getProducts() as $product) {
                    $articles[$product->getId()][] = $category;
                }

                $criteria->offset($criteria->getOffset() + $criteria->getLimit());
            } while ($criteria->getOffset() < $result->getTotalCount());
        }

        return $articles;
    }
}
