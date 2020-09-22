<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values;

use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Media;

abstract class ColorImageFilterValue extends FilterValue
{
    /** @var string */
    protected $displayType;

    /**
     * @var Media|null
     */
    protected $media;

    public function getMedia()
    {
        return $this->media;
    }

    public function setMedia(Media $media = null)
    {
        $this->media = $media;

        return $this;
    }

    public function getDisplayType()
    {
        return $this->displayType;
    }

    public function setDisplayType($displayType)
    {
        $this->displayType = $displayType;

        return $this;
    }
}
