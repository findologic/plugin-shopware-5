<?php

namespace FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic;

use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NewQueryBuilder;
use FinSearchUnified\Bundle\SearchBundleFindologic\QueryBuilder\NewQueryBuilderFactoryInterface;
use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\CustomFacetGatewayInterface;
use FinSearchUnified\Bundle\StoreFrontBundle\Gateway\Findologic\Hydrator\CustomListingHydrator;
use FinSearchUnified\Bundle\StoreFrontBundle\Struct\Search\CustomFacet;
use FinSearchUnified\Helper\StaticHelper;
use Shopware\Bundle\SearchBundle\Condition\CategoryCondition;
use Shopware\Bundle\SearchBundle\Criteria;
use Shopware\Bundle\StoreFrontBundle\Struct\ShopContextInterface;
use SimpleXMLElement;
use Zend_Cache_Exception;

class CustomFacetGateway implements CustomFacetGatewayInterface
{
    /**
     * @var CustomListingHydrator
     */
    protected $hydrator;

    /**
     * @var NewQueryBuilderFactoryInterface
     */
    protected $queryBuilderFactory;

    /**
     * @param CustomListingHydrator $hydrator
     * @param NewQueryBuilderFactoryInterface $queryBuilderFactory
     */
    public function __construct(
        CustomListingHydrator $hydrator,
        NewQueryBuilderFactoryInterface $queryBuilderFactory
    ) {
        $this->hydrator = $hydrator;
        $this->queryBuilderFactory = $queryBuilderFactory;
    }

    /**
     * @param int[] $ids
     * @param ShopContextInterface $context
     *
     * @return CustomFacet[]
     * @throws Zend_Cache_Exception
     */
    public function getList(array $ids, ShopContextInterface $context)
    {
        $criteria = new Criteria();
        $criteria->offset(0)->limit(1);

        /** @var NewQueryBuilder $query */
        $query = $this->queryBuilderFactory->createSearchNavigationQueryWithoutAdditionalFilters($criteria, $context);
        $response = $query->execute()->getRawResponse();

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
     * @throws Zend_Cache_Exception
     */
    public function getFacetsOfCategories(array $categoryIds, ShopContextInterface $context)
    {
        $categoryId = $categoryIds[0];

        $criteria = new Criteria();
        $criteria->offset(0)->limit(1);
        $criteria->addCondition(new CategoryCondition($categoryIds));

        $query = $this->queryBuilderFactory->createProductQuery($criteria, $context);
        $response = $query->execute()->getRawResponse();

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
     * @throws Zend_Cache_Exception
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
