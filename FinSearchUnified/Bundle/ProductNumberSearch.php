<?php

namespace FinSearchUnified\Bundle;

use Exception;
use FinSearchUnified\Bundle\SearchBundle\Condition\ProductAttributeCondition;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\SearchBundle;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\ProductNumberSearchInterface;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class ProductNumberSearch implements ProductNumberSearchInterface
{
    /**
     * @var ProductNumberSearchInterface
     */
    protected $originalService;

    /**
     * @var array
     */
    protected $facets = [];

    /**
     * @var QueryBuilderFactoryInterface
     */
    protected $queryBuilderFactory;

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
    }

    /**
     * Creates a product search result for the passed criteria object.
     * The criteria object contains different core conditions and plugin conditions.
     * This conditions has to be handled over the different condition handlers
     *
     * @param Criteria $criteria
     * @param ShopContextInterface $context
     *
     * @return SearchBundle\ProductNumberSearchResult
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

            $this->facets = StaticHelper::getFacetResultsFromXml($xmlResponse);

            $this->setSelectedFacets($criteria);
            $criteria->resetConditions();

            $totalResults = (int)$xmlResponse->results->count;
            $foundProducts = StaticHelper::getProductsFromXml($xmlResponse);
            $searchResult = StaticHelper::getShopwareArticlesFromFindologicId($foundProducts);

            $searchResult = new SearchBundle\ProductNumberSearchResult($searchResult, $totalResults, $this->facets);
        }

        return $searchResult;
    }

    /**
     * Checks if a landing page is present in the response and in that case, performs a redirect.
     *
     * @param \SimpleXMLElement $xmlResponse
     */
    protected static function redirectOnLandingpage(\SimpleXMLElement $xmlResponse)
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
     * Marks the selected facets as such and prevents duplicates.
     *
     * @param Criteria $criteria
     */
    protected function setSelectedFacets(Criteria $criteria)
    {
        foreach ($criteria->getConditions() as $condition) {
            if (($condition instanceof ProductAttributeCondition) === false) {
                continue;
            }

            /** @var SearchBundle\Facet\ProductAttributeFacet $currentFacet */
            $currentFacet = $criteria->getFacet($condition->getName());

            if (($currentFacet instanceof SearchBundle\FacetInterface) === false) {
                continue;
            }

            $tempFacet = StaticHelper::createSelectedFacet(
                $currentFacet->getFormFieldName(),
                $currentFacet->getLabel(),
                $condition->getValue()
            );

            if (count($tempFacet->getValues()) === 0) {
                continue;
            }

            $foundFacet = StaticHelper::arrayHasFacet($this->facets, $currentFacet->getLabel());

            if ($foundFacet === false) {
                $this->facets[] = $tempFacet;
            }
        }
    }
}
