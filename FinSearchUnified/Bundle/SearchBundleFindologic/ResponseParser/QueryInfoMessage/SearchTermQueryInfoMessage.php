<?php

declare(strict_types=1);

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage;

class SearchTermQueryInfoMessage extends QueryInfoMessage
{
    /** @var string */
    protected $type = QueryInfoMessage::TYPE_QUERY;

    public function __construct(string $query)
    {
        $this->query = $query;
    }
}
