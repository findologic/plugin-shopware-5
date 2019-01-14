<?php

namespace FinSearchUnified\BusinessLogic;

use Assert\AssertionFailedException;
use FinSearchUnified\Bundles\SearchBundle\Condition\HasActiveCategoryCondition;
use FinSearchUnified\Bundles\SearchBundle\Condition\HasActiveChildOfShopCategoryCondition;
use FinSearchUnified\XmlInformation;
use Shopware\Bundle\SearchBundle\Condition\IsAvailableCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactory;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Models\Config\Value;
use Shopware\Models\Shop\Shop;
use UnexpectedValueException;
use FINDOLOGIC\Export\Exporter;

class Export
{
    /**
     * @var Shop
     */
    private $shop;

    /**
     * @var string
     */
    private $shopkey;

    /**
     * @var QueryBuilderFactory
     */
    private $queryBuilderFactory;

    /**
     * Export constructor.
     *
     * @param QueryBuilderFactory $queryBuilderFactory
     */
    public function __construct(QueryBuilderFactory $queryBuilderFactory)
    {
        $this->queryBuilderFactory = $queryBuilderFactory;
    }

    /**
     * @param string $shopkey
     * @param int $start
     * @param int $count
     *
     * @return XmlInformation
     * @throws AssertionFailedException
     * @throws \Exception
     */
    public function getXml($shopkey, $start = 0, $count = 0)
    {
        $response = new XmlInformation();

        $response->items = [];

        $this->shop = null;
        $this->shopkey = null;

        if (Shopware()->Container()->has('shop') && Shopware()->Config()->offsetGet('ShopKey') === $shopkey) {
            $this->shop = Shopware()->Shop();
            $this->shopkey = $shopkey;
        } else {
            $configValue = Shopware()->Models()->getRepository(Value::class)->findOneBy(['value' => $shopkey]);

            if ($configValue && $configValue->getShop()) {
                $this->shopkey = $shopkey;
                $this->shop = $configValue->getShop();
                $this->shop->registerResources();
            }
        }

        if (!$this->shop) {
            throw new UnexpectedValueException('Provided shopkey not assigned to any shop!');
        }

        $shopCategory = $this->shop->getCategory()->getId();

        $queryBuilder = $this->queryBuilderFactory->createQueryBuilder();

        $queryBuilder->from('s_articles', 'product');

        $queryBuilder->select([
            'SQL_CALC_FOUND_ROWS product.id AS __product_id',
            'mainDetail.ordernumber AS __main_detail_number',
            "GROUP_CONCAT(variant.ordernumber SEPARATOR ', ') AS __variant_numbers"
        ]);

        $queryBuilder->leftJoin(
            'product',
            's_articles_details',
            'mainDetail',
            'mainDetail.id = product.main_detail_id'
        );

        $queryBuilder->leftJoin(
            'product',
            's_articles_details',
            'variant',
            'variant.articleID = product.id AND variant.id != product.main_detail_id'
        );

        $queryBuilder->where("product.name != '' AND product.active = 1");

        // Is article of current shop.
        $queryBuilder->andWhere('(SELECT COUNT(*) FROM s_articles_categories_ro WHERE s_articles_categories_ro.articleID = product.id AND s_articles_categories_ro.categoryID = :baseCategoryId) > 0');

        // Has active categories. Explicitly ignore shop's root category.
        $queryBuilder->andWhere('(SELECT COUNT(*) FROM s_articles_categories_ro LEFT JOIN s_categories ON s_categories.id = s_articles_categories_ro.categoryID WHERE s_articles_categories_ro.articleID = product.id AND s_categories.active = 1 AND s_articles_categories_ro.categoryID != :baseCategoryId) > 0');

        // Has active main detail.
        $queryBuilder->andWhere('(SELECT COUNT(*) FROM s_articles_details WHERE s_articles_details.id = product.main_detail_id AND s_articles_details.active = 1) > 0');

        $queryBuilder->groupBy('__product_id, __main_detail_number');

        if ($count !== null) {
            $queryBuilder->setMaxResults($count);
        }

        $queryBuilder->setFirstResult($start);

        $queryBuilder->setParameter(':baseCategoryId', $shopCategory);

        $statement = $queryBuilder->execute();
        $products = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $total = $this->getTotalCount($queryBuilder);

        $productService = Shopware()->Container()->get('shopware_storefront.product_service');
        $legacyStructConverter = Shopware()->Container()->get('legacy_struct_converter');

        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

//        print_r(Shopware()->Modules()->Articles()->sGetArticleById($products[0]['__product_id'], null, $products[0]['__main_detail_number']));
//        die();

        foreach ($products as $product) {
            $numbers = explode(', ', $product['__variant_numbers']);
            $numbers[] = $product['__main_detail_number'];

            $listProducts = $legacyStructConverter->convertListProductStructList($productService->getList($numbers, $context));

            if ($listProducts) {
                $mainDetail = $listProducts[$product['__main_detail_number']];

//                var_dump($mainDetail);
//                die();

                unset($listProducts[$product['__main_detail_number']]);

                $article = new Models\Article\Article($product['__product_id'], $mainDetail, $listProducts);

                $response->items[] = $article->getXml();
            }
        }

        $response->count = count($response->items);
        $response->total = $total;

        $exporter = Exporter::create(Exporter::TYPE_XML);

        return $exporter->serializeItems($response->items, $start, $response->count, $response->total);
    }

    /**
     * Calculated the total count of the result.
     *
     * @param QueryBuilder $query
     *
     * @return int
     */
    private function getTotalCount(QueryBuilder $query)
    {
        return (int)$query->getConnection()->fetchColumn('SELECT FOUND_ROWS()');
    }
}
