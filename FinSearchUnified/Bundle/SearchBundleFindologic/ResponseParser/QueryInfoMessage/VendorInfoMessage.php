<?php

declare(strict_types=1);

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage;

class VendorInfoMessage extends QueryInfoMessage
{
    /** @var string */
    public $type = QueryInfoMessage::TYPE_VENDOR;

    public function __construct(string $filterName, string $filterValue)
    {
        $this->filterName = $filterName;
        $this->filterValue = $filterValue;
    }
}
