<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic\ConditionHandler;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\ProductAttributeConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use Shopware\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use Shopware\Components\Test\Plugin\TestCase;

class ProductAttributeConditionHandlerTest extends TestCase
{
    /**
     * @var QueryBuilder
     */
    private $querybuilder;

    /**
     * @var ProductContextInterface
     */
    private $context = null;

    /**
     * @throws Exception
     */
    protected function setUp()
    {
        parent::setUp();
        $this->querybuilder = new QueryBuilder(
            Shopware()->Container()->get('http_client'),
            Shopware()->Container()->get('shopware_plugininstaller.plugin_manager'),
            Shopware()->Config()
        );
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $this->context = $contextService->getShopContext();
    }

    public function attributesDataProvider()
    {
        return [
            'Vendor is "Brand+Name"' => [
                [
                    ['field' => 'vendor', 'value' => 'Brand+Name']
                ],
                ['vendor' => ['Brand Name']]
            ],
            'Color is "blue" and "red"' => [
                [
                    ['field' => 'color', 'value' => 'blue'],
                    ['field' => 'color', 'value' => 'red']
                ],
                ['color' => ['blue', 'red']]
            ],
            'Vendor is "Brand+Name" and color is "red"' => [
                [
                    ['field' => 'vendor', 'value' => 'Brand+Name'],
                    ['field' => 'color', 'value' => 'red']
                ],
                ['vendor' => ['Brand Name'], 'color' => ['red']]
            ]
        ];
    }

    /**
     * @dataProvider attributesDataProvider
     *
     * @param array $attributes
     * @param array $expectedValues
     *
     * @throws Exception
     */
    public function testGenerateCondition(array $attributes, array $expectedValues)
    {
        $handler = new ProductAttributeConditionHandler();
        foreach ($attributes as $value) {
            $handler->generateCondition(
                new ProductAttributeCondition($value['field'], '=', $value['value']),
                $this->querybuilder,
                $this->context
            );
        }

        $params = $this->querybuilder->getParameters();
        $this->assertArrayHasKey('attrib', $params, 'Parameter "attrib" was not found in the parameters');

        foreach ($expectedValues as $field => $values) {
            $this->assertArrayHasKey($field, $params['attrib'], sprintf(
                '"%s" is not set in the "attrib" parameter',
                $field
            ));
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
