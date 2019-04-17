<?php

namespace FinSearchUnified\Bundle\SearchBundle\Condition;

use Assert\Assertion;
use Assert\AssertionFailedException;
use Shopware\Bundle\SearchBundle\ConditionInterface;

class HasActiveCategoryCondition implements ConditionInterface, \JsonSerializable
{
    /**
     * @var int
     */
    protected $shopCategoryId;

    /**
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
        return 'has_active_category_condition';
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
