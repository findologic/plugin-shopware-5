<?php

namespace FinSearchUnified\Bundles\SearchBundle\Condition;

use Shopware\Bundle\SearchBundle\ConditionInterface;

class IsActiveProductCondition implements ConditionInterface, \JsonSerializable
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'is_active_product_condition';
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
