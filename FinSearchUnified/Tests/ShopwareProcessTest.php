<?php

namespace FinSearchUnified\Tests;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use Exception;
use FINDOLOGIC\Export\Data\Attribute;
use FINDOLOGIC\Export\Data\Item;
use FinSearchUnified\Bundle\ProductNumberSearch;
use FinSearchUnified\Components\ProductStream\Repository;
use FinSearchUnified\ShopwareProcess;
use FinSearchUnified\Tests\Helper\Utility;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use Shopware\Bundle\SearchBundle\ProductNumberSearchResult;
use Shopware\Bundle\StoreFrontBundle\Struct\BaseProduct;
use Shopware\Components\Api\Manager;
use Shopware\Components\Api\Resource\Category as CategoryResource;
use Shopware\Models\Category\Category;
use Shopware\Models\ProductStream\ProductStream;
use Zend_Cache_Core;

class ShopwareProcessTest extends TestCase
{
    protected static $ensureLoadedPlugins = [
        'FinSearchUnified' => [
            'ShopKey' => 'ABCDABCDABCDABCDABCDABCDABCDABCD'
        ],
    ];

    protected function tearDown()
    {
        parent::tearDown();
        Utility::sResetArticles();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function testProductStreams()
    {
        $categoryA = new Category();
        $categoryA->setId(1);
        $categoryA->setActive(true);

        $categoryB = new Category();
        $categoryB->setId(2);
        $categoryB->setActive(true);
        $categoryB->setStream(new ProductStream());

        $categoryC = new Category();
        $categoryC->setId(3);
        $categoryC->setActive(false);
        $categoryC->setStream(new ProductStream());
        $categoryC->setParent($categoryB);

        $categoryD = new Category();
        $categoryD->setId(4);
        $categoryD->setActive(true);
        $categoryD->setStream(new ProductStream());
        $categoryD->setParent($categoryB);

        $childCategories = new ArrayCollection(
            [$categoryC, $categoryD]
        );

        $children = new PersistentCollection(
            Shopware()->Models(),
            Shopware()->Models()->getClassMetadata(Category::class),
            $childCategories
        );

        $categoryB->setChildren($children);

        $categories = new ArrayCollection(
            [$categoryA, $categoryB]
        );

        $parameters = new PersistentCollection(
            Shopware()->Models(),
            Shopware()->Models()->getClassMetadata(Category::class),
            $categories
        );

        $products = [];

        for ($i = 0; $i < 10; $i++) {
            $product = new BaseProduct(rand(), rand(), uniqid());
            $products[] = $product;
        }

        $results = new ProductNumberSearchResult($products, 10, []);

        $mockProductNumberSearch = $this->createMock(ProductNumberSearch::class);
        $mockProductNumberSearch->expects($this->exactly(2))->method('search')->willReturn($results);

        /** @var ProductNumberSearch $mockProductNumberSearch */
        $shopwareProcess = new ShopwareProcess(
            Shopware()->Container()->get('cache'),
            Shopware()->Container()->get('shopware_product_stream.repository'),
            Shopware()->Container()->get('shopware_storefront.context_service'),
            $mockProductNumberSearch
        );

        $reflector = new ReflectionObject($shopwareProcess);
        $method = $reflector->getMethod('parseProductStreams');
        $method->setAccessible(true);
        $articles = $method->invoke($shopwareProcess, $parameters);

        $this->assertEmpty(
            array_diff_key(array_column($products, 'id'), $articles),
            'Expected returned articles to only contain the same products that were created'
        );

        foreach ($articles as $articleID => $categories) {
            $this->assertCount(2, $categories, 'Expected not more than two categories to be assigned');

            $this->assertSame(
                $categoryD,
                $categories[0],
                'Expected categoryD to be assigned to the article'
            );
            $this->assertSame(
                $categoryB,
                $categories[1],
                'Expected parent of categoryD to be assigned to the article'
            );
        }
    }

    public function testProductWithoutCategoryHasProductStreamCategoryAssigned()
    {
        $productStreamArticles = [];

        // Assign an inactive category to the product so it does not get exported
        $override['categories'] = [['id' => 75]];

        $article = Utility::createTestProduct(1, true, $override);
        $product = new BaseProduct(
            $article->getId(),
            $article->getMainDetail()->getId(),
            $article->getMainDetail()->getNumber()
        );

        /** @var CategoryResource $resource */
        $resource = Manager::getResource('Category');

        /** @var Category $category */
        $category = $resource->getRepository()->findOneBy(['name' => 'Genusswelten']);

        $productStreamArticles[$product->getId()][] = $category;

        $cacheMock = $this->createMock(Zend_Cache_Core::class);
        $cacheMock->expects($this->exactly(2))->method('load')->willReturn($productStreamArticles);

        Shopware()->Container()->set('cache', $cacheMock);

        /** @var Repository $mockRepository */
        /** @var ProductNumberSearch $mockProductNumberSearch */
        $shopwareProcess = new ShopwareProcess(
            $cacheMock,
            Shopware()->Container()->get('shopware_product_stream.repository'),
            Shopware()->Container()->get('shopware_storefront.context_service'),
            Shopware()->Container()->get('shopware_search.product_number_search')
        );

        $shopwareProcess->setShopKey('ABCDABCDABCDABCDABCDABCDABCDABCD');
        $xml = $shopwareProcess->getAllProductsAsXmlArray();

        // Make sure that the product is exported
        $this->assertCount(1, $xml->items);

        $xmlItem = current($xml->items);

        $reflector = new ReflectionClass(Item::class);
        $attributes = $reflector->getProperty('attributes');
        $attributes->setAccessible(true);
        $values = $attributes->getValue($xmlItem);

        $this->assertArrayHasKey('cat', $values);

        /** @var Attribute $categoryAttribute */
        $categoryAttribute = $values['cat'];
        // Make sure that the product stream category is assigned to the exported product
        $this->assertContains('Genusswelten', $categoryAttribute->getValues());

        // Reset cache only for this test
        Shopware()->Container()->reset('cache');
        Shopware()->Container()->load('cache');
    }
}
