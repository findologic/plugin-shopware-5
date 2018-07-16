<?php

namespace FinSearchUnified;

use Doctrine\ORM\EntityNotFoundException;
use FINDOLOGIC\Export\Exporter;
use FinSearchUnified\BusinessLogic\FindologicArticleFactory;
use Shopware\Models\Article\Article;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Order\Order;
use Shopware\Models\Shop\Repository;
use Shopware\Models\Shop\Shop;

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

        /** @var Repository $repository */
        $repository = Shopware()->Container()->get('models')->getRepository('Shopware\Models\Shop\Shop');

        /** @var Shop[] $languageShops */
        $languageShops = $repository->getActiveShops();

        /** @var Category $baseCategory */
        $baseCategory = null;
        foreach ($languageShops as $languageShop){
            $language = $languageShop->getLocale();
            if ($language->getLocale() == $selectedLanguage){
                // Set active language as active shop for multilang descriptions
                $this->shop = $languageShop;
                $baseCategory = $languageShop->getCategory();
            }
        }

        // When no locale Shop is found, the default one is used
        if ($baseCategory == null){
            $baseCategory = Shopware()->Shop()->getCategory();
        }


        $this->customerRepository = Shopware()->Container()->get('models')->getRepository(Customer::class);
        $this->articleRepository = Shopware()->Container()->get('models')->getRepository(Article::class);
        $this->orderRepository = Shopware()->Container()->get('models')->getRepository(Order::class);

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
        $configValue = Shopware()->Models()->getRepository('Shopware\Models\Config\Value')->findOneBy(['value' => $shopKey]);
        $this->shop = $configValue ? $configValue->getShop() : null;
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
