<?php

namespace FinSearchUnified\Bundles\SearchBundle\Condition;

use Shopware\Bundle\SearchBundle\ConditionInterface;

class HasActiveCategoryCondition implements ConditionInterface, \JsonSerializable
{
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
}
