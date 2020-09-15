<?php

namespace FinSearchUnified\BusinessLogic;

use JsonSerializable;

class ExportErrorInformation implements JsonSerializable
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string[]
     */
    protected $errors = [];

    /**
     * @param string $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * @param string $errorMessage
     */
    public function addError($errorMessage)
    {
        $this->errors[] = $errorMessage;
    }

    /**
     * @return string[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
