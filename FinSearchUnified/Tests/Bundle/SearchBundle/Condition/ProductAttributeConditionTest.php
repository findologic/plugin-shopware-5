<?php

use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Tests\TestCase;

class ProductAttributeConditionTest extends TestCase
{
    public function exceptionDataProvider()
    {
        return [
            'The value for "field" is an integer' => [
                'field' => 1,
                'exception' => Assert\InvalidArgumentException::class
            ],
            'The value for "field" is "vendor"' => [
                'field' => 'vendor',
                'exception' => null
            ],
        ];
    }

    /**
     * @dataProvider exceptionDataProvider
     *
     * @param string $field
     * @param string $exception
     */
    public function testException($field, $exception)
    {
        if ($exception !== null) {
            $this->expectException($exception);
        }

        $condition = new ProductAttributeCondition($field, '', '');
        $conditionName = $condition->getName();
        $this->assertEquals('product_attribute_vendor', $conditionName);
    }
}
