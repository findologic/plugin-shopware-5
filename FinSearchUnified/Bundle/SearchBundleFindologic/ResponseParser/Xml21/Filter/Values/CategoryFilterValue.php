<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values;

class CategoryFilterValue extends FilterValue
{
    /** @var bool */
    private $selected = false;

    /** @var CategoryFilterValue[] */
    private $values = [];

    /**
     * @return bool
     */
    public function isSelected()
    {
        return $this->selected;
    }

    /**
     * @return CategoryFilterValue
     */
    public function setSelected($selected)
    {
        $this->selected = $selected;

        return $this;
    }

    /**
     * @return CategoryFilterValue[]
     */
    public function getValues()
    {
        if (empty($this->values)) {
            return [];
        }

        return $this->values;
    }

    /**
     * @return CategoryFilterValue
     */
    public function addValue(CategoryFilterValue $filter)
    {
        $this->values[] = $filter;

        return $this;
    }

    /**
     * @param int $frequency
     *
     * @return CategoryFilterValue
     */
    public function setFrequency($frequency)
    {
        $this->frequency = $frequency;

        return $this;
    }
}
