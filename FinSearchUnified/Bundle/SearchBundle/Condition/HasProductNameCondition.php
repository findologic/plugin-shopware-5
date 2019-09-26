<?php

namespace FinSearchUnified\Bundle\SearchBundle\Condition;

use Shopware\Bundle\SearchBundle\ConditionInterface;

class HasProductNameCondition implements ConditionInterface, \JsonSerializable
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'has_product_name_condition';
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
