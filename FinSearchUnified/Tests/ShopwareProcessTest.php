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

        $children = new ArrayCollection(
            [$categoryC, $categoryD]
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

        for ($i = 1; $i <= 10; $i++) {
            $categoryId = rand(1, 4);

            if (!isset($sArticles['sArticles'][$categoryId])) {
                $sArticles['sArticles'][$categoryId] = [];
            }

            $sArticles['sArticles'][$categoryId][] = ['articleID' => $i + 1];
        }

        $result = [
            [$categoryA->getId(), $sArticles['sArticle'][$categoryA->getId()]],
            [$categoryB->getId(), $sArticles['sArticle'][$categoryB->getId()]],
        ];

        $mockModules = $this->createMock(Shopware_Components_Modules::class);

        $mockArticlesModule = $this->createMock(sArticles::class);
        $mockArticlesModule->method('sGetArticlesByCategory')->willReturnMap($result);

        $mockModules->method('getModule')->with('Articles')->willReturn($mockArticlesModule);

        Shopware()->Container()->set('modules', $mockModules);

        /** @var Repository $mockRepository */
        $shopwareProcess = new ShopwareProcess(
            Shopware()->Container()->get('cache'),
            $mockRepository
        );

        $reflector = new ReflectionObject($shopwareProcess);
        $method = $reflector->getMethod('parseProductStreams');
        $method->setAccessible(true);
        $method->invoke($shopwareProcess, $parameters);
    }
}
