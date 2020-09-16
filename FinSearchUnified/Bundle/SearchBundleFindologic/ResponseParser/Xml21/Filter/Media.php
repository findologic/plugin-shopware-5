<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter;

use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Struct;

class Media extends Struct
{
    /** @var string|null */
    private $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function getUrl()
    {
        return $this->url;
    }
}
