<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic;

use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\Helper\UrlBuilder;
use Shopware\Bundle\StoreFrontBundle\Gateway\CustomFacetGatewayInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;

class CustomFacetGateway implements CustomFacetGatewayInterface
{
    /**
     * @var CustomListingHydrator
     */
    protected $hydrator;

    private $originalService;

    private $urlBuilder;

    public function __construct(CustomFacetGatewayInterface $service, CustomListingHydrator $hydrator)
    {
        $this->originalService = $service;
        $this->urlBuilder = new UrlBuilder();
        $this->hydrator = $hydrator;
    }

    /**
     * @param int[] $ids
     * @param ShopContextInterface $context
     *
     * @return \Shopware\Bundle\StoreFrontBundle\Struct\Search\CustomFacet indexed by id
     */
    public function getList(array $ids, ShopContextInterface $context)
    {
        if (StaticHelper::useShopSearch()) {
            return $this->originalService->getList($ids, $context);
        }

        $this->urlBuilder->setCustomerGroup($context->getCurrentCustomerGroup());
        $response = $this->urlBuilder->buildCompleteFilterList();
        if ($response instanceof \Zend_Http_Response && $response->getStatus() == 200) {
            $xmlResponse = StaticHelper::getXmlFromResponse($response);
            $categoryFacets = [];
            $categoryFacets[] = $this->hydrate($xmlResponse->filters->filter);

            return $categoryFacets[0];
        } else {
            return $this->originalService->getList($ids, $context);
        }
    }

    /**
     * @param array $categoryIds
     * @param ShopContextInterface $context
     *
     * @return array indexed by category id, each element contains a list of CustomFacet
     */
    public function getFacetsOfCategories(array $categoryIds, ShopContextInterface $context)
    {
        if (StaticHelper::useShopSearch()) {
            return $this->originalService->getFacetsOfCategories($categoryIds, $context);
        }

        // Facets abfragen
        $categoryId = $categoryIds[0];
        $this->urlBuilder->setCustomerGroup($context->getCurrentCustomerGroup());
        $response = $this->urlBuilder->buildCategoryUrlAndGetResponse($categoryId);
        if ($response instanceof \Zend_Http_Response && $response->getStatus() == 200) {
            $xmlResponse = StaticHelper::getXmlFromResponse($response);
            $categoryFacets = [];
            $categoryFacets[$categoryId] = $this->hydrate($xmlResponse->filters->filter);

            return $categoryFacets;
        }

        return $this->originalService->getFacetsOfCategories($categoryIds, $context);
    }

    /**
     * @param ShopContextInterface $context
     *
     * @return \Shopware\Bundle\StoreFrontBundle\Struct\Search\CustomFacet
     */
    public function getAllCategoryFacets(ShopContextInterface $context)
    {
        // TODO: Implement getAllCategoryFacets() method.
        return $this->originalService->getAllCategoryFacets($context);
    }

    private function hydrate(\SimpleXMLElement $filters)
    {
        $facets = [];

        foreach ($filters as $filter) {
            $facets[] = $this->hydrator->hydrateFacet($filter);
        }

        return $facets;
    }
}
