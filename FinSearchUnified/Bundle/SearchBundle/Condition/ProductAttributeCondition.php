<?php

namespace FinSearchUnified\Bundle\SearchBundle\Condition;

use Assert\Assertion;
use JsonSerializable;
use Shopware\Bundle\SearchBundle\ConditionInterface;

class ProductAttributeCondition implements ConditionInterface, JsonSerializable
{
    /**
     * @var string
     */
    protected $field;

    /**
     * @var string|array
     */
    protected $value;

    /**
     * @var string
     */
    protected $operator;

    /**
     * @param string $field
     * @param string $operator
     * @param string|array $value ['min' => 1, 'max' => 10] for between operator
     */
    public function __construct($field, $operator, $value)
    {
        Assertion::string($field);
        $this->field = $field;
        $this->value = $value;
        $this->operator = $operator;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'product_attribute_' . $this->field;
    }

    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @param string $field
     */
    public function setField($field)
    {
        $this->field = $field;
    }

    /**
     * @return string|array|null $value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string|array $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * @param string $operator
     */
    public function setOperator($operator)
    {
        $this->operator = $operator;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return get_object_vars($this);
    }
}
