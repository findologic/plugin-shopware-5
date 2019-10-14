<?php

use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Tests\TestCase;


class ProductAttributeConditionTest extends TestCase
{

    public function exceptionDataProvider()
    {
        return [
            'integer for $field' => [
               'field' => 1,
               'Exception' => InvalidArgumentException::class
            ],
            'vendor for $field' => [
                'field' => 'vendor',
                'Exception' => null
            ],
        ];
    }

    /**
     * @dataProvider exceptionDataProvider
     *
     * @param string $field
     * @param string $exception
     */

    public function testException($field,$exception){
        $ProductAttribute = new ProductAttributeCondition($field, '', '');
            $product_attribute = $ProductAttribute->getName();
            if($exception !== null){
                $this->expectException($exception);
            }
            var_dump($product_attribute);
            $this->assertEquals('product_attribute_vendor',$product_attribute);

}
}