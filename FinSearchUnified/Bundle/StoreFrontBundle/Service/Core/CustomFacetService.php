<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Service\Core;

use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\CustomFacetGatewayInterface;
use FinSearchUnified\Bundle\StoreFrontBundle\Service\CustomFacetServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class CustomFacetService implements CustomFacetServiceInterface, CustomFacetGatewayInterface
{
    /**
     * @var CustomFacetGatewayInterface
     */
    private $gateway;

    public function __construct(CustomFacetGatewayInterface $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * {@inheritdoc}
     */
    public function getList(array $ids, ShopContextInterface $context)
    {
        return $this->gateway->getList($ids, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function getFacetsOfCategories(array $categoryIds, ShopContextInterface $context)
    {
        return $this->gateway->getFacetsOfCategories($categoryIds, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function getAllCategoryFacets(ShopContextInterface $context)
    {
        return $this->gateway->getAllCategoryFacets($context);
    }
}
