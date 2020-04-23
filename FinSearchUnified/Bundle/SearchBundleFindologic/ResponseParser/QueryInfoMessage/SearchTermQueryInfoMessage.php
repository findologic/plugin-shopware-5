<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage;

class SearchTermQueryInfoMessage extends QueryInfoMessage
{
    /** @var string */
    protected $type = QueryInfoMessage::TYPE_QUERY;

    public function __construct($query)
    {
        $this->query = $query;
    }
}
