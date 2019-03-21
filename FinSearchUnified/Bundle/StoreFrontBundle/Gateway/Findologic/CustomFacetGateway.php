<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic;

use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\StoreFrontBundle\Gateway\CustomFacetGatewayInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use SimpleXMLElement;

class CustomFacetGateway implements CustomFacetGatewayInterface
{
    /**
     * @var CustomListingHydrator
     */
    protected $hydrator;

    /**
     * @var CustomFacetGatewayInterface
     */
    private $originalService;

    /**
     * @param CustomFacetGatewayInterface $service
     * @param CustomListingHydrator $hydrator
     *
     * @throws \Exception
     */
    public function __construct(
        CustomFacetGatewayInterface $service,
        CustomListingHydrator $hydrator
    ) {
        $this->originalService = $service;
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

        $urlBuilder = Shopware()->Container()->get('fin_search_unified.helper.url_builder');
        $urlBuilder->setCustomerGroup($context->getCurrentCustomerGroup());
        $response = $urlBuilder->buildCompleteFilterList();
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

        $urlBuilder = Shopware()->Container()->get('fin_search_unified.helper.url_builder');

        // Facets abfragen
        $categoryId = $categoryIds[0];
        $urlBuilder->setCustomerGroup($context->getCurrentCustomerGroup());
        $response = $urlBuilder->buildCategoryUrlAndGetResponse($categoryId);
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
