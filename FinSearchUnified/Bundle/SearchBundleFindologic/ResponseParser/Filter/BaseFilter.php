<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Filter;

use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values\FilterValue;

abstract class BaseFilter
{
    /** @var string|null */
    protected $type;

    /** @var string */
    protected $id;

    /** @var string */
    protected $name;

    /** @var FilterValue[] */
    protected $values;

    /** @var string */
    protected $mode;

    /**
     * @param string $id
     * @param string $name
     * @param array $values
     */
    public function __construct($id, $name, array $values = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->values = $values;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getValues()
    {
        return $this->values;
    }

    /**
     * @return string
     */
    public function getMode()
    {
        return $this->mode;
    }

    /**
     * @param string $mode
     */
    public function setMode($mode)
    {
        $this->mode = $mode;
    }
}
