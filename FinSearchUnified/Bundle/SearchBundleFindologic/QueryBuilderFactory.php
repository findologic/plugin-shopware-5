<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\CategoryConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\PriceConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\ProductAttributeConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\SearchTermConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\SimpleConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\PopularitySortingHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\PriceSortingHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\ProductNameSortingHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\ReleaseDateSortingHandler;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Shopware\Components\HttpClient\HttpClientInterface;
use Shopware_Components_Config;

class QueryBuilderFactory implements QueryBuilderFactoryInterface
{
    /**
     * @var HttpClientInterface
     */
    protected $httpClient;

    /**
     * @var InstallerService
     */
    protected $installerService;

    /**
     * @var Shopware_Components_Config
     */
    protected $config;

    /**
     * @var array
     */
    private $sortingHandlers;

    /**
     * @var array
     */
    private $conditionHandlers;

    /**
     * QueryBuilderFactory constructor.
     *
     * @param HttpClientInterface $httpClient
     * @param InstallerService $installerService
     * @param Shopware_Components_Config $config
     */
    public function __construct(
        HttpClientInterface $httpClient,
        InstallerService $installerService,
        Shopware_Components_Config $config
    ) {
        $this->httpClient = $httpClient;
        $this->installerService = $installerService;
        $this->config = $config;

        $this->sortingHandlers = $this->registerSortingHandlers();
        $this->conditionHandlers = $this->registerConditionHandlers();
    }

    /**
     * @return SortingHandlerInterface[]
     */
    private function registerSortingHandlers()
    {
        $sortingHandlers = [];

        $sortingHandlers[] = new PopularitySortingHandler();
        $sortingHandlers[] = new PriceSortingHandler();
        $sortingHandlers[] = new ProductNameSortingHandler();
        $sortingHandlers[] = new ReleaseDateSortingHandler();

        return $sortingHandlers;
    }

    /**
     * @return ConditionHandlerInterface[]
     */
    private function registerConditionHandlers()
    {
        $conditionHandlers = [];

        $conditionHandlers[] = new CategoryConditionHandler();
        $conditionHandlers[] = new PriceConditionHandler();
        $conditionHandlers[] = new ProductAttributeConditionHandler();
        $conditionHandlers[] = new SearchTermConditionHandler();
        $conditionHandlers[] = new SimpleConditionHandler();

        return $conditionHandlers;
    }

    /**
     * @param ConditionInterface $condition
     *
     * @return ConditionHandlerInterface|null
     */
    private function getConditionHandler(ConditionInterface $condition)
    {
        foreach ($this->conditionHandlers as $handler) {
            if ($handler->supportsCondition($condition)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * @param Criteria $criteria
     * @param QueryBuilder $query
     * @param ShopContextInterface $context
     */
    private function addConditions(Criteria $criteria, QueryBuilder $query, ShopContextInterface $context)
    {
        foreach ($criteria->getConditions() as $condition) {
            $handler = $this->getConditionHandler($condition);
            if ($handler !== null) {
                $handler->generateCondition($condition, $query, $context);
            }
        }
    }

    /**
     * @param SortingInterface $sorting
     *
     * @return SortingHandlerInterface|null
     */
    private function getSortingHandler(SortingInterface $sorting)
    {
        foreach ($this->sortingHandlers as $handler) {
            if ($handler->supportsSorting($sorting)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * @param Criteria $criteria
     * @param QueryBuilder $query
     * @param ShopContextInterface $context
     */
    private function addSorting(Criteria $criteria, QueryBuilder $query, ShopContextInterface $context)
    {
        foreach ($criteria->getSortings() as $sorting) {
            $handler = $this->getSortingHandler($sorting);
            if ($handler !== null) {
                $handler->generateSorting($sorting, $query, $context);
            }
        }
    }

    /**
     * Creates the product number search query for the provided
     * criteria and context.
     * Adds the sortings and conditions of the provided criteria.
     *
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return QueryBuilder
     * @throws Exception
     */
    public function createQueryWithSorting(Criteria $criteria, ShopContextInterface $context)
    {
        $query = $this->createQuery($criteria, $context);

        $this->addSorting($criteria, $query, $context);

        return $query;
    }

    /**
     * Generates the product selection query of the product number search
     *
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return QueryBuilder
     * @throws Exception
     */
    public function createProductQuery(Criteria $criteria, ShopContextInterface $context)
    {
        $query = $this->createQueryWithSorting($criteria, $context);
        $query->setFirstResult($criteria->getOffset());

        if ($criteria->getOffset() === 0 && $criteria->getLimit() === 1) {
            $limit = 0;
        } else {
            $limit = $criteria->getLimit();
        }

        $query->setMaxResults($limit);

        return $query;
    }

    /**
     * Creates the product number search query for the provided
     * criteria and context.
     * Adds only the conditions of the provided criteria.
     *
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return QueryBuilder
     * @throws Exception
     */
    public function createQuery(Criteria $criteria, ShopContextInterface $context)
    {
        $query = $this->createQueryBuilder();
        $query->addUserGroup($context->getCurrentCustomerGroup()->getKey());
        $this->addConditions($criteria, $query, $context);

        return $query;
    }

    /**
     * @return QueryBuilder
     * @throws Exception
     */
    public function createQueryBuilder()
    {
        $isSearchPage = Shopware()->Session()->offsetGet('isSearchPage');

        if ($isSearchPage) {
            $querybuilder = new SearchQueryBuilder(
                $this->httpClient,
                $this->installerService,
                $this->config
            );
        } else {
            $querybuilder = new NavigationQueryBuilder(
                $this->httpClient,
                $this->installerService,
                $this->config
            );
        }

        return $querybuilder;
    }

    /**
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return QueryBuilder
     * @throws Exception
     */
    public function createSearchNavigationQueryWithoutAdditionalFilters(
        Criteria $criteria,
        ShopContextInterface $context
    ) {
        $query = $this->createQueryBuilder();
        $condition = null;

        if ($query instanceof SearchQueryBuilder) {
            $condition = $criteria->getCondition('search');
        }
        if ($query instanceof NavigationQueryBuilder) {
            $condition = $criteria->getCondition('category');
        }

        if ($condition !== null) {
            $handler = $this->getConditionHandler($condition);
            if ($handler !== null) {
                $handler->generateCondition($condition, $query, $context);
            }
        }

        return $query;
    }
}
