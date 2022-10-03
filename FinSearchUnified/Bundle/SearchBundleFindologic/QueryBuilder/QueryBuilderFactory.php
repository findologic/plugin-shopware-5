<?php

namespace FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;

use Exception;
use FINDOLOGIC\Api\Definitions\QueryParameter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\CategoryConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\PriceConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\ProductAttributeConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\SearchTermConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\SimpleConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandler\ManufacturerConditionHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\ConditionHandlerInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\PopularitySortingHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\PriceSortingHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\ProductNameSortingHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandler\ReleaseDateSortingHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\SortingHandlerInterface;
use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Condition\ManufacturerCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\SortingInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Shopware_Components_Config;
use FINDOLOGIC\Api\Client;
use FINDOLOGIC\Api\Config;

class QueryBuilderFactory implements QueryBuilderFactoryInterface
{
    /**
     * @var InstallerService
     */
    private $installerService;

    /**
     * @var Shopware_Components_Config
     */
    private $config;

    /**
     * @var SortingHandlerInterface[]
     */
    private $sortingHandlers;

    /**
     * @var ConditionHandlerInterface[]
     */
    private $conditionHandlers;

    /**
     * @var Client;
     */
    private $apiClient;

    public function __construct(
        InstallerService $installerService,
        Shopware_Components_Config $config
    ) {
        $this->installerService = $installerService;
        $this->config = $config;

        $this->sortingHandlers = $this->registerSortingHandlers();
        $this->conditionHandlers = $this->registerConditionHandlers();
        $this->apiClient = $this->createClient();
    }

    /**
     * @return Client
     */
    private function createClient()
    {
        $apiConfig = new Config();
        $apiConfig->setServiceId($this->config->offsetGet('ShopKey'));

        return new Client($apiConfig);
    }

    /**
     * @return SortingHandlerInterface[]
     */
    protected function registerSortingHandlers()
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
        $conditionHandlers[] = new ManufacturerConditionHandler();

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
     * @param QueryBuilder $query
     */
    private function addPushAttribs(QueryBuilder $query)
    {
        $pushAttribs = Shopware()->Front()->Request()->getParam(QueryParameter::PUSH_ATTRIB);

        foreach ($pushAttribs as $pushAttribFilterName => $pushAttribFilter) {
            foreach ($pushAttribFilter as $pushAttribValue => $weight) {
                $query->addPushAttrib($pushAttribFilterName, $pushAttribValue, floatval($weight));
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
        $this->addPushAttribs($query);

        return $query;
    }

    /**
     * @return QueryBuilder
     */
    public function createQueryBuilder()
    {
        $isSearchPage = Shopware()->Session()->offsetGet('isSearchPage');

        if ($isSearchPage) {
            $querybuilder = new SearchQueryBuilder(
                $this->installerService,
                $this->config,
                $this->apiClient
            );
        } else {
            $querybuilder = new NavigationQueryBuilder(
                $this->installerService,
                $this->config,
                $this->apiClient
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
        $query->addUserGroup($context->getCurrentCustomerGroup()->getKey());
        $query->setMaxResults(0);

        $condition = null;

        if ($query instanceof SearchQueryBuilder) {
            $condition = $criteria->getCondition('search');
        } elseif ($query instanceof NavigationQueryBuilder) {
            if ($criteria->getConditions()[0] instanceof ManufacturerCondition) {
                $condition = $criteria->getCondition('manufacturer');
            } elseif ($criteria->getConditions()[0] instanceof CategoryCondition) {
                $condition = $criteria->getCondition('category');
            }
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
