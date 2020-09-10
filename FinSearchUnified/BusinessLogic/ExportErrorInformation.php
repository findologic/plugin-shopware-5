<?php

namespace FinSearchUnified\BusinessLogic;

class ExportErrorInformation
{
    /**
     * @var string
     */
    public $id;

    /**
     * @var string[]
     */
    public $errors = [];

    /**
     * @param string $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }
}
