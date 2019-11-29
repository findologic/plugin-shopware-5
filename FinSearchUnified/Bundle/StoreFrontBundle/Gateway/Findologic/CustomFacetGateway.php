<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic;

use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
use Shopware\Bundle\StoreFrontBundle\Gateway\CustomFacetGatewayInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use SimpleXMLElement;

class CustomFacetGateway implements CustomFacetGatewayInterface
{
    /**
     * @var CustomListingHydrator
     */
    protected $hydrator;

    /**
     * @var QueryBuilderFactoryInterface
     */
    protected $queryBuilderFactory;

    /**
     * @var CustomFacetGatewayInterface
     */
    private $originalService;

    /**
     * @param CustomFacetGatewayInterface $service
     * @param CustomListingHydrator $hydrator
     * @param QueryBuilderFactoryInterface $queryBuilderFactory
     */
    public function __construct(
        CustomFacetGatewayInterface $service,
        CustomListingHydrator $hydrator,
        QueryBuilderFactoryInterface $queryBuilderFactory
    ) {
        $this->originalService = $service;
        $this->hydrator = $hydrator;
        $this->queryBuilderFactory = $queryBuilderFactory;
    }

    /**
     * @param int[] $ids
     * @param ShopContextInterface $context
     *
     * @return CustomFacet[]
     */
    public function getList(array $ids, ShopContextInterface $context)
    {
        if (StaticHelper::useShopSearch()) {
            return $this->originalService->getList($ids, $context);
        }

        $criteria = new Criteria();
        $criteria->offset(0)->limit(1);

        /** @var QueryBuilder $query */
        $query = $this->queryBuilderFactory->createProductQuery($criteria, $context);

        $response = $query->execute();

        if (!empty($response)) {
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

        $categoryId = $categoryIds[0];

        $criteria = new Criteria();
        $criteria->offset(0)->limit(1);
        $criteria->addCondition(new CategoryCondition($categoryIds));

        $query = $this->queryBuilderFactory->createProductQuery($criteria, $context);
        $response = $query->execute();

        if (!empty($response)) {
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
     * @return CustomFacet
     */
    public function getAllCategoryFacets(ShopContextInterface $context)
    {
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
        $hasCategoryFacet = false;
        $hasVendorFacet = false;

        foreach ($filters as $filter) {
            $facet = $this->hydrator->hydrateFacet($filter);
            $facetName = $facet->getName();
            if($facetName == 'vendor'){
                $hasVendorFacet = true;
            }
            if($facetName == 'cat'){
                $hasCategoryFacet = true;
            }
            $facets[] = $facet;
        }

        if($hasVendorFacet == false){
            $facets[] = $this->hydrator->hydrateDefaultVendorFacet();
        }
        if($hasCategoryFacet == false){
            $facets[] = $this->hydrator->hydrateDefaultCategoryFacet();
        }
        return $facets;
    }
}
