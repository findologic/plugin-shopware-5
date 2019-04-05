<?php

namespace FinSearchUnified\Tests;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use Exception;
use FinSearchUnified\Bundle\ProductNumberSearch;
use FinSearchUnified\Components\ProductStream\Repository;
use FinSearchUnified\ShopwareProcess;
use ReflectionException;
use ReflectionObject;
use Shopware\Bundle\SearchBundle\ProductNumberSearchResult;
use Shopware\Bundle\StoreFrontBundle\Struct\BaseProduct;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware\Models\Category\Category;
use Shopware\Models\ProductStream\ProductStream;

class ShopwareProcessTest extends TestCase
{
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

        $mockRepository = $this->createMock(Repository::class);
        $mockRepository->method('prepareCriteria');

        $products = [];

        for ($i = 0; $i < 10; $i++) {
            $product = new BaseProduct(rand(), rand(), uniqid());
            $products[] = $product;
        }

        $results = new ProductNumberSearchResult($products, 10, []);

        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');

        $mockProductNumberSearch = $this->createMock(ProductNumberSearch::class);
        $mockProductNumberSearch->expects($this->exactly(2))->method('search')->willReturn($results);

        /** @var Repository $mockRepository */
        /** @var ProductNumberSearch $mockProductNumberSearch */
        $shopwareProcess = new ShopwareProcess(
            Shopware()->Container()->get('cache'),
            $mockRepository,
            $contextService,
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
}
