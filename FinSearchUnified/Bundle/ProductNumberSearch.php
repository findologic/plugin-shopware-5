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
use Shopware\Bundle\SearchBundle;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\ProductNumberSearchInterface;
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
     * @var array
     */
    protected $facets = [];

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
     * @param PartialFacetHandlerInterface $facetHandlers
     */
    public function __construct(
        ProductNumberSearchInterface $service,
        QueryBuilderFactoryInterface $queryBuilderFactory,
        PartialFacetHandlerInterface $facetHandlers
    ) {
        $this->originalService = $service;
        $this->queryBuilderFactory = $queryBuilderFactory;
        $this->facetHandlers = $this->registerFacetHandlers();
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

            $totalResults = (int)$xmlResponse->results->count;
            $foundProducts = StaticHelper::getProductsFromXml($xmlResponse);
            $searchResult = StaticHelper::getShopwareArticlesFromFindologicId($foundProducts);
            $createFacets = $this->createFacets('','','');
            $searchResult = new SearchBundle\ProductNumberSearchResult($searchResult, $totalResults, $createFacets);
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
            if (($condition instanceof SearchBundle\Condition\ProductAttributeCondition) === false) {
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
    private function registerFacetHandlers(){
        return [
             $CategoryFacetHandler = new CategoryFacetHandler(),
             $ColorFacetHandler = new ColorFacetHandler(),
             $ImageFacetHandler = new ImageFacetHandler( '',''),
             $RangeFacetHandler = new RangeFacetHandler(),
             $TextFacetHandler = new TextFacetHandler(),
        ];
    }

    private function getFacetHandler(\SimpleXMLElement $filter)
    {

        foreach ($this->facetHandlers as $handler) {
            if ($handler->supportsFacet($filter)) {
                return $handler;
            }
        }

        return null;
    }

    protected function createFacets(Criteria $criteria, SimpleXMLElement $filters, $facet = [])
    {
        foreach ($criteria->getFacets() as $criteriaFacet) {
            $getField = $criteriaFacet->getField($filters);
            var_dump(['$getField'=>$getField]);
            $xpath = $filters->xpath('name[.=' . $getField . ']/parent::*');
            $dpath = $filters->xpath($getField);
            var_dump(['$xpath'=>$xpath]);
            var_dump(['$dpath'=>$dpath]);
            if(empty($xpath))
            {
                continue;
            }
            $handler = $this->getFacetHandler($xpath[0]);
            if($handler == null)
            {
                continue;
            }
            $partialhandler = $handler->generatePartialFacet($criteriaFacet,$criteria,$xpath);
            if($partialhandler == null)
            {
                continue;
            }
//            $facet[] = $partialhandler;
            array_push($facet, $partialhandler);
        }

        return $facet;
    }
}
