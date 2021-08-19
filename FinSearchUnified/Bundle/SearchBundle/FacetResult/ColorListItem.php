<?php

namespace FinSearchUnified\Bundle\SearchBundle\FacetResult;

use Shopware\Bundle\SearchBundle\FacetResult;

class ColorListItem extends FacetResult\MediaListItem
{
    /**
     * @var string
     */
    protected $colorcode;

    /**
     * @var string|null
     */
    protected $imageUrl;

    /**
     * @param int|string $id
     * @param string $label
     * @param bool $active
     * @param string|null $color
     * @param string|null $imageUrl
     * @param array $attributes
     */
    public function __construct($id, $label, $active, $color = null, $imageUrl = null, array $attributes = [])
    {
        parent::__construct($id, $label, $active, null, $attributes);
        $this->colorcode = $color;
        $this->imageUrl = $imageUrl;
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

    /**
     * @return string|null
     */
    public function getImageUrl()
    {
        return $this->imageUrl;
    }
}
