<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandlerInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilder;
use Shopware\Bundle\SearchBundle\Condition\ManufacturerCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use FinSearchUnified\Helper\StaticHelper;

class ManufacturerConditionHandler implements ConditionHandlerInterface
{
    /**
     * @param ConditionInterface $condition
     *
     * @return bool
     */
    public function supportsCondition(ConditionInterface $condition)
    {
        return $condition instanceof ManufacturerCondition;
    }

    /**
     * @param ConditionInterface $condition
     * @param QueryBuilder $query
     * @param ShopContextInterface $context
     *
     * @throws Exception
     */
    public function generateCondition(
        ConditionInterface $condition,
        QueryBuilder $query,
        ShopContextInterface $context
    ) {
        $manufacturers = [];

        /** @var ManufacturerCondition $condition */
        foreach ($condition->getManufacturerIds() as $manufacturerId) {
            $manufacturerName = StaticHelper::buildManufacturerName($manufacturerId);
            if (!StaticHelper::isEmpty($manufacturerName)) {
                $manufacturers[] = $manufacturerName;
            }
        }

        if (!StaticHelper::isEmpty($manufacturers)) {
            $query->addManufactures($manufacturers);
        }
    }
}
