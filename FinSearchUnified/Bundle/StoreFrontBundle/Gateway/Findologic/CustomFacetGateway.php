<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic;

use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder;
use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\CustomFacetGatewayInterface;
use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use FinSearchUnified\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilderFactoryInterface;
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
     * @param CustomListingHydrator $hydrator
     * @param QueryBuilderFactoryInterface $queryBuilderFactory
     */
    public function __construct(
        CustomListingHydrator $hydrator,
        QueryBuilderFactoryInterface $queryBuilderFactory
    ) {
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
        $criteria = new Criteria();
        $criteria->offset(0)->limit(1);

        /** @var QueryBuilder $query */
        $query = $this->queryBuilderFactory->createSearchNavigationQueryWithoutAdditionalFilters($criteria, $context);

        $response = $query->execute();

        if (!empty($response)) {
            $xmlResponse = StaticHelper::getXmlFromResponse($response);

            return $this->hydrate($xmlResponse->filters->filter);
        }

        return [];
    }

    /**
     * @param array $categoryIds
     * @param ShopContextInterface $context
     *
     * @return array
     */
    public function getFacetsOfCategories(array $categoryIds, ShopContextInterface $context)
    {
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

        return [];
    }

    /**
     * @param ShopContextInterface $context
     *
     * @return array
     */
    public function getAllCategoryFacets(ShopContextInterface $context)
    {
        return [];
    }

    /**
     * @param SimpleXMLElement $filters
     *
     * @return CustomFacet[]
     */
    private function hydrate(SimpleXMLElement $filters)
    {
        $facets = [];
        $hasCategoryFacet = false;
        $hasVendorFacet = false;

        foreach ($filters as $filter) {
            $facet = $this->hydrator->hydrateFacet($filter);
            $facetName = $facet->getName();

            if ($facetName === 'vendor') {
                $hasVendorFacet = true;
            }
            if ($facetName === 'cat') {
                $hasCategoryFacet = true;
            }

            $facets[] = $facet;
        }

        if (!$hasCategoryFacet) {
            $facets[] = $this->hydrator->hydrateDefaultCategoryFacet();
        }
        if (!$hasVendorFacet) {
            $facets[] = $this->hydrator->hydrateDefaultVendorFacet();
        }

        return $facets;
    }
}
