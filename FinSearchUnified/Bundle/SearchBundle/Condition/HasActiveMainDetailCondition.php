<?php

namespace FinSearchUnified\Bundle\SearchBundle\Condition;

use Shopware\Bundle\SearchBundle\ConditionInterface;

class HasActiveMainDetailCondition implements ConditionInterface, \JsonSerializable
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'has_active_main_detail_condition';
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
