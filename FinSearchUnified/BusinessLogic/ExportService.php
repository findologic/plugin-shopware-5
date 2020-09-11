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
    const GENERAL_ERRORS_KEY = 'general';
    const PRODUCTS_ERRORS_KEY = 'products';

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
     * @var ExportErrorInformation[][]
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

        $this->errors[self::GENERAL_ERRORS_KEY] = [];
        $this->errors[self::PRODUCTS_ERRORS_KEY] = [];
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
            $this->errors[self::GENERAL_ERRORS_KEY][] = sprintf('No article found with ID %s', $productId);
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
     *
     * @return null|Item
     * @throws Exception
     */
    public function getFindologicArticle($shopwareArticle, $logErrors)
    {
        $errorInformation = new ExportErrorInformation($shopwareArticle->getId());
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
                $errorInformation->addError('Product is not active.');
            } else {
                return null;
            }
        }

        if ($totalCatCount === $inactiveCatCount) {
            if ($logErrors) {
                $errorInformation->addError(sprintf('All configured categories are inactive.'));
            } else {
                return null;
            }
        }

        try {
            if ($shopwareArticle->getMainDetail() === null || !$shopwareArticle->getMainDetail()->getActive()) {
                if ($logErrors) {
                    $errorInformation->addError('Main Detail is not active or not available.');
                } else {
                    return null;
                }
            }
        } catch (EntityNotFoundException $exception) {
            if ($logErrors) {
                $errorInformation->addError(sprintf('EntityNotFoundException: %s', $exception->getMessage()));
            } else {
                return null;
            }
        }

        try {
            $findologicArticle = $this->findologicArticleFactory->create(
                $shopwareArticle,
                $this->shopkey,
                $this->allUserGroups,
                [],
                $this->baseCategory
            );

            if ($findologicArticle->shouldBeExported && count($errorInformation->getErrors()) === 0) {
                return $findologicArticle->getXmlRepresentation();
            } elseif ($logErrors) {
                $errorInformation->addError('shouldBeExported is false.');
            }
        } catch (EmptyValueNotAllowedException $e) {
            $errorInformation->addError('EmptyValueNotAllowedException');

            Shopware()->Container()->get('pluginlogger')->info(
                sprintf(
                    'Product with number "%s" could not be exported. ' .
                    'It appears to have empty values assigned to it. ' .
                    'If you see this message in your logs, please report this as a bug',
                    $shopwareArticle->getMainDetail()->getNumber()
                )
            );
        }

        $this->errors[self::PRODUCTS_ERRORS_KEY][] = $errorInformation;
        return null;
    }

    /**
     * @return array[]
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return count($this->errors[self::GENERAL_ERRORS_KEY]) || count($this->errors[self::PRODUCTS_ERRORS_KEY]);
    }
}
