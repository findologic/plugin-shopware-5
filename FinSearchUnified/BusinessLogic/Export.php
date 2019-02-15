<?php

namespace FinSearchUnified\BusinessLogic;

use Assert\AssertionFailedException;
use FinSearchUnified\Bundles\SearchBundle\Condition\HasActiveCategoryCondition;
use FinSearchUnified\Bundles\SearchBundle\Condition\HasActiveMainDetailCondition;
use FinSearchUnified\Bundles\SearchBundle\Condition\HasProductNameCondition;
use FinSearchUnified\Bundles\SearchBundle\Condition\IsActiveProductCondition;
use FinSearchUnified\Bundles\SearchBundle\Condition\IsChildOfShopCategoryCondition;
use FinSearchUnified\Bundles\SearchBundleDBAL\QueryBuilderFactory;
use FinSearchUnified\XmlInformation;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ProductServiceInterface;
use Shopware\Components\Compatibility\LegacyStructConverter;
use Shopware\Models\Config\Value;
use Shopware\Models\Shop\Shop;
use UnexpectedValueException;

class Export
{
    /**
     * @var ProductServiceInterface
     */
    protected $productService;

    /**
     * @var LegacyStructConverter
     */
    protected $legacyStructConverter;

    /**
     * @var ContextServiceInterface
     */
    protected $contextService;

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
     * @param QueryBuilderFactoryInterface $queryBuilderFactory
     * @param ProductServiceInterface $productService
     * @param LegacyStructConverter $legacyStructConverter
     * @param ContextServiceInterface $contextService
     */
    public function __construct(
        QueryBuilderFactoryInterface $queryBuilderFactory,
        ProductServiceInterface $productService,
        LegacyStructConverter $legacyStructConverter,
        ContextServiceInterface $contextService
    ) {
        $this->queryBuilderFactory = $queryBuilderFactory;
        $this->productService = $productService;
        $this->legacyStructConverter = $legacyStructConverter;
        $this->contextService = $contextService;
    }

    /**
     * @param string $shopkey
     * @param int $start
     * @param int|null $count
     *
     * @return XmlInformation
     * @throws AssertionFailedException
     * @throws \Exception
     */
    public function getXml($shopkey, $start = 0, $count = null)
    {
        $response = new XmlInformation();

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

        $shopCategoryId = $this->shop->getCategory()->getId();

        $criteria = new Criteria();
        $criteria->addCondition(new IsActiveProductCondition());
        $criteria->addCondition(new HasProductNameCondition());
        $criteria->addCondition(new HasActiveMainDetailCondition());
        $criteria->addCondition(new HasActiveCategoryCondition($shopCategoryId));
        $criteria->addCondition(new IsChildOfShopCategoryCondition($shopCategoryId));

        if ($count !== null) {
            $criteria->limit($count);
        }
        $criteria->offset($start);

        $query = $this->queryBuilderFactory->createProductQuery($criteria, $this->contextService->getShopContext());

        $statement = $query->execute();
        $products = $statement->fetchAll();

        $response->items = [];
        $response->count = 0;
        $response->total = $this->getTotalCount($query);

        while ($product = array_shift($products)) {
            $numbers = explode(', ', $product['__variant_numbers']);
            $numbers[] = $product['__main_detail_number'];

            $listProducts = $this->legacyStructConverter->convertListProductStructList(
                $this->productService->getList($numbers, $this->contextService->getShopContext())
            );

            if ($listProducts) {
                $response->count++;
            }
        }

        $response->items = $products;

        return $response;
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
