<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Enlight_Controller_Request_RequestHttp;
use Exception;
use FinSearchUnified\Bundle\SearchBundle\Condition\Operator;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\ProductAttributeConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NewQueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NewSearchQueryBuilder;
use FinSearchUnified\Tests\TestCase;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;

class ProductAttributeConditionHandlerTest extends TestCase
{
    /**
     * @var NewQueryBuilder
     */
    private $querybuilder;

    /**
     * @var ProductContextInterface
     */
    private $context;

    /**
     * @var ProductAttributeConditionHandler
     */
    private $handler;

    /**
     * @throws Exception
     */
    protected function setUp()
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR'] = '192.168.0.1';

        $request = new Enlight_Controller_Request_RequestHttp();
        Shopware()->Front()->setRequest($request);

        $this->querybuilder = new NewSearchQueryBuilder(
            Shopware()->Container()->get('shopware_plugininstaller.plugin_manager'),
            Shopware()->Config()
        );
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $this->context = $contextService->getShopContext();
        $this->handler = new ProductAttributeConditionHandler();
    }

    public function attributesDataProvider()
    {
        return [
            'Vendor is "Brand+Name"' => [
                Operator::EQ,
                [
                    ['field' => 'vendor', 'value' => 'Brand+Name']
                ],
                ['vendor' => ['' => 'Brand Name']]
            ],
            'Color is "blue" and "red"' => [
                Operator::EQ,
                [
                    ['field' => 'color', 'value' => 'blue'],
                    ['field' => 'color', 'value' => 'red']
                ],
                ['color' => ['' => ['blue', 'red']]]
            ],
            'Vendor is "Brand+Name" and color is "red"' => [
                Operator::EQ,
                [
                    ['field' => 'vendor', 'value' => 'Brand+Name'],
                    ['field' => 'color', 'value' => 'red']
                ],
                ['vendor' => ['' => 'Brand Name'], 'color' => ['' => 'red']]
            ],
            'Discount is between 12.69 and PHP_INT_MAX' => [
                Operator::BETWEEN,
                [
                    ['field' => 'discount', 'value' => ['min' => 12.69]]
                ],
                ['discount' => ['min' => 12.69, 'max' => PHP_INT_MAX]]
            ],
            'Discount is between 0 and 50' => [
                Operator::BETWEEN,
                [
                    ['field' => 'discount', 'value' => ['max' => 50]]
                ],
                ['discount' => ['min' => 0, 'max' => 50]]
            ],
            'Discount is between 12 and 50' => [
                Operator::BETWEEN,
                [
                    ['field' => 'discount', 'value' => ['min' => 12, 'max' => 50]]
                ],
                ['discount' => ['min' => 12, 'max' => 50]]
            ]
        ];
    }

    /**
     * @dataProvider attributesDataProvider
     *
     * @param string $operator
     * @param array $attributes
     * @param array $expectedValues
     *
     * @throws Exception
     */
    public function testGenerateCondition($operator, array $attributes, array $expectedValues)
    {
        foreach ($attributes as $value) {
            $this->handler->generateCondition(
                new ProductAttributeCondition($value['field'], $operator, $value['value']),
                $this->querybuilder,
                $this->context
            );
        }

        $params = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('attrib', $params, 'Parameter "attrib" was not found in the parameters');

        foreach ($expectedValues as $field => $values) {
            $this->assertArrayHasKey(
                $field,
                $params['attrib'],
                sprintf(
                    '"%s" is not set in the "attrib" parameter',
                    $field
                )
            );
            $this->assertEquals(
                $values,
                $params['attrib'][$field],
                sprintf(
                    'Field "%s" does not contain the expected attribute value',
                    $field
                )
            );
        }
    }
}
