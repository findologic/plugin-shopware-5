<?php

namespace FinSearchUnified\Bundles;

use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\Helper\UrlBuilder;
use Shopware\Bundle\SearchBundle;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundle\ProductNumberSearchInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use Shopware\Models\Search\CustomFacet;
use Shopware\Bundle\StoreFrontBundle\Struct\Customer\Group;

class ProductNumberSearch implements ProductNumberSearchInterface
{
    protected $urlBuilder;

    protected $originalService;

    protected $facets = [];

    public function __construct(ProductNumberSearchInterface $service)
    {
        $this->urlBuilder = new UrlBuilder();
        $this->originalService = $service;
    }
    
    /**
     * Creates a product search result for the passed criteria object.
     * The criteria object contains different core conditions and plugin conditions.
     * This conditions has to be handled over the different condition handlers.
     *
     * The search gateway has to implement an event which plugin can be listened to,
     * to add their own handler classes.
     *
     * @param \Shopware\Bundle\SearchBundle\Criteria                        $criteria
     * @param \Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface $context
     *
     * @return SearchBundle\ProductNumberSearchResult
     */
    public function search(Criteria $criteria, ShopContextInterface $context)
    {
        $controllerName = Shopware()->Front()->Request()->getControllerName();
        $moduleName = Shopware()->Front()->Request()->getModuleName();

        // Shopware sets fetchCount to false when the search is used for internal purposes, which we don't care about.
        // Checking its value is the only way to tell if we should actually perform the search.
        $fetchCount = $criteria->fetchCount();

        if (
            $moduleName !== 'backend' &&
            $fetchCount &&
            ($controllerName === 'search' || $controllerName === 'listing') &&
            !StaticHelper::useShopSearch()
        ) {
            try {

                $response = $this->sendRequestToFindologic($criteria, $context->getCurrentCustomerGroup());

                if ($response instanceof \Zend_Http_Response && $response->getStatus() == 200) {
                    self::setFallbackFlag(0);

                    $xmlResponse = StaticHelper::getXmlFromResponse($response);

                    self::redirectOnLandingpage($xmlResponse);

                    StaticHelper::setPromotion($xmlResponse);

                    $this->facets = StaticHelper::getFacetResultsFromXml($xmlResponse);

                    $facetsInterfaces = StaticHelper::getFindologicFacets($xmlResponse);

                    /** @var CustomFacet $facets_interface */
                    foreach ($facetsInterfaces as $facetsInterface) {
                        $criteria->addFacet($facetsInterface->getFacet());
                    }

                    $this->setSelectedFacets($criteria);

                    $criteria->resetConditions();

                    $totalResults = (int)$xmlResponse->results->count;

                    $foundProducts = StaticHelper::getProductsFromXml($xmlResponse);
                    $searchResult = StaticHelper::getShopwareArticlesFromFindologicId($foundProducts);

                    return new SearchBundle\ProductNumberSearchResult($searchResult, $totalResults, $this->facets);
                } else {
                    self::setFallbackFlag(1);

                    return $this->originalService->search($criteria, $context);
                }
            } catch (\Zend_Http_Client_Exception $e) {
                self::setFallbackFlag(1);

                return $this->originalService->search($criteria, $context);
            }
        } else {
            return $this->originalService->search($criteria, $context);
        }
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
        setcookie('Fallback', $status);
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

    /**
     * @param Criteria $criteria
     * @param Group $customerGroup
     * @return null|\Zend_Http_Response
     */
    protected function sendRequestToFindologic(Criteria $criteria, Group $customerGroup)
    {
        $this->urlBuilder->setCustomerGroup($customerGroup);
        $response = $this->urlBuilder->buildQueryUrlAndGetResponse($criteria);

        return $response;
    }
}
