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

        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $context = $contextService->getShopContext();

        $criteria = new Criteria();

        if (Shopware()->Config()->offsetGet('hideNoInStock') === true) {
            $criteria->addCondition(new IsAvailableCondition());
        }
        $criteria->addCondition(new HasActiveChildOfShopCategoryCondition($shopCategory));
        $criteria->addCondition(new HasActiveCategoryCondition());

        if ($count) {
            $criteria->limit($count);
        }
        $criteria->offset($start);

        $query = $this->queryBuilderFactory->createProductQuery($criteria, $context);

        $statement = $query->execute();
        $products = $statement->fetchAll();

        $total = $this->getTotalCount($query);

        $response->items = $products;
        $response->count = count($products);
        $response->total = $total;

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
