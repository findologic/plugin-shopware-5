<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter;

class RangeSliderFilter extends Filter
{
    /** @var string */
    protected $type = 'range-slider';

    /** @var string */
    private $minKey;

    /** @var string */
    private $maxKey;

    /** @var string */
    private $unit = '€';

    /** @var float */
    private $min;

    /** @var float */
    private $max;

    /** @var float */
    private $activeMin;

    /** @var float */
    private $activeMax;

    public function __construct($id, $name, array $values = [])
    {
        parent::__construct($id, $name, $values);
        if ($id === 'price') {
            $this->minKey = 'min';
            $this->maxKey = 'max';
        } else {
            $this->minKey = sprintf('min-%s', $id);
            $this->maxKey = sprintf('max-%s', $id);
        }
    }

    public function getMinKey()
    {
        return $this->minKey;
    }

    public function getMaxKey()
    {
        return $this->maxKey;
    }

    public function setUnit($unit)
    {
        $this->unit = $unit;

        return $this;
    }

    public function getUnit()
    {
        return $this->unit;
    }

    /**
     * @return float
     */
    public function getMin()
    {
        return $this->min;
    }

    /**
     * @param float $min
     */
    public function setMin($min)
    {
        $this->min = $min;
    }

    /**
     * @return float
     */
    public function getMax()
    {
        return $this->max;
    }

    /**
     * @param float $max
     */
    public function setMax($max)
    {
        $this->max = $max;
    }

    /**
     * @return float
     */
    public function getActiveMin()
    {
        return $this->activeMin;
    }

    /**
     * @param float $activeMin
     */
    public function setActiveMin($activeMin)
    {
        $this->activeMin = $activeMin;
    }

    /**
     * @return float
     */
    public function getActiveMax()
    {
        return $this->activeMax;
    }

    /**
     * @param float $activeMax
     */
    public function setActiveMax($activeMax)
    {
        $this->activeMax = $activeMax;
    }
}
