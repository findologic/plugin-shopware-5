<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage;

class CategoryInfoMessage extends QueryInfoMessage
{
    /** @var string */
    protected $type = QueryInfoMessage::TYPE_CATEGORY;

    public function __construct($filterName, $filterValue)
    {
        $this->filterName = $filterName;
        $this->filterValue = $filterValue;
    }
}
