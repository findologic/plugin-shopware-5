<?php

use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Tests\TestCase;

class ProductAttributeConditionTest extends TestCase
{
    public function exceptionDataProvider()
    {
        return [
            'The value for "field" is an integer' => [
                'field' => 1,
                'exceptionMessage' => 'Value "1" expected to be string, type integer given.'
            ],
            'The value for "field" is a string' => [
                'field' => 'vendor',
                'exceptionMessage' => null
            ],
        ];
    }

    /**
     * @dataProvider exceptionDataProvider
     *
     * @param string $field
     * @param string $exceptionMessage
     */
    public function testConditionException($field, $exceptionMessage)
    {
        try {
            $condition = new ProductAttributeCondition($field, Operator::EQ, 'Findologic');
            $this->assertEquals(sprintf('product_attribute_%s', $field), $condition->getName());
            $this->assertEquals('Findologic', $condition->getValue());
        } catch (\Assert\InvalidArgumentException $e) {
            $this->assertSame($exceptionMessage, $e->getMessage());
        } catch (Exception $e) {
            $this->fail();
        }
    }
}
