<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\QueryInfoMessage;

use InvalidArgumentException;

abstract class QueryInfoMessage
{
    const
        TYPE_QUERY = 'query', // Search results for "<query>" (<count> hits)
        TYPE_CATEGORY = 'cat', // Search results for <cat-filter-name> <cat-name> (<count> hits)
        TYPE_VENDOR = 'vendor', // Search results for <vendor-filter-name> <vendor-name> (<count> hits)
        TYPE_DEFAULT = 'default';

    /** @var string */
    protected $filterName;

    /** @var string */
    protected $filterValue;

    /** @var string */
    protected $type = QueryInfoMessage::TYPE_DEFAULT;

        /** @var string */
    protected $query; // Search results (<count> hits)

    /**
     * @param string $type
     * @param string|null $query
     * @param string|null $filterName
     * @param string|null $filterValue
     *
     * @return QueryInfoMessage
     */
    public static function buildInstance(
        $type,
        $query = null,
        $filterName = null,
        $filterValue = null
    ) {
        switch ($type) {
            case self::TYPE_QUERY:
                static::assertQueryIsEmpty($query);

                return new SearchTermQueryInfoMessage($query);
            case self::TYPE_CATEGORY:
                static::assertFilterNameAndValueAreNotEmpty($filterName, $filterValue);

                return new CategoryInfoMessage($filterName, $filterValue);
            case self::TYPE_VENDOR:
                static::assertFilterNameAndValueAreNotEmpty($filterName, $filterValue);

                return new VendorInfoMessage($filterName, $filterValue);
            case self::TYPE_DEFAULT:
                return new DefaultInfoMessage();
            default:
                throw new InvalidArgumentException(sprintf('Unknown query info message type "%s".', $type));
        }
    }

    /**
     * @param string|null $filterName
     * @param string|null $filterValue
     */
    private static function assertFilterNameAndValueAreNotEmpty($filterName, $filterValue)
    {
        if (!$filterName || !$filterValue) {
            throw new InvalidArgumentException('Filter name and filter value must be set!');
        }
    }

    /**
     * @param string|null $query
     */
    private static function assertQueryIsEmpty($query)
    {
        if (!$query) {
            throw new InvalidArgumentException('Query must be set for a SearchTermQueryInfoMessage!');
        }
    }

    /**
     * @return string
     */
    public function getFilterName()
    {
        return $this->filterName;
    }

    /**
     * @param string $filterName
     */
    public function setFilterName($filterName)
    {
        $this->filterName = $filterName;
    }

    /**
     * @return string
     */
    public function getFilterValue()
    {
        return $this->filterValue;
    }

    /**
     * @param string $filterValue
     */
    public function setFilterValue($filterValue)
    {
        $this->filterValue = $filterValue;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param string $query
     */
    public function setQuery($query)
    {
        $this->query = $query;
    }
}
