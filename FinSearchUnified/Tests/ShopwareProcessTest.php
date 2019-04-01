<?php

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use FinSearchUnified\Components\ProductStream\Repository;
use FinSearchUnified\ShopwareProcess;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Components\Test\Plugin\TestCase;
use Shopware\Models\Category\Category;
use Shopware\Models\ProductStream\ProductStream;

class ShopwareProcessTest extends TestCase
{
    protected function tearDown()
    {
        parent::tearDown();

        Shopware()->Container()->reset('modules');
        Shopware()->Container()->load('modules');
    }

    /**
     * @throws ReflectionException
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
        $mockRepository->method('prepareCriteria')->willReturnSelf();

        $criteria = new Criteria();
        $criteria
            ->limit(200)
            ->offset(0);

        $sArticles['sArticles'] = [];
        $sArticles['sNumberArticles'] = 10;

        for ($i = 0; $i < 10; $i++) {
            $sArticles['sArticles'][] = ['articleID' => $i + 1];
        }

        $criteria = new Criteria();
        $criteria
            ->limit(200)
            ->offset(0);

        $mockModules = $this->createMock(Shopware_Components_Modules::class);

        $mockArticlesModule = $this->createMock(sArticles::class);
        $mockArticlesModule->expects($this->atLeastOnce())->method('sGetArticlesByCategory')->willReturn($sArticles);

        $mockModules->method('Articles')->willReturn($mockArticlesModule);

        Shopware()->Container()->set('modules', $mockModules);

        /** @var Repository $mockRepository */
        $shopwareProcess = new ShopwareProcess(
            Shopware()->Container()->get('cache'),
            $mockRepository
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
