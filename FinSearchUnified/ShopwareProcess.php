<?php

namespace FinSearchUnified;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\PersistentCollection;
use Enlight_Exception;
use Exception;
use FINDOLOGIC\Export\Exporter;
use FinSearchUnified\BusinessLogic\ExportService;
use FinSearchUnified\BusinessLogic\XmlInformation;
use RuntimeException;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\ProductNumberSearchInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Components\ProductStream\RepositoryInterface;
use Shopware\Models\Category\Category;
use Shopware\Models\Config\Value;
use Shopware\Models\Shop\Repository;
use Shopware\Models\Shop\Shop;
use Zend_Cache_Core;
use Zend_Cache_Exception;

class ShopwareProcess
{
    /**
     * @var Shop
     */
    protected $shop;

    /**
     * @var string
     */
    protected $shopkey;

    /**
     * @var ContextServiceInterface
     */
    protected $contextService;

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

    /**
     * @var ExportService
     */
    protected $exportService;

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
     * @param int $start
     * @param int $count
     * @param bool $save
     *
     * @return null|string
     */
    public function getFindologicXml($start, $count, $save = false)
    {
        $xmlDocument = null;
        $exporter = Exporter::create(Exporter::TYPE_XML);

        try {
            $id = sprintf('%s_%s', Constants::CACHE_ID_PRODUCT_STREAMS, $this->shopkey);
            $lastModified = $this->cache->test($id);

            // Make a type safe check since \Zend_Cache_Core::test might actually return zero.
            if ($start === 0 || $lastModified === false) {
                $this->warmUpCache();
            } else {
                $extraLifetime = Constants::CACHE_LIFETIME_PRODUCT_STREAMS - (time() - $lastModified);
                $this->cache->touch($id, $extraLifetime);
            }

            $xmlArray = $this->getAllProductsAsXmlArray($start, $count);
        } catch (Exception $e) {
            die($e->getMessage());
        }

        if ($save) {
            $exporter->serializeItemsToFile(
                __DIR__ . '',
                $xmlArray->getItems(),
                $start,
                $xmlArray->getCount(),
                $xmlArray->getTotal()
            );
        } else {
            $xmlDocument = $exporter->serializeItems(
                $xmlArray->getItems(),
                $start,
                $xmlArray->getCount(),
                $xmlArray->getTotal()
            );
        }

        return $xmlDocument;
    }

    /**
     * @param int $start
     * @param int $count
     *
     * @return XmlInformation
     * @throws Exception
     */
    public function getAllProductsAsXmlArray($start, $count)
    {
        $response = new XmlInformation();
        $response->setTotal($this->exportService->fetchTotalProductCount());

        /** @var array $allArticles */
        $allArticles = $this->exportService->fetchAllProducts($start, $count);
        $findologicArticles = $this->exportService->generateFindologicProducts($allArticles);

        $response->setItems($findologicArticles);
        $response->setCount(count($findologicArticles));

        return $response;
    }

    /**
     * @param string $productId
     *
     * @return string
     */
    public function getProductsById($productId)
    {
        $xmlArray = new XmlInformation();
        $exporter = Exporter::create(Exporter::TYPE_XML);

        $shopwareArticles = $this->exportService->fetchProductsById($productId);
        $findologicArticles = $this->exportService->generateFindologicProducts($shopwareArticles);

        $xmlArray->setTotal(count($findologicArticles));
        $xmlArray->setCount(count($findologicArticles));
        $xmlArray->setItems($findologicArticles);

        if ($this->exportService->hasErrors()) {
            return json_encode([
                'errors' => [
                    'general' => $this->exportService->getGeneralErrors(),
                    'products' => $this->exportService->getProductErrors()
                ]
            ]);
        } else {
            return $exporter->serializeItems($xmlArray->getItems(), 0, $xmlArray->getCount(), $xmlArray->getTotal());
        }
    }

    /**
     * @param string $shopkey
     *
     * @throws Exception
     */
    public function setShopKey($shopkey)
    {
        $this->shopkey = $shopkey;
        $this->shop = null;
        $configValue = Shopware()->Models()->getRepository(Value::class)->findOneBy(['value' => $shopkey]);

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

    public function setUpExportService()
    {
        $this->exportService = new ExportService($this->shopkey, $this->shop->getCategory());
    }

    /**
     * @throws Enlight_Exception
     * @throws Zend_Cache_Exception
     */
    protected function warmUpCache()
    {
        $id = sprintf('%s_%s', Constants::CACHE_ID_PRODUCT_STREAMS, $this->shopkey);

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

    /**
     * @return ExportService
     */
    public function getExportService()
    {
        return $this->exportService;
    }
}
