<?php

namespace FinSearchUnified\Bundle;

use Enlight_Controller_Request_Request;
use Exception;
use FINDOLOGIC\Api\Exceptions\ServiceNotAliveException;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\CategoryFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\ColorFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\ImageFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\RangeFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\TextFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\QueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Filter\BaseFilter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\ResponseParser;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\Filter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\LabelTextFilter;
use FinSearchUnified\Bundle\SearchBundleFindologic\ResponseParser\Xml21\Filter\RangeSliderFilter;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\SearchBundle\Condition\PriceCondition;
use Shopware\Bundle\SearchBundle\ConditionInterface;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\Facet\ProductAttributeFacet;
use Shopware\Bundle\SearchBundle\FacetInterface;
use Shopware\Bundle\SearchBundle\ProductNumberSearchInterface;
use Shopware\Bundle\SearchBundle\ProductNumberSearchResult;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Zend_Cache_Core;
use Zend_Cache_Exception;

class ProductNumberSearch implements ProductNumberSearchInterface
{
    /**
     * @var ProductNumberSearchInterface
     */
    protected $originalService;

    /**
     * @var QueryBuilderFactoryInterface
     */
    protected $queryBuilderFactory;

    /**
     * @var PartialFacetHandlerInterface[]
     */
    private $facetHandlers;

    /**
     * @var Zend_Cache_Core
     */
    private $cache;

    /**
     * @param ProductNumberSearchInterface $service
     * @param QueryBuilderFactoryInterface $queryBuilderFactory
     * @param Zend_Cache_Core $cache
     */
    public function __construct(
        ProductNumberSearchInterface $service,
        QueryBuilderFactoryInterface $queryBuilderFactory,
        Zend_Cache_Core $cache
    ) {
        $this->originalService = $service;
        $this->queryBuilderFactory = $queryBuilderFactory;
        $this->facetHandlers = self::registerFacetHandlers();
        $this->cache = $cache;
    }

    /**
     * Creates a product search result for the passed criteria object.
     * The criteria object contains different core conditions and plugin conditions.
     * This conditions has to be handled over the different condition handlers
     *
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return ProductNumberSearchResult
     * @throws Exception
     */
    public function search(Criteria $criteria, ShopContextInterface $context)
    {
        $fetchCount = true;
        if (method_exists($criteria, 'fetchCount')) {
            // Shopware sets fetchCount to false when the search is used for internal purposes, which we don't care
            // about. Checking its value is the only way to tell if we should actually perform the search.
            // Unfortunately this method only exists in Shopware >= 5.2.14.
            $fetchCount = $criteria->fetchCount();
        }

        $useShopSearch = StaticHelper::useShopSearch();

        if (!$fetchCount || $useShopSearch) {
            return $this->originalService->search($criteria, $context);
        }

        /** @var QueryBuilder $query */
        $query = $this->queryBuilderFactory->createProductQuery($criteria, $context);

        try {
            /** @var Xml21Response $response */
            $response = $query->execute();
        } catch (ServiceNotAliveException $e) {
            return $this->originalService->search($criteria, $context);
        }

        if (!$response instanceof Xml21Response) {
            static::setFallbackFlag(1);
            static::setFallbackSearchFlag(1);
            static::redirectToSameUrl();

            return null;
        }
        static::setFallbackFlag(0);
        static::setFallbackSearchFlag(0);

        $responseParser = ResponseParser::getInstance($response);
        $smartDidYouMean = $responseParser->getSmartDidYouMean();

        static::redirectOnLandingpage($responseParser->getLandingPageUri());
        StaticHelper::setPromotion($responseParser->getPromotion());
        StaticHelper::setSmartDidYouMean($smartDidYouMean);
        StaticHelper::setQueryInfoMessage($responseParser->getQueryInfoMessage($smartDidYouMean));

        $totalResults = $response->getResults()->getCount();
        $foundProducts = $responseParser->getProducts();
        $searchResult = StaticHelper::getShopwareArticlesFromFindologicId($foundProducts);

        $facets = $this->createFacets($criteria, $context, $responseParser->getFilters());

        $searchResult = new ProductNumberSearchResult($searchResult, $totalResults, $facets);

        return $searchResult;
    }

    /**
     * Checks if a landing page is present in the response and in that case, performs a redirect.
     *
     * @param string|null $landingPageUri
     */
    protected static function redirectOnLandingpage($landingPageUri)
    {
        if ($landingPageUri !== null) {
            header('Location: ' . $landingPageUri);
            exit();
        }
    }

    /**
     * Sets a browser cookie with the given value.
     *
     * @param bool $status
     */
    protected static function setFallbackFlag($status)
    {
        setcookie('Fallback', $status, 0, '', '', true);
    }

    /**
     * @return PartialFacetHandlerInterface[]
     */
    private static function registerFacetHandlers()
    {
        return [
            new CategoryFacetHandler(),
            new ColorFacetHandler(),
            new ImageFacetHandler(Shopware()->Container()->get('guzzle_http_client_factory')),
            new RangeFacetHandler(),
            new TextFacetHandler()
        ];
    }

    /**
     * @param BaseFilter $filter
     *
     * @return PartialFacetHandlerInterface|null
     */
    private function getFacetHandler(BaseFilter $filter)
    {
        foreach ($this->facetHandlers as $handler) {
            if ($handler->supportsFilter($filter)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     * @param Filter[] $filters
     *
     * @return array
     * @throws Zend_Cache_Exception
     */
    protected function createFacets(Criteria $criteria, ShopContextInterface $context, array $filters = [])
    {
        $facets = [];

        $request = Shopware()->Front()->Request();
        if (!$this->isAjaxRequest($request) && StaticHelper::isProductAndFilterLiveReloadingEnabled()) {
            $response = $this->getResponseWithoutFilters($criteria, $context);
            // Show all filters for the initial requests for ajax reloading. This is required because Shopware
            // needs a general overview of all available filters before disabling other filters that may
            // not be available.
            $filters = ResponseParser::getInstance($response)->getFilters();
            if (StaticHelper::isEmpty($filters)) {
                return [];
            }
        }

        /** @var ProductAttributeFacet $criteriaFacet */
        foreach ($criteria->getFacets() as $criteriaFacet) {
            if (!($criteriaFacet instanceof ProductAttributeFacet)) {
                continue;
            }

            $field = $criteriaFacet->getField();
            $selectedFilter = $selectedFilterByResponse = $this->fetchSelectedFilterByResponse(
                $filters,
                $field
            );

            if (!$selectedFilterByResponse) {
                $selectedFilter = $this->fetchSelectedFilterByUserCondition(
                    $criteria,
                    $criteriaFacet
                );

                if (!$selectedFilter) {
                    continue;
                }
            }

            $handler = $this->getFacetHandler($selectedFilter);
            if ($handler === null) {
                continue;
            }

            $partialFacet = $handler->generatePartialFacet($criteriaFacet, $criteria, $selectedFilter);
            if ($partialFacet === null) {
                continue;
            }

            $facets[] = $partialFacet;
        }

        return $facets;
    }

    private function isAjaxRequest(Enlight_Controller_Request_Request $request)
    {
        $isIndexSearchRequest = (
            $request->getControllerName() === 'search' &&
            $request->getActionName() === 'defaultSearch'
        );
        $isIndexNavigationRequest = (
            $request->getControllerName() === 'listing' &&
            $request->getActionName() === 'index'
        );

        return (
            StaticHelper::isProductAndFilterLiveReloadingEnabled() &&
            !$isIndexSearchRequest &&
            !$isIndexNavigationRequest
        );
    }

    /**
     * @param Filter[] $filters
     * @param string $field
     *
     * @return Filter|null
     */
    private function fetchSelectedFilterByResponse(array $filters, $field)
    {
        foreach ($filters as $filter) {
            if ($filter->getId() === $field) {
                return $filter;
            }
        }

        return null;
    }

    /**
     * @param Criteria $criteria
     * @param FacetInterface $criteriaFacet
     *
     * @return BaseFilter|null
     */
    private function fetchSelectedFilterByUserCondition(
        Criteria $criteria,
        FacetInterface $criteriaFacet
    ) {
        if ($criteria->hasUserCondition($criteriaFacet->getName())) {
            $facetName = $criteriaFacet->getName();
        } elseif ($criteriaFacet->getField() === 'price') {
            $facetName = 'price';
        } else {
            return null;
        }

        $condition = $criteria->getUserCondition($facetName);

        if (!$condition) {
            return null;
        }

        return $this->createSelectedFilter(
            $criteriaFacet,
            $condition
        );
    }

    /**
     * @param FacetInterface $facet
     * @param ConditionInterface $condition
     *
     * @return BaseFilter
     */
    private function createSelectedFilter(FacetInterface $facet, ConditionInterface $condition)
    {
        if ($condition instanceof PriceCondition) {
            $filter = new RangeSliderFilter($condition->getName(), $condition->getName());
            $filter->setMode('range-slider');
            $filter->setActiveMin($condition->getMinPrice());
            $filter->setActiveMax($condition->getMaxPrice());
            $filter->setMin($condition->getMinPrice());
            $filter->setMax($condition->getMaxPrice() ?: PHP_INT_MAX);

            return $filter;
        }

        if ($facet->getMode() === ProductAttributeFacet::MODE_RANGE_RESULT) {
            $values = $condition->getValues();

            $filter = new RangeSliderFilter($condition->getName(), $condition->getField());
            $filter->setMode('range-slider');
            $filter->setActiveMin(isset($values['min']) ? $values['min'] : 0);
            $filter->setActiveMax(isset($values['max']) ? $values['max'] : PHP_INT_MAX);
            $filter->setMin(isset($values['min']) ? $values['min'] : 0);
            $filter->setMax(isset($values['max']) ? $values['max'] : PHP_INT_MAX);

            return $filter;
        }

        return new LabelTextFilter($condition->getName(), $condition->getField());
    }

    /**
     * @param int $flag
     * @param int $mins
     */
    protected static function setFallbackSearchFlag($flag, $mins = 10)
    {
        setcookie('fallback-search', $flag, time() + (60 * $mins), '/', '', false, true);
    }

    protected static function redirectToSameUrl()
    {
        header('Location: ' . Shopware()->Front()->Request()->getRequestUri());
        exit;
    }

    /**
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return Response
     * @throws Zend_Cache_Exception
     */
    private function getResponseWithoutFilters(Criteria $criteria, ShopContextInterface $context)
    {
        $url = md5(Shopware()->Front()->Request()->getRequestUri());
        $cacheId = sprintf('finsearch_%s', $url);

        if ($this->cache->load($cacheId) === false) {
            /** @var QueryBuilder $query */
            $query = $this->queryBuilderFactory->createSearchNavigationQueryWithoutAdditionalFilters(
                $criteria,
                $context
            );
            try {
                /** @var Xml21Response $response */
                $response = $query->execute();
                $this->cache->save($response, $cacheId, ['FINDOLOGIC'], 60 * 60 * 24);
            } catch (ServiceNotAliveException $ignored) {
            }
        } else {
            $response = $this->cache->load($cacheId);
        }

        return $response;
    }
}
