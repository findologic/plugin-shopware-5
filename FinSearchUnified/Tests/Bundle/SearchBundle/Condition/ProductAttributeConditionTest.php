<?php

use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Tests\TestCase;


class ProductAttributeConditionTest extends TestCase
{

    public function exceptionDataProvider()
    {
        return [
            'integer for $field' => [
               'field' => 1
            ],
            'vendor for $field' => [
                'field' => 'vendor'
            ],
        ];
    }

    /**
     * @dataProvider exceptionDataProvider
     *
     * @param string $field
     * @param string $operator
     * @param string $value
     */

    public function testException($field, $operator, $value){

        $Productattribute = new ProductAttributeCondition($field, $operator, $value);
        $product_attribute = $Productattribute->getName();
//        return $product_attribute;

        assertTrue($product_attribute);

}
}