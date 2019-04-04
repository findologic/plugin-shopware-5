<?php

namespace FinSearchUnified\Tests\Bundle\SearchBundleFindologic;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilderFactory;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Condition\IsAvailableCondition;
use Shopware\Bundle\SearchBundle\Condition\PriceCondition;
use Shopware\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use Shopware\Bundle\SearchBundle\Condition\SearchTermCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Sorting\PopularitySorting;
use Shopware\Bundle\SearchBundle\Sorting\SimpleSorting;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ProductContextInterface;
use Shopware\Components\Test\Plugin\TestCase;

class QueryBuilderFactoryTest extends TestCase
{
    /**
     * @var QueryBuilderFactory
     */
    private $factory;

    /**
     * @var ProductContextInterface
     */
    private $context;

    /**
     * @throws Exception
     */
    protected function setUp()
    {
        parent::setUp();

        $this->factory = new QueryBuilderFactory(
            Shopware()->Container()->get('http_client'),
            Shopware()->Container()->get('shopware_plugininstaller.plugin_manager'),
            Shopware()->Container()->get('config')
        );

        /** @var ContextServiceInterface $contextService */
        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');
        $this->context = $contextService->getShopContext();
    }

    /**
     * @throws Exception
     */
    public function testCreateQueryWithoutConditions()
    {
        $criteria = new Criteria();

        $query = $this->factory->createQuery($criteria, $this->context);
        $params = $query->getParameters();

        $this->assertArrayHasKey('group', $params, 'Usergroup was expected to be present in the parameters');
        $this->assertArrayNotHasKey('attrib', $params, 'No attributes were expected to be present in the parameters');
        $this->assertArrayNotHasKey('query', $params, 'No search query was expected to be present in the parameters ');
    }

    /**
     * @throws Exception
     */
    public function testCreateQueryithConditions()
    {
        $criteria = new Criteria();
        $criteria->addCondition(new CategoryCondition([5, 12]));
        $criteria->addCondition(new PriceCondition(1, 20));
        $criteria->addCondition(new ProductAttributeCondition('vendor', '=', 'Findologic Rockers'));
        $criteria->addCondition(new SearchTermCondition('blubbergurke'));
        $criteria->addCondition(new IsAvailableCondition());

        $query = $this->factory->createQuery($criteria, $this->context);
        $params = $query->getParameters();

        $this->assertArrayHasKey('attrib', $params, 'Attributes were expected to be present in the parameters');
        $attrib = $params['attrib'];

        // CategoryCondition
        $this->assertArrayHasKey('cat', $attrib, 'Category was expected to be present in the attribute parameters');
        $this->assertEquals(
            ['Genusswelten', 'Genusswelten_Tees und Zubehör_Tees'],
            $attrib['cat'],
            'Expected categories to contain the name of the provided category IDs'
        );

        // PriceCondition
        $this->assertArrayHasKey('price', $attrib, 'Prices were expected to be present in the attribute parameters');
        $this->assertEquals(1, $attrib['price']['min'], 'Expected minimum price to be 1');
        $this->assertEquals(20, $attrib['price']['max'], 'Expected maximum price to be 20');

        // ProductAttributeCondition
        $this->assertArrayHasKey('vendor', $attrib, 'Expected "vendor" to be available in the attribute parameters');
        $this->assertEquals(
            ['Findologic Rockers'],
            $attrib['vendor'],
            'Expected vendor to be an array containing "Findologic Rockers"'
        );

        // SearchTermCondition
        $this->assertArrayHasKey('query', $params, 'Expected search query to be in the parameters');
        $this->assertSame('blubbergurke', $params['query'], 'Expected search query to be "blubbergurke"');

        $this->assertCount(3, $attrib, 'Expected attributes to not contain any other parameters');
    }

    /**
     * @throws Exception
     */
    public function testCreateQueryWithoutSorting()
    {
        $criteria = new Criteria();

        $query = $this->factory->createQueryWithSorting($criteria, $this->context);
        $params = $query->getParameters();

        $this->assertArrayNotHasKey('order', $params, 'Sorting was not expected to be present in the parameters');
        $this->assertArrayNotHasKey('attrib', $params, 'No attributes were expected to be present in the parameters');
        $this->assertArrayNotHasKey('query', $params, 'No search query was expected to be present in the parameters ');
    }

    /**
     * @dataProvider sortingProvider
     *
     * @param SortingInterface $sorting
     * @param string $expected
     *
     * @throws Exception
     */
    public function testCreateQueryWithSingleSorting($sorting, $expected)
    {
        $criteria = new Criteria();
        $criteria->addSorting($sorting);

        $query = $this->factory->createQueryWithSorting($criteria, $this->context);
        $params = $query->getParameters();

        $this->assertSame($expected, $params['order'], sprintf('Expected sorting to be "%s"', $expected));
    }

    /**
     * @throws Exception
     */
    public function testCreateQueryWithMultipleSortingAndConditions()
    {
        $criteria = new Criteria();
        $criteria->addSorting(new PopularitySorting());
        $criteria->addSorting(new SimpleSorting('name'));
        $criteria->addCondition(new SearchTermCondition('blubbergurke'));
        $criteria->addCondition(new IsAvailableCondition());

        $query = $this->factory->createQueryWithSorting($criteria, $this->context);
        $params = $query->getParameters();

        $this->assertArrayHasKey('order', $params, 'Sorting was expected to be present in the parameters');
        $this->assertArrayHasKey('query', $params, 'Search query was expected to be present in the parameters ');
        $this->assertArrayNotHasKey('attrib', $params, 'No attributes were expected to be present in the parameters');

        $this->assertSame('salesfrequency ASC', $params['order'], 'Expected sorting order to be "salesfrequency ASC"');
        $this->assertSame('salesfrequency ASC', $params['order'], 'Expected sorting order to be "salesfrequency ASC"');
    }

    public function sortingProvider()
    {
        return [
            'Sort by popularity' => [
                new PopularitySorting(SortingInterface::SORT_DESC),
                'salesfrequency DESC'
            ],
            'Sort by name' => [
                new SimpleSorting('name'),
                null
            ],
        ];
    }
}
