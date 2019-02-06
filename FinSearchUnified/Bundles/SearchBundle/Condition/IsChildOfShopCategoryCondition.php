<?php

namespace FinSearchUnified\Bundles\SearchBundle\Condition;

use Assert\Assertion;
use Assert\AssertionFailedException;
use Shopware\Bundle\SearchBundle\ConditionInterface;

class IsChildOfShopCategoryCondition implements ConditionInterface, \JsonSerializable
{
    /**
     * @var int
     */
    private $shopCategoryId;

    /**
     * HasActiveCategoryCondition constructor.
     *
     * @param int $shopCategoryId
     *
     * @throws AssertionFailedException
     */
    public function __construct($shopCategoryId)
    {
        Assertion::integer($shopCategoryId);

        $this->shopCategoryId = $shopCategoryId;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'is_child_of_shop_category_condition';
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return int
     */
    public function getShopCategoryId()
    {
        return $this->shopCategoryId;
    }
}
