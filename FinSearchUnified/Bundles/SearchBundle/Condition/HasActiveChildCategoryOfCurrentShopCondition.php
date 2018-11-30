<?php

namespace FinSearchUnified\Bundles\SearchBundle\Condition;

use Shopware\Bundle\SearchBundle\ConditionInterface;

class HasActiveChildCategoryOfCurrentShopCondition implements ConditionInterface, \JsonSerializable
{
    private $shopCategoryId;

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'has_active_child_category_of_current_shop_condition';
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * HasActiveCategoryCondition constructor.
     *
     * @param int $shopCategoryId
     */
    public function __construct($shopCategoryId)
    {
        $this->shopCategoryId = $shopCategoryId;
    }

    /**
     * @return int
     */
    public function getShopCategoryId()
    {
        return $this->shopCategoryId;
    }
}
