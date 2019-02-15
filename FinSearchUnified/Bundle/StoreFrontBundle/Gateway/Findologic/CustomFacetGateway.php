<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic;

use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use FinSearchUnified\Helper\StaticHelper;
use FinSearchUnified\Helper\UrlBuilder;
use Shopware\Bundle\StoreFrontBundle\Gateway\CustomFacetGatewayInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use SimpleXMLElement;

class CustomFacetGateway implements CustomFacetGatewayInterface
{
    /**
     * @var CustomListingHydrator
     */
    protected $hydrator;

    private $originalService;

    private $urlBuilder;

    /**
     * CustomFacetGateway constructor.
     *
     * @param CustomFacetGatewayInterface $service
     * @param CustomListingHydrator $hydrator
     * @param UrlBuilder|null $urlBuilder
     *
     * @throws \Exception
     */
    public function __construct(
        CustomFacetGatewayInterface $service,
        CustomListingHydrator $hydrator,
        $urlBuilder = null
    ) {
        $this->originalService = $service;
        if ($urlBuilder === null) {
            $this->urlBuilder = new UrlBuilder();
        } else {
            $this->urlBuilder = $urlBuilder;
        }
        $this->hydrator = $hydrator;
    }

    /**
     * @param int[] $ids
     * @param ShopContextInterface $context
     *
     * @return \Shopware\Bundle\StoreFrontBundle\Struct\Search\CustomFacet[]
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

            return $this->hydrate($xmlResponse->filters->filter);
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

    /**
     * @param SimpleXMLElement $filters
     *
     * @return array
     */
    private function hydrate(SimpleXMLElement $filters)
    {
        $facets = [];

        foreach ($filters as $filter) {
            $facets[] = $this->hydrator->hydrateFacet($filter);
        }

        return $facets;
    }
}
