<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser;

use DateTime;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionClass;
use ReflectionException;

class Struct implements JsonSerializable
{
    /**
     * @return array|mixed
     */
    public function jsonSerialize()
    {
        $vars = get_object_vars($this);
        foreach ($vars as $property => $value) {
            if ($value instanceof DateTimeInterface) {
                $value = $value->format(DateTime::ATOM);
            }

            $vars[$property] = $value;
        }

        return $vars;
    }

    /**
     * @return array
     */
    public function getVars()
    {
        return get_object_vars($this);
    }

    /**
     * @param array $options
     *
     * @return $this
     */
    public function assign(array $options)
    {
        foreach ($options as $key => $value) {
            if ($key === 'id' && method_exists($this, 'setId')) {
                $this->setId($value);

                continue;
            }

            try {
                $this->$key = $value;
            } catch (Exception $ignored) {
            }
        }

        return $this;
    }

    /**
     * @param Struct $object
     *
     * @return static
     */
    public static function createFrom(Struct $object)
    {
        try {
            $self = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
        } catch (ReflectionException $exception) {
            throw new InvalidArgumentException($exception->getMessage());
        }

        foreach (get_object_vars($object) as $property => $value) {
            $self->$property = $value;
        }

        /* @var static $self */
        return $self;
    }

    public function __clone()
    {
        $variables = get_object_vars($this);
        foreach ($variables as $key => $value) {
            if (is_object($value)) {
                $this->$key = clone $this->$key;
            } elseif (is_array($value)) {
                $this->$key = $this->cloneArray($value);
            }
        }
    }

    /**
     * @param array $array
     *
     * @return array
     */
    private function cloneArray(array $array)
    {
        $newValue = [];

        foreach ($array as $index => $value) {
            if (is_object($value)) {
                $newValue[$index] = clone $value;
            } elseif (is_array($value)) {
                $newValue[$index] = $this->cloneArray($value);
            } else {
                $newValue[$index] = $value;
            }
        }

        return $newValue;
    }
}
