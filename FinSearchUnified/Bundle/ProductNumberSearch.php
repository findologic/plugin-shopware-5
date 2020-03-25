<?php

namespace FinSearchUnified\Bundle;

use Enlight_Controller_Request_Request;
use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\CategoryFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\ColorFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\ImageFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\RangeFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\TextFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
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
use SimpleXMLElement;
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
        $response = $query->execute();

        if (empty($response)) {
            static::setFallbackFlag(1);
            static::setFallbackSearchFlag(1);
            static::redirectToSameUrl();

            return null;
        }
        static::setFallbackFlag(0);
        static::setFallbackSearchFlag(0);
        $xmlResponse = StaticHelper::getXmlFromResponse($response);
        static::redirectOnLandingpage($xmlResponse);
        StaticHelper::setPromotion($xmlResponse);
        StaticHelper::setSmartDidYouMean($xmlResponse);
        StaticHelper::setQueryInfoMessage($xmlResponse);

        $totalResults = (int)$xmlResponse->results->count;
        $foundProducts = StaticHelper::getProductsFromXml($xmlResponse);
        $searchResult = StaticHelper::getShopwareArticlesFromFindologicId($foundProducts);
        $facets = $this->createFacets($criteria, $context, $xmlResponse->filters->filter);

        $searchResult = new ProductNumberSearchResult($searchResult, $totalResults, $facets);

        return $searchResult;
    }

    /**
     * Checks if a landing page is present in the response and in that case, performs a redirect.
     *
     * @param SimpleXMLElement $xmlResponse
     */
    protected static function redirectOnLandingpage(SimpleXMLElement $xmlResponse)
    {
        $hasLandingpage = StaticHelper::checkIfRedirect($xmlResponse);

        if ($hasLandingpage !== null) {
            header('Location: ' . $hasLandingpage);
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
     * @param SimpleXMLElement $filter
     *
     * @return PartialFacetHandlerInterface|null
     */
    private function getFacetHandler(SimpleXMLElement $filter)
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
     * @param SimpleXMLElement|null $filters
     *
     * @return array
     * @throws Zend_Cache_Exception
     */
    protected function createFacets(Criteria $criteria, ShopContextInterface $context, SimpleXMLElement $filters = null)
    {
        $facets = [];

        $request = Shopware()->Front()->Request();
        if (!$this->isAjaxRequest($request) && StaticHelper::isProductAndFilterLiveReloadingEnabled()) {
            $response = $this->getResponseWithoutFilters($criteria, $context);
            $xmlResponse = StaticHelper::getXmlFromResponse($response);

            if (!$xmlResponse->filters->filter) {
                return [];
            }

            // Show all filters for the initial requests for ajax reloading. This is required because Shopware
            // needs a general overview of all available filters before disabling other filters that may
            // not be available.
            $filters = $xmlResponse->filters->filter;
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
     * @param SimpleXMLElement $filters
     * @param string $field
     *
     * @return SimpleXMLElement|null
     */
    private function fetchSelectedFilterByResponse(SimpleXMLElement $filters, $field)
    {
        $selectedFilter = $filters->xpath(sprintf('//name[.="%s"]/parent::*', $field));

        return isset($selectedFilter[0]) ? $selectedFilter[0] : null;
    }

    /**
     * @param Criteria $criteria
     * @param FacetInterface $criteriaFacet
     *
     * @return SimpleXMLElement|null
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
     * @return SimpleXMLElement
     */
    private function createSelectedFilter(FacetInterface $facet, ConditionInterface $condition)
    {
        $data = '<filter />';
        $filter = new SimpleXMLElement($data);

        if ($condition instanceof PriceCondition) {
            $filter->addChild('name', $condition->getName());
            $filter->addChild('type', 'range-slider');
            $attributes = $filter->addChild('attributes');
            $totalRange = $attributes->addChild('totalRange');
            $totalRange->addChild('min', $condition->getMinPrice());
            $totalRange->addChild('max', $condition->getMaxPrice() ?: PHP_INT_MAX);
            $selectedRange = $attributes->addChild('selectedRange');
            $selectedRange->addChild('min', $condition->getMinPrice());
            $selectedRange->addChild('max', $condition->getMaxPrice() ?: PHP_INT_MAX);

            return $filter;
        }

        if ($facet->getMode() === ProductAttributeFacet::MODE_RANGE_RESULT) {
            $values = $condition->getValues();
            $filter->addChild('name', $condition->getField());
            $filter->addChild('type', 'range-slider');
            $attributes = $filter->addChild('attributes');
            $totalRange = $attributes->addChild('totalRange');
            $totalRange->addChild('min', isset($values['min']) ? $values['min'] : 0);
            $totalRange->addChild('max', isset($values['max']) ? $values['max'] : PHP_INT_MAX);
            $selectedRange = $attributes->addChild('selectedRange');
            $selectedRange->addChild('min', isset($values['min']) ? $values['min'] : 0);
            $selectedRange->addChild('max', isset($values['max']) ? $values['max'] : PHP_INT_MAX);

            return $filter;
        }

        $filter->addChild('name', $condition->getField());
        $filter->addChild('type', 'label');

        return $filter;
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
     * @return false|mixed|string|null
     * @throws Zend_Cache_Exception
     */
    private function getResponseWithoutFilters(Criteria $criteria, ShopContextInterface $context)
    {
        $url = md5(Shopware()->Front()->Request()->getRequestUri());
        $cacheId = sprintf('finsearch_%s', $url);

        if ($this->cache->load($cacheId) === false) {
            /** @var QueryBuilder $query */
            $query =
                $this->queryBuilderFactory->createSearchNavigationQueryWithoutAdditionalFilters($criteria, $context);
            $response = $query->execute();
            $this->cache->save($response, $cacheId, ['FINDOLOGIC'], 60 * 60 * 24);
        } else {
            $response = $this->cache->load($cacheId);
        }

        return $response;
    }
}
