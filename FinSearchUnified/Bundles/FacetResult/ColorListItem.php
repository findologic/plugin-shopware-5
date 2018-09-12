<?php

namespace FinSearchUnified\Bundles\FacetResult;

use Shopware\Bundle\SearchBundle\FacetResult;

class ColorListItem extends FacetResult\MediaListItem
{
    /**
     * @var string
     */
    protected $colorcode;

    /**
     * @param int|string  $id
     * @param string      $label
     * @param bool        $active
     * @param string|null $color
     * @param Attribute[] $attributes
     */
    public function __construct($id, $label, $active, $color = null, $attributes = [])
    {
        parent::__construct($id, $label, $active, null, $attributes);
        $this->colorcode = $color;
    }

    /**
     * @return string
     */
    public function getColorcode()
    {
        return $this->colorcode;
    }

    /**
     * @param string $colorcode
     */
    public function setColorcode($colorcode)
    {
        $this->colorcode = $colorcode;
    }
}
