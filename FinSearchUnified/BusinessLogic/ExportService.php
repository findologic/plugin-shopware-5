<?php

namespace FinSearchUnified\BusinessLogic;

use Doctrine\ORM\EntityNotFoundException;
use Exception;
use FINDOLOGIC\Export\Data\Item;
use FINDOLOGIC\Export\Helpers\EmptyValueNotAllowedException;
use Shopware\Models\Article\Article;
use Shopware\Models\Article\Repository as ArticleRepository;
use Shopware\Models\Category\Category;
use Shopware\Models\Customer\Customer;
use Shopware\Models\Customer\Group;
use Shopware\Models\Customer\Repository as CustomerRepository;

class ExportService
{
    /**
     * @var CustomerRepository
     */
    protected $customerRepository;

    /**
     * @var ArticleRepository
     */
    protected $articleRepository;

    /**
     * @var FindologicArticleFactory
     */
    protected $findologicArticleFactory;

    /**
     * @var string
     */
    public $shopkey;

    /**
     * @var Category
     */
    protected $baseCategory;

    /**
     * @var Group[]
     */
    protected $allUserGroups;

    /**
     * @var string[][]
     */
    protected $errors = [];

    public function __construct($shopkey, Category $baseCategory)
    {
        $this->customerRepository = Shopware()->Container()->get('models')->getRepository(Customer::class);
        $this->articleRepository = Shopware()->Container()->get('models')->getRepository(Article::class);
        $this->findologicArticleFactory = Shopware()->Container()->get('fin_search_unified.article_model_factory');
        $this->shopkey = $shopkey;
        $this->baseCategory = $baseCategory;
        $this->allUserGroups = $this->customerRepository->getCustomerGroupsQuery()->getResult();
    }

    /**
     * @return int
     */
    public function fetchTotalProductCount()
    {
        $countQuery = $this->articleRepository->createQueryBuilder('articles')
            ->select('count(articles.id)')
            ->where('articles.active = :active')
            ->orderBy('articles.id')
            ->setParameter('active', true);

        return intval($countQuery->getQuery()->getScalarResult()[0][1]);
    }

    /**
     * @param int $start
     * @param int $count
     *
     * @return Article[]
     */
    public function fetchAllProducts($start, $count)
    {
        $articlesQuery = $this->articleRepository->createQueryBuilder('articles')
            ->select('articles')
            ->where('articles.active = :active')
            ->orderBy('articles.id')
            ->setParameter('active', true);

        if ($count > 0) {
            $articlesQuery->setMaxResults($count)->setFirstResult($start);
        }

        return $articlesQuery->getQuery()->execute();
    }

    /**
     * @param string $productId
     *
     * @return Article[]
     */
    public function fetchProductsById($productId)
    {
         $articlesQuery = $this->articleRepository->createQueryBuilder('articles')
            ->select('articles')
            ->where('articles.id = :productId')
            ->orWhere('articles.supplier = :productId')
            ->setParameter('productId', $productId);

        $shopwareArticles = $articlesQuery->getQuery()->execute();

        if (count($shopwareArticles) === 0) {
            $this->errors['general'][] = 'No article found with given ID';
            return null;
        }

        return $shopwareArticles;
    }

    /**
     * @param array $shopwareArticles
     * @param false $logErrors
     *
     * @return Item[]
     */
    public function generateFindologicProducts($shopwareArticles, $logErrors = false)
    {
        $findologicArticles = [];

        /** @var Article $article */
        foreach ($shopwareArticles as $article) {
            try {
                $findologicArticle = self::getFindologicArticle($article, $logErrors);

                if ($findologicArticle) {
                    $findologicArticles[] = $findologicArticle;
                }
            } catch (Exception $e) {
                Shopware()->Container()->get('pluginlogger')->info(
                    sprintf(
                        'Error while exporting the product with number "%s"' .
                        'If you see this message in your logs, please report this as a bug' .
                        'Error message: %s',
                        $article->getMainDetail()->getNumber(),
                        $e->getMessage()
                    )
                );
            }
        }

        return $findologicArticles;
    }

    /**
     * @param Article $shopwareArticle
     * @param bool $logErrors
     * @return null|Item
     * @throws Exception
     */
    public function getFindologicArticle($shopwareArticle, $logErrors)
    {
        $articleId = $shopwareArticle->getId();
        $inactiveCatCount = 0;
        $totalCatCount = 0;

        /** @var Category $category */
        foreach ($shopwareArticle->getCategories() as $category) {
            if (!$category->isChildOf($this->baseCategory)) {
                continue;
            }

            if (!$category->getActive()) {
                $inactiveCatCount++;
            }

            $totalCatCount++;
        }

        if (!$shopwareArticle->getActive()) {
            if ($logErrors) {
                $this->errors[$articleId][] = 'Product is not active';
            }
            return null;
        }

        if ($totalCatCount === $inactiveCatCount) {
            if ($logErrors) {
                $this->errors[$articleId][] = sprintf('All configured categories are inactive');
            }
            return null;
        }

        try {
            if ($shopwareArticle->getMainDetail() === null || !$shopwareArticle->getMainDetail()->getActive()) {
                if ($logErrors) {
                    $this->errors[$articleId][] = 'Main Detail is not active or not available';
                }
                return null;
            }
        } catch (EntityNotFoundException $exception) {
            if ($logErrors) {
                $this->errors[$articleId][] = sprintf('EntityNotFoundException: %s', $exception->getMessage());
            }
            return null;
        }

        try {
            $findologicArticle = $this->findologicArticleFactory->create(
                $shopwareArticle,
                $this->shopkey,
                $this->allUserGroups,
                [],
                $this->baseCategory
            );

            if ($findologicArticle->shouldBeExported) {
                return $findologicArticle->getXmlRepresentation();
            } elseif ($logErrors) {
                $this->errors[$articleId][] = 'shouldBeExported is false';
            }
        } catch (EmptyValueNotAllowedException $e) {
            $this->errors[$articleId][] = 'EmptyValueNotAllowedException';

            Shopware()->Container()->get('pluginlogger')->info(
                sprintf(
                    'Product with number "%s" could not be exported. ' .
                    'It appears to have empty values assigned to it. ' .
                    'If you see this message in your logs, please report this as a bug',
                    $shopwareArticle->getMainDetail()->getNumber()
                )
            );
        }

        return null;
    }

    /**
     * @return array[]
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
