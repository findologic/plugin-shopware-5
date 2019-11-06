<?php

namespace FinSearchUnified\Bundle;

use Exception;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\CategoryFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\ColorFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\ImageFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\RangeFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\FacetHandler\TextFacetHandler;
use FinSearchUnified\Bundle\SearchBundleFindologic\PartialFacetHandlerInterface;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\ProductNumberSearchInterface;
use Shopware\Bundle\SearchBundle\ProductNumberSearchResult;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use SimpleXMLElement;

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
     * @param ProductNumberSearchInterface $service
     * @param QueryBuilderFactoryInterface $queryBuilderFactory
     */
    public function __construct(
        ProductNumberSearchInterface $service,
        QueryBuilderFactoryInterface $queryBuilderFactory
    ) {
        $this->originalService = $service;
        $this->queryBuilderFactory = $queryBuilderFactory;
        $this->facetHandlers = self::registerFacetHandlers();
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
        // Shopware sets fetchCount to false when the search is used for internal purposes, which we don't care about.
        // Checking its value is the only way to tell if we should actually perform the search.
        $fetchCount = $criteria->fetchCount();

        $useShopSearch = StaticHelper::useShopSearch();

        if (!$fetchCount || $useShopSearch) {
            return $this->originalService->search($criteria, $context);
        }

        /** @var QueryBuilder $query */
        $query = $this->queryBuilderFactory->createProductQuery($criteria, $context);
        $response = $query->execute();

        if (empty($response)) {
            self::setFallbackFlag(1);
            $searchResult = $this->originalService->search($criteria, $context);
        } else {
            self::setFallbackFlag(0);
            $xmlResponse = StaticHelper::getXmlFromResponse($response);
            self::redirectOnLandingpage($xmlResponse);
            StaticHelper::setPromotion($xmlResponse);
            StaticHelper::setSmartDidYouMean($xmlResponse);

            $totalResults = (int)$xmlResponse->results->count;
            $foundProducts = StaticHelper::getProductsFromXml($xmlResponse);
            $searchResult = StaticHelper::getShopwareArticlesFromFindologicId($foundProducts);
            $facets = $this->createFacets($criteria, $xmlResponse->filters->filter);

            $searchResult = new ProductNumberSearchResult($searchResult, $totalResults, $facets);
        }

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

        if ($hasLandingpage != null) {
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
     * @param SimpleXMLElement $filters
     *
     * @return array
     */
    protected function createFacets(Criteria $criteria, SimpleXMLElement $filters)
    {
        $facets = [];

        foreach ($criteria->getFacets() as $criteriaFacet) {
            $field = $criteriaFacet->getField();
            $filter = $filters->xpath(sprintf('//name[.="%s"]/parent::*', $field));
            if (empty($filter)) {
                continue;
            }
            $handler = $this->getFacetHandler($filter[0]);
            if ($handler === null) {
                continue;
            }
            $partialFacet = $handler->generatePartialFacet($criteriaFacet, $criteria, $filter[0]);
            if ($partialFacet === null) {
                continue;
            }
            $facets[] = $partialFacet;
        }

        return $facets;
    }
}
