<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Values;

use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Struct;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\TranslatedName;

class FilterValue extends Struct
{
    /** @var string */
    private $id;

    /** @var string */
    private $name;

    /** @var TranslatedName */
    private $translated;

    protected $frequency = 0;

    public function __construct($id, $name)
    {
        $this->id = $id;
        $this->name = $name;
        $this->translated = new TranslatedName($name);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getTranslated()
    {
        return $this->translated;
    }

    /**
     * @return int
     */
    public function getFrequency()
    {
        return $this->frequency;
    }

    /**
     * @param int $frequency
     */
    public function setFrequency($frequency)
    {
        $this->frequency = $frequency;
    }
}
