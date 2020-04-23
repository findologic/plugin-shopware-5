<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage;

class VendorInfoMessage extends QueryInfoMessage
{
    /** @var string */
    public $type = QueryInfoMessage::TYPE_VENDOR;

    public function __construct($filterName, $filterValue)
    {
        $this->filterName = $filterName;
        $this->filterValue = $filterValue;
    }
}
