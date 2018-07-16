<?php

namespace FinSearchUnified\Bundles\FacetResult;

class ColorListItem extends \Shopware\Bundle\SearchBundle\FacetResult\MediaListItem
{
    /** @var string */
    protected $colorcode;

    public function __construct($id, $label, $active, \Shopware\Bundle\StoreFrontBundle\Struct\Media $media = null, $color = null, $attributes = [])
    {
        parent::__construct($id, $label, $active, $media, $attributes);
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
