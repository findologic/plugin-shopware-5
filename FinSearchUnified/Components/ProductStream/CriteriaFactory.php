<?php

namespace FinSearchUnified\Components\ProductStream;

use Enlight_Controller_Request_Request as Request;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\StoreFrontCriteriaFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Shopware\Components\ProductStream\CriteriaFactoryInterface;

class CriteriaFactory implements CriteriaFactoryInterface
{
    /**
     * @var StoreFrontCriteriaFactoryInterface
     */
    private $criteriaFactory;

    /**
     * @var CriteriaFactoryInterface
     */
    private $originalFactory;

    /**
     * @param StoreFrontCriteriaFactoryInterface $criteriaFactory
     * @param CriteriaFactoryInterface $service
     */
    public function __construct(StoreFrontCriteriaFactoryInterface $criteriaFactory, CriteriaFactoryInterface $service)
    {
        $this->criteriaFactory = $criteriaFactory;
        $this->originalFactory = $service;
    }

    /**
     * @param Request              $request
     * @param ShopContextInterface $context
     *
     * @return Criteria
     */
    public function createCriteria(Request $request, ShopContextInterface $context)
    {
        if (StaticHelper::useShopSearch()) {
            $criteria = $this->originalFactory->createCriteria($request, $context);
        } else {
            $criteria = $this->criteriaFactory->createListingCriteria($request, $context);
        }

        return $criteria;
    }
}
