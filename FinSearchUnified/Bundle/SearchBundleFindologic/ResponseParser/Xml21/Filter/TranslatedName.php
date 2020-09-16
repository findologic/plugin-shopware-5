<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter;

use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Struct;

class TranslatedName extends Struct
{
    /** @var string */
    private $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }
}
