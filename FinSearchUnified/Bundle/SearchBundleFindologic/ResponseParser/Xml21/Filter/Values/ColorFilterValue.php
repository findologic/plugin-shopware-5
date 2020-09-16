<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values;

class ColorFilterValue extends ColorImageFilterValue
{
    /** @var string */
    protected $displayType = 'color';

    /**
     * @var string|null
     */
    private $colorHexCode;

    /**
     * @return string|null
     */
    public function getColorHexCode()
    {
        return $this->colorHexCode;
    }

    public function setColorHexCode($colorHexCode)
    {
        $this->colorHexCode = $colorHexCode;

        return $this;
    }
}
