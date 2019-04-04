<?php

namespace FinSearchUnified\Tests;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use FinSearchUnified\Bundle\ProductNumberSearch;
use FinSearchUnified\Components\ProductStream\Repository;
use FinSearchUnified\ShopwareProcess;
use ReflectionObject;
use Shopware\Bundle\SearchBundle\ProductNumberSearchResult;
use Shopware\Bundle\StoreFrontBundle\Struct\BaseProduct;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware\Models\Category\Category;
use Shopware\Models\ProductStream\ProductStream;

class ShopwareProcessTest extends TestCase
{
    /**
     * @throws \ReflectionException
     * @throws \Exception
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

        $result = new \stdClass();
        $result->totalCount = 10;
        $result->products = [];

        for ($i = 0; $i < 10; $i++) {
            $product = new BaseProduct(rand(), rand(), uniqid());
            $result->products[] = $product;
        }

        $mockSearchResult = $this->createMock(ProductNumberSearchResult::class);
        $mockSearchResult->method('getProducts')->willReturn($result->products);

        $contextService = Shopware()->Container()->get('shopware_storefront.context_service');

        $mockProductNumberSearch = $this->createMock(ProductNumberSearch::class);
        $mockProductNumberSearch->expects($this->atLeastOnce())->method('search')->willReturn($mockSearchResult);

        /** @var Repository $mockRepository */
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

        foreach ($articles as $article) {
            foreach ($article as $category) {
                $this->assertThat($category, $this->logicalOr(
                    $this->attributeEqualTo('id', $categoryB->getId()),
                    $this->attributeEqualTo('id', $categoryD->getId())
                ));
            }
        }
    }
}
