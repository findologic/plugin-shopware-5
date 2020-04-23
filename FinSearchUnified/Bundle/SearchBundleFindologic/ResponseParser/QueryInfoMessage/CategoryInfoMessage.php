<?php

declare(strict_types=1);

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage;

class CategoryInfoMessage extends QueryInfoMessage
{
    /** @var string */
    protected $type = QueryInfoMessage::TYPE_CATEGORY;

    public function __construct(string $filterName, string $filterValue)
    {
        $this->filterName = $filterName;
        $this->filterValue = $filterValue;
    }
}
