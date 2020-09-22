<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser;

class Promotion extends Struct
{
    /** @var string */
    private $image;

    /** @var string */
    private $link;

    public function __construct($image, $link)
    {
        $this->image = $image;
        $this->link = $link;
    }

    public function getImage()
    {
        return $this->image;
    }

    public function getLink()
    {
        return $this->link;
    }
}
